<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\AttomService;
use App\Services\PropertySeedingService;
use Illuminate\Console\Command;

class TestAttomServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attom:test
                            {--address= : Specific address to test}
                            {--city= : City for address}
                            {--state= : State code (2 letters)}
                            {--zip= : ZIP code}
                            {--endpoint= : Specific endpoint to test (detail, sale_history, comparable_sales, events)}
                            {--property-id= : Test with existing property ID}
                            {--all-endpoints : Test all endpoints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test ATTOM service with real USA addresses and verify data is fetched correctly';

    /**
     * Test addresses from different US states
     */
    protected array $testAddresses = [
        [
            'address' => '4529 Winona Court',
            'city' => 'Denver',
            'state' => 'CO',
            'zip' => '80212',
        ],
        [
            'address' => '586 Franklin Avenue',
            'city' => 'Brooklyn',
            'state' => 'NY',
            'zip' => '11238',
        ],
        [
            'address' => '200 W Adams Street',
            'city' => 'Chicago',
            'state' => 'IL',
            'zip' => '60606',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(AttomService $attomService, PropertySeedingService $seedingService): int
    {
        $this->info('Testing ATTOM Service with Real USA Addresses');
        $this->newLine();

        // Check if API key is configured
        if (empty(config('services.attom.api_key'))) {
            $this->error('ATTOM API key is not configured. Please set ATTOM_API_KEY in your .env file.');
            return Command::FAILURE;
        }

        $this->info('ATTOM API Key: ' . substr(config('services.attom.api_key'), 0, 8) . '...');
        $this->info('ATTOM Base URL: ' . config('services.attom.api_url'));
        $this->newLine();

        // Test with specific property ID
        if ($propertyId = $this->option('property-id')) {
            return $this->testWithProperty($propertyId, $attomService, $seedingService);
        }

        // Test with specific address
        if ($address = $this->option('address')) {
            return $this->testWithAddress(
                $address,
                $this->option('city'),
                $this->option('state'),
                $this->option('zip'),
                $attomService,
                $this->option('endpoint'),
                $this->option('all-endpoints')
            );
        }

        // Test with default addresses
        return $this->testWithDefaultAddresses($attomService, $seedingService);
    }

    /**
     * Test with existing property
     */
    protected function testWithProperty(string $propertyId, AttomService $attomService, PropertySeedingService $seedingService): int
    {
        $property = Property::find($propertyId);

        if (!$property) {
            $this->error("Property with ID {$propertyId} not found.");
            return Command::FAILURE;
        }

        $this->info("Testing with property: {$property->title}");
        $this->info("Address: {$property->address}, {$property->city}, {$property->state} {$property->zip_code}");
        $this->newLine();

        // Validate USA
        if (!$property->isUsa()) {
            $this->error("Property is not in USA (country: {$property->country})");
            return Command::FAILURE;
        }

        // Test enrichment
        try {
            $this->info('Enriching property with all ATTOM endpoints...');
            $enriched = $seedingService->enrichProperty($property, ['detail', 'sale_history', 'comparable_sales', 'events'], true);

            $this->displayEnrichmentResults($enriched);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Enrichment failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Test with specific address
     */
    protected function testWithAddress(
        string $address,
        ?string $city,
        ?string $state,
        ?string $zip,
        AttomService $attomService,
        ?string $endpoint = null,
        bool $allEndpoints = false
    ): int {
        $this->info("Testing address: {$address}");
        if ($city && $state) {
            $this->info("Location: {$city}, {$state} {$zip}");
        }
        $this->newLine();

        // Validate state if provided
        if ($state && !Property::isValidUsState($state)) {
            $this->error("Invalid US state code: {$state}");
            return Command::FAILURE;
        }

        // Validate ZIP if provided
        if ($zip && !Property::isValidUsZip($zip)) {
            $this->error("Invalid US ZIP code: {$zip}");
            return Command::FAILURE;
        }

        $endpoints = $allEndpoints 
            ? ['detail', 'sale_history', 'comparable_sales', 'events']
            : ($endpoint ? [$endpoint] : ['detail']);

        $success = true;

        foreach ($endpoints as $ep) {
            $this->info("Testing endpoint: {$ep}");
            $result = $this->testEndpoint($attomService, $ep, $address, $city, $state, $zip);
            if (!$result) {
                $success = false;
            }
            $this->newLine();
        }

        return $success ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Test with default addresses
     */
    protected function testWithDefaultAddresses(AttomService $attomService, PropertySeedingService $seedingService): int
    {
        $this->info('Testing with default USA addresses from different states...');
        $this->newLine();

        $results = [
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($this->testAddresses as $testAddress) {
            $this->info("Testing: {$testAddress['address']}, {$testAddress['city']}, {$testAddress['state']}");
            
            try {
                // Test detail endpoint
                $detail = $attomService->getPropertyDetails(
                    $testAddress['address'],
                    $testAddress['city'],
                    $testAddress['state'],
                    $testAddress['zip'],
                    true
                );

                if ($detail) {
                    $this->info("  ✓ Detail endpoint: SUCCESS");
                    $this->displayPropertyData($detail);
                    $results['success']++;
                } else {
                    $this->warn("  ✗ Detail endpoint: No data returned");
                    $results['failed']++;
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $results['failed']++;
            }

            $this->newLine();
        }

        $this->displaySummary($results);

        return $results['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Test specific endpoint
     */
    protected function testEndpoint(
        AttomService $attomService,
        string $endpoint,
        string $address,
        ?string $city,
        ?string $state,
        ?string $zip
    ): bool {
        try {
            $result = null;

            switch ($endpoint) {
                case 'detail':
                    $result = $attomService->getPropertyDetails($address, $city, $state, $zip, true);
                    break;
                case 'sale_history':
                    $result = $attomService->getSaleHistory($address, $city, $state, $zip, true);
                    break;
                case 'comparable_sales':
                    $result = $attomService->getComparableSales($address, $city, $state, $zip, ['maxResults' => 5], true);
                    break;
                case 'events':
                    $result = $attomService->getPropertyEvents($address, $city, $state, $zip, true);
                    break;
                default:
                    $this->error("Unknown endpoint: {$endpoint}");
                    return false;
            }

            if ($result) {
                $this->info("  ✓ {$endpoint}: SUCCESS");
                $this->displayEndpointData($endpoint, $result);
                return true;
            } else {
                $this->warn("  ✗ {$endpoint}: No data returned");
                return false;
            }

        } catch (\Exception $e) {
            $this->error("  ✗ {$endpoint}: Error - {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Display property data
     */
    protected function displayPropertyData(array $data): void
    {
        $this->line("  Square Feet: " . ($data['square_feet'] ?? 'N/A'));
        $this->line("  Bedrooms: " . ($data['bedrooms'] ?? 'N/A'));
        $this->line("  Bathrooms: " . ($data['bathrooms'] ?? 'N/A'));
        $this->line("  Year Built: " . ($data['year_built'] ?? 'N/A'));
        $this->line("  Property Type: " . ($data['property_type'] ?? 'N/A'));
        $this->line("  Attom ID: " . ($data['attom_id'] ?? 'N/A'));
        
        // If bedrooms/bathrooms are missing, check raw data
        if (empty($data['bedrooms']) && empty($data['bathrooms']) && isset($data['raw_data'])) {
            $this->checkRawDataForRooms($data['raw_data']);
        }
    }

    /**
     * Display endpoint-specific data
     */
    protected function displayEndpointData(string $endpoint, array $data): void
    {
        switch ($endpoint) {
            case 'detail':
                $this->displayPropertyData($data);
                break;
            case 'sale_history':
                $sales = $data['property'][0]['salehistory']['sale'] ?? [];
                $this->line("  Sales found: " . count($sales));
                if (!empty($sales)) {
                    $latest = $sales[0];
                    $this->line("  Latest sale: $" . number_format($latest['saleAmt'] ?? 0) . " on " . ($latest['saleTransDate'] ?? 'N/A'));
                }
                break;
            case 'comparable_sales':
                $comps = $data['property'] ?? [];
                $this->line("  Comparable sales found: " . count($comps));
                
                // Check if comps have bedroom/bathroom data
                if (!empty($comps) && isset($data['raw_data'])) {
                    $this->checkComparableSalesForRooms($data['raw_data']);
                }
                break;
            case 'events':
                $events = $data['property'][0]['event'] ?? [];
                $this->line("  Events found: " . count($events));
                break;
        }
    }

    /**
     * Display enrichment results
     */
    protected function displayEnrichmentResults(Property $property): void
    {
        $this->newLine();
        $this->info('Enrichment Results:');
        $this->table(
            ['Field', 'Value'],
            [
                ['Attom Data', $property->attom_data ? 'Yes' : 'No'],
                ['Sale History', $property->attom_sale_history ? 'Yes' : 'No'],
                ['Comparable Sales', $property->attom_comparable_sales ? 'Yes' : 'No'],
                ['Property Events', $property->attom_property_events ? 'Yes' : 'No'],
                ['Bedrooms', $property->bedrooms ?? 'N/A'],
                ['Bathrooms', $property->bathrooms ?? 'N/A'],
                ['Square Feet', $property->square_feet ?? 'N/A'],
                ['Enrichment Status', json_encode($property->attom_enrichment_status)],
                ['Enriched At', $property->attom_enriched_at?->toDateTimeString() ?? 'N/A'],
            ]
        );

        if ($property->attom_sale_history) {
            $sales = $property->attom_sale_history['total_sales'] ?? 0;
            $this->info("Sale History: {$sales} sales found");
        }

        if ($property->attom_comparable_sales) {
            $comps = $property->attom_comparable_sales['total_comps'] ?? 0;
            $this->info("Comparable Sales: {$comps} comps found");
            
            // Check if comps have bedroom/bathroom data
            $compsData = $property->attom_comparable_sales['comparable_sales'] ?? [];
            $compsWithData = 0;
            foreach ($compsData as $comp) {
                if (!empty($comp['bedrooms']) || !empty($comp['bathrooms'])) {
                    $compsWithData++;
                }
            }
            if ($compsWithData > 0) {
                $this->info("  → {$compsWithData} comps have bedroom/bathroom data (could be used as estimate)");
            }
        }

        if ($property->attom_property_events) {
            $events = $property->attom_property_events['total_events'] ?? 0;
            $this->info("Property Events: {$events} events found");
        }
        
        // If bedrooms/bathrooms are missing, check raw data
        if (empty($property->bedrooms) && empty($property->bathrooms) && $property->attom_data) {
            $this->newLine();
            $this->warn('⚠️  Bedrooms/Bathrooms missing - Checking raw ATTOM data...');
            // attom_data is cast as array in Property model, so it's already an array
            $rawData = $property->attom_data;
            if (is_array($rawData)) {
                $this->checkRawDataForRooms($rawData);
            }
        }
    }

    /**
     * Check raw ATTOM data for bedrooms/bathrooms
     */
    protected function checkRawDataForRooms(array $rawData): void
    {
        $property = $rawData['property'] ?? [];
        if (empty($property)) {
            return;
        }

        $propertyData = is_array($property[0] ?? null) ? $property[0] : $property;
        $building = $propertyData['building'] ?? [];
        $rooms = $building['rooms'] ?? [];
        
        $this->line('  🔍 Checking raw ATTOM data structure...');
        
        if (empty($rooms)) {
            $this->warn('    ❌ building.rooms is empty or missing');
        } else {
            $this->line('    Available fields in building.rooms: ' . implode(', ', array_keys($rooms)));
            
            // Check all possible bedroom fields
            $bedroomFields = ['beds', 'bedrooms', 'bedroom', 'bed', 'bedrm', 'bedrms'];
            $foundBedrooms = false;
            foreach ($bedroomFields as $field) {
                if (isset($rooms[$field]) && $rooms[$field] !== null && $rooms[$field] !== '') {
                    $this->info("    ✓ Found bedrooms in '{$field}': {$rooms[$field]}");
                    $foundBedrooms = true;
                }
            }
            if (!$foundBedrooms) {
                $this->warn('    ❌ No bedroom data found in building.rooms');
            }
            
            // Check all possible bathroom fields
            $bathroomFields = ['bathstotal', 'bathsfull', 'baths', 'bathroom', 'bathrooms', 'bath', 'bathrm', 'bathrms'];
            $foundBathrooms = false;
            foreach ($bathroomFields as $field) {
                if (isset($rooms[$field]) && $rooms[$field] !== null && $rooms[$field] !== '') {
                    $this->info("    ✓ Found bathrooms in '{$field}': {$rooms[$field]}");
                    $foundBathrooms = true;
                }
            }
            if (!$foundBathrooms) {
                $this->warn('    ❌ No bathroom data found in building.rooms');
            }
        }
    }

    /**
     * Check comparable sales for bedroom/bathroom data
     */
    protected function checkComparableSalesForRooms(array $rawData): void
    {
        $property = $rawData['property'] ?? [];
        if (empty($property)) {
            return;
        }

        $this->line('  🔍 Checking comparable sales for bedrooms/bathrooms...');
        
        $compsWithData = 0;
        $bedroomCounts = [];
        $bathroomCounts = [];
        
        foreach ($property as $comp) {
            $compRooms = $comp['building']['rooms'] ?? [];
            if (!empty($compRooms)) {
                $beds = $compRooms['beds'] ?? $compRooms['bedrooms'] ?? null;
                $baths = $compRooms['bathstotal'] ?? $compRooms['bathsfull'] ?? $compRooms['baths'] ?? null;
                
                if ($beds || $baths) {
                    $compsWithData++;
                    if ($beds) {
                        $bedroomCounts[] = (int)$beds;
                    }
                    if ($baths) {
                        $bathroomCounts[] = (float)$baths;
                    }
                }
            }
        }
        
        if ($compsWithData > 0) {
            $this->info("    ✓ Found bedroom/bathroom data in {$compsWithData} comparable sales");
            if (!empty($bedroomCounts)) {
                $avgBeds = round(array_sum($bedroomCounts) / count($bedroomCounts), 1);
                $this->info("    Average bedrooms: {$avgBeds} (from " . count($bedroomCounts) . " comps)");
            }
            if (!empty($bathroomCounts)) {
                $avgBaths = round(array_sum($bathroomCounts) / count($bathroomCounts), 1);
                $this->info("    Average bathrooms: {$avgBaths} (from " . count($bathroomCounts) . " comps)");
            }
            $this->line('    💡 Suggestion: Could use average from comparable sales as estimate');
        } else {
            $this->warn('    ✗ No bedroom/bathroom data found in comparable sales');
        }
    }

    /**
     * Display summary
     */
    protected function displaySummary(array $results): void
    {
        $this->newLine();
        $this->info('Test Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Success', $results['success']],
                ['Failed', $results['failed']],
                ['Total', $results['success'] + $results['failed']],
            ]
        );
    }
}
