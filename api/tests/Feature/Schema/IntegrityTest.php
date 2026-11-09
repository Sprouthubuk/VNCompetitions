<?php

/*
 * These tests write straight to the database, bypassing models, to prove that
 * integrity is enforced by Postgres itself and not only by application code.
 */

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Run a statement expected to fail inside a savepoint, so the failure doesn't abort
 * the test's surrounding transaction (Postgres refuses further statements until rollback).
 */
function attempt(Closure $statement): Closure
{
    return fn () => DB::transaction($statement);
}

function rawUser(): string
{
    $id = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $id, 'name' => 'Test User', 'email' => "{$id}@example.test", 'password' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

/** @param array<string, mixed> $overrides */
function rawCompetition(array $overrides = []): string
{
    $id = (string) Str::uuid();
    DB::table('competitions')->insert(array_merge([
        'id' => $id, 'slug' => "comp-{$id}", 'title' => 'Comp', 'brand' => 'Target', 'kind' => 'darts',
        'type' => 'standard', 'category' => 'darts', 'description' => 'A comp', 'status' => 'live',
        'price' => '0.99', 'max_tickets' => 100, 'draw_at' => now()->addWeek(),
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    return $id;
}

function rawPostalTicket(string $competitionId, int $number): void
{
    $entry = (string) Str::uuid();
    DB::table('postal_entries')->insert([
        'id' => $entry, 'competition_id' => $competitionId, 'name' => 'Postal Entrant',
        'address' => '1 High Street', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('tickets')->insert([
        'id' => (string) Str::uuid(), 'competition_id' => $competitionId, 'number' => $number,
        'status' => 'postal', 'postal_entry_id' => $entry, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param array<string, mixed> $overrides */
function rawOrder(string $userId, array $overrides = []): string
{
    $id = (string) Str::uuid();
    DB::table('orders')->insert(array_merge([
        'id' => $id, 'user_id' => $userId, 'status' => 'pending', 'subtotal' => '10.00',
        'discount' => '0.00', 'credit_used' => '0.00', 'total' => '10.00',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    return $id;
}

describe('tickets', function () {
    it('rejects a second ticket with the same number in the same competition', function () {
        $comp = rawCompetition();
        rawPostalTicket($comp, 7);

        expect(attempt(fn () => rawPostalTicket($comp, 7)))->toThrow(UniqueConstraintViolationException::class);
        expect(DB::table('tickets')->where('competition_id', $comp)->count())->toBe(1);
    });

    it('allows the same number in different competitions', function () {
        rawPostalTicket(rawCompetition(), 7);
        rawPostalTicket(rawCompetition(), 7);

        expect(DB::table('tickets')->count())->toBe(2);
    });

    it('rejects numbers outside 1..max_tickets', function (int $number) {
        $comp = rawCompetition(['max_tickets' => 100]);

        expect(attempt(fn () => rawPostalTicket($comp, $number)))->toThrow(QueryException::class);
    })->with([0, -1, 101]);

    it('accepts the boundary numbers 1 and max_tickets', function () {
        $comp = rawCompetition(['max_tickets' => 100]);
        rawPostalTicket($comp, 1);
        rawPostalTicket($comp, 100);

        expect(DB::table('tickets')->count())->toBe(2);
    });

    it('will not lower max_tickets below a number already in use', function () {
        $comp = rawCompetition(['max_tickets' => 100]);
        rawPostalTicket($comp, 90);

        expect(attempt(fn () => DB::table('competitions')->where('id', $comp)->update(['max_tickets' => 50])))
            ->toThrow(QueryException::class, 'below a number already in use');

        DB::table('competitions')->where('id', $comp)->update(['max_tickets' => 95]);
        expect(DB::table('competitions')->where('id', $comp)->value('max_tickets'))->toBe(95);
    });

    it('requires a held ticket to have an expiry', function () {
        $comp = rawCompetition();
        $user = rawUser();
        $order = rawOrder($user);
        $item = (string) Str::uuid();
        DB::table('order_items')->insert([
            'id' => $item, 'order_id' => $order, 'competition_id' => $comp, 'quantity' => 1,
            'unit_price' => '0.99', 'line_total' => '0.99', 'created_at' => now(), 'updated_at' => now(),
        ]);

        expect(attempt(fn () => DB::table('tickets')->insert([
            'id' => (string) Str::uuid(), 'competition_id' => $comp, 'number' => 1, 'status' => 'held',
            'order_item_id' => $item, 'user_id' => $user, 'created_at' => now(), 'updated_at' => now(),
        ])))->toThrow(QueryException::class, 'tickets_status_shape');
    });
});

describe('competitions', function () {
    it('rejects invalid money and capacity', function (array $overrides, string $constraint) {
        expect(attempt(fn () => rawCompetition($overrides)))->toThrow(QueryException::class, $constraint);
    })->with([
        'zero capacity' => [['max_tickets' => 0], 'competitions_max_tickets_positive'],
        'negative price' => [['price' => '-0.01'], 'competitions_price_non_negative'],
        'sale price equal to price' => [['price' => '1.00', 'sale_price' => '1.00'], 'competitions_sale_price_below_price'],
        'sale price above price' => [['price' => '1.00', 'sale_price' => '1.50'], 'competitions_sale_price_below_price'],
        'free comp with a price' => [['is_free' => true, 'price' => '1.00'], 'competitions_free_has_no_price'],
        'instant comp without a game' => [['type' => 'instant'], 'competitions_instant_has_game'],
        'unknown status' => [['status' => 'active'], 'competitions_status_check'],
    ]);

    it('rejects a duplicate slug', function () {
        rawCompetition(['slug' => 'pick-your-board']);

        expect(attempt(fn () => rawCompetition(['slug' => 'pick-your-board'])))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('instant win prizes', function () {
    it('rejects two prizes on the same number', function () {
        $comp = rawCompetition(['type' => 'instant', 'game' => 'wheel']);
        $line = (string) Str::uuid();
        DB::table('instant_win_prize_lines')->insert([
            'id' => $line, 'competition_id' => $comp, 'name' => 'Darts', 'tier' => 'darts', 'quantity' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $prize = fn (int $n) => DB::table('instant_win_prizes')->insert([
            'id' => (string) Str::uuid(), 'competition_id' => $comp, 'instant_win_prize_line_id' => $line,
            'ticket_number' => $n, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $prize(5);
        expect(attempt(fn () => $prize(5)))->toThrow(UniqueConstraintViolationException::class);
        expect(attempt(fn () => $prize(101)))->toThrow(QueryException::class, 'outside 1..100');
    });
});

describe('orders', function () {
    it('rejects a duplicate payment reference', function () {
        $user = rawUser();
        rawOrder($user, ['payment_reference' => 'CF-123']);

        expect(attempt(fn () => rawOrder($user, ['payment_reference' => 'CF-123'])))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('rejects totals that do not add up or go negative', function (array $overrides, string $constraint) {
        expect(attempt(fn () => rawOrder(rawUser(), $overrides)))->toThrow(QueryException::class, $constraint);
    })->with([
        'total mismatch' => [['subtotal' => '10.00', 'discount' => '1.00', 'total' => '10.00'], 'orders_total_adds_up'],
        'negative total' => [['subtotal' => '1.00', 'credit_used' => '2.00', 'total' => '-1.00'], 'orders_money_non_negative'],
        'paid without paid_at' => [['status' => 'paid'], 'orders_paid_has_paid_at'],
    ]);
});

describe('ledgers', function () {
    it('makes wallet transactions append-only', function () {
        $user = rawUser();
        $id = (string) Str::uuid();
        DB::table('wallet_transactions')->insert([
            'id' => $id, 'user_id' => $user, 'amount' => '1.00', 'type' => 'prize', 'balance_after' => '1.00',
        ]);

        expect(attempt(fn () => DB::table('wallet_transactions')->where('id', $id)->update(['amount' => '100.00'])))
            ->toThrow(QueryException::class, 'append-only');
        expect(attempt(fn () => DB::table('wallet_transactions')->where('id', $id)->delete()))
            ->toThrow(QueryException::class, 'append-only');
    });

    it('rejects a wallet entry whose sign does not match its type or that overdraws', function (array $row, string $constraint) {
        $user = rawUser();
        expect(attempt(fn () => DB::table('wallet_transactions')->insert(array_merge(
            ['id' => (string) Str::uuid(), 'user_id' => $user],
            $row,
        ))))->toThrow(QueryException::class, $constraint);
    })->with([
        'negative prize' => [['amount' => '-1.00', 'type' => 'prize', 'balance_after' => '0.00'], 'wallet_transactions_sign_matches_type'],
        'positive spend' => [['amount' => '1.00', 'type' => 'spend', 'balance_after' => '1.00'], 'wallet_transactions_sign_matches_type'],
        'overdrawn' => [['amount' => '-1.00', 'type' => 'spend', 'balance_after' => '-1.00'], 'wallet_transactions_balance_non_negative'],
    ]);

    it('makes the admin audit log append-only', function () {
        $id = (string) Str::uuid();
        DB::table('admin_audit_log')->insert(['id' => $id, 'action' => 'competition.updated']);

        expect(attempt(fn () => DB::table('admin_audit_log')->where('id', $id)->update(['action' => 'nothing'])))
            ->toThrow(QueryException::class, 'append-only');
    });

    it('seals a draw once its seed is revealed and never deletes it', function () {
        $comp = rawCompetition();
        $id = (string) Str::uuid();
        DB::table('draws')->insert([
            'id' => $id, 'competition_id' => $comp, 'kind' => 'standard',
            'entries_hash' => hash('sha256', 'entries'), 'seed_commitment' => hash('sha256', 'seed'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The commitment can never change, even before the reveal.
        expect(attempt(fn () => DB::table('draws')->where('id', $id)->update(['seed_commitment' => hash('sha256', 'other')])))
            ->toThrow(QueryException::class, 'immutable');

        // Completing the draw before reveal is allowed.
        DB::table('draws')->where('id', $id)->update(['public_entropy' => 'drand:123', 'drawn_at' => now(), 'seed' => 'seed']);

        expect(attempt(fn () => DB::table('draws')->where('id', $id)->update(['public_entropy' => 'drand:999'])))
            ->toThrow(QueryException::class, 'sealed');
        expect(attempt(fn () => DB::table('draws')->where('id', $id)->delete()))
            ->toThrow(QueryException::class, 'append-only');
    });
});

describe('users', function () {
    it('treats email addresses case-insensitively', function () {
        DB::table('users')->insert(['id' => (string) Str::uuid(), 'name' => 'A', 'email' => 'Craig@Example.com', 'password' => 'x']);

        expect(attempt(fn () => DB::table('users')->insert(['id' => (string) Str::uuid(), 'name' => 'B', 'email' => 'craig@example.com', 'password' => 'x'])))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});
