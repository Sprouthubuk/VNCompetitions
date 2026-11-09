<?php

namespace Database\Factories;

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A first credit for a user (balance_after = amount). For a running ledger,
 * set balance_after explicitly; WalletService owns that in production.
 *
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => '1.00',
            'type' => WalletTransactionType::Prize,
            'balance_after' => '1.00',
            'expires_at' => now()->addDays(30),
            'note' => null,
        ];
    }
}
