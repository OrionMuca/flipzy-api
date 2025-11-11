<?php

namespace Database\Factories;

use App\Models\WaitingListEntry;
use App\Models\WaitingListTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WaitingListTransaction>
 */
class WaitingListTransactionFactory extends Factory
{
    protected $model = WaitingListTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entry = WaitingListEntry::factory()->create();
        $amount = (float) $entry->discounted_price;

        return [
            'waiting_list_entry_id' => $entry->id,
            'type' => 'subscription',
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'usd',
            'original_amount' => (float) $entry->original_price,
            'discount_amount' => (float) $entry->discount_amount,
            'stripe_payment_intent_id' => null,
            'stripe_charge_id' => null,
            'stripe_refund_id' => null,
            'description' => 'Subscription payment',
            'metadata' => [],
            'processed_at' => null,
        ];
    }

    /**
     * Completed transaction
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'stripe_payment_intent_id' => 'pi_' . fake()->bothify('########'),
            'stripe_charge_id' => 'ch_' . fake()->bothify('########'),
            'processed_at' => now(),
        ]);
    }

    /**
     * Failed transaction
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
