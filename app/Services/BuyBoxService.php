<?php

namespace App\Services;

use App\Models\BuyBox;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuyBoxService
{
    /**
     * Get or create a buy box for a user
     */
    public function getOrCreate(User $user): BuyBox
    {
        return BuyBox::firstOrCreate(
            ['user_id' => $user->id],
            []
        );
    }

    /**
     * Update a buy box with validated data
     */
    public function update(BuyBox $buyBox, array $data): BuyBox
    {
        // Validate ranges (min < max where applicable)
        $this->validateRanges($data);

        // Handle JSON array fields - ensure they're arrays
        $jsonFields = [
            'preferred_cities',
            'preferred_zip_codes',
            'target_counties',
            'target_neighborhoods',
            'must_have_amenities',
            'property_conditions',
            'property_types',
            'construction_types',
            'layout_types',
            'funding_methods',
            'investment_strategies',
        ];

        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                // If it's already an array, keep it; if it's a string, try to decode it
                if (is_string($data[$field])) {
                    $decoded = json_decode($data[$field], true);
                    $data[$field] = $decoded !== null ? $decoded : [];
                } elseif (!is_array($data[$field])) {
                    $data[$field] = [];
                }
            }
        }

        $buyBox->update($data);

        return $buyBox->fresh();
    }

    /**
     * Validate that min values are less than max values
     */
    protected function validateRanges(array &$data): void
    {
        $rangeFields = [
            'bedrooms' => ['min_bedrooms', 'max_bedrooms'],
            'bathrooms' => ['min_bathrooms', 'max_bathrooms'],
            'square_feet' => ['min_square_feet', 'max_square_feet'],
            'lot_size' => ['min_lot_size', 'max_lot_size'],
        ];

        foreach ($rangeFields as $fieldName => [$minField, $maxField]) {
            $min = $data[$minField] ?? null;
            $max = $data[$maxField] ?? null;

            if ($min !== null && $max !== null && $min > $max) {
                // Swap if min > max
                $data[$minField] = $max;
                $data[$maxField] = $min;
            }
        }
    }

    /**
     * Find properties that match the buy box criteria
     */
    public function findMatchingProperties(BuyBox $buyBox, int $perPage = 15): LengthAwarePaginator
    {
        $query = Property::with(['wholesaler', 'images', 'primaryImage'])
            ->where('status', 'active'); // Only show active properties

        // Location filters
        if ($buyBox->preferred_cities && count($buyBox->preferred_cities) > 0) {
            $query->whereIn('city', $buyBox->preferred_cities);
        }

        if ($buyBox->preferred_zip_codes && count($buyBox->preferred_zip_codes) > 0) {
            $query->whereIn('zip_code', $buyBox->preferred_zip_codes);
        }

        // Property type filter
        if ($buyBox->property_types && count($buyBox->property_types) > 0) {
            // Map buy box property types to database property types
            $propertyTypeMap = [
                'Single-Family' => 'house',
                'Multifamily' => 'apartment',
                'Land' => 'land',
                'Commercial' => 'other',
            ];
            
            $mappedTypes = [];
            foreach ($buyBox->property_types as $type) {
                if (isset($propertyTypeMap[$type])) {
                    $mappedTypes[] = $propertyTypeMap[$type];
                }
            }
            
            if (count($mappedTypes) > 0) {
                $query->whereIn('property_type', $mappedTypes);
            }
        }

        // Bedrooms filter
        if ($buyBox->min_bedrooms !== null) {
            $query->where('bedrooms', '>=', $buyBox->min_bedrooms);
        }
        if ($buyBox->max_bedrooms !== null) {
            $query->where('bedrooms', '<=', $buyBox->max_bedrooms);
        }

        // Bathrooms filter
        if ($buyBox->min_bathrooms !== null) {
            $query->where('bathrooms', '>=', $buyBox->min_bathrooms);
        }
        if ($buyBox->max_bathrooms !== null) {
            $query->where('bathrooms', '<=', $buyBox->max_bathrooms);
        }

        // Square feet filter
        if ($buyBox->min_square_feet !== null) {
            $query->where('square_feet', '>=', $buyBox->min_square_feet);
        }
        if ($buyBox->max_square_feet !== null) {
            $query->where('square_feet', '<=', $buyBox->max_square_feet);
        }

        // Lot size filter
        if ($buyBox->min_lot_size !== null) {
            $query->where('lot_size', '>=', $buyBox->min_lot_size);
        }
        if ($buyBox->max_lot_size !== null) {
            $query->where('lot_size', '<=', $buyBox->max_lot_size);
        }

        // Property condition filter
        if ($buyBox->property_conditions && count($buyBox->property_conditions) > 0) {
            // Map buy box conditions to database conditions
            $conditionMap = [
                'Turnkey' => 'excellent',
                'Retail Ready' => 'good',
                'Rental Ready' => 'fair',
            ];
            
            $mappedConditions = [];
            foreach ($buyBox->property_conditions as $condition) {
                if (isset($conditionMap[$condition])) {
                    $mappedConditions[] = $conditionMap[$condition];
                }
            }
            
            if (count($mappedConditions) > 0) {
                $query->whereIn('condition', $mappedConditions);
            }
        }

        // Price range filter (using asking_price)
        // Note: We could also filter by potential_profit, ROI, etc. if those are calculated
        // For now, we'll use asking_price as a proxy
        if ($buyBox->min_profit !== null) {
            // If min_profit is set, we need ARV and repair_estimate to calculate
            // This is a simplified version - you might want to enhance this
            $query->whereRaw('(arv - asking_price - COALESCE(repair_estimate, 0)) >= ?', [$buyBox->min_profit]);
        }

        // Order by relevance (you could add a scoring system here)
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }
}
