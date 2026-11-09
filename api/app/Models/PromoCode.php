<?php

namespace App\Models;

use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string|null $percent
 * @property string|null $fixed
 * @property string|null $min_order
 * @property int|null $max_uses
 * @property int $uses
 * @property CarbonImmutable|null $expires_at
 * @property string|null $influencer
 * @property bool $is_active
 */
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'fixed' => 'decimal:2',
            'min_order' => 'decimal:2',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Codes are stored lower-case (the database checks it).
     *
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    /** @return BelongsToMany<Competition, $this> */
    public function excludedCompetitions(): BelongsToMany
    {
        return $this->belongsToMany(Competition::class, 'promo_code_excluded_competitions');
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
