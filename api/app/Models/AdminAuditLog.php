<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $admin_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property string|null $ip_address
 * @property CarbonImmutable $created_at
 */
class AdminAuditLog extends Model
{
    use AppendOnly;

    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;
    use HasUuids;
    use StoresTimesInUtc;

    public const UPDATED_AT = null;

    protected $table = 'admin_audit_log';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
