<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InstantWinResultFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ticket_id
 * @property string|null $instant_win_prize_id
 * @property CarbonImmutable|null $revealed_at
 */
class InstantWinResult extends Model
{
    /** @use HasFactory<InstantWinResultFactory> */
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revealed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<InstantWinPrize, $this> */
    public function prize(): BelongsTo
    {
        return $this->belongsTo(InstantWinPrize::class, 'instant_win_prize_id');
    }
}
