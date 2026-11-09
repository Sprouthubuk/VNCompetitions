<?php

namespace App\Models;

use Database\Factories\CompetitionBundleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $competition_id
 * @property int $tickets
 * @property string $price
 * @property string|null $tag
 * @property int $sort
 */
class CompetitionBundle extends Model
{
    /** @use HasFactory<CompetitionBundleFactory> */
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tickets' => 'integer', 'price' => 'decimal:2', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Competition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
