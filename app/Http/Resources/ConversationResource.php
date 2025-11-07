<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        
        // Determine the other participant
        $otherParticipant = $this->participant_one_id === $user->id
            ? $this->participantTwo
            : $this->participantOne;

        // Get unread count for current user
        $unreadCount = $this->unreadCountForUser($user->id);

        return [
            'id' => $this->id,
            'property' => $this->when(
                $this->relationLoaded('property'),
                fn() => $this->property ? new PropertyResource($this->property) : null
            ),
            'other_participant' => $this->when(
                $otherParticipant,
                fn() => new UserResource($otherParticipant)
            ),
            'last_message' => $this->when(
                $this->relationLoaded('messages') && $this->messages->isNotEmpty(),
                fn() => new MessageResource($this->messages->first())
            ),
            'unread_count' => $unreadCount ?? 0,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

