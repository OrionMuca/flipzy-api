<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Log;

class PropertyEnrichmentService
{
    public function __construct(
        protected AttomService $attomService,
        protected GeoService $geoService
    ) {}

    /**
     * Enrich a property with data from external APIs (queued by default)
     */
    public function enrichProperty(Property $property, bool $forceFresh = false): Property
    {
        \App\Jobs\EnrichPropertyJob::dispatch($property, $forceFresh);
        return $property;
    }

    /**
     * Perform the actual enrichment
     */
    public function performEnrichment(Property $property, bool $forceFresh = false): Property
    {
        $enrichmentData = [
            'attom_data' => null,
            'geocoding_data' => null,
        ];

        try {
            $attomData = $this->attomService->getPropertyDetails(
                $property->address,
                $property->city,
                $property->state,
                $property->zip_code,
                $forceFresh
            );

            if ($attomData) {
                $enrichmentData['attom_data'] = $attomData['raw_data'] ?? $attomData;
                $this->updatePropertyFromAttom($property, $attomData);
            }
        } catch (\Exception $e) {
            Log::error('ATTOM enrichment failed', [
                'property_id' => $property->id,
                'address' => $property->address,
                'error' => $e->getMessage(),
            ]);
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
                ]);
            }
        }

        $property->attom_data = $enrichmentData['attom_data'];
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
}

