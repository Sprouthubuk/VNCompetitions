<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\CompetitionKind;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\InstantGame;
use App\Enums\OfferKind;
use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $slug
 * @property string $title
 * @property string $brand
 * @property CompetitionKind $kind
 * @property CompetitionType $type
 * @property Category $category
 * @property string $description
 * @property CompetitionStatus $status
 * @property bool $is_free
 * @property string $price
 * @property string|null $sale_price
 * @property string|null $cash_alternative
 * @property int $max_tickets
 * @property CarbonImmutable $draw_at
 * @property bool $auto_draw
 * @property bool $featured
 * @property int $sort
 * @property string|null $image_path
 * @property string|null $end_prize
 * @property InstantGame|null $game
 * @property CarbonImmutable|null $prizes_locked_at
 * @property string|null $prize_commitment
 * @property string $cost_price
 * @property bool $prize_purchased
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $tickets_count
 * @property int|null $instant_prizes_count
 * @property int|null $instant_prizes_remaining_count
 */
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    /** Statuses whose comp page is reachable by slug. */
    public const PUBLIC_STATUSES = [
        CompetitionStatus::Live,
        CompetitionStatus::SoldOut,
        CompetitionStatus::Closed,
        CompetitionStatus::Drawn,
    ];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CompetitionKind::class,
            'type' => CompetitionType::class,
            'category' => Category::class,
            'status' => CompetitionStatus::class,
            'game' => InstantGame::class,
            'is_free' => 'boolean',
            'auto_draw' => 'boolean',
            'featured' => 'boolean',
            'prize_purchased' => 'boolean',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cash_alternative' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'max_tickets' => 'integer',
            'sort' => 'integer',
            'draw_at' => 'immutable_datetime',
            'prizes_locked_at' => 'immutable_datetime',
        ];
    }

    /**
     * On sale now: the only status the public list shows.
     *
     * @param  Builder<Competition>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', CompetitionStatus::Live);
    }

    /**
     * Reachable by slug: live, plus sold-out, closed and drawn comps so old links keep working.
     * Draft, coming-soon and cancelled comps are never public.
     *
     * @param  Builder<Competition>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->whereIn('status', self::PUBLIC_STATUSES);
    }

    /**
     * Everything CompetitionResource reads, in a fixed number of queries.
     * Prize lines carry counts only; prize numbers are never selected.
     *
     * @param  Builder<Competition>  $query
     */
    public function scopeForPublicApi(Builder $query): void
    {
        $query->with([
            'bundles',
            'buyXOffer',
            'multiplierOffer',
            'prizeLines' => fn ($lines) => $lines->withCount([
                'units',
                'units as units_left_count' => fn ($units) => $units->whereNull('won_at'),
            ]),
        ])->withCount([
            'tickets',
            'instantPrizes',
            'instantPrizes as instant_prizes_remaining_count' => fn ($units) => $units->whereNull('won_at'),
        ]);
    }

    public function isInstant(): bool
    {
        return $this->type === CompetitionType::Instant;
    }

    /** The price a ticket costs right now, in pounds. */
    public function currentPrice(): string
    {
        return $this->sale_price ?? $this->price;
    }

    /** @return HasMany<CompetitionBundle, $this> */
    public function bundles(): HasMany
    {
        return $this->hasMany(CompetitionBundle::class)->orderBy('sort')->orderBy('tickets');
    }

    /** @return HasMany<CompetitionOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(CompetitionOffer::class);
    }

    /** @return HasOne<CompetitionOffer, $this> */
    public function buyXOffer(): HasOne
    {
        return $this->hasOne(CompetitionOffer::class)->where('kind', OfferKind::BuyXGetY);
    }

    /** @return HasOne<CompetitionOffer, $this> */
    public function multiplierOffer(): HasOne
    {
        return $this->hasOne(CompetitionOffer::class)->where('kind', OfferKind::Multiplier);
    }

    /** @return HasMany<InstantWinPrizeLine, $this> */
    public function prizeLines(): HasMany
    {
        return $this->hasMany(InstantWinPrizeLine::class)->orderBy('sort');
    }

    /** @return HasMany<InstantWinPrize, $this> */
    public function instantPrizes(): HasMany
    {
        return $this->hasMany(InstantWinPrize::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<PostalEntry, $this> */
    public function postalEntries(): HasMany
    {
        return $this->hasMany(PostalEntry::class);
    }

    /** @return HasMany<Draw, $this> */
    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class);
    }

    /** @return HasMany<Winner, $this> */
    public function winners(): HasMany
    {
        return $this->hasMany(Winner::class);
    }
}
