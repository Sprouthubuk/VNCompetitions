<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\InstantWinPrize;
use App\Models\User;
use App\Models\Winner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('seeds an admin who can reach the panel', function () {
    $admin = User::query()->where('email', 'admin@vaultninecompetitions.co.uk')->firstOrFail();

    expect($admin->is_admin)->toBeTrue();
});

it('seeds the 8 sample comps from comps.ts as live', function () {
    $sample = require database_path('seeders/data/sample-competitions.php');

    $live = Competition::query()->live()->orderBy('sort')->withCount('tickets')->get();

    expect($live->pluck('slug')->all())->toBe(array_column($sample, 'slug'))
        ->and($live->pluck('tickets_count')->all())->toBe(array_column($sample, 'sold'));
});

it('keeps every won prize unit consistent with a sold ticket on the same number', function () {
    $mismatches = DB::table('instant_win_prizes as p')
        ->join('tickets as t', 't.id', '=', 'p.won_by_ticket_id')
        ->where(fn ($q) => $q->whereColumn('t.number', '<>', 'p.ticket_number')
            ->orWhereColumn('t.competition_id', '<>', 'p.competition_id'))
        ->count();

    // And no sold ticket sits on an unwon prize number (it would have won it).
    $missed = DB::table('instant_win_prizes as p')
        ->join('tickets as t', fn ($j) => $j->on('t.competition_id', '=', 'p.competition_id')->on('t.number', '=', 'p.ticket_number'))
        ->whereNull('p.won_by_ticket_id')
        ->count();

    expect($mismatches)->toBe(0)->and($missed)->toBe(0);
});

it('matches the prize ledger from comps.ts', function () {
    $comp = Competition::query()->where('slug', 'target-takeover')->firstOrFail();

    $line = $comp->prizeLines()->where('name', '10p site credit')->firstOrFail();

    expect($line->quantity)->toBe(15046)
        ->and(InstantWinPrize::query()->where('instant_win_prize_line_id', $line->id)->whereNull('won_at')->count())->toBe(13088)
        ->and($line->credit_amount)->toBe('0.10');
});

it('seeds the sample winners on drawn comps', function () {
    expect(Winner::query()->announced()->count())->toBe(8)
        ->and(Competition::query()->where('status', CompetitionStatus::Drawn)->count())->toBe(8);
});

it('can be run twice without duplicating anything', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Competition::query()->count())->toBe(16)
        ->and(Winner::query()->count())->toBe(8)
        ->and(User::query()->where('is_admin', true)->count())->toBe(1);
});
