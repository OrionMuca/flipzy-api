<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropertyRehabEstimate>
 */
class PropertyRehabEstimateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalCost = fake()->numberBetween(20000, 100000);
        $breakdown = [
            'kitchen' => fake()->numberBetween(10000, 25000),
            'bathrooms' => fake()->numberBetween(8000, 20000),
            'flooring' => fake()->numberBetween(5000, 15000),
            'paint' => fake()->numberBetween(2000, 5000),
            'electrical' => fake()->numberBetween(3000, 8000),
            'plumbing' => fake()->numberBetween(4000, 10000),
            'hvac' => fake()->numberBetween(2000, 8000),
        ];

        $aiResponse = json_encode([
            'total_cost' => $totalCost,
            'breakdown' => $breakdown,
            'labor_percentage' => 40,
            'materials_percentage' => 60,
            'timeline_weeks' => fake()->numberBetween(4, 12),
            'risk_factors' => [
                fake()->randomElement([
                    'Older electrical system may need replacement',
                    'Potential foundation issues',
                    'Roof may need repair',
                ]),
            ],
            'notes' => fake()->paragraph(),
            'confidence' => fake()->randomElement(['high', 'medium', 'low']),
        ]);

        return [
            'property_id' => Property::factory(),
            'requested_by' => User::factory(),
            'ai_response' => $aiResponse,
            'property_data' => [
                'location' => [
                    'city' => fake()->city(),
                    'state' => fake()->stateAbbr(),
                ],
                'property' => [
                    'type' => fake()->randomElement(['house', 'condo', 'townhouse']),
                    'square_feet' => fake()->numberBetween(1000, 3000),
                    'bedrooms' => fake()->numberBetween(2, 4),
                    'bathrooms' => fake()->numberBetween(1, 3),
                ],
            ],
            'model_used' => 'gpt-3.5-turbo',
            'estimated_cost' => $totalCost,
            'tokens_used' => fake()->numberBetween(500, 1500),
        ];
    }
}
