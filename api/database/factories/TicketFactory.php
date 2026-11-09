<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Competition;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PostalEntry;
use App\Models\Ticket;
use App\Models\User;
use Database\Factories\Concerns\PicksFreeNumbers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A sold ticket by default, on a random free number of its comp.
 *
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    use PicksFreeNumbers;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'number' => fn (array $a) => self::randomFreeNumber($a['competition_id'], 'tickets', 'number'),
            'status' => TicketStatus::Sold,
            'order_item_id' => fn (array $a) => OrderItem::factory()->state([
                'competition_id' => $a['competition_id'],
                'order_id' => Order::factory()->paid(),
            ]),
            'user_id' => fn (array $a) => self::orderUser($a['order_item_id']),
            'postal_entry_id' => null,
            'held_until' => null,
            'multiplier' => 1,
        ];
    }

    public function held(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Held,
            'held_until' => now()->addMinutes(15),
        ]);
    }

    public function postal(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Postal,
            'order_item_id' => null,
            'user_id' => null,
            'postal_entry_id' => fn (array $a) => PostalEntry::factory()->state(['competition_id' => $a['competition_id']]),
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Free,
            'order_item_id' => null,
            'user_id' => User::factory(),
        ]);
    }

    private static function orderUser(mixed $orderItemId): ?string
    {
        if ($orderItemId === null) {
            return null;
        }

        $orderId = OrderItem::query()->whereKey($orderItemId)->value('order_id');

        return Order::query()->whereKey($orderId)->value('user_id');
    }
}
