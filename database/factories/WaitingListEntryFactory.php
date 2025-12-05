<?php

namespace Database\Factories;

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
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'phone_number' => fake()->phoneNumber(),
            'company_name' => fake()->optional()->company(),
            'selected_roles' => fake()->optional()->randomElements(['wholesaler', 'investor'], fake()->numberBetween(0, 2)),
            'subscription_plan_id' => null,
            'coupon_id' => null,
            'coupon_code' => null,
            'status' => 'pending',
            'verification_token' => fake()->sha256(),
            'email_verified_at' => null,
            'metadata' => [],
            'account_created_at' => null,
        ];
    }

    /**
     * Entry with payment completed (deprecated - use pending status instead)
     * @deprecated This method is deprecated. Use regular create() with status 'pending' instead.
     */
    public function paymentCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending', // Changed from 'payment_completed' to 'pending'
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
