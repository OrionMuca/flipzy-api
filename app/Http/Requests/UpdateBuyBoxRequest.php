<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyBoxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        // Only investors and admins can manage buy boxes
        return $user && ($user->hasRole('investor') || $user->hasRole('admin'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Location Preferences
            'preferred_cities' => 'nullable|array',
            'preferred_cities.*' => 'string|max:100',
            'preferred_zip_codes' => 'nullable|array',
            'preferred_zip_codes.*' => 'string|max:10',
            'target_counties' => 'nullable|array',
            'target_counties.*' => 'string|max:100',
            'target_neighborhoods' => 'nullable|array',
            'target_neighborhoods.*' => 'string|max:100',
            'must_have_amenities' => 'nullable|array',
            'must_have_amenities.*' => 'string|max:255',
            
            // Property Details
            'min_bedrooms' => 'nullable|integer|min:0|max:50',
            'max_bedrooms' => 'nullable|integer|min:0|max:50',
            'min_bathrooms' => 'nullable|numeric|min:0|max:50',
            'max_bathrooms' => 'nullable|numeric|min:0|max:50',
            'min_square_feet' => 'nullable|integer|min:0',
            'max_square_feet' => 'nullable|integer|min:0',
            'min_lot_size' => 'nullable|integer|min:0',
            'max_lot_size' => 'nullable|integer|min:0',
            
            // Property Condition
            'property_conditions' => 'nullable|array',
            'property_conditions.*' => Rule::in(['Turnkey', 'Retail Ready', 'Rental Ready']),
            
            // Property Type
            'property_types' => 'nullable|array',
            'property_types.*' => Rule::in(['Single-Family', 'Land', 'Multifamily', 'Commercial']),
            
            // Additional Considerations
            'has_adu_potential' => 'nullable|boolean',
            
            // Home Construction
            'construction_types' => 'nullable|array',
            'construction_types.*' => Rule::in(['Brick Built', 'Stick Built', 'Block Built', 'Stucco Exterior', 'Other']),
            
            // Amenities & Features
            'has_pool' => 'nullable|boolean',
            'is_waterfront' => 'nullable|boolean',
            
            // Desired Layout
            'layout_types' => 'nullable|array',
            'layout_types.*' => Rule::in(['Open floor plan', 'Traditional', 'Custom']),
            
            // Funding Methods
            'funding_methods' => 'nullable|array',
            'funding_methods.*' => Rule::in(['Cash', 'Hard money', 'Private money', 'DSCR loans', 'Conventional mortgages', 'FHA loans', 'VA loans']),
            
            // Rental Investment Criteria
            'min_profit' => 'nullable|numeric|min:0',
            'min_roi' => 'nullable|numeric|min:0|max:100',
            'target_cap_rate' => 'nullable|numeric|min:0|max:100',
            'desired_occupancy_rate' => 'nullable|numeric|min:0|max:100',
            'expected_monthly_cash_flow' => 'nullable|numeric',
            'expected_annual_cash_flow' => 'nullable|numeric',
            
            // Investment Strategies
            'investment_strategies' => 'nullable|array',
            'investment_strategies.*' => Rule::in(['Fix and Flip', 'Short-Term Rental', 'Mid-Term Rental', 'Long-Term Rental', 'Lease Option', 'House Hacking']),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate max >= min for bedrooms
            if ($this->filled('min_bedrooms') && $this->filled('max_bedrooms')) {
                if ($this->input('max_bedrooms') < $this->input('min_bedrooms')) {
                    $validator->errors()->add('max_bedrooms', 'Maximum bedrooms must be greater than or equal to minimum bedrooms.');
                }
            }

            // Validate max >= min for bathrooms
            if ($this->filled('min_bathrooms') && $this->filled('max_bathrooms')) {
                if ($this->input('max_bathrooms') < $this->input('min_bathrooms')) {
                    $validator->errors()->add('max_bathrooms', 'Maximum bathrooms must be greater than or equal to minimum bathrooms.');
                }
            }

            // Validate max >= min for square feet
            if ($this->filled('min_square_feet') && $this->filled('max_square_feet')) {
                if ($this->input('max_square_feet') < $this->input('min_square_feet')) {
                    $validator->errors()->add('max_square_feet', 'Maximum square feet must be greater than or equal to minimum square feet.');
                }
            }

            // Validate max >= min for lot size
            if ($this->filled('min_lot_size') && $this->filled('max_lot_size')) {
                if ($this->input('max_lot_size') < $this->input('min_lot_size')) {
                    $validator->errors()->add('max_lot_size', 'Maximum lot size must be greater than or equal to minimum lot size.');
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'property_conditions.*.in' => 'Property condition must be one of: Turnkey, Retail Ready, Rental Ready.',
            'property_types.*.in' => 'Property type must be one of: Single-Family, Land, Multifamily, Commercial.',
            'construction_types.*.in' => 'Construction type must be one of: Brick Built, Stick Built, Block Built, Stucco Exterior, Other.',
            'layout_types.*.in' => 'Layout type must be one of: Open floor plan, Traditional, Custom.',
            'funding_methods.*.in' => 'Funding method must be one of: Cash, Hard money, Private money, DSCR loans, Conventional mortgages, FHA loans, VA loans.',
            'investment_strategies.*.in' => 'Investment strategy must be one of: Fix and Flip, Short-Term Rental, Mid-Term Rental, Long-Term Rental, Lease Option, House Hacking.',
            'min_roi.max' => 'Minimum ROI cannot exceed 100%.',
            'target_cap_rate.max' => 'Target cap rate cannot exceed 100%.',
            'desired_occupancy_rate.max' => 'Desired occupancy rate cannot exceed 100%.',
        ];
    }
}
