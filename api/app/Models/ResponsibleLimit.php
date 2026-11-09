<?php

namespace App\Models;

use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\ResponsibleLimitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $daily_limit
 * @property string|null $weekly_limit
 * @property string|null $monthly_limit
 * @property string|null $pending_daily_limit
 * @property string|null $pending_weekly_limit
 * @property string|null $pending_monthly_limit
 * @property CarbonImmutable|null $pending_effective_at
 * @property CarbonImmutable|null $cool_off_until
 * @property CarbonImmutable|null $excluded_until
 */
class ResponsibleLimit extends Model
{
    /** @use HasFactory<ResponsibleLimitFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'daily_limit' => 'decimal:2',
            'weekly_limit' => 'decimal:2',
            'monthly_limit' => 'decimal:2',
            'pending_daily_limit' => 'decimal:2',
            'pending_weekly_limit' => 'decimal:2',
            'pending_monthly_limit' => 'decimal:2',
            'pending_effective_at' => 'immutable_datetime',
            'cool_off_until' => 'immutable_datetime',
            'excluded_until' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
