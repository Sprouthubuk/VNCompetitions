<?php

use App\Models\AdminAuditLog;
use App\Models\Draw;
use App\Models\InstantWinPrize;
use App\Models\LimitEvent;
use App\Models\WalletTransaction;
use App\Models\Winner;

it('refuses to update or delete ledger rows in app code', function (string $model) {
    $row = $model::factory()->create();

    expect(fn () => $row->forceFill(['created_at' => now()->subYear()])->save())->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $row->delete())->toThrow(LogicException::class, 'append-only');
})->with([WalletTransaction::class, LimitEvent::class, AdminAuditLog::class]);

it('seals a draw model once the seed is revealed', function () {
    $draw = Draw::factory()->create();

    expect(fn () => $draw->update(['seed_commitment' => hash('sha256', 'x')]))->toThrow(LogicException::class, 'cannot change');

    $draw->refresh()->update(['seed' => 'revealed', 'drawn_at' => now()]);

    expect(fn () => $draw->update(['public_entropy' => 'later']))->toThrow(LogicException::class, 'sealed')
        ->and(fn () => $draw->delete())->toThrow(LogicException::class, 'append-only');
});

it('never serialises an instant-win prize number', function () {
    $prize = InstantWinPrize::factory()->create();

    expect($prize->toArray())->not->toHaveKey('ticket_number')
        ->and($prize->toJson())->not->toContain('ticket_number');
});

it('shows winners as first name and surname initial', function (string $name, string $public) {
    expect(Winner::factory()->make(['display_name' => $name])->publicName())->toBe($public);
})->with([
    ['Craig Baker', 'Craig B.'],
    ['McKenzie Vetier', 'McKenzie V.'],
    ['Robert James Pickford', 'Robert P.'],
    ['  dean   bush ', 'dean B.'],
    ['Cher', 'Cher'],
]);
