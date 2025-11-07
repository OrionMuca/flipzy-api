<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'property_id' => $this->resource['property_id'],
            'period_days' => $this->resource['period_days'],
            'total_views' => $this->resource['total_views'],
            'unique_views' => $this->resource['unique_views'],
            'total_saves' => $this->resource['total_saves'],
            'unique_saves' => $this->resource['unique_saves'],
            'total_inquiries' => $this->resource['total_inquiries'],
            'unique_inquiries' => $this->resource['unique_inquiries'],
            'breakdown' => $this->resource['breakdown'] ?? [],
        ];
    }
}
