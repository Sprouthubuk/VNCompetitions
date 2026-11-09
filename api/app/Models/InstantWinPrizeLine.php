<?php

namespace App\Models;

use App\Enums\PrizeTier;
use App\Models\Concerns\StoresTimesInUtc;
use Database\Factories\InstantWinPrizeLineFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prize as staff configure it (name, tier, quantity). At go-live it is expanded
 * into `quantity` InstantWinPrize units, each on its own random ticket number.
 *
 * @property string $id
 * @property string $competition_id
 * @property string $name
 * @property PrizeTier $tier
 * @property int $quantity
 * @property string|null $credit_amount
 * @property int $sort
 * @property int|null $units_count
 * @property int|null $units_left_count
 */
class InstantWinPrizeLine extends Model
{
    /** @use HasFactory<InstantWinPrizeLineFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tier' => PrizeTier::class,
            'quantity' => 'integer',
            'credit_amount' => 'decimal:2',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return HasMany<InstantWinPrize, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(InstantWinPrize::class);
    }
}
