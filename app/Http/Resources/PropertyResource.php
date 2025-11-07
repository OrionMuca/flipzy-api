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
            ],
            
            // Financial
            'financial' => [
                'asking_price' => $this->asking_price,
                'arv' => $this->arv,
                'repair_estimate' => $this->repair_estimate,
                'potential_profit' => $this->potential_profit,
            ],
            
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
            
            // Metadata
            'enriched_at' => $this->enriched_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
