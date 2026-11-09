<?php

namespace App\Models;

use App\Enums\DrawKind;
use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\DrawFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only draw record (Verity). Before the seed is revealed the draw can be
 * completed; once the seed is set the row is sealed. The database enforces the
 * same rules (draws_append_only trigger).
 *
 * @property string $id
 * @property string $competition_id
 * @property DrawKind $kind
 * @property string $entries_hash
 * @property string $seed_commitment
 * @property string|null $seed
 * @property string|null $public_entropy
 * @property string|null $winning_ticket_id
 * @property CarbonImmutable|null $drawn_at
 * @property string|null $drawn_by
 */
class Draw extends Model
{
    /** @use HasFactory<DrawFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => DrawKind::class, 'drawn_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (Draw $draw): void {
            if ($draw->getOriginal('seed') !== null) {
                throw new LogicException('This draw is sealed: its seed has been revealed.');
            }
            if ($draw->isDirty(['competition_id', 'kind', 'entries_hash', 'seed_commitment'])) {
                throw new LogicException('A draw commitment cannot change.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Draws are append-only and cannot be deleted.');
        });
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function winningTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'winning_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function drawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }
}
