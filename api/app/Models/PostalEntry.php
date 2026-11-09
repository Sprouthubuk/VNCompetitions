<?php

namespace App\Models;

use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\PostalEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $competition_id
 * @property string $name
 * @property string $address
 * @property string|null $postcode
 * @property string|null $email
 * @property CarbonImmutable $received_at
 * @property string|null $keyed_by
 * @property string|null $user_id
 */
class PostalEntry extends Model
{
    /** @use HasFactory<PostalEntryFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime'];
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

    /** @return BelongsTo<User, $this> */
    public function keyedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keyed_by');
    }

    /** @return HasOne<Ticket, $this> */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
