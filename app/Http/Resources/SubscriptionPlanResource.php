<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'billing_interval' => $this->billing_interval,
            'features' => $this->features ?? [],
            'max_properties' => $this->max_properties,
            'max_messages' => $this->max_messages,
            'has_ai_estimates' => $this->has_ai_estimates,
            'has_api_access' => $this->has_api_access,
            'is_active' => $this->is_active,
            'stripe_price_id' => $this->when($request->user()?->hasRole('admin'), $this->stripe_price_id),
            'created_at' => $this->created_at?->format('m-d-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('m-d-Y H:i:s'),
        ];
    }
}

