<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'action_text' => $data['action_text'] ?? null,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->format('m-d-Y H:i:s'),
            'created_at' => $this->created_at->format('m-d-Y H:i:s'),
        ];
    }
}

