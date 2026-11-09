<?php

use App\Models\Competition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('stores times as UTC whatever the Postgres server zone is', function () {
    // Summer, when London is UTC+1: a wrong session zone would shift this by an hour.
    $drawAt = CarbonImmutable::parse('2026-07-01 20:00:00', 'Europe/London');
    $comp = Competition::factory()->create(['draw_at' => $drawAt]);

    $stored = DB::selectOne("select to_char(draw_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI') as utc from competitions where id = ?", [$comp->id]);

    expect($stored->utc)->toBe('2026-07-01 19:00')
        ->and($comp->fresh()?->draw_at->equalTo($drawAt))->toBeTrue();
});
