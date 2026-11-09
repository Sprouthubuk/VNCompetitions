<?php

namespace Database\Factories;

use App\Models\ResponsibleLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResponsibleLimit>
 */
class ResponsibleLimitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'daily_limit' => '20.00',
            'weekly_limit' => '100.00',
            'monthly_limit' => '300.00',
        ];
    }
}
