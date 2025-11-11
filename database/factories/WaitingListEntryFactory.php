<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\WaitingListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WaitingListEntry>
 */
class WaitingListEntryFactory extends Factory
{
    protected $model = WaitingListEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plan = SubscriptionPlan::factory()->create();
        $price = (float) $plan->price;

        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'subscription_plan_id' => $plan->id,
            'coupon_id' => null,
            'coupon_code' => null,
            'status' => 'pending',
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'stripe_checkout_session_id' => null,
            'original_price' => $price,
            'discounted_price' => $price,
            'discount_amount' => 0,
            'verification_token' => fake()->sha256(),
            'email_verified_at' => null,
            'metadata' => [],
            'account_created_at' => null,
        ];
    }

    /**
     * Entry with payment completed
     */
    public function paymentCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'payment_completed',
            'stripe_customer_id' => 'cus_' . fake()->bothify('########'),
            'stripe_subscription_id' => 'sub_' . fake()->bothify('########'),
            'stripe_checkout_session_id' => 'cs_' . fake()->bothify('########'),
        ]);
    }

    /**
     * Entry with account created
     */
    public function accountCreated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'account_created',
            'account_created_at' => now(),
        ]);
    }

    /**
     * Entry with email verified
     */
    public function emailVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => now(),
        ]);
    }
}
