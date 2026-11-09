<?php

use App\Models\AdminAuditLog;
use App\Models\Competition;
use App\Models\CompetitionBundle;
use App\Models\CompetitionOffer;
use App\Models\Draw;
use App\Models\InstantWinPrize;
use App\Models\InstantWinPrizeLine;
use App\Models\InstantWinResult;
use App\Models\LimitEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PostalEntry;
use App\Models\PromoCode;
use App\Models\ResponsibleLimit;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Winner;
use Illuminate\Database\Eloquent\Model;

it('creates a valid row from every factory', function (string $model) {
    /** @var class-string<Model> $model */
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($model::query()->whereKey($record->getKey())->exists())->toBeTrue();
})->with([
    User::class, Competition::class, CompetitionBundle::class, CompetitionOffer::class,
    InstantWinPrizeLine::class, InstantWinPrize::class, InstantWinResult::class, Order::class,
    OrderItem::class, PostalEntry::class, Ticket::class, Draw::class, Winner::class, PromoCode::class,
    WalletTransaction::class, ResponsibleLimit::class, LimitEvent::class, AdminAuditLog::class,
]);

it('creates valid rows from the factory states', function () {
    $comp = Competition::factory()->create(['max_tickets' => 50]);

    Ticket::factory()->for($comp)->held()->create();
    Ticket::factory()->for($comp)->postal()->create();
    Ticket::factory()->for($comp)->free()->create();
    CompetitionOffer::factory()->for($comp)->multiplier()->create();
    CompetitionOffer::factory()->for($comp)->buyXGetY()->create();
    InstantWinPrizeLine::factory()->credit()->create();
    PromoCode::factory()->fixed()->create();
    Order::factory()->paid()->amounts('10.00', '1.00', '2.50')->create();
    Competition::factory()->instant()->onSale('2.00', '1.00')->create();
    Competition::factory()->free()->create();

    expect($comp->tickets()->count())->toBe(3);
});

it('gives factory tickets random, distinct numbers within capacity', function () {
    $comp = Competition::factory()->create(['max_tickets' => 30]);

    $numbers = Ticket::factory()->count(30)->for($comp)->postal()->create()->pluck('number');

    expect($numbers->unique())->toHaveCount(30)
        ->and($numbers->min())->toBe(1)
        ->and($numbers->max())->toBe(30)
        // Drawing 1..30 in order by chance has odds of 1 in 30!.
        ->and($numbers->values()->all())->not->toBe(range(1, 30));
});
