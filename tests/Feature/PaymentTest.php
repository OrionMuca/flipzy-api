<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Client;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
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

        // Create test user
        $this->user = User::factory()->create();
        $this->user->assignRole('investor');

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
    public function user_can_create_payment_intent(): void
    {
        $this->stripeService
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->with(
                Mockery::on(function ($user) {
                    return $user->id === $this->user->id;
                }),
                29.99,
                'usd',
                'Test payment',
                []
            )
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_test123',
                'client_secret' => 'pi_test123_secret_abc',
                'amount' => 29.99,
                'currency' => 'usd',
            ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/payments/intent', [
            'amount' => 29.99,
            'currency' => 'usd',
            'description' => 'Test payment',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'payment_intent_id',
                    'client_secret',
                    'amount',
                    'currency',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 29.99,
                    'currency' => 'usd',
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'pending',
            'amount' => 29.99,
            'stripe_payment_intent_id' => 'pi_test123',
        ]);
    }

    /** @test */
    public function payment_intent_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/payments/intent', [
            'amount' => 29.99,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function payment_intent_validates_amount(): void
    {
        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/payments/intent', [
            'amount' => 0.10, // Below minimum
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['success', 'errors']);
    }

    /** @test */
    public function payment_intent_validates_maximum_amount(): void
    {
        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/payments/intent', [
            'amount' => 1000000.00, // Above maximum
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['success', 'errors']);
    }

    /** @test */
    public function user_can_confirm_payment(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'pending',
            'amount' => 29.99,
            'stripe_payment_intent_id' => 'pi_test123',
        ]);

        $this->stripeService
            ->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_test123')
            ->andReturn([
                'success' => true,
                'status' => 'succeeded',
                'charge_id' => 'ch_test123',
            ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/payments/confirm', [
            'payment_intent_id' => 'pi_test123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'amount' => 29.99,
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'completed',
            'stripe_charge_id' => 'ch_test123',
        ]);
    }

    /** @test */
    public function payment_confirmation_fails_for_invalid_intent(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'pending',
            'amount' => 29.99,
            'stripe_payment_intent_id' => 'pi_invalid',
        ]);

        $this->stripeService
            ->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_invalid')
            ->andReturn([
                'success' => false,
                'status' => 'requires_payment_method',
                'error' => 'Payment failed',
            ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/payments/confirm', [
            'payment_intent_id' => 'pi_invalid',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function user_can_get_transaction_history(): void
    {
        Transaction::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'completed',
        ]);

        Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'type' => 'refund',
            'status' => 'completed',
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/payments/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ])
            ->assertJsonCount(8, 'data');
    }

    /** @test */
    public function user_can_filter_transactions_by_status(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/payments/transactions?status=completed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    /** @test */
    public function user_can_filter_transactions_by_type(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'refund',
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/payments/transactions?type=payment');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'payment');
    }

    /** @test */
    public function user_can_get_single_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => 29.99,
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/payments/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $transaction->id,
                    'amount' => 29.99,
                ],
            ]);
    }

    /** @test */
    public function user_cannot_access_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/payments/transactions/{$transaction->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function payment_intent_creation_fails_when_stripe_fails(): void
    {
        $this->stripeService
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Stripe API error',
            ]);

        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/payments/intent', [
            'amount' => 29.99,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }
}
