<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use App\Models\Role;
use Tests\TestCase;

class AdminTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

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
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->user = User::factory()->create();
        $this->user->assignRole('investor');
    }

    /** @test */
    public function admin_can_list_all_transactions(): void
    {
        Transaction::factory()->count(10)->create([
            'user_id' => $this->user->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ])
            ->assertJsonCount(10, 'data');
    }

    /** @test */
    public function admin_can_filter_transactions_by_status(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions?status=completed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    /** @test */
    public function admin_can_filter_transactions_by_type(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
        ]);

        Transaction::factory()->refund()->create([
            'user_id' => $this->user->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions?type=payment');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'payment');
    }

    /** @test */
    public function admin_can_filter_transactions_by_user(): void
    {
        $otherUser = User::factory()->create();

        Transaction::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/admin/transactions?user_id={$this->user->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->user->id);
    }

    /** @test */
    public function admin_can_filter_transactions_by_date_range(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->addDays(2),
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions?date_from=' . now()->subDays(3)->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function admin_can_get_transaction_statistics(): void
    {
        Transaction::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => 29.99,
        ]);

        Transaction::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'failed',
        ]);

        Transaction::factory()->refund()->create([
            'user_id' => $this->user->id,
            'amount' => 15.00,
            'status' => 'completed',
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_revenue',
                    'total_refunded',
                    'net_revenue',
                    'total_transactions',
                    'completed_transactions',
                    'failed_transactions',
                    'refunded_transactions',
                    'pending_transactions',
                    'by_status',
                    'by_type',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertEquals(8, $data['total_transactions']);
        $this->assertEquals(6, $data['completed_transactions']);
        $this->assertEquals(2, $data['failed_transactions']);
    }

    /** @test */
    public function admin_can_get_single_transaction(): void
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 29.99,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/admin/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'amount',
                    'status',
                    'type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $transaction->id,
                    'amount' => 29.99,
                ],
            ]);
    }

    /** @test */
    public function non_admin_cannot_access_admin_transaction_endpoints(): void
    {
        $token = $this->user->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_transaction_list_is_paginated(): void
    {
        Transaction::factory()->count(25)->create([
            'user_id' => $this->user->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 25)
            ->assertJsonPath('pagination.last_page', 3);
    }

    /** @test */
    public function admin_can_sort_transactions(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 10.00,
            'created_at' => now()->subDays(2),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 50.00,
            'created_at' => now()->subDays(1),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 30.00,
            'created_at' => now(),
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions?sort_by=amount&sort_order=asc');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
        
        $data = $response->json('data');
        $this->assertEquals('10.00', $data[0]['amount']);
        $this->assertEquals('50.00', $data[2]['amount']);
    }

    /** @test */
    public function admin_transaction_stats_can_filter_by_date(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => 29.99,
            'created_at' => now()->subDays(10),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => 49.99,
            'created_at' => now()->subDays(2),
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/transactions/stats?date_from=' . now()->subDays(5)->format('Y-m-d'));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(49.99, $data['total_revenue']);
    }
}
