<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Winner;
use Database\Seeders\DatabaseSeeder;
use Faker\Generator;
use Illuminate\Support\Facades\DB;

/**
 * Forge deploys with `composer install --no-dev`, so Faker isn't there and
 * `php artisan db:seed` must not touch factories or fake(). Here any request
 * for Faker throws, as it would on the server.
 */
beforeEach(function () {
    $noFaker = fn () => throw new RuntimeException('Faker is a dev dependency; seeders must not use factories or fake().');

    $this->app->bind(Generator::class, $noFaker);
    foreach (['en_US', 'en_GB', (string) config('app.faker_locale')] as $locale) {
        $this->app->bind(Generator::class.':'.$locale, $noFaker);
    }
});

it('seeds everything without Faker, and a second run changes nothing', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = fn () => [
        'comps' => Competition::query()->count(),
        'tickets' => Ticket::query()->count(),
        'users' => User::query()->count(),
        'admins' => User::query()->where('is_admin', true)->count(),
        'winners' => Winner::query()->count(),
        'bundles' => DB::table('competition_bundles')->count(),
        'offers' => DB::table('competition_offers')->count(),
        'prizes' => DB::table('instant_win_prizes')->count(),
        'orders' => DB::table('orders')->count(),
        'wallet' => DB::table('wallet_transactions')->count(),
    ];
    $first = $counts();

    $this->seed(DatabaseSeeder::class);

    expect($first['comps'])->toBe(16)
        ->and($first['admins'])->toBe(1)
        ->and($first['users'])->toBe(41)
        ->and($first['winners'])->toBe(8)
        ->and($counts())->toBe($first);
});

it('updates sample comps by slug on a re-run but keeps their status', function () {
    $this->seed(DatabaseSeeder::class);

    $comp = Competition::query()->where('slug', 'pick-your-board')->firstOrFail();
    $sold = $comp->tickets()->count();
    $comp->update(['title' => 'Edited by hand', 'status' => CompetitionStatus::SoldOut]);
    $comp->bundles()->delete();

    $this->seed(DatabaseSeeder::class);
    $comp->refresh();

    expect($comp->title)->toBe('Pick your board')
        ->and($comp->status)->toBe(CompetitionStatus::SoldOut)
        ->and($comp->bundles()->count())->toBeGreaterThan(0)
        ->and($comp->tickets()->count())->toBe($sold);
});
