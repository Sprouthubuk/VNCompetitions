<?php

namespace App\Models;

use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\WinnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $competition_id
 * @property string|null $user_id
 * @property string|null $ticket_id
 * @property string|null $draw_id
 * @property int|null $ticket_number
 * @property string $prize_text
 * @property string $display_name
 * @property string|null $photo_path
 * @property CarbonImmutable|null $announced_at
 * @property CarbonImmutable|null $verified_at
 */
class Winner extends Model
{
    /** @use HasFactory<WinnerFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ticket_number' => 'integer',
            'announced_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }

    /** @param  Builder<Winner>  $query */
    public function scopeAnnounced(Builder $query): void
    {
        $query->whereNotNull('announced_at')->where('announced_at', '<=', now());
    }

    /** First name plus surname initial, e.g. "Craig B.". Never the full surname. */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim($this->display_name)) ?: [];
        $first = array_shift($parts) ?? '';
        $last = array_pop($parts);

        return $last === null ? $first : $first.' '.mb_strtoupper(mb_substr($last, 0, 1)).'.';
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Draw, $this> */
    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }
}
