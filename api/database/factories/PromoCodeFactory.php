<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::lower(Str::random(8)),
            'percent' => '10.00',
            'fixed' => null,
            'min_order' => null,
            'max_uses' => null,
            'uses' => 0,
            'expires_at' => now()->addMonth(),
            'influencer' => null,
            'is_active' => true,
        ];
    }

    public function fixed(string $amount = '1.00'): static
    {
        return $this->state(fn (array $attributes) => ['percent' => null, 'fixed' => $amount]);
    }
}
