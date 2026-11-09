<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\CompetitionBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionBundle>
 */
class CompetitionBundleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tickets = fake()->randomElement([5, 10, 20, 25, 50, 100]);

        return [
            'competition_id' => Competition::factory(),
            'tickets' => $tickets,
            'price' => number_format($tickets * 0.2, 2, '.', ''),
            'tag' => null,
            'sort' => 0,
        ];
    }
}
