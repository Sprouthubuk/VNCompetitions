<?php

namespace Database\Factories;

use App\Models\AdminAuditLog;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => User::factory()->admin(),
            'action' => 'updated',
            'subject_type' => (new Competition())->getMorphClass(),
            'subject_id' => fake()->uuid(),
            'before' => ['status' => 'coming_soon'],
            'after' => ['status' => 'live'],
            'reason' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
