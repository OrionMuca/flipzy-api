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
            'coupon_code' => $this->coupon_code,
            'email_verified' => $this->isEmailVerified(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'account_created' => $this->isAccountCreated(),
            'account_created_at' => $this->account_created_at?->toISOString(),
            'coupon' => $this->whenLoaded('coupon', function () {
                return new CouponResource($this->coupon);
            }),
            'metadata' => $this->when($request->user()?->hasRole('admin'), $this->metadata ?? []),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

