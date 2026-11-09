<?php

namespace Database\Factories;

use App\Enums\PrizeTier;
use App\Models\Competition;
use App\Models\InstantWinPrizeLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstantWinPrizeLine>
 */
class InstantWinPrizeLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->instant(),
            'name' => fake()->randomElement(['Target', 'Winmau', 'Red Dragon']).' darts '.fake()->numberBetween(20, 26).'g',
            'tier' => PrizeTier::Darts,
            'quantity' => 3,
            'credit_amount' => null,
            'sort' => 0,
        ];
    }

    public function credit(string $amount = '0.50'): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '£'.$amount.' site credit',
            'tier' => PrizeTier::Credit,
            'credit_amount' => $amount,
            'quantity' => 100,
        ]);
    }
}
