<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $investor;
    protected User $wholesaler;
    protected User $admin;
    protected string $investorToken;
    protected string $wholesalerToken;
    protected string $adminToken;

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

        $this->admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('Test Token')->accessToken;
    }

    // ==================== CONVERSATION Tests ====================

    /** @test */
    public function user_can_list_their_conversations(): void
    {
        // Create conversations
        $conversation1 = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);
        $conversation2 = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->admin->id,
        ]);
        // Conversation not involving investor
        Conversation::factory()->create([
            'participant_one_id' => $this->wholesaler->id,
            'participant_two_id' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson('/api/v1/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'other_participant' => [
                            'id',
                            'name',
                        ],
                        'unread_count',
                        'last_message_at',
                    ],
                ],
                'meta',
                'links',
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    /** @test */
    public function user_can_view_specific_conversation(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'other_participant' => [
                        'id',
                        'name',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $conversation->id,
                ],
            ]);
    }

    /** @test */
    public function user_cannot_view_conversation_they_are_not_part_of(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->wholesaler->id,
            'participant_two_id' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/conversations/{$conversation->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function user_can_create_or_get_conversation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/api/v1/conversations', [
            'user_id' => $this->wholesaler->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'other_participant' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('conversations', [
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);
    }

    /** @test */
    public function user_can_create_conversation_about_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/api/v1/conversations', [
            'user_id' => $this->wholesaler->id,
            'property_id' => $property->id,
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('conversations', [
            'property_id' => $property->id,
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);
    }

    /** @test */
    public function user_cannot_create_conversation_with_themselves(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/api/v1/conversations', [
            'user_id' => $this->investor->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot create conversation with yourself',
            ]);
    }

    /** @test */
    public function creating_existing_conversation_returns_existing_one(): void
    {
        // Create conversation using the service to ensure correct ordering
        $conversation = app(\App\Services\MessageService::class)->getOrCreateConversation(
            $this->investor,
            $this->wholesaler
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/api/v1/conversations', [
            'user_id' => $this->wholesaler->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($conversation->id, $response->json('data.id'));
    }

    // ==================== MESSAGE Tests ====================

    /** @test */
    public function user_can_send_message_in_conversation(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Hello, I am interested in this property!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'body',
                    'sender' => [
                        'id',
                        'name',
                    ],
                    'receiver' => [
                        'id',
                        'name',
                    ],
                    'is_read',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'body' => 'Hello, I am interested in this property!',
                    'is_read' => false,
                ],
            ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->investor->id,
            'receiver_id' => $this->wholesaler->id,
            'body' => 'Hello, I am interested in this property!',
        ]);
    }

    /** @test */
    public function user_cannot_send_message_in_conversation_they_are_not_part_of(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->wholesaler->id,
            'participant_two_id' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Unauthorized message',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function sending_message_updates_conversation_last_message_at(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
            'last_message_at' => now()->subDays(5),
        ]);

        $oldLastMessageAt = $conversation->last_message_at;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'New message',
        ]);

        $conversation->refresh();
        $this->assertNotEquals($oldLastMessageAt, $conversation->last_message_at);
        $this->assertNotNull($conversation->last_message_at);
    }

    /** @test */
    public function user_can_get_messages_from_conversation(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        Message::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->investor->id,
            'receiver_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'body',
                        'sender' => [
                            'id',
                            'name',
                        ],
                        'is_read',
                    ],
                ],
                'meta',
                'links',
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function user_cannot_get_messages_from_conversation_they_are_not_part_of(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->wholesaler->id,
            'participant_two_id' => $this->admin->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_mark_conversation_messages_as_read(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        // Create unread messages for investor
        Message::factory()->count(3)->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/conversations/{$conversation->id}/messages/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'messages_marked' => 3,
                ],
            ]);

        $this->assertEquals(0, Message::where('conversation_id', $conversation->id)
            ->where('receiver_id', $this->investor->id)
            ->where('is_read', false)
            ->count());
    }

    /** @test */
    public function user_can_mark_specific_message_as_read(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_read' => true,
                ],
            ]);

        $this->assertTrue($message->fresh()->is_read);
        $this->assertNotNull($message->fresh()->read_at);
    }

    /** @test */
    public function user_cannot_mark_message_as_read_if_not_receiver(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->investor->id,
            'receiver_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/messages/{$message->id}/read");

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_get_unread_message_count(): void
    {
        $conversation1 = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);
        $conversation2 = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->admin->id,
        ]);

        // Create unread messages for investor
        Message::factory()->count(2)->unread()->create([
            'conversation_id' => $conversation1->id,
            'sender_id' => $this->wholesaler->id,
            'receiver_id' => $this->investor->id,
        ]);
        Message::factory()->count(3)->unread()->create([
            'conversation_id' => $conversation2->id,
            'sender_id' => $this->admin->id,
            'receiver_id' => $this->investor->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson('/api/v1/messages/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 5,
                ],
            ]);
    }

    /** @test */
    public function message_creation_requires_body(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    /** @test */
    public function message_body_cannot_exceed_max_length(): void
    {
        $conversation = Conversation::factory()->create([
            'participant_one_id' => $this->investor->id,
            'participant_two_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => str_repeat('a', 5001), // Exceeds 5000 character limit
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }
}

