<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'property_type' => $this->property_type,
            'status' => $this->status,

            // Address
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],

            // Property Details
            'details' => [
                'bedrooms' => $this->bedrooms,
                'bathrooms' => $this->bathrooms,
                'square_feet' => $this->square_feet,
                'lot_size' => $this->lot_size,
                'year_built' => $this->year_built,
                'condition' => $this->condition,
                'living_size' => $this->living_size,
                'gross_size' => $this->gross_size,
                'zoning_type' => $this->zoning_type,
                'pool_type' => $this->pool_type,
                'municipality' => $this->municipality,
                'legal1' => $this->legal1,
                'cooling_type' => $this->cooling_type,
                'heating_fuel' => $this->heating_fuel,
                'heating_type' => $this->heating_type,
                'last_sale_date' => $this->last_sale_date?->format('Y-m-d'),
                'tax_amount' => $this->tax_amount,
                'tax_year' => $this->tax_year,
            ],

            // Financial
            'financial' => [
                'asking_price' => $this->asking_price,
                'arv' => $this->arv,
                'repair_estimate' => $this->repair_estimate,
                'potential_profit' => $this->potential_profit,
            ],

            // Latest AI rehab estimate (when loaded; property can have many, we return the most recent)
            'rehab_estimate' => $this->whenLoaded(
                'latestRehabEstimate',
                fn () => new RehabEstimateResource($this->latestRehabEstimate),
                null
            ),

            // Images (ordered by 'order' field - maintained by Property model relationship)
            'images' => PropertyImageResource::collection(
                $this->whenLoaded('images', function () {
                    // Ensure images are sorted by order (relationship already does this, but explicit for clarity)
                    return $this->images->sortBy('order')->values();
                })
            ),
            'primary_image' => $this->when(
                $this->relationLoaded('images'),
                fn() => $this->images->where('is_primary', true)->first()
                    ? new PropertyImageResource($this->images->where('is_primary', true)->first())
                    : null
            ),

            // Wholesaler
            'wholesaler' => new UserResource($this->whenLoaded('wholesaler')),

            // Flags
            'is_featured' => $this->is_featured,
            'is_verified' => $this->is_verified,
            'allow_inquiries' => $this->allow_inquiries,

            // ATTOM enrichment data
            'attom_details' => $this->formatAttomDetails(),
            'sale_history' => $this->formatSaleHistory(),
            'building_permits' => $this->formatBuildingPermits(),

            // Metadata
            'enriched_at' => $this->enriched_at?->format('m-d-Y H:i:s'),
            'created_at' => $this->created_at->format('m-d-Y H:i:s'),
            'updated_at' => $this->updated_at->format('m-d-Y H:i:s'),
        ];
    }

    protected function formatAttomDetails(): ?array
    {
        $data = $this->attom_data;
        if (empty($data)) {
            return null;
        }

        // Navigate into the raw ATTOM response structure
        $property = $data['property'][0] ?? $data[0] ?? [];
        $address = $property['address'] ?? [];
        $summary = $property['summary'] ?? [];
        $building = $property['building'] ?? [];
        $buildingSize = $building['size'] ?? [];
        $buildingRooms = $building['rooms'] ?? [];
        $lot = $property['lot'] ?? [];
        $area = $property['area'] ?? [];
        $utilities = $property['utilities'] ?? [];
        $location = $property['location'] ?? [];
        $assessment = $property['assessment'] ?? [];
        $taxData = $assessment['tax'] ?? $assessment;

        $sale = $property['sale'] ?? [];
        $saleAmount = $sale['amount'] ?? [];

        return [
            'zoning_type' => $lot['zoningType'] ?? $area['zoningType'] ?? null,
            'pool_type' => $lot['pooltype'] ?? $lot['poolType'] ?? null,
            'municipality' => $area['munname'] ?? $area['munName'] ?? null,
            'latitude' => isset($location['latitude']) ? (float) $location['latitude'] : null,
            'longitude' => isset($location['longitude']) ? (float) $location['longitude'] : null,
            'country' => $address['country'] ?? $address['countryCode'] ?? 'US',
            'state' => $address['countrySubd'] ?? null,
            'one_line' => $address['oneLine'] ?? null,
            'property_type' => $summary['propertyType'] ?? $summary['proptype'] ?? null,
            'year_built' => $summary['yearbuilt'] ?? $summary['yearBuilt'] ?? null,
            'legal1' => $area['legal1'] ?? $summary['legal1'] ?? null,
            'cooling_type' => $utilities['coolingtype'] ?? $utilities['coolingType'] ?? null,
            'heating_fuel' => $utilities['heatingfuel'] ?? $utilities['heatingFuel'] ?? null,
            'heating_type' => $utilities['heatingtype'] ?? $utilities['heatingType'] ?? null,
            'living_size' => $buildingSize['livingsize'] ?? $buildingSize['livingSize'] ?? null,
            'gross_size' => $buildingSize['grosssize'] ?? $buildingSize['grossSize'] ?? null,
            'beds' => $buildingRooms['beds'] ?? null,
            'baths_total' => $buildingRooms['bathstotal'] ?? $buildingRooms['bathsTotal'] ?? null,
            'tax_amount' => $taxData['taxamt'] ?? $taxData['taxAmt'] ?? null,
            'tax_year' => $taxData['taxyear'] ?? $taxData['taxYear'] ?? null,
            'last_sale_date' => $saleAmount['saleRecDate'] ?? $saleAmount['salerecdate'] ?? null,
        ];
    }

    protected function formatSaleHistory(): ?array
    {
        $data = $this->attom_sale_history;
        if (empty($data)) {
            return null;
        }

        return $data['sales'] ?? null;
    }

    protected function formatBuildingPermits(): ?array
    {
        $data = $this->attom_property_events;
        if (empty($data)) {
            return null;
        }

        $permits = $data['categorized']['permits'] ?? [];

        // Sort by event_date descending (latest first)
        usort($permits, function ($a, $b) {
            $dateA = $a['event_date'] ?? '';
            $dateB = $b['event_date'] ?? '';
            return strcmp($dateB, $dateA);
        });

        return $permits;
    }
}
