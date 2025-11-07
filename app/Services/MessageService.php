<?php

namespace App\Services;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MessageService
{
    /**
     * Get or create a conversation between two users
     * If property_id is provided, conversation is linked to that property
     */
    public function getOrCreateConversation(
        User $userOne,
        User $userTwo,
        ?Property $property = null
    ): Conversation {
        // Ensure consistent ordering (smaller UUID first)
        $participants = [$userOne->id, $userTwo->id];
        sort($participants);
        
        $conversation = Conversation::where(function ($query) use ($participants, $property) {
            $query->where('participant_one_id', $participants[0])
                  ->where('participant_two_id', $participants[1]);
            
            if ($property) {
                $query->where('property_id', $property->id);
            } else {
                $query->whereNull('property_id');
            }
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'property_id' => $property?->id,
                'participant_one_id' => $participants[0],
                'participant_two_id' => $participants[1],
            ]);
        }

        return $conversation;
    }

    /**
     * Get all conversations for a user
     */
    public function getConversations(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Conversation::where(function ($query) use ($user) {
            $query->where('participant_one_id', $user->id)
                  ->orWhere('participant_two_id', $user->id);
        })
        ->with(['property', 'participantOne', 'participantTwo'])
        ->with(['messages' => function ($query) {
            $query->latest('created_at')->limit(1);
        }])
        ->orderBy('last_message_at', 'desc')
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);
    }

    /**
     * Get a specific conversation
     */
    public function getConversation(string $conversationId, User $user): ?Conversation
    {
        $conversation = Conversation::with(['property', 'participantOne', 'participantTwo'])
            ->find($conversationId);

        if (!$conversation) {
            return null;
        }

        // Verify user is a participant
        if ($conversation->participant_one_id !== $user->id && 
            $conversation->participant_two_id !== $user->id) {
            return null;
        }

        return $conversation;
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(Conversation $conversation, User $user, int $perPage = 50): LengthAwarePaginator
    {
        // Verify user is a participant
        if ($conversation->participant_one_id !== $user->id && 
            $conversation->participant_two_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Not authorized to view this conversation');
        }

        return $conversation->messages()
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Send a message in a conversation
     */
    public function sendMessage(
        Conversation $conversation,
        User $sender,
        string $body
    ): Message {
        // Verify sender is a participant
        if ($conversation->participant_one_id !== $sender->id && 
            $conversation->participant_two_id !== $sender->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Not authorized to send messages in this conversation');
        }

        // Determine receiver
        $receiverId = $conversation->participant_one_id === $sender->id 
            ? $conversation->participant_two_id 
            : $conversation->participant_one_id;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'body' => $body,
        ]);

        // Update conversation's last_message_at
        $conversation->update([
            'last_message_at' => now(),
        ]);

        // Broadcast the message sent event
        event(new MessageSent($message));

        return $message->load(['sender', 'receiver', 'conversation']);
    }

    /**
     * Mark messages as read
     */
    public function markMessagesAsRead(Conversation $conversation, User $user): int
    {
        // Verify user is a participant
        if ($conversation->participant_one_id !== $user->id && 
            $conversation->participant_two_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Not authorized to mark messages in this conversation');
        }

        // Get messages that will be marked as read
        $messages = Message::where('conversation_id', $conversation->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->get();

        $count = Message::where('conversation_id', $conversation->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        // Broadcast read events for each message
        foreach ($messages as $message) {
            $message->refresh(); // Refresh to get updated read_at and is_read
            event(new MessageRead($message));
        }

        return $count;
    }

    /**
     * Mark a specific message as read
     */
    public function markMessageAsRead(Message $message, User $user): bool
    {
        // Verify user is the receiver
        if ($message->receiver_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Not authorized to mark this message as read');
        }

        if ($message->is_read) {
            return false;
        }

        $message->markAsRead();
        
        // Broadcast the message read event
        event(new MessageRead($message));
        
        return true;
    }

    /**
     * Get unread message count for a user
     */
    public function getUnreadCount(User $user): int
    {
        return Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get unread count per conversation for a user
     */
    public function getUnreadCountsByConversation(User $user): Collection
    {
        return Conversation::where(function ($query) use ($user) {
            $query->where('participant_one_id', $user->id)
                  ->orWhere('participant_two_id', $user->id);
        })
        ->withCount(['messages as unread_count' => function ($query) use ($user) {
            $query->where('receiver_id', $user->id)
                  ->where('is_read', false);
        }])
        ->get();
    }
}

