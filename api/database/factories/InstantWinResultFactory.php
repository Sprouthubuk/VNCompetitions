<?php

namespace Database\Factories;

use App\Models\InstantWinResult;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A ticket's instant-win outcome. Defaults to "no prize", not yet revealed.
 *
 * @extends Factory<InstantWinResult>
 */
class InstantWinResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'instant_win_prize_id' => null,
            'revealed_at' => null,
        ];
    }

    public function revealed(): static
    {
        return $this->state(fn (array $attributes) => ['revealed_at' => now()]);
    }
}
