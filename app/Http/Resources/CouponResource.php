<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'minimum_amount' => $this->minimum_amount ? (float) $this->minimum_amount : null,
            'maximum_discount' => $this->maximum_discount ? (float) $this->maximum_discount : null,
            'valid_from' => $this->valid_from->toDateTimeString(),
            'valid_until' => $this->valid_until?->toDateTimeString(),
            'usage_limit' => $this->usage_limit,
            'usage_count' => $this->usage_count,
            'user_limit' => $this->user_limit,
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'applicable_plans' => $this->applicable_plans ?? [],
            'waiting_list_entries_count' => $this->when($request->user()?->hasRole('admin'), function () {
                return $this->whenLoaded('waitingListEntries', fn() => $this->waitingListEntries->count());
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

