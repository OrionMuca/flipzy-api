<?php

namespace App\Services;

use App\Models\BuyBox;
use App\Models\User;

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
}
