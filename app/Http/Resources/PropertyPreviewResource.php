<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for formatting ATTOM API property data to match PropertyResource structure
 * This ensures consistency between lookup/preview data and saved property data
 */
class PropertyPreviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Matches PropertyResource structure for consistency
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource; // This is an array, not a model
        
        // Map ATTOM data to PropertyResource structure
        return [
            'id' => null, // No ID for preview data
            'title' => null, // Title is set by user when creating property
            'description' => null, // Description is set by user when creating property
            'property_type' => $this->mapPropertyType($data['property_type'] ?? null),
            'status' => null, // Status is set when property is created
            
            // Address - map ATTOM fields to Property entity fields
            'address' => $data['street_address'] ?? $data['full_address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'country' => 'US', // Default to US for ATTOM data
            'location' => [
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ],
            
            // Property Details - map ATTOM fields to Property entity fields
            'details' => [
                'bedrooms' => $this->castToInt($data['bedrooms'] ?? null),
                'bathrooms' => $this->castToFloat($data['bathrooms'] ?? $data['bathrooms_total_decimal'] ?? null),
                'square_feet' => $this->castToInt($data['square_feet'] ?? $data['living_size'] ?? null),
                'lot_size' => $this->castToInt($data['lot_size'] ?? null),
                'year_built' => $this->castToInt($data['year_built'] ?? null),
                'condition' => $this->mapCondition($data['construction_condition'] ?? null),
            ],
            
            // Financial - these are typically not in ATTOM preview data
            'financial' => [
                'asking_price' => null, // Set by wholesaler
                'arv' => $this->castToFloat($data['market_value'] ?? $data['assessed_value'] ?? null),
                'repair_estimate' => null, // Calculated separately
                'potential_profit' => null, // Calculated when property is created
            ],
            
            // Images - not available in preview
            'images' => [],
            'primary_image' => null,
            
            // Wholesaler - not available in preview
            'wholesaler' => null,
            
            // Flags - not available in preview
            'is_featured' => false,
            'is_verified' => false,
            'allow_inquiries' => false,
            
            // Additional ATTOM data that might be useful
            'attom_data' => [
                'assessed_value' => $this->castToFloat($data['assessed_value'] ?? null),
                'market_value' => $this->castToFloat($data['market_value'] ?? null),
                'tax_amount' => $this->castToFloat($data['tax_amount'] ?? null),
                'tax_year' => $data['tax_year'] ?? null,
                'attom_id' => $data['attom_id'] ?? null,
                'apn' => $data['apn'] ?? null,
                'full_address' => $data['full_address'] ?? null,
                'property_class' => $data['property_class'] ?? null,
                'subdivision' => $data['subdivision'] ?? null,
                'county' => $data['county'] ?? null,
                'country' => $data['country'] ?? 'US',
                'zoning_type' => $data['zoning_type'] ?? null,
                'pool_type' => $data['pool_type'] ?? null,
                'municipality' => $data['municipality'] ?? null,
                'legal1' => $data['legal1'] ?? null,
                'cooling_type' => $data['cooling_type'] ?? null,
                'heating_type' => $data['heating_type'] ?? null,
                'heating_fuel' => $data['heating_fuel'] ?? null,
                'living_size' => $data['living_size'] ?? null,
                'gross_size' => $data['gross_size'] ?? null,
                'last_sale_date' => $data['last_sale_date'] ?? null,
            ],

            // Building permits (from ATTOM /property/buildingpermits endpoint)
            'building_permits' => $this->extractPermits($data['building_permits'] ?? null),

            // Metadata
            'created_at' => null,
            'updated_at' => null,
        ];
    }
    
    /**
     * Map ATTOM property type to our property type
     */
    protected function mapPropertyType(?string $attomType): ?string
    {
        if (!$attomType) {
            return null;
        }
        
        $type = strtolower($attomType);
        
        $mapping = [
            'single family' => 'house',
            'single family residence' => 'house',
            'condominium' => 'condo',
            'townhouse' => 'townhouse',
            'multi-family' => 'apartment',
            'commercial' => 'other',
            'land' => 'land',
            'mobile home' => 'other',
        ];
        
        foreach ($mapping as $attomKey => $ourType) {
            if (str_contains($type, $attomKey)) {
                return $ourType;
            }
        }
        
        return 'house'; // Default
    }
    
    /**
     * Map ATTOM condition to our condition
     */
    protected function mapCondition(?string $attomCondition): ?string
    {
        if (!$attomCondition) {
            return null;
        }
        
        $condition = strtolower($attomCondition);
        
        $mapping = [
            'excellent' => 'excellent',
            'good' => 'good',
            'fair' => 'fair',
            'poor' => 'poor',
            'needs repair' => 'needs_repair',
        ];
        
        foreach ($mapping as $attomKey => $ourCondition) {
            if (str_contains($condition, $attomKey)) {
                return $ourCondition;
            }
        }
        
        return 'fair'; // Default
    }
    
    /**
     * Extract permits array from mapped building permits data.
     * Already sorted by effective_date desc in AttomDataMapper.
     */
    protected function extractPermits(?array $buildingPermits): ?array
    {
        if (empty($buildingPermits)) {
            return null;
        }

        $permits = $buildingPermits['permits'] ?? [];

        return !empty($permits) ? $permits : null;
    }

    /**
     * Cast to integer, handling null
     */
    protected function castToInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }
    
    /**
     * Cast to float, handling null
     */
    protected function castToFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }
}
