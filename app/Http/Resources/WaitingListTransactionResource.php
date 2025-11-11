<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitingListTransactionResource extends JsonResource
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
            'waiting_list_entry_id' => $this->waiting_list_entry_id,
            'type' => $this->type,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'original_amount' => (float) $this->original_amount,
            'discount_amount' => (float) $this->discount_amount,
            'description' => $this->description,
            'metadata' => $this->metadata ?? [],
            'is_completed' => $this->isCompleted(),
            'is_refunded' => $this->isRefunded(),
            'is_failed' => $this->isFailed(),
            'processed_at' => $this->processed_at?->toISOString(),
            'waiting_list_entry' => $this->whenLoaded('waitingListEntry', function () {
                return new WaitingListEntryResource($this->waitingListEntry);
            }),
            'stripe_payment_intent_id' => $this->when($request->user()?->hasRole('admin'), $this->stripe_payment_intent_id),
            'stripe_charge_id' => $this->when($request->user()?->hasRole('admin'), $this->stripe_charge_id),
            'stripe_refund_id' => $this->when($request->user()?->hasRole('admin'), $this->stripe_refund_id),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

