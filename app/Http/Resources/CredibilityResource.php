<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredibilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource['user_id'],
            'score' => $this->resource['score'],
            'normalized_score' => $this->resource['normalized_score'],
            'breakdown' => [
                'total_views' => $this->resource['breakdown']['total_views'],
                'total_saves' => $this->resource['breakdown']['total_saves'],
                'total_inquiries' => $this->resource['breakdown']['total_inquiries'],
            ],
            'property_count' => $this->resource['property_count'] ?? 0,
            'period_days' => $this->resource['period_days'],
        ];
    }
}
