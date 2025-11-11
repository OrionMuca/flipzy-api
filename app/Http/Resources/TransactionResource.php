<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'subscription_id' => $this->subscription_id,
            'type' => $this->type,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'metadata' => $this->metadata ?? [],
            'failure_reason' => $this->failure_reason,
            'processed_at' => $this->processed_at?->toISOString(),
            'is_completed' => $this->isCompleted(),
            'is_refunded' => $this->isRefunded(),
            'is_failed' => $this->isFailed(),
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'subscription' => $this->whenLoaded('subscription', function () {
                return new SubscriptionResource($this->subscription);
            }),
            'stripe_payment_intent_id' => $this->when($request->user()?->hasRole('admin') || $request->user()?->id === $this->user_id, $this->stripe_payment_intent_id),
            'stripe_charge_id' => $this->when($request->user()?->hasRole('admin') || $request->user()?->id === $this->user_id, $this->stripe_charge_id),
            'stripe_refund_id' => $this->when($request->user()?->hasRole('admin') || $request->user()?->id === $this->user_id, $this->stripe_refund_id),
            'stripe_customer_id' => $this->when($request->user()?->hasRole('admin') || $request->user()?->id === $this->user_id, $this->stripe_customer_id),
            'stripe_response' => $this->when($request->user()?->hasRole('admin'), $this->stripe_response),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

