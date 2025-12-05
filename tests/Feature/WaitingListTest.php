<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Mockery;
use App\Models\Role;
use Tests\TestCase;

class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Passport client
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function user_can_validate_valid_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'EARLYBIRD50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
            'usage_limit' => 100,
            'usage_count' => 0,
            'applicable_plans' => null, // Applies to all plans
        ]);

        $response = $this->postJson('/api/v1/waiting-list/validate-coupon', [
            'code' => 'EARLYBIRD50',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'coupon' => [
                        'id',
                        'code',
                        'name',
                        'description',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function user_cannot_validate_invalid_coupon(): void
    {
        $response = $this->postJson('/api/v1/waiting-list/validate-coupon', [
            'code' => 'INVALID',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function user_cannot_validate_expired_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'EXPIRED',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'valid_from' => now()->subYear(),
            'valid_until' => now()->subDay(),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/waiting-list/validate-coupon', [
            'code' => 'EXPIRED',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function user_can_register_for_waiting_list(): void
    {
        $response = $this->postJson('/api/v1/waiting-list/register', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'phone_number' => '+1234567890',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'email',
                    'name',
                    'status',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('waiting_list_entries', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function user_can_register_with_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'EARLYBIRD50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
            'usage_limit' => 100,
            'usage_count' => 0,
            'applicable_plans' => null, // Applies to all plans
        ]);

        $response = $this->postJson('/api/v1/waiting-list/register', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'phone_number' => '+1234567890',
            'coupon_code' => 'EARLYBIRD50',
        ]);

        $response->assertStatus(201);

        $entry = WaitingListEntry::where('email', 'test@example.com')->first();
        $this->assertNotNull($entry->coupon_id);
        $this->assertEquals('EARLYBIRD50', $entry->coupon_code);
    }

    /** @test */
    public function user_cannot_register_twice_with_same_email(): void
    {
        WaitingListEntry::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/waiting-list/register', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'phone_number' => '+1234567890',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function user_can_check_status_with_valid_token(): void
    {
        $entry = WaitingListEntry::factory()->create([
            'email' => 'test@example.com',
            'verification_token' => 'test-token-123',
        ]);

        $response = $this->getJson('/api/v1/waiting-list/status?email=test@example.com&token=test-token-123');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'email',
                    'name',
                    'status',
                    'email_verified',
                    'account_created',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'test@example.com',
                ],
            ]);
    }

    /** @test */
    public function user_cannot_check_status_with_invalid_token(): void
    {
        $response = $this->getJson('/api/v1/waiting-list/status?email=test@example.com&token=invalid-token');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function validation_requires_code(): void
    {
        $response = $this->postJson('/api/v1/waiting-list/validate-coupon', []);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function registration_requires_email_and_name(): void
    {
        $response = $this->postJson('/api/v1/waiting-list/register', []);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['email', 'name', 'phone_number']);
    }

    /** @test */
    public function status_requires_email_and_token(): void
    {
        $response = $this->getJson('/api/v1/waiting-list/status');

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['email', 'token']);
    }
}

