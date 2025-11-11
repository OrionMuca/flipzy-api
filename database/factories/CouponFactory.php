<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('COUPON##??')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'discount_type' => fake()->randomElement(['percentage', 'fixed_amount']),
            'discount_value' => fake()->randomFloat(2, 5, 50),
            'minimum_amount' => fake()->optional()->randomFloat(2, 10, 100),
            'maximum_discount' => fake()->optional()->randomFloat(2, 10, 100),
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addYear(),
            'usage_limit' => fake()->optional()->numberBetween(10, 1000),
            'usage_count' => 0,
            'user_limit' => 1,
            'is_active' => true,
            'applicable_plans' => null,
        ];
    }

    /**
     * Percentage discount coupon
     */
    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => 'percentage',
            'discount_value' => fake()->numberBetween(10, 50),
        ]);
    }

    /**
     * Fixed amount discount coupon
     */
    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => 'fixed_amount',
            'discount_value' => fake()->randomFloat(2, 10, 100),
        ]);
    }

    /**
     * Expired coupon
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subYear(),
            'valid_until' => now()->subDay(),
        ]);
    }

    /**
     * Inactive coupon
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
