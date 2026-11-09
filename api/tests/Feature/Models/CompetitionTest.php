<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\CompetitionOffer;

it('scopes live() to comps on sale now', function () {
    foreach (CompetitionStatus::cases() as $status) {
        Competition::factory()->status($status)->create(['slug' => $status->value]);
    }

    expect(Competition::query()->live()->pluck('slug')->all())->toBe(['live']);
});

it('scopes public() to comps reachable by slug', function () {
    foreach (CompetitionStatus::cases() as $status) {
        Competition::factory()->status($status)->create(['slug' => $status->value]);
    }

    expect(Competition::query()->public()->pluck('slug')->sort()->values()->all())
        ->toBe(['closed', 'drawn', 'live', 'sold_out']);
});

it('uses the sale price as the current price', function () {
    expect(Competition::factory()->make(['price' => '0.49', 'sale_price' => '0.29'])->currentPrice())->toBe('0.29')
        ->and(Competition::factory()->make(['price' => '0.49', 'sale_price' => null])->currentPrice())->toBe('0.49');
});

it('casts money to two-decimal strings', function () {
    $comp = Competition::factory()->create(['price' => 1.5]);

    expect($comp->fresh()?->price)->toBe('1.50');
});

it('exposes each offer kind as its own relation', function () {
    $comp = Competition::factory()->create();
    CompetitionOffer::factory()->for($comp)->buyXGetY(10, 2)->create();
    CompetitionOffer::factory()->for($comp)->multiplier(50, 2)->create();

    expect($comp->buyXOffer?->buy)->toBe(10)
        ->and($comp->multiplierOffer?->times)->toBe(2);
});
