<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Mockery;
use App\Models\Role;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected StripeService $stripeService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create Passport personal access client
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
        $this->user = User::factory()->create();
        $this->user->assignRole('investor');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Mock StripeService
        $this->stripeService = Mockery::mock(StripeService::class);
        $this->app->instance(StripeService::class, $this->stripeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function user_can_create_full_refund(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => 29.99,
            'stripe_charge_id' => 'ch_test123',
        ]);

        $this->stripeService
            ->shouldReceive('createRefund')
            ->once()
            ->with(
                Mockery::on(function ($tx) use ($transaction) {
                    return $tx->id === $transaction->id;
                }),
                null, // Full refund
                'requested_by_customer'
            )
            ->andReturn([
                'success' => true,
                'refund_id' => 're_test123',
                'amount' => 29.99,
                'status' => 'succeeded',
            ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
            'reason' => 'requested_by_customer',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'refund_transaction_id',
                    'original_transaction_id',
                    'refund_amount',
                    'currency',
                    'status',
                    'is_full_refund',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'original_transaction_id' => $transaction->id,
                    'refund_amount' => 29.99,
                    'is_full_refund' => true,
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'refunded',
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'refund',
            'status' => 'completed',
            'amount' => 29.99,
        ]);
    }

    /** @test */
    public function user_can_create_partial_refund(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => 50.00,
            'stripe_charge_id' => 'ch_test123',
        ]);

        $this->stripeService
            ->shouldReceive('createRefund')
            ->once()
            ->with(
                Mockery::on(function ($tx) use ($transaction) {
                    return $tx->id === $transaction->id;
                }),
                20.00, // Partial refund
                'requested_by_customer'
            )
            ->andReturn([
                'success' => true,
                'refund_id' => 're_test123',
                'amount' => 20.00,
                'status' => 'succeeded',
            ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
            'amount' => 20.00,
            'reason' => 'requested_by_customer',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'refund_amount' => 20.00,
                    'is_full_refund' => false,
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'partially_refunded',
        ]);
    }

    /** @test */
    public function refund_requires_authentication(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function cannot_refund_pending_transaction(): void
    {
        $transaction = Transaction::factory()->pending()->create([
            'user_id' => $this->user->id,
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Only completed transactions can be refunded',
            ]);
    }

    /** @test */
    public function cannot_refund_failed_transaction(): void
    {
        $transaction = Transaction::factory()->failed()->create([
            'user_id' => $this->user->id,
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Only completed transactions can be refunded',
            ]);
    }

    /** @test */
    public function cannot_refund_already_refunded_transaction(): void
    {
        $transaction = Transaction::factory()->refunded()->create([
            'user_id' => $this->user->id,
            'amount' => 29.99,
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Transaction has already been refunded',
            ]);
    }

    /** @test */
    public function cannot_refund_more_than_transaction_amount(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'amount' => 29.99,
            'stripe_charge_id' => 'ch_test123',
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
            'amount' => 50.00, // More than transaction amount
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Refund amount cannot exceed transaction amount',
            ]);
    }

    /** @test */
    public function user_cannot_refund_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'completed',
            'stripe_charge_id' => 'ch_test123',
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to refund this transaction',
            ]);
    }

    /** @test */
    public function admin_can_refund_any_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'amount' => 29.99,
            'stripe_charge_id' => 'ch_test123',
        ]);

        $this->stripeService
            ->shouldReceive('createRefund')
            ->once()
            ->andReturn([
                'success' => true,
                'refund_id' => 're_test123',
                'amount' => 29.99,
                'status' => 'succeeded',
            ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_can_get_refund_details(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'amount' => 29.99,
            'stripe_charge_id' => 'ch_test123',
        ]);

        $refund = Transaction::factory()->refund()->create([
            'user_id' => $this->user->id,
            'amount' => 29.99,
            'metadata' => [
                'original_transaction_id' => $transaction->id,
            ],
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/refunds/{$refund->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $refund->id,
                    'type' => 'refund',
                ],
            ]);
    }

    /** @test */
    public function refund_validation_checks_transaction_exists(): void
    {
        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['success', 'errors']);
    }

    /** @test */
    public function refund_validation_checks_amount_minimum(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'stripe_charge_id' => 'ch_test123',
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/refunds', [
            'transaction_id' => $transaction->id,
            'amount' => 0.10, // Below minimum
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['success', 'errors']);
    }
}
