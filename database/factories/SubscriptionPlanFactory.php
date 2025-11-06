<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->slug(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 0, 199),
            'billing_interval' => fake()->randomElement(['monthly', 'yearly']),
            'features' => ['feature1', 'feature2'],
            'max_properties' => fake()->numberBetween(5, 100),
            'max_messages' => fake()->numberBetween(10, 1000),
            'has_ai_estimates' => fake()->boolean(),
            'has_api_access' => fake()->boolean(),
            'is_active' => true,
        ];
    }

    /**
     * Free plan state
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0,
            'max_properties' => 5,
            'max_messages' => 10,
            'has_ai_estimates' => false,
            'has_api_access' => false,
        ]);
    }

    /**
     * Premium plan state
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 49.99,
            'max_properties' => null, // unlimited
            'max_messages' => null, // unlimited
            'has_ai_estimates' => true,
            'has_api_access' => false,
        ]);
    }

    /**
     * VIP plan state
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'VIP',
            'slug' => 'vip',
            'price' => 199.99,
            'max_properties' => null, // unlimited
            'max_messages' => null, // unlimited
            'has_ai_estimates' => true,
            'has_api_access' => true,
        ]);
    }
}
