<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\PostalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostalEntry>
 */
class PostalEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'name' => fake()->name(),
            'address' => fake()->streetAddress()."\n".fake()->city(),
            'postcode' => fake()->postcode(),
            'email' => fake()->safeEmail(),
            'received_at' => now(),
            'keyed_by' => null,
            'user_id' => null,
        ];
    }
}
