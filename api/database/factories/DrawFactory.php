<?php

namespace Database\Factories;

use App\Enums\DrawKind;
use App\Models\Competition;
use App\Models\Draw;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A committed draw that hasn't run yet.
 *
 * @extends Factory<Draw>
 */
class DrawFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory()->closed(),
            'kind' => DrawKind::Standard,
            'entries_hash' => hash('sha256', fake()->uuid()),
            'seed_commitment' => hash('sha256', bin2hex(random_bytes(32))),
            'seed' => null,
            'public_entropy' => null,
            'winning_ticket_id' => null,
            'drawn_at' => null,
            'drawn_by' => null,
        ];
    }
}
