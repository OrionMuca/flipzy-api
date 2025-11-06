<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Log;

class PropertyEnrichmentService
{
    public function __construct(
        protected AttomService $attomService,
        // protected EstatedService $estatedService, // Temporarily disabled
        protected GeoService $geoService
    ) {}

    /**
     * Enrich a property with data from external APIs
     */
    public function enrichProperty(Property $property, bool $useQueue = true): Property
    {
        if ($useQueue) {
            // Dispatch to queue for async processing
            \App\Jobs\EnrichPropertyJob::dispatch($property);
            return $property;
        }

        // Synchronous enrichment
        return $this->performEnrichment($property);
    }

    /**
     * Perform the actual enrichment
     */
    public function performEnrichment(Property $property, bool $forceFresh = false): Property
    {
        $enrichmentData = [
            'attom_data' => null,
            // 'estated_data' => null, // Temporarily disabled
            'geocoding_data' => null,
        ];

        // Step 1: Try ATTOM API first (most comprehensive)
        // ATTOM can work with just address1, but address2 (city, state, zip) improves accuracy
        try {
            $attomData = $this->attomService->getPropertyDetails(
                $property->address,
                $property->city,
                $property->state,
                $property->zip_code,
                $forceFresh ?? false
            );

            if ($attomData) {
                $enrichmentData['attom_data'] = $attomData['raw_data'] ?? $attomData;
                
                // Log what data was received from ATTOM
                Log::info('ATTOM data received successfully', [
                    'property_id' => $property->id,
                    'address' => $property->address,
                    'city' => $property->city,
                    'state' => $property->state,
                    'data_fields_received' => array_keys(array_filter($attomData, fn($v) => $v !== null && !is_array($v))),
                ]);
                
                // Update property with ATTOM data
                $this->updatePropertyFromAttom($property, $attomData);
            } else {
                Log::warning('ATTOM returned no data (property may not exist in ATTOM database)', [
                    'property_id' => $property->id,
                    'address' => $property->address,
                    'city' => $property->city,
                    'state' => $property->state,
                    'zip_code' => $property->zip_code,
                    'note' => 'Property may not exist in ATTOM database or address format is incorrect',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ATTOM enrichment exception', [
                'property_id' => $property->id,
                'address' => $property->address,
                'city' => $property->city,
                'state' => $property->state,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Step 2: Estated Service - Temporarily disabled
        // if (!$enrichmentData['attom_data'] || $this->isDataIncomplete($attomData ?? [])) {
        //     try {
        //         $estatedData = $this->estatedService->getPropertyDetails(
        //             $property->address,
        //             $property->city,
        //             $property->state,
        //             $property->zip_code
        //         );
        //
        //         if ($estatedData) {
        //             $enrichmentData['estated_data'] = $estatedData['raw_data'] ?? $estatedData;
        //             
        //             // Update property with Estated data (only if ATTOM didn't provide it)
        //             if (!$enrichmentData['attom_data']) {
        //                 $this->updatePropertyFromEstated($property, $estatedData);
        //             }
        //         }
        //     } catch (\Exception $e) {
        //         Log::error('Estated enrichment failed', [
        //             'property_id' => $property->id,
        //             'error' => $e->getMessage(),
        //         ]);
        //     }
        // }

        // Step 3: Always geocode address (if coordinates missing)
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
                ]);
            }
        }

        // Update property with all enrichment data
        $property->attom_data = $enrichmentData['attom_data'];
        // $property->estated_data = $enrichmentData['estated_data']; // Temporarily disabled
        $property->enriched_at = now();
        $property->save();

        return $property->fresh();
    }

    /**
     * Update property with data from ATTOM
     */
    protected function updatePropertyFromAttom(Property $property, array $data): void
    {
        $updates = [];
        $beforeValues = [];

        // Track what fields will be updated
        if (isset($data['square_feet']) && !$property->square_feet) {
            $beforeValues['square_feet'] = $property->square_feet;
            $updates['square_feet'] = $data['square_feet'];
        }
        if (isset($data['lot_size']) && !$property->lot_size) {
            $beforeValues['lot_size'] = $property->lot_size;
            $updates['lot_size'] = $data['lot_size'];
        }
        if (isset($data['year_built']) && !$property->year_built) {
            $beforeValues['year_built'] = $property->year_built;
            $updates['year_built'] = $data['year_built'];
        }
        if (isset($data['bedrooms']) && !$property->bedrooms) {
            $beforeValues['bedrooms'] = $property->bedrooms;
            $updates['bedrooms'] = $data['bedrooms'];
        }
        if (isset($data['bathrooms']) && !$property->bathrooms) {
            $beforeValues['bathrooms'] = $property->bathrooms;
            $updates['bathrooms'] = $data['bathrooms'];
        }
        if (isset($data['property_type']) && !$property->property_type) {
            $beforeValues['property_type'] = $property->property_type;
            $updates['property_type'] = $data['property_type'];
        }
        if (isset($data['latitude']) && !$property->latitude) {
            $beforeValues['latitude'] = $property->latitude;
            $updates['latitude'] = $data['latitude'];
        }
        if (isset($data['longitude']) && !$property->longitude) {
            $beforeValues['longitude'] = $property->longitude;
            $updates['longitude'] = $data['longitude'];
        }

        if (!empty($updates)) {
            $property->update($updates);
            
            // Log what was updated
            Log::info('Property updated from ATTOM data', [
                'property_id' => $property->id,
                'fields_updated' => array_keys($updates),
                'before_values' => $beforeValues,
                'new_values' => $updates,
            ]);
        } else {
            Log::info('No property fields updated from ATTOM (all fields already have values)', [
                'property_id' => $property->id,
                'available_data' => array_keys(array_filter($data, fn($v) => $v !== null)),
            ]);
        }
    }

    /**
     * Update property with data from Estated
     */
    protected function updatePropertyFromEstated(Property $property, array $data): void
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
     * Check if enrichment data is incomplete
     */
    protected function isDataIncomplete(array $data): bool
    {
        $requiredFields = ['square_feet', 'year_built', 'bedrooms', 'bathrooms'];
        $missingFields = 0;

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields++;
            }
        }

        // Consider incomplete if more than 2 required fields are missing
        return $missingFields > 2;
    }
}

