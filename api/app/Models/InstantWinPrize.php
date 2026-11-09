<?php

namespace App\Models;

use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\InstantWinPrizeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One unit of an instant-win prize, sitting on a random ticket number.
 * The number is secret until won: it is hidden from serialisation and no
 * public resource reads it.
 *
 * @property string $id
 * @property string $competition_id
 * @property string $instant_win_prize_line_id
 * @property int $ticket_number
 * @property string|null $won_by_ticket_id
 * @property CarbonImmutable|null $won_at
 */
class InstantWinPrize extends Model
{
    /** @use HasFactory<InstantWinPrizeFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['ticket_number'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ticket_number' => 'integer', 'won_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return BelongsTo<InstantWinPrizeLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(InstantWinPrizeLine::class, 'instant_win_prize_line_id');
    }

    /** @return BelongsTo<Ticket, $this> */
    public function wonByTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'won_by_ticket_id');
    }
}
