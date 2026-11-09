<?php

namespace Database\Factories;

use App\Enums\OfferKind;
use App\Models\Competition;
use App\Models\CompetitionOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionOffer>
 */
class CompetitionOfferFactory extends Factory
{
    /**
     * Buy 10, get 2 free by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'kind' => OfferKind::BuyXGetY,
            'buy' => 10,
            'free' => 2,
            'from_tickets' => null,
            'times' => null,
        ];
    }

    public function buyXGetY(int $buy = 10, int $free = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => OfferKind::BuyXGetY, 'buy' => $buy, 'free' => $free, 'from_tickets' => null, 'times' => null,
        ]);
    }

    public function multiplier(int $from = 50, int $times = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => OfferKind::Multiplier, 'buy' => null, 'free' => null, 'from_tickets' => $from, 'times' => $times,
        ]);
    }
}
