<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = ['Los Angeles', 'Miami', 'Phoenix', 'Atlanta', 'Dallas', 'Houston', 'Tampa', 'Orlando'];
        $states = ['CA', 'FL', 'AZ', 'GA', 'TX', 'NC', 'SC'];
        $propertyTypes = ['house', 'condo', 'townhouse', 'duplex', 'multi-family'];
        $conditions = ['excellent', 'good', 'fair', 'poor'];
        
        $city = fake()->randomElement($cities);
        $state = fake()->randomElement($states);
        $askingPrice = fake()->numberBetween(50000, 500000);
        $arv = $askingPrice * fake()->randomFloat(2, 1.2, 2.5);
        $repairEstimate = $askingPrice * fake()->randomFloat(2, 0.1, 0.3);
        
        return [
            'wholesaler_id' => User::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraphs(3, true),
            'property_type' => fake()->randomElement($propertyTypes),
            'status' => fake()->randomElement(['active', 'active', 'active', 'pending', 'sold']),
            'address' => fake()->streetAddress(),
            'city' => $city,
            'state' => $state,
            'zip_code' => fake()->postcode(),
            'country' => 'US',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->randomFloat(1, 1, 4),
            'square_feet' => fake()->numberBetween(800, 4000),
            'lot_size' => fake()->numberBetween(5000, 15000),
            'year_built' => fake()->numberBetween(1950, 2020),
            'condition' => fake()->randomElement($conditions),
            'asking_price' => $askingPrice,
            'arv' => $arv,
            'repair_estimate' => $repairEstimate,
            'potential_profit' => $arv - $askingPrice - $repairEstimate,
            'is_featured' => fake()->boolean(20), // 20% chance
            'is_verified' => fake()->boolean(70), // 70% chance
            'allow_inquiries' => true,
        ];
    }

    /**
     * Indicate that the property is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the property is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    /**
     * Indicate that the property is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
