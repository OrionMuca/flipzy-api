<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PropertySeedingService
{
    public function __construct(
        protected AttomService $attomService,
        protected AttomDataMapper $dataMapper,
        protected ?EstatedService $estatedService = null
    ) {}

    /**
     * Enrich a property with all ATTOM endpoints
     */
    public function enrichProperty(Property $property, array $endpoints = ['detail', 'sale_history', 'comparable_sales', 'events'], bool $forceFresh = false): Property
    {
        $enrichmentStatus = [
            'detail' => false,
            'sale_history' => false,
            'comparable_sales' => false,
            'events' => false,
        ];

        $detailData = null;
        $saleHistoryData = null;
        $comparableSalesData = null;
        $eventsData = null;

        try {
            // Get property detail (required for other endpoints)
            if (in_array('detail', $endpoints)) {
                $detailData = $this->attomService->getPropertyDetails(
                    $property->address,
                    $property->city,
                    $property->state,
                    $property->zip_code,
                    $forceFresh,
                    $property
                );

                if ($detailData) {
                    $enrichmentStatus['detail'] = true;
                    $this->updatePropertyFromAttomData($property, $detailData);
                    
                    // Check if ATTOM data is incomplete (missing critical fields like bedrooms/bathrooms)
                    $useEstatedFallback = config('services.attom.use_estated_fallback', true);
                    
                    if ($useEstatedFallback) {
                        $isIncomplete = $this->isAttomDataIncomplete($detailData);
                        
                        if ($isIncomplete) {
                            Log::info('ATTOM data incomplete, attempting Estated fallback', [
                                'property_id' => $property->id,
                                'missing_fields' => $this->getMissingFields($detailData),
                            ]);
                            
                            // Only try Estated if API key is configured
                            if (!empty(config('services.estated.api_key')) && $this->estatedService) {
                                try {
                                    $estatedData = $this->estatedService->getPropertyDetails(
                                        $property->address,
                                        $property->city,
                                        $property->state,
                                        $property->zip_code
                                    );
                                    
                                    if ($estatedData) {
                                        // Merge Estated data to fill gaps
                                        $this->mergeEstatedData($property, $estatedData, $detailData);
                                        $enrichmentStatus['estated_fallback'] = true;
                                        
                                        Log::info('Estated fallback successful', [
                                            'property_id' => $property->id,
                                            'filled_fields' => $this->getFilledFields($estatedData, $detailData),
                                        ]);
                                    } else {
                                        Log::warning('Estated fallback returned no data', [
                                            'property_id' => $property->id,
                                        ]);
                                        $enrichmentStatus['estated_fallback'] = false;
                                    }
                                } catch (\Exception $e) {
                                    // Don't fail enrichment if Estated fails - it's just a fallback
                                    Log::warning('Estated fallback failed', [
                                        'property_id' => $property->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                    $enrichmentStatus['estated_fallback'] = false;
                                }
                            } else {
                                Log::info('Estated fallback not configured (API key missing)', [
                                    'property_id' => $property->id,
                                    'hint' => 'Set ESTATED_API_KEY in .env to enable fallback',
                                ]);
                                $enrichmentStatus['estated_fallback'] = false;
                            }
                        }
                    }
                }
            }

            // Get sale history
            if (in_array('sale_history', $endpoints)) {
                $saleHistoryData = $this->attomService->getSaleHistory(
                    $property->address,
                    $property->city,
                    $property->state,
                    $property->zip_code,
                    $forceFresh,
                    $property
                );

                if ($saleHistoryData) {
                    $mappedSaleHistory = $this->dataMapper->mapSaleHistory($saleHistoryData);
                    $property->attom_sale_history = $mappedSaleHistory;
                    $enrichmentStatus['sale_history'] = true;
                }
            }

            // Get comparable sales
            if (in_array('comparable_sales', $endpoints)) {
                $comparableSalesData = $this->attomService->getComparableSales(
                    $property->address,
                    $property->city,
                    $property->state,
                    $property->zip_code,
                    ['maxResults' => 10],
                    $forceFresh,
                    $property
                );

                if ($comparableSalesData) {
                    $mappedComps = $this->dataMapper->mapComparableSales($comparableSalesData);
                    $property->attom_comparable_sales = $mappedComps;
                    $enrichmentStatus['comparable_sales'] = true;
                }
            }

            // Get property events
            if (in_array('events', $endpoints)) {
                $eventsData = $this->attomService->getPropertyEvents(
                    $property->address,
                    $property->city,
                    $property->state,
                    $property->zip_code,
                    $forceFresh,
                    $property
                );

                if ($eventsData) {
                    $mappedEvents = $this->dataMapper->mapPropertyEvents($eventsData);
                    $property->attom_property_events = $mappedEvents;
                    $enrichmentStatus['events'] = true;
                }
            }

            // Store full detail response in attom_data for backward compatibility
            if ($detailData && isset($detailData['raw_data'])) {
                $property->attom_data = $detailData['raw_data'];
            }

            // Update enrichment status and timestamp
            $property->attom_enrichment_status = $enrichmentStatus;
            $property->attom_enriched_at = now();
            $property->enriched_at = now();
            $property->save();

            Log::info('Property enriched with ATTOM data', [
                'property_id' => $property->id,
                'enrichment_status' => $enrichmentStatus,
            ]);

        } catch (\Exception $e) {
            Log::error('Property enrichment failed', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Save partial enrichment status
            $property->attom_enrichment_status = $enrichmentStatus;
            $property->save();

            throw $e;
        }

        return $property->fresh();
    }

    /**
     * Batch enrich multiple properties
     */
    public function batchEnrichProperties(Collection $properties, array $endpoints = ['detail', 'sale_history', 'comparable_sales', 'events'], bool $forceFresh = false): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($properties as $property) {
            try {
                // Validate property has required address data
                if (!$this->hasValidAddress($property)) {
                    $results['skipped']++;
                    Log::warning('Property skipped - invalid address', [
                        'property_id' => $property->id,
                    ]);
                    continue;
                }

                $this->enrichProperty($property, $endpoints, $forceFresh);
                $results['success']++;

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'property_id' => $property->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Batch enrichment failed for property', [
                    'property_id' => $property->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Seed a new property from ATTOM data
     */
    public function seedPropertyFromAttom(array $attomData, ?User $wholesaler = null): ?Property
    {
        try {
            // Ensure data is in the correct format (property array structure)
            if (!isset($attomData['property']) && isset($attomData[0])) {
                $attomData = ['property' => $attomData];
            }
            
            // Map ATTOM data
            $mappedData = $this->dataMapper->mapPropertyDetail($attomData);

            // Validate USA-only
            $addressParts = $this->parseAddress($mappedData['full_address'] ?? '');
            $state = $addressParts['state'] ?? '';
            $zip = $addressParts['zip'] ?? '';

            // Validate US state
            if (!empty($state) && !Property::isValidUsState($state)) {
                Log::warning('Invalid US state code for property seeding', [
                    'state' => $state,
                    'address' => $mappedData['full_address'] ?? '',
                ]);
                return null;
            }

            // Validate US ZIP code
            if (!empty($zip) && !Property::isValidUsZip($zip)) {
                Log::warning('Invalid US ZIP code for property seeding', [
                    'zip' => $zip,
                    'address' => $mappedData['full_address'] ?? '',
                ]);
                return null;
            }

            // Validate data
            $validation = $this->dataMapper->validatePropertyData($mappedData);
            if (!$validation['valid']) {
                Log::warning('Invalid ATTOM data for property seeding', [
                    'errors' => $validation['errors'],
                    'data' => $mappedData,
                ]);
                return null;
            }

            // Check if property already exists by AttomID
            if (isset($mappedData['attom_id']) && $mappedData['attom_id']) {
                $existing = Property::whereJsonContains('attom_data->property[0]->identifier->attomId', $mappedData['attom_id'])
                    ->orWhere('attom_data->property[0]->identifier->attomId', $mappedData['attom_id'])
                    ->first();

                if ($existing) {
                    Log::info('Property already exists with AttomID', [
                        'attom_id' => $mappedData['attom_id'],
                        'property_id' => $existing->id,
                    ]);
                    return $existing;
                }
            }

            // Extract address components
            $addressParts = $this->parseAddress($mappedData['full_address'] ?? '');

            // Create property (USA-only)
            $property = Property::create([
                'wholesaler_id' => $wholesaler?->id,
                'title' => $this->generatePropertyTitle($mappedData),
                'description' => $this->generatePropertyDescription($mappedData),
                'property_type' => $mappedData['property_type'] ?? 'house',
                'status' => 'active',
                'address' => $addressParts['address'] ?? $mappedData['full_address'] ?? '',
                'city' => $addressParts['city'] ?? '',
                'state' => strtoupper($addressParts['state'] ?? ''),
                'zip_code' => $addressParts['zip'] ?? '',
                'country' => 'US', // Enforce USA-only
                'latitude' => $mappedData['latitude'],
                'longitude' => $mappedData['longitude'],
                'bedrooms' => $mappedData['bedrooms'],
                'bathrooms' => $mappedData['bathrooms'],
                'square_feet' => $mappedData['square_feet'],
                'lot_size' => $mappedData['lot_size'],
                'year_built' => $mappedData['year_built'],
                'condition' => 'fair',
                'asking_price' => $mappedData['assessed_value'] ?? $mappedData['market_value'] ?? 0,
                'attom_data' => $attomData['raw_data'] ?? $attomData,
                'attom_enrichment_status' => ['detail' => true],
                'attom_enriched_at' => now(),
                'enriched_at' => now(),
                'is_verified' => true, // ATTOM data is verified
                'allow_inquiries' => true,
            ]);

            Log::info('Property seeded from ATTOM data', [
                'property_id' => $property->id,
                'attom_id' => $mappedData['attom_id'] ?? null,
            ]);

            return $property;

        } catch (\Exception $e) {
            Log::error('Failed to seed property from ATTOM data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Discover and seed new properties from ATTOM search
     */
    public function discoverProperties(array $criteria, ?User $wholesaler = null, int $limit = 10): array
    {
        $results = [
            'found' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $searchResults = $this->attomService->searchProperties($criteria);

            if (!$searchResults || empty($searchResults['property'])) {
                Log::info('No properties found in ATTOM search', ['criteria' => $criteria]);
                return $results;
            }

            $properties = $searchResults['property'];
            $results['found'] = count($properties);

            // Limit results
            if ($limit > 0) {
                $properties = array_slice($properties, 0, $limit);
            }

            foreach ($properties as $propertyData) {
                try {
                    // Get full detail for each property
                    $attomId = $propertyData['identifier']['attomId'] ?? null;
                    if (!$attomId) {
                        $results['skipped']++;
                        continue;
                    }

                    // Check if already exists
                    $existing = Property::whereJsonContains('attom_data->property[0]->identifier->attomId', $attomId)
                        ->orWhere('attom_data->property[0]->identifier->attomId', $attomId)
                        ->first();

                    if ($existing) {
                        $results['skipped']++;
                        continue;
                    }

                    // Get full property detail
                    $address = $propertyData['address']['oneLine'] ?? '';
                    $city = $propertyData['address']['city'] ?? '';
                    $state = $propertyData['address']['state'] ?? '';
                    $zip = $propertyData['address']['postal1'] ?? '';
                    
                    $detailData = $this->attomService->getPropertyDetails($address, $city, $state, $zip);

                    if (!$detailData || !isset($detailData['raw_data'])) {
                        $results['skipped']++;
                        continue;
                    }

                    // Seed property with full detail data
                    $property = $this->seedPropertyFromAttom($detailData['raw_data'], $wholesaler);

                    if ($property) {
                        $results['created']++;
                    } else {
                        $results['skipped']++;
                    }

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'attom_id' => $attomId ?? null,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to seed discovered property', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Property discovery failed', [
                'error' => $e->getMessage(),
                'criteria' => $criteria,
            ]);
            $results['errors'][] = ['error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Update property with data from ATTOM
     */
    protected function updatePropertyFromAttomData(Property $property, array $data): void
    {
        $updates = [];

        if (isset($data['square_feet']) && !$property->square_feet) {
            $updates['square_feet'] = $data['square_feet'];
        }
        if (isset($data['lot_size']) && !$property->lot_size) {
            $updates['lot_size'] = $data['lot_size'];
        }
        if (isset($data['year_built']) && !$property->year_built) {
            $updates['year_built'] = $data['year_built'];
        }
        if (isset($data['bedrooms']) && !$property->bedrooms) {
            $updates['bedrooms'] = $data['bedrooms'];
        }
        if (isset($data['bathrooms']) && !$property->bathrooms) {
            $updates['bathrooms'] = $data['bathrooms'];
        }
        if (isset($data['property_type']) && !$property->property_type) {
            $updates['property_type'] = $data['property_type'];
        }
        if (isset($data['latitude']) && !$property->latitude) {
            $updates['latitude'] = $data['latitude'];
        }
        if (isset($data['longitude']) && !$property->longitude) {
            $updates['longitude'] = $data['longitude'];
        }

        if (!empty($updates)) {
            $property->update($updates);
        }
    }

    /**
     * Check if property has valid address
     */
    protected function hasValidAddress(Property $property): bool
    {
        if (empty($property->address) || empty($property->city) || empty($property->state)) {
            return false;
        }

        // Validate USA-only
        if ($property->country !== 'US') {
            return false;
        }

        // Validate US state code
        if (!Property::isValidUsState($property->state)) {
            return false;
        }

        // Validate US ZIP code if provided
        if (!empty($property->zip_code) && !Property::isValidUsZip($property->zip_code)) {
            return false;
        }

        return true;
    }

    /**
     * Parse address string into components
     */
    protected function parseAddress(string $fullAddress): array
    {
        $parts = [
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
        ];

        if (empty($fullAddress)) {
            return $parts;
        }

        // Try to parse "Street Address, City, State ZIP" format
        if (preg_match('/^(.+?),\s*(.+?),\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $fullAddress, $matches)) {
            $parts['address'] = trim($matches[1]);
            $parts['city'] = trim($matches[2]);
            $parts['state'] = trim($matches[3]);
            $parts['zip'] = trim($matches[4]);
        } elseif (preg_match('/^(.+?),\s*(.+?),\s*([A-Z]{2})$/', $fullAddress, $matches)) {
            $parts['address'] = trim($matches[1]);
            $parts['city'] = trim($matches[2]);
            $parts['state'] = trim($matches[3]);
        }

        return $parts;
    }

    /**
     * Generate property title from ATTOM data
     */
    protected function generatePropertyTitle(array $data): string
    {
        $bedrooms = $data['bedrooms'] ?? '';
        $bathrooms = $data['bathrooms'] ?? '';
        $sqft = $data['square_feet'] ?? '';
        $city = $this->extractCityFromAddress($data['full_address'] ?? '');

        $parts = [];
        if ($bedrooms) $parts[] = "{$bedrooms}BR";
        if ($bathrooms) $parts[] = "{$bathrooms}BA";
        if ($sqft) $parts[] = number_format($sqft) . ' sqft';
        if ($city) $parts[] = $city;

        return !empty($parts) ? implode(' ', $parts) : 'Property';
    }

    /**
     * Generate property description from ATTOM data
     */
    protected function generatePropertyDescription(array $data): string
    {
        $parts = [];
        
        if (isset($data['property_type'])) {
            $parts[] = ucfirst($data['property_type']);
        }
        if (isset($data['year_built'])) {
            $parts[] = "built in {$data['year_built']}";
        }
        if (isset($data['square_feet'])) {
            $parts[] = number_format($data['square_feet']) . ' square feet';
        }
        if (isset($data['lot_size'])) {
            $parts[] = number_format($data['lot_size']) . ' sqft lot';
        }

        $description = !empty($parts) ? implode(', ', $parts) . '.' : 'Property details available.';
        
        if (isset($data['full_address'])) {
            $description .= " Located at {$data['full_address']}.";
        }

        return $description;
    }

    /**
     * Extract city from full address
     */
    protected function extractCityFromAddress(string $fullAddress): string
    {
        if (preg_match('/,\s*([^,]+?),\s*[A-Z]{2}/', $fullAddress, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Check if ATTOM data is incomplete (missing critical fields)
     */
    protected function isAttomDataIncomplete(array $detailData): bool
    {
        // Check if bedrooms or bathrooms are missing
        $bedrooms = $detailData['bedrooms'] ?? null;
        $bathrooms = $detailData['bathrooms'] ?? null;
        
        return empty($bedrooms) || empty($bathrooms);
    }

    /**
     * Get list of missing fields from ATTOM data
     */
    protected function getMissingFields(array $detailData): array
    {
        $missing = [];
        
        if (empty($detailData['bedrooms'])) {
            $missing[] = 'bedrooms';
        }
        if (empty($detailData['bathrooms'])) {
            $missing[] = 'bathrooms';
        }
        
        return $missing;
    }

    /**
     * Get list of fields that were filled by Estated
     */
    protected function getFilledFields(array $estatedData, array $attomData): array
    {
        $filled = [];
        
        if (empty($attomData['bedrooms']) && !empty($estatedData['bedrooms'])) {
            $filled[] = 'bedrooms';
        }
        if (empty($attomData['bathrooms']) && !empty($estatedData['bathrooms'])) {
            $filled[] = 'bathrooms';
        }
        
        return $filled;
    }

    /**
     * Merge Estated data to fill missing fields in property
     */
    protected function mergeEstatedData(Property $property, array $estatedData, array $attomData): void
    {
        $updates = [];
        
        // Only fill fields that are missing from ATTOM
        if (empty($attomData['bedrooms']) && !empty($estatedData['bedrooms'])) {
            $updates['bedrooms'] = $estatedData['bedrooms'];
        }
        
        if (empty($attomData['bathrooms']) && !empty($estatedData['bathrooms'])) {
            $updates['bathrooms'] = $estatedData['bathrooms'];
        }
        
        // Also fill other missing fields if available
        if (empty($property->square_feet) && !empty($estatedData['square_feet'])) {
            $updates['square_feet'] = $estatedData['square_feet'];
        }
        
        if (empty($property->lot_size) && !empty($estatedData['lot_size'])) {
            $updates['lot_size'] = $estatedData['lot_size'];
        }
        
        if (empty($property->year_built) && !empty($estatedData['year_built'])) {
            $updates['year_built'] = $estatedData['year_built'];
        }
        
        if (!empty($updates)) {
            $property->update($updates);
        }
    }
}

