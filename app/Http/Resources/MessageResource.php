<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'conversation_id' => $this->conversation_id,
            'sender' => $this->when(
                $this->relationLoaded('sender'),
                fn() => new UserResource($this->sender)
            ),
            'receiver' => $this->when(
                $this->relationLoaded('receiver'),
                fn() => new UserResource($this->receiver)
            ),
            'body' => $this->body,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}

