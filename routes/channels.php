<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Conversation channel - only participants can listen
Broadcast::channel('conversation.{conversationId}', function ($user, string $conversationId) {
    // Validate UUID format
    if (!\Illuminate\Support\Str::isUuid($conversationId)) {
        return false;
    }
    
    $conversation = Conversation::find($conversationId);
    
    if (!$conversation) {
        return false;
    }
    
    // Check if user is a participant
    return (string) $conversation->participant_one_id === (string) $user->id || 
           (string) $conversation->participant_two_id === (string) $user->id;
}, ['guards' => ['web', 'api']]);

// User-specific channel for notifications (optional)
Broadcast::channel('user.{userId}', function ($user, string $userId) {
    // Only allow if the authenticated user matches the channel user ID
    return (string) $user->id === (string) $userId;
}, ['guards' => ['web', 'api']]);

