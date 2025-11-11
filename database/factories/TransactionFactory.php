<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'payment',
            'status' => 'completed',
            'amount' => fake()->randomFloat(2, 10, 1000),
            'currency' => 'usd',
            'stripe_payment_intent_id' => 'pi_' . fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'stripe_charge_id' => 'ch_' . fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'description' => fake()->sentence(),
            'processed_at' => now(),
        ];
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_at' => null,
        ]);
    }

    /**
     * Indicate that the transaction failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => 'Payment failed',
            'processed_at' => null,
        ]);
    }

    /**
     * Indicate that the transaction is a refund.
     */
    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'refund',
            'status' => 'completed',
            'stripe_refund_id' => 're_' . fake()->unique()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }

    /**
     * Indicate that the transaction is refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'stripe_refund_id' => 're_' . fake()->unique()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }
}
