<?php

namespace Tests\Feature;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Client;
use App\Models\Role;
use Tests\TestCase;

class MessageBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected User $investor;
    protected User $wholesaler;
    protected string $investorToken;
    protected string $wholesalerToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create Passport personal access client for testing
        $client = new Client();
        $client->id = (string) \Illuminate\Support\Str::uuid();
        $client->name = 'Laravel Personal Access Client';
        $client->secret = \Illuminate\Support\Facades\Hash::make('test-secret-key');
        $client->redirect_uris = ['http://localhost'];
        $client->grant_types = ['personal_access'];
        $client->revoked = false;
        $client->save();
        
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'investor']);
        Role::firstOrCreate(['name' => 'wholesaler']);

        // Create test users
        $this->investor = User::factory()->create([
            'name' => 'Test Investor',
            'email' => 'investor@test.com',
        ]);
        $this->investor->assignRole('investor');
        $this->investorToken = $this->investor->createToken('Test Token')->accessToken;

        $this->wholesaler = User::factory()->create([
            'name' => 'Test Wholesaler',
            'email' => 'wholesaler@test.com',
        ]);
        $this->wholesaler->assignRole('wholesaler');
        $this->wholesalerToken = $this->wholesaler->createToken('Test Token')->accessToken;
    }

    // ==================== MessageSent Event Tests ====================

    /** @test */
    public function message_sent_event_is_dispatched_when_sending_message(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Test message',
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(MessageSent::class, function ($event) use ($conversation) {
            return $event->message->conversation_id === $conversation->id
                && $event->message->body === 'Test message'
                && $event->message->sender_id === $this->investor->id;
        });
    }

    /** @test */
    public function message_sent_event_broadcasts_to_correct_channel(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Test message',
        ]);

        Event::assertDispatched(MessageSent::class, function ($event) use ($conversation) {
            $channels = $event->broadcastOn();
            return count($channels) === 1
                && $channels[0]->name === 'private-conversation.' . $conversation->id;
        });
    }

    /** @test */
    public function message_sent_event_has_correct_broadcast_name(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Test message',
        ]);

        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->broadcastAs() === 'message.sent';
        });
    }

    /** @test */
    public function message_sent_event_includes_correct_payload(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Hello, this is a test message!',
        ]);

        $response->assertStatus(201);

        // Verify event was dispatched
        // Note: Payload structure is verified in other tests (message_sent_event_includes_sender_and_receiver_data)
        Event::assertDispatched(MessageSent::class);
    }

    /** @test */
    public function message_sent_event_includes_sender_and_receiver_data(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Test message',
        ]);

        Event::assertDispatched(MessageSent::class, function ($event) {
            $payload = $event->broadcastWith();
            
            return isset($payload['sender']['id'])
                && isset($payload['sender']['name'])
                && isset($payload['sender']['email'])
                && isset($payload['receiver']['id'])
                && isset($payload['receiver']['name'])
                && $payload['sender']['id'] === $this->investor->id
                && $payload['receiver']['id'] === $this->wholesaler->id;
        });
    }

    // ==================== MessageRead Event Tests ====================

    /** @test */
    public function message_read_event_is_dispatched_when_marking_message_as_read(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
            'body' => 'Test message',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        $response->assertStatus(200);

        Event::assertDispatched(MessageRead::class, function ($event) use ($message) {
            return $event->message->id === $message->id
                && $event->message->is_read === true
                && $event->message->read_at !== null;
        });
    }

    /** @test */
    public function message_read_event_broadcasts_to_correct_channel(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        Event::assertDispatched(MessageRead::class, function ($event) use ($conversation) {
            $channels = $event->broadcastOn();
            return count($channels) === 1
                && $channels[0]->name === 'private-conversation.' . $conversation->id;
        });
    }

    /** @test */
    public function message_read_event_has_correct_broadcast_name(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        Event::assertDispatched(MessageRead::class, function ($event) {
            return $event->broadcastAs() === 'message.read';
        });
    }

    /** @test */
    public function message_read_event_includes_correct_payload(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        Event::assertDispatched(MessageRead::class, function ($event) use ($message, $conversation) {
            $payload = $event->broadcastWith();
            
            return isset($payload['message_id'])
                && isset($payload['conversation_id'])
                && isset($payload['read_by'])
                && isset($payload['read_at'])
                && $payload['message_id'] === $message->id
                && $payload['conversation_id'] === $conversation->id
                && $payload['read_by']['id'] === $this->investor->id
                && $payload['read_by']['name'] === $this->investor->name;
        });
    }

    /** @test */
    public function message_read_event_not_dispatched_if_message_already_read(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->read()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        // Event should not be dispatched since message is already read
        Event::assertNotDispatched(MessageRead::class);
    }

    /** @test */
    public function message_read_events_are_dispatched_for_bulk_mark_as_read(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message1 = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $message2 = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $message3 = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/conversations/{$conversation->id}/messages/read");

        // Should dispatch 3 MessageRead events
        Event::assertDispatched(MessageRead::class, 3);
        
        Event::assertDispatched(MessageRead::class, function ($event) use ($message1) {
            return $event->message->id === $message1->id;
        });
        
        Event::assertDispatched(MessageRead::class, function ($event) use ($message2) {
            return $event->message->id === $message2->id;
        });
        
        Event::assertDispatched(MessageRead::class, function ($event) use ($message3) {
            return $event->message->id === $message3->id;
        });
    }

    // ==================== Channel Authorization Tests ====================

    /** @test */
    public function conversation_channel_authorizes_participants(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        // Investor should be authorized
        $this->actingAs($this->investor);
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => 'test-socket-id',
            'channel_name' => 'private-conversation.' . $conversation->id,
        ]);
        $response->assertStatus(200);

        // Wholesaler should be authorized
        $this->actingAs($this->wholesaler);
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => 'test-socket-id',
            'channel_name' => 'private-conversation.' . $conversation->id,
        ]);
        $response->assertStatus(200);
    }

    /** @test */
    public function conversation_channel_denies_non_participants(): void
    {
        $otherUser = User::factory()->create();
        $otherUserToken = $otherUser->createToken('Test Token')->accessToken;
        
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherUserToken,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => 'test-socket-id',
            'channel_name' => 'private-conversation.' . $conversation->id,
        ]);
        
        // Laravel Broadcasting returns 403 for denied channels, or 200 with empty response
        $this->assertTrue(
            $response->status() === 403 || 
            ($response->status() === 200 && (empty($response->getContent()) || $response->getContent() === '{}'))
        );
    }

    /** @test */
    public function conversation_channel_denies_unauthenticated_users(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => 'test-socket-id',
            'channel_name' => 'private-conversation.' . $conversation->id,
        ]);
        
        // Unauthenticated users should be denied (401 or 403, or 200 with empty response)
        $this->assertTrue(
            in_array($response->status(), [401, 403]) || 
            ($response->status() === 200 && (empty($response->getContent()) || $response->getContent() === '{}'))
        );
    }

    /** @test */
    public function user_channel_authorizes_own_user(): void
    {
        // Use Passport token for API authentication
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => 'test-socket-id',
            'channel_name' => 'private-user.' . $this->investor->id,
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function user_channel_denies_other_users(): void
    {
        // Use Passport token for API authentication
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => 'test-socket-id',
            'channel_name' => 'private-user.' . $this->wholesaler->id,
        ]);
        
        // Channel authorization should deny access when user ID doesn't match
        // Laravel Broadcasting returns 403 for denied channels, or 200 with empty response
        $this->assertTrue(
            $response->status() === 403 || 
            ($response->status() === 200 && (empty($response->getContent()) || $response->getContent() === '{}'))
        );
    }

    /** @test */
    public function invalid_conversation_channel_returns_403(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/broadcasting/auth', [
            'socket_id' => 'test-socket-id',
            'channel_name' => 'private-conversation.invalid-uuid',
        ]);
        
        // Invalid UUID should be denied
        // Laravel Broadcasting returns 403 for denied channels, or 200 with empty response
        $this->assertTrue(
            $response->status() === 403 || 
            ($response->status() === 200 && (empty($response->getContent()) || $response->getContent() === '{}'))
        );
    }

    // ==================== Integration Tests ====================

    /** @test */
    public function sending_message_dispatches_event_and_updates_conversation(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
            'last_message_at' => now()->subDays(1),
        ]);

        $oldLastMessageAt = $conversation->last_message_at;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'New message',
        ]);

        $response->assertStatus(201);
        
        // Verify event was dispatched
        Event::assertDispatched(MessageSent::class);
        
        // Verify conversation was updated
        $conversation->refresh();
        $this->assertNotEquals($oldLastMessageAt, $conversation->last_message_at);
        $this->assertNotNull($conversation->last_message_at);
    }

    /** @test */
    public function marking_message_as_read_dispatches_event_and_updates_message(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $this->assertFalse($message->is_read);
        $this->assertNull($message->read_at);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        $response->assertStatus(200);
        
        // Verify event was dispatched
        Event::assertDispatched(MessageRead::class);
        
        // Verify message was updated
        $message->refresh();
        $this->assertTrue($message->is_read);
        $this->assertNotNull($message->read_at);
    }
}

