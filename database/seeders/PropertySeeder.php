<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * Real properties with valid addresses that exist in ATTOM database
     * Using real addresses from major US cities
     */
    protected array $realProperties = [
        [
            'address' => '4529 Winona Court',
            'city' => 'Denver',
            'state' => 'CO',
            'zip_code' => '80212',
            'title' => 'Denver Investment Property',
            'description' => 'Great investment opportunity in Denver. Property needs some updates but has excellent location.',
            'property_type' => 'house',
            'asking_price' => 380000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '1600 Pennsylvania Avenue NW',
            'city' => 'Washington',
            'state' => 'DC',
            'zip_code' => '20500',
            'title' => 'Historic DC Property',
            'description' => 'Prime location in Washington DC. Excellent investment potential.',
            'property_type' => 'house',
            'asking_price' => 850000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '350 Fifth Avenue',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10118',
            'title' => 'NYC Investment Opportunity',
            'description' => 'Prime Manhattan location. High-value investment property.',
            'property_type' => 'house',
            'asking_price' => 1200000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '1 Infinite Loop',
            'city' => 'Cupertino',
            'state' => 'CA',
            'zip_code' => '95014',
            'title' => 'Silicon Valley Property',
            'description' => 'Excellent location in Cupertino. Great for tech professionals.',
            'property_type' => 'house',
            'asking_price' => 950000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '1600 Amphitheatre Parkway',
            'city' => 'Mountain View',
            'state' => 'CA',
            'zip_code' => '94043',
            'title' => 'Mountain View Tech Hub',
            'description' => 'Prime Silicon Valley location. Excellent investment.',
            'property_type' => 'house',
            'asking_price' => 1100000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '586 Franklin Avenue',
            'city' => 'Brooklyn',
            'state' => 'NY',
            'zip_code' => '11238',
            'title' => 'Brooklyn Fixer Upper',
            'description' => 'Great Brooklyn location. Needs renovation but excellent potential.',
            'property_type' => 'house',
            'asking_price' => 650000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '123 Main Street',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'zip_code' => '90001',
            'title' => 'LA Downtown Property',
            'description' => 'Downtown Los Angeles location. Great for investors.',
            'property_type' => 'house',
            'asking_price' => 450000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '100 Biscayne Boulevard',
            'city' => 'Miami',
            'state' => 'FL',
            'zip_code' => '33132',
            'title' => 'Miami Beachfront Property',
            'description' => 'Prime Miami location. Excellent beach access.',
            'property_type' => 'house',
            'asking_price' => 750000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '200 W Adams Street',
            'city' => 'Chicago',
            'state' => 'IL',
            'zip_code' => '60606',
            'title' => 'Chicago Loop Property',
            'description' => 'Downtown Chicago location. Great investment opportunity.',
            'property_type' => 'house',
            'asking_price' => 550000,
            'bedrooms' => null,
            'bathrooms' => null,
            'square_feet' => null,
            'year_built' => null,
        ],
        [
            'address' => '500 Market Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip_code' => '94102',
            'title' => 'SF Market Street Property',
            'description' => 'Prime San Francisco location. High-value investment.',
            'property_type' => 'house',
            'asking_price' => 1300000,
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
        $this->command->info('Address format: address1="Street Address", address2="City, State" (matching ATTOM API format)');
    }
}
