<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ticket numbers are always picked at random from the comp's free numbers,
 * server-side (AllocationService, build plan phase 2).
 *
 * @property string $id
 * @property string $competition_id
 * @property int $number
 * @property TicketStatus $status
 * @property string|null $order_item_id
 * @property string|null $postal_entry_id
 * @property string|null $user_id
 * @property CarbonImmutable|null $held_until
 * @property int $multiplier
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'number' => 'integer',
            'multiplier' => 'integer',
            'held_until' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<PostalEntry, $this> */
    public function postalEntry(): BelongsTo
    {
        return $this->belongsTo(PostalEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<InstantWinResult, $this> */
    public function instantWinResult(): HasOne
    {
        return $this->hasOne(InstantWinResult::class);
    }
}
