<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'subtotal' => '9.90',
            'discount' => '0.00',
            'credit_used' => '0.00',
            'total' => '9.90',
            'held_until' => now()->addMinutes(15),
        ];
    }

    /** Set the money so the order adds up (total = subtotal - discount - credit). */
    public function amounts(string $subtotal, string $discount = '0.00', string $creditUsed = '0.00'): static
    {
        $total = bcsub(bcsub($subtotal, $discount, 2), $creditUsed, 2);

        return $this->state(fn (array $attributes) => [
            'subtotal' => $subtotal, 'discount' => $discount, 'credit_used' => $creditUsed, 'total' => $total,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'payment_reference' => 'CF-'.Str::upper(Str::random(12)),
            'held_until' => null,
            'paid_at' => now(),
        ]);
    }
}
