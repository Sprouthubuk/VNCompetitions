<?php

namespace Database\Factories;

use App\Models\InstantWinPrize;
use App\Models\InstantWinPrizeLine;
use App\Models\Ticket;
use Database\Factories\Concerns\PicksFreeNumbers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstantWinPrize>
 */
class InstantWinPrizeFactory extends Factory
{
    use PicksFreeNumbers;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instant_win_prize_line_id' => InstantWinPrizeLine::factory(),
            'competition_id' => fn (array $a) => InstantWinPrizeLine::query()->whereKey($a['instant_win_prize_line_id'])->value('competition_id'),
            'ticket_number' => fn (array $a) => self::randomFreeNumber($a['competition_id'], 'instant_win_prizes', 'ticket_number'),
            'won_by_ticket_id' => null,
            'won_at' => null,
        ];
    }

    public function wonBy(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'competition_id' => $ticket->competition_id,
            'ticket_number' => $ticket->number,
            'won_by_ticket_id' => $ticket->id,
            'won_at' => now(),
        ]);
    }
}
