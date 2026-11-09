<?php

namespace App\Http\Resources;

use App\Models\Winner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public winner: first name and surname initial only, never contact details.
 *
 * @property Winner $resource
 */
class WinnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $winner = $this->resource;
        $number = $winner->ticket_number ?? $winner->ticket?->number;

        return [
            'name' => $winner->publicName(),
            'prize' => $winner->prize_text,
            'ticket' => $number === null ? 'live draw' : "#{$number}",
            'date' => $winner->announced_at?->setTimezone('Europe/London')->format('d M Y') ?? '',
        ];
    }
}
