<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WholesalerInvestorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WholesalerInvestorProfile>
 */
class WholesalerInvestorProfileFactory extends Factory
{
    protected $model = WholesalerInvestorProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wholesaler_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'notes' => fake()->sentence(),
            'preferred_cities' => [fake()->city()],
            'preferred_zip_codes' => [fake()->postcode()],
            'min_bedrooms' => 2,
            'max_bedrooms' => 4,
            'min_bathrooms' => 1.5,
            'max_bathrooms' => 3.5,
            'property_types' => ['Single-Family'],
            'min_profit' => 20000,
        ];
    }
}

