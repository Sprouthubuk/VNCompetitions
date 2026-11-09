<?php

namespace Database\Factories;

use App\Enums\LimitEventType;
use App\Models\LimitEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LimitEvent>
 */
class LimitEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => LimitEventType::LimitsChanged,
            'before' => ['daily_limit' => null],
            'after' => ['daily_limit' => '20.00'],
            'effective_at' => now(),
        ];
    }
}
