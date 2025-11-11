<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'is_active' => $this->isActive(),
            'plan' => $this->whenLoaded('plan', function () {
                return new SubscriptionPlanResource($this->plan);
            }),
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'stripe_subscription_id' => $this->when($request->user()?->hasRole('admin') || $request->user()?->id === $this->user_id, $this->stripe_subscription_id),
            'stripe_customer_id' => $this->when($request->user()?->hasRole('admin') || $request->user()?->id === $this->user_id, $this->stripe_customer_id),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

