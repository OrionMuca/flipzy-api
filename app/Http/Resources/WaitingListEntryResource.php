<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitingListEntryResource extends JsonResource
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
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'original_price' => (float) $this->original_price,
            'discounted_price' => (float) $this->discounted_price,
            'discount_amount' => (float) $this->discount_amount,
            'coupon_code' => $this->coupon_code,
            'email_verified' => $this->isEmailVerified(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'payment_completed' => $this->isPaymentCompleted(),
            'account_created' => $this->isAccountCreated(),
            'account_created_at' => $this->account_created_at?->toISOString(),
            'plan' => $this->whenLoaded('plan', function () {
                return new SubscriptionPlanResource($this->plan);
            }),
            'coupon' => $this->whenLoaded('coupon', function () {
                return new CouponResource($this->coupon);
            }),
            'transactions' => $this->whenLoaded('transactions', function () {
                return WaitingListTransactionResource::collection($this->transactions);
            }),
            'stripe_customer_id' => $this->when($request->user()?->hasRole('admin'), $this->stripe_customer_id),
            'stripe_subscription_id' => $this->when($request->user()?->hasRole('admin'), $this->stripe_subscription_id),
            'stripe_checkout_session_id' => $this->when($request->user()?->hasRole('admin'), $this->stripe_checkout_session_id),
            'metadata' => $this->when($request->user()?->hasRole('admin'), $this->metadata ?? []),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

