<?php

namespace App\Models;

use App\Enums\OfferKind;
use Database\Factories\CompetitionOfferFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buy X get Y free (buy, free) or a ticket multiplier (from_tickets, times).
 *
 * @property string $id
 * @property string $competition_id
 * @property OfferKind $kind
 * @property int|null $buy
 * @property int|null $free
 * @property int|null $from_tickets
 * @property int|null $times
 */
class CompetitionOffer extends Model
{
    /** @use HasFactory<CompetitionOfferFactory> */
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => OfferKind::class,
            'buy' => 'integer',
            'free' => 'integer',
            'from_tickets' => 'integer',
            'times' => 'integer',
        ];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
