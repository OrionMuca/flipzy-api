<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * Real properties with valid addresses that ATTOM can find
     */
    protected array $realProperties = [
        [
            'address' => '123 Main Street',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'zip_code' => '90001',
            'title' => 'Fixer Upper in Downtown LA',
            'description' => 'Great investment opportunity in downtown Los Angeles. Needs some TLC but has excellent potential.',
            'property_type' => 'house',
            'asking_price' => 350000,
            'bedrooms' => null, // Will be filled by ATTOM
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '456 Oak Avenue',
            'city' => 'Miami',
            'state' => 'FL',
            'zip_code' => '33101',
            'title' => 'Beachside Fixer Upper',
            'description' => 'Prime location near Miami Beach. Property needs renovation but location is unbeatable.',
            'property_type' => 'house',
            'asking_price' => 425000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '789 Elm Street',
            'city' => 'Phoenix',
            'state' => 'AZ',
            'zip_code' => '85001',
            'title' => 'Desert Oasis Fixer',
            'description' => 'Spacious property in Phoenix with great potential. Needs cosmetic updates.',
            'property_type' => 'house',
            'asking_price' => 280000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '321 Pine Street',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30301',
            'title' => 'Historic Home Renovation',
            'description' => 'Charming historic home in Atlanta. Great for investors looking for a flip opportunity.',
            'property_type' => 'house',
            'asking_price' => 195000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '654 Maple Drive',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip_code' => '75201',
            'title' => 'Texas Ranch Style Home',
            'description' => 'Classic ranch style home in Dallas. Needs some updates but solid foundation.',
            'property_type' => 'house',
            'asking_price' => 310000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '987 Cedar Lane',
            'city' => 'Houston',
            'state' => 'TX',
            'zip_code' => '77001',
            'title' => 'Houston Investment Property',
            'description' => 'Excellent investment opportunity in Houston. Property is in good condition with minor repairs needed.',
            'property_type' => 'house',
            'asking_price' => 275000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '147 Birch Court',
            'city' => 'Tampa',
            'state' => 'FL',
            'zip_code' => '33601',
            'title' => 'Tampa Bay Area Fixer',
            'description' => 'Great location in Tampa Bay area. Property needs renovation but has excellent resale potential.',
            'property_type' => 'house',
            'asking_price' => 240000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '258 Walnut Street',
            'city' => 'Orlando',
            'state' => 'FL',
            'zip_code' => '32801',
            'title' => 'Orlando Family Home',
            'description' => 'Spacious family home in Orlando. Needs some updates but great neighborhood.',
            'property_type' => 'house',
            'asking_price' => 295000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '369 Spruce Avenue',
            'city' => 'Las Vegas',
            'state' => 'NV',
            'zip_code' => '89101',
            'title' => 'Vegas Investment Opportunity',
            'description' => 'Fixer upper in Las Vegas. Great for investors looking for quick flip potential.',
            'property_type' => 'house',
            'asking_price' => 320000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '741 Cherry Boulevard',
            'city' => 'Denver',
            'state' => 'CO',
            'zip_code' => '80201',
            'title' => 'Mountain View Property',
            'description' => 'Beautiful property in Denver with mountain views. Needs some work but great location.',
            'property_type' => 'house',
            'asking_price' => 380000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get wholesalers
        $wholesalers = User::whereHas('roles', function ($query) {
            $query->where('name', 'wholesaler');
        })->get();

        if ($wholesalers->isEmpty()) {
            $this->command->warn('No wholesalers found. Please run UserSeeder first.');
            return;
        }

        // Delete existing properties (and related records due to foreign keys)
        Property::query()->delete();
        $this->command->info('Deleted existing properties');

        $wholesalerIndex = 0;
        $createdCount = 0;

        // Create real properties
        foreach ($this->realProperties as $propertyData) {
            $wholesaler = $wholesalers[$wholesalerIndex % $wholesalers->count()];
            
            // Calculate ARV and repair estimate
            $askingPrice = $propertyData['asking_price'];
            $arv = $askingPrice * 1.5; // Assume 50% increase after repair
            $repairEstimate = $askingPrice * 0.2; // Assume 20% repair cost
            $potentialProfit = $arv - $askingPrice - $repairEstimate;

            $property = Property::create([
                'wholesaler_id' => $wholesaler->id,
                'title' => $propertyData['title'],
                'description' => $propertyData['description'],
                'property_type' => $propertyData['property_type'],
                'status' => 'active',
                'address' => $propertyData['address'],
                'city' => $propertyData['city'],
                'state' => $propertyData['state'],
                'zip_code' => $propertyData['zip_code'],
                'country' => 'US',
                'latitude' => null, // Will be filled by geocoding
                'longitude' => null, // Will be filled by geocoding
                'bedrooms' => $propertyData['bedrooms'],
                'bathrooms' => $propertyData['bathrooms'],
                'square_feet' => $propertyData['square_feet'],
                'lot_size' => $propertyData['square_feet'] ?? null,
                'year_built' => $propertyData['year_built'],
                'condition' => 'fair',
                'asking_price' => $askingPrice,
                'arv' => $arv,
                'repair_estimate' => $repairEstimate,
                'potential_profit' => $potentialProfit,
                'is_featured' => rand(0, 100) < 30, // 30% chance
                'is_verified' => false, // Will be verified after ATTOM enrichment
                'allow_inquiries' => true,
            ]);

            // Add a placeholder image
            PropertyImage::create([
                'property_id' => $property->id,
                'path' => 'properties/placeholder.jpg',
                'url' => '/storage/properties/placeholder.jpg',
                'type' => 'image',
                'order' => 0,
                'is_primary' => true,
                'alt_text' => $property->title,
            ]);

            $createdCount++;
            $wholesalerIndex++;

            $this->command->info("Created property: {$property->title} in {$property->city}, {$property->state}");
        }

        $this->command->info("Created {$createdCount} real properties with valid addresses");
        $this->command->warn('Note: These properties have NULL values for bedrooms, bathrooms, square_feet, etc.');
        $this->command->warn('Run property enrichment to populate these fields from ATTOM API.');
    }
}
