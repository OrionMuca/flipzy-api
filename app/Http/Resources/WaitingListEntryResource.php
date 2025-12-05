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
            'phone_number' => $this->phone_number,
            'company_name' => $this->company_name,
            'selected_roles' => $this->selected_roles ?? [],
            'status' => $this->status,
            'coupon_code' => $this->coupon_code,
            'email_verified' => $this->isEmailVerified(),
            'email_verified_at' => $this->email_verified_at?->format('m-d-Y H:i:s'),
            'account_created' => $this->isAccountCreated(),
            'account_created_at' => $this->account_created_at?->format('m-d-Y H:i:s'),
            'coupon' => $this->whenLoaded('coupon', function () {
                return new CouponResource($this->coupon);
            }),
            'metadata' => $this->when($request->user()?->hasRole('admin'), $this->metadata ?? []),
            'created_at' => $this->created_at?->format('m-d-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('m-d-Y H:i:s'),
        ];
    }
}

