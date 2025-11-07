<?php

namespace Database\Factories;

use App\Models\Analytic;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Analytic>
 */
class AnalyticFactory extends Factory
{
    protected $model = Analytic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'user_id' => User::factory(),
            'event_type' => fake()->randomElement(['view', 'save', 'inquiry']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => [],
        ];
    }

    /**
     * Indicate that this is a view event.
     */
    public function view(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'view',
        ]);
    }

    /**
     * Indicate that this is a save event.
     */
    public function save(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'save',
        ]);
    }

    /**
     * Indicate that this is an inquiry event.
     */
    public function inquiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'inquiry',
            'metadata' => [
                'message' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * Indicate that this event has no user (anonymous).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
