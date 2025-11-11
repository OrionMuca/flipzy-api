<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Models\WaitingListTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use App\Models\Role;
use Tests\TestCase;

class AdminWaitingListTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected SubscriptionPlan $plan;

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

        // Create roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'investor']);

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Create regular user
        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('investor');

        // Create plan
        $this->plan = SubscriptionPlan::factory()->create();
    }

    /** @test */
    public function admin_can_list_waiting_list_entries(): void
    {
        WaitingListEntry::factory()->count(5)->create([
            'subscription_plan_id' => $this->plan->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/waiting-list');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'email',
                        'name',
                        'status',
                        'subscription_plan',
                    ],
                ],
                'pagination',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function admin_can_filter_entries_by_status(): void
    {
        WaitingListEntry::factory()->create([
            'status' => 'pending',
            'subscription_plan_id' => $this->plan->id,
        ]);

        WaitingListEntry::factory()->create([
            'status' => 'payment_completed',
            'subscription_plan_id' => $this->plan->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/waiting-list?status=payment_completed');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('payment_completed', $data[0]['status']);
    }

    /** @test */
    public function admin_can_search_entries_by_email(): void
    {
        WaitingListEntry::factory()->create([
            'email' => 'test@example.com',
            'subscription_plan_id' => $this->plan->id,
        ]);

        WaitingListEntry::factory()->create([
            'email' => 'other@example.com',
            'subscription_plan_id' => $this->plan->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/waiting-list?search=test@example.com');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('test@example.com', $data[0]['email']);
    }

    /** @test */
    public function admin_can_get_entry_details(): void
    {
        $entry = WaitingListEntry::factory()->create([
            'subscription_plan_id' => $this->plan->id,
        ]);

        WaitingListTransaction::factory()->create([
            'waiting_list_entry_id' => $entry->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/admin/waiting-list/{$entry->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'email',
                    'name',
                    'status',
                    'subscription_plan',
                    'transactions',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $entry->id,
                ],
            ]);
    }

    /** @test */
    public function admin_can_get_waiting_list_statistics(): void
    {
        WaitingListEntry::factory()->count(3)->create([
            'status' => 'pending',
            'subscription_plan_id' => $this->plan->id,
        ]);

        WaitingListEntry::factory()->count(2)->create([
            'status' => 'payment_completed',
            'subscription_plan_id' => $this->plan->id,
            'discounted_price' => 49.99,
        ]);

        WaitingListEntry::factory()->create([
            'status' => 'account_created',
            'subscription_plan_id' => $this->plan->id,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/waiting-list/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'by_status',
                    'revenue',
                    'by_plan',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 6,
                    'by_status' => [
                        'pending' => 3,
                        'payment_completed' => 2,
                        'account_created' => 1,
                    ],
                ],
            ]);
    }

    /** @test */
    public function non_admin_cannot_access_waiting_list_endpoints(): void
    {
        $token = $this->regularUser->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/waiting-list');

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admin_endpoints(): void
    {
        $response = $this->getJson('/api/v1/admin/waiting-list');

        $response->assertStatus(401);
    }
}

