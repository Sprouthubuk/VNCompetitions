<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use App\Models\Concerns\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only wallet ledger. Amount is signed; balance_after is the running balance.
 *
 * @property string $id
 * @property string $user_id
 * @property string $amount
 * @property WalletTransactionType $type
 * @property string $balance_after
 * @property CarbonImmutable|null $expires_at
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
class WalletTransaction extends Model
{
    use AppendOnly;

    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
