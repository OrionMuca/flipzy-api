<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Log;

class PropertyEnrichmentService
{
    public function __construct(
        protected AttomService $attomService,
        protected GeoService $geoService,
        protected PropertySeedingService $seedingService
    ) {}

    /**
     * Enrich a property with data from external APIs (queued by default)
     */
    public function enrichProperty(
        Property $property, 
        bool $forceFresh = false, 
        array $endpoints = ['detail', 'sale_history', 'comparable_sales', 'events']
    ): Property {
        // Use the new comprehensive ATTOM enrichment
        \App\Jobs\EnrichPropertyWithAttomJob::dispatch($property, $endpoints, $forceFresh);
        return $property;
    }

    /**
     * Perform the actual enrichment
     */
    public function performEnrichment(
        Property $property, 
        bool $forceFresh = false, 
        array $endpoints = ['detail', 'sale_history', 'comparable_sales', 'events']
    ): Property {
        $enrichmentData = [
            'attom_data' => null,
            'geocoding_data' => null,
        ];

        try {
            // Use comprehensive ATTOM enrichment
            $this->seedingService->enrichProperty($property, $endpoints, $forceFresh);
            $enrichmentData['attom_data'] = $property->attom_data;
        } catch (\Exception $e) {
            Log::error('ATTOM enrichment failed', [
                'property_id' => $property->id,
                'address' => $property->address,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Fallback to basic enrichment if comprehensive fails
            try {
                $attomData = $this->attomService->getPropertyDetails(
                    $property->address,
                    $property->city,
                    $property->state,
                    $property->zip_code,
                    $forceFresh,
                    $property
                );

                if ($attomData) {
                    $enrichmentData['attom_data'] = $attomData['raw_data'] ?? $attomData;
                    $this->updatePropertyFromAttom($property, $attomData);
                }
            } catch (\Exception $fallbackException) {
                Log::error('ATTOM fallback enrichment also failed', [
                    'property_id' => $property->id,
                    'error' => $fallbackException->getMessage(),
                    'trace' => $fallbackException->getTraceAsString(),
                ]);
            }
        }

        // Geocode address if coordinates are missing
        if (!$property->latitude || !$property->longitude) {
            try {
                $geoData = $this->geoService->geocode(
                    $property->address,
                    $property->city,
                    $property->state,
                    $property->zip_code,
                    $forceFresh
                );

                if ($geoData) {
                    $enrichmentData['geocoding_data'] = $geoData['raw_data'] ?? $geoData;
                    
                    if (!$property->latitude && isset($geoData['latitude'])) {
                        $property->latitude = $geoData['latitude'];
                    }
                    if (!$property->longitude && isset($geoData['longitude'])) {
                        $property->longitude = $geoData['longitude'];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Geocoding failed', [
                    'property_id' => $property->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $property->attom_data = $enrichmentData['attom_data'];
        $property->enriched_at = now();
        $property->save();

        return $property->fresh();
    }

    /**
     * Fetch property data without storing to database (for preview/testing)
     */
    public function fetchPropertyData(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null,
        array $endpoints = ['detail'],
        bool $forceFresh = false
    ): array {
        $results = [];

        foreach ($endpoints as $endpoint) {
            try {
                $results[$endpoint] = match($endpoint) {
                    'detail' => $this->attomService->fetchPropertyData($address, $city, $state, $zip, $forceFresh),
                    'sale_history' => $this->attomService->getSaleHistory($address, $city, $state, $zip, $forceFresh, null),
                    'comparable_sales' => $this->attomService->getComparableSales($address, $city, $state, $zip, [], $forceFresh, null),
                    'events' => $this->attomService->getPropertyEvents($address, $city, $state, $zip, $forceFresh, null),
                    'snapshot' => $this->attomService->getPropertySnapshot($address, $city, $state, $forceFresh, null),
                    default => null,
                };
            } catch (\Exception $e) {
                Log::warning("Failed to fetch {$endpoint} data", [
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
                $results[$endpoint] = null;
            }
        }

        return $results;
    }

    /**
     * Fetch and extract property data (returns clean formatted data)
     */
    public function fetchAndExtractPropertyData(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null,
        bool $forceFresh = false
    ): ?array {
        try {
            return $this->attomService->fetchAndExtractPropertyData(
                $address, 
                $city, 
                $state, 
                $zip, 
                $forceFresh
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch and extract property data', [
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update property with data from ATTOM
     */
    protected function updatePropertyFromAttom(Property $property, array $data): void
    {
        $updates = [];

        // Only update fields that are empty/null
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
        if (isset($data['zoning_type']) && !$property->zoning_type) {
            $updates['zoning_type'] = $data['zoning_type'];
        }
        if (isset($data['pool_type']) && !$property->pool_type) {
            $updates['pool_type'] = $data['pool_type'];
        }
        if (isset($data['municipality']) && !$property->municipality) {
            $updates['municipality'] = $data['municipality'];
        }
        if (isset($data['legal1']) && !$property->legal1) {
            $updates['legal1'] = $data['legal1'];
        }
        if (isset($data['cooling_type']) && !$property->cooling_type) {
            $updates['cooling_type'] = $data['cooling_type'];
        }
        if (isset($data['heating_fuel']) && !$property->heating_fuel) {
            $updates['heating_fuel'] = $data['heating_fuel'];
        }
        if (isset($data['heating_type']) && !$property->heating_type) {
            $updates['heating_type'] = $data['heating_type'];
        }
        if (isset($data['last_sale_date']) && !$property->last_sale_date) {
            $updates['last_sale_date'] = $data['last_sale_date'];
        }
        if (isset($data['living_size']) && !$property->living_size) {
            $updates['living_size'] = $data['living_size'];
        }
        if (isset($data['gross_size']) && !$property->gross_size) {
            $updates['gross_size'] = $data['gross_size'];
        }
        if (isset($data['tax_amount']) && !$property->tax_amount) {
            $updates['tax_amount'] = $data['tax_amount'];
        }
        if (isset($data['tax_year']) && !$property->tax_year) {
            $updates['tax_year'] = $data['tax_year'];
        }

        if (!empty($updates)) {
            $property->update($updates);
            Log::info('Updated property from ATTOM data', [
                'property_id' => $property->id,
                'updated_fields' => array_keys($updates),
            ]);
        }
    }

    /**
     * Validate if address is complete enough for API call
     */
    public function validateAddress(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null
    ): bool {
        // At minimum we need street address
        if (empty(trim($address))) {
            return false;
        }

        // Prefer having city and state, but can work with just address
        // ATTOM API works best with complete addresses
        return true;
    }

    /**
     * Format address for display
     */
    public function formatAddress(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null
    ): string {
        $parts = array_filter([
            trim($address),
            trim($city ?? ''),
            trim($state ?? ''),
            trim($zip ?? ''),
        ]);

        return implode(', ', $parts);
    }
}