<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyBoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            
            // Location Preferences
            'location' => [
                'preferred_cities' => $this->preferred_cities ?? [],
                'preferred_zip_codes' => $this->preferred_zip_codes ?? [],
                'target_counties' => $this->target_counties ?? [],
                'target_neighborhoods' => $this->target_neighborhoods ?? [],
                'must_have_amenities' => $this->must_have_amenities ?? [],
            ],
            
            // Property Details
            'property_details' => [
                'bedrooms' => [
                    'min' => $this->min_bedrooms,
                    'max' => $this->max_bedrooms,
                ],
                'bathrooms' => [
                    'min' => $this->min_bathrooms,
                    'max' => $this->max_bathrooms,
                ],
                'square_feet' => [
                    'min' => $this->min_square_feet,
                    'max' => $this->max_square_feet,
                ],
                'lot_size' => [
                    'min' => $this->min_lot_size,
                    'max' => $this->max_lot_size,
                ],
            ],
            
            // Property Condition
            'property_conditions' => $this->property_conditions ?? [],
            
            // Property Type
            'property_types' => $this->property_types ?? [],
            
            // Additional Considerations
            'has_adu_potential' => $this->has_adu_potential,
            
            // Home Construction
            'construction_types' => $this->construction_types ?? [],
            
            // Amenities & Features
            'amenities' => [
                'has_pool' => $this->has_pool,
                'is_waterfront' => $this->is_waterfront,
            ],
            
            // Desired Layout
            'layout_types' => $this->layout_types ?? [],
            
            // Funding Methods
            'funding_methods' => $this->funding_methods ?? [],
            
            // Rental Investment Criteria
            'rental_investment_criteria' => [
                'min_profit' => $this->min_profit,
                'min_roi' => $this->min_roi,
                'target_cap_rate' => $this->target_cap_rate,
                'desired_occupancy_rate' => $this->desired_occupancy_rate,
                'expected_monthly_cash_flow' => $this->expected_monthly_cash_flow,
                'expected_annual_cash_flow' => $this->expected_annual_cash_flow,
            ],
            
            // Investment Strategies
            'investment_strategies' => $this->investment_strategies ?? [],
            
            // Metadata
            'created_at' => $this->created_at?->format('m-d-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('m-d-Y H:i:s'),
        ];
    }
}
