<?php

namespace Database\Factories;

use App\Enums\Category;
use App\Enums\CompetitionKind;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\InstantGame;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brand = fake()->randomElement(['Target', 'Winmau', 'Red Dragon', 'Unicorn', 'Harrows', 'Shot']);
        $title = $brand.' '.fake()->randomElement(['darts', 'dartboard', 'mystery box', 'bundle']).' '.fake()->numberBetween(18, 30).'g';

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'title' => $title,
            'brand' => $brand,
            'kind' => CompetitionKind::Darts,
            'type' => CompetitionType::Standard,
            'category' => Category::Darts,
            'description' => fake()->sentence(12),
            'status' => CompetitionStatus::Live,
            'is_free' => false,
            'price' => '0.99',
            'max_tickets' => 999,
            'draw_at' => now()->addWeek()->startOfMinute(),
            'auto_draw' => false,
            'featured' => false,
            'sort' => 0,
            'cost_price' => '50.00',
        ];
    }

    public function status(CompetitionStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function draft(): static
    {
        return $this->status(CompetitionStatus::Draft);
    }

    public function comingSoon(): static
    {
        return $this->status(CompetitionStatus::ComingSoon);
    }

    public function closed(): static
    {
        return $this->status(CompetitionStatus::Closed)->state(fn (array $attributes) => ['draw_at' => now()->subDay()->startOfMinute()]);
    }

    public function drawn(): static
    {
        return $this->status(CompetitionStatus::Drawn)->state(fn (array $attributes) => ['draw_at' => now()->subDay()->startOfMinute()]);
    }

    /** A sale: the current price drops below the normal ("was") price. */
    public function onSale(string $price = '0.49', string $salePrice = '0.29'): static
    {
        return $this->state(fn (array $attributes) => ['price' => $price, 'sale_price' => $salePrice]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => ['is_free' => true, 'price' => '0.00', 'sale_price' => null]);
    }

    public function autoDraw(): static
    {
        return $this->state(fn (array $attributes) => ['auto_draw' => true]);
    }

    public function instant(InstantGame $game = InstantGame::Wheel): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CompetitionType::Instant,
            'category' => Category::InstantWin,
            'kind' => CompetitionKind::Ring,
            'game' => $game,
            'auto_draw' => true,
            'max_tickets' => 9999,
            'price' => '1.00',
        ]);
    }
}
