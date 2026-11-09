<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\Winner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Winner>
 */
class WinnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->drawn(),
            'user_id' => null,
            'ticket_id' => null,
            'draw_id' => null,
            'ticket_number' => fake()->numberBetween(1, 999),
            'prize_text' => fake()->randomElement(['Target', 'Winmau', 'Harrows']).' darts '.fake()->numberBetween(20, 26).'g',
            'display_name' => fake()->firstName().' '.fake()->lastName(),
            'photo_path' => null,
            'announced_at' => now()->subDay(),
            'verified_at' => null,
        ];
    }

    public function unannounced(): static
    {
        return $this->state(fn (array $attributes) => ['announced_at' => null]);
    }
}
