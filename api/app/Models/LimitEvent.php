<?php

namespace App\Models;

use App\Enums\LimitEventType;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\LimitEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property LimitEventType $type
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property CarbonImmutable|null $effective_at
 * @property CarbonImmutable $created_at
 */
class LimitEvent extends Model
{
    use AppendOnly;

    /** @use HasFactory<LimitEventFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => LimitEventType::class,
            'before' => 'array',
            'after' => 'array',
            'effective_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
