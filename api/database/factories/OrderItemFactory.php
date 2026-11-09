<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'competition_id' => Competition::factory(),
            'quantity' => 10,
            'bonus_tickets' => 0,
            'unit_price' => '0.99',
            'line_total' => '9.90',
            'bundle_id' => null,
        ];
    }
}
