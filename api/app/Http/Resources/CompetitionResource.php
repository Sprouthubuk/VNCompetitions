<?php

namespace App\Http\Resources;

use App\Models\Competition;
use App\Models\CompetitionBundle;
use App\Models\InstantWinPrizeLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The public comp shape from docs/API_CONTRACT.md (the `Comp` type in
 * web/src/data/comps.ts). Instant-win prize numbers are never read here:
 * prize lines only carry names and counts.
 *
 * Expects the relations and counts loaded by Competition::scopeForPublicApi().
 *
 * @property Competition $resource
 */
class CompetitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $comp = $this->resource;
        $onSale = $comp->sale_price !== null;
        $buyX = $comp->buyXOffer;
        $multiplier = $comp->multiplierOffer;

        $data = [
            'slug' => $comp->slug,
            'title' => $comp->title,
            'status' => $comp->status->value,
            'brand' => $comp->brand,
            'kind' => $comp->kind->value,
            'type' => $comp->type->value,
            'category' => $comp->category->label(),
            'price' => self::pounds($comp->currentPrice()),
            'was' => $onSale ? self::pounds($comp->price) : null,
            'sold' => (int) $comp->tickets_count,
            'max' => $comp->max_tickets,
            'drawAt' => $comp->draw_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'drawMode' => $comp->auto_draw ? 'auto' : 'live',
            'description' => $comp->description,
            'cashAlternative' => $comp->cash_alternative === null ? null : self::pounds($comp->cash_alternative),
            'featured' => $comp->featured,
            'free' => $comp->is_free,
            'image' => $comp->image_path === null ? null : Storage::url($comp->image_path),
            'bundles' => $comp->bundles->map(fn (CompetitionBundle $b) => array_filter([
                'tickets' => $b->tickets,
                'price' => self::pounds($b->price),
                'tag' => $b->tag,
            ], fn ($v) => $v !== null))->values()->all(),
            'buyX' => $buyX === null ? null : ['buy' => $buyX->buy, 'free' => $buyX->free],
            'multiplier' => $multiplier === null ? null : ['from' => $multiplier->from_tickets, 'times' => $multiplier->times],
            'endPrize' => $comp->end_prize,
        ];

        if ($comp->isInstant()) {
            $data += [
                'game' => $comp->game?->value,
                'instantCount' => (int) $comp->instant_prizes_count,
                'instantRemaining' => (int) $comp->instant_prizes_remaining_count,
                'instantPrizes' => $comp->prizeLines->map(fn (InstantWinPrizeLine $line) => [
                    'name' => $line->name,
                    'tier' => $line->tier->label(),
                    // Before go-live there are no units yet: show the configured quantity.
                    'left' => $comp->prizes_locked_at === null ? $line->quantity : (int) $line->units_left_count,
                    'total' => $comp->prizes_locked_at === null ? $line->quantity : (int) $line->units_count,
                ])->values()->all(),
            ];
        }

        return $data;
    }

    /** "2.50" => 2.5, "10.00" => 10 (a JSON number, as the storefront expects). */
    private static function pounds(string $amount): int|float
    {
        $value = (float) $amount;

        return floor($value) === $value ? (int) $value : $value;
    }
}
