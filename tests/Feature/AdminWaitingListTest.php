<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use App\Models\Role;
use Tests\TestCase;

class AdminWaitingListTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

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
    }

    /** @test */
    public function admin_can_list_waiting_list_entries(): void
    {
        WaitingListEntry::factory()->count(5)->create();

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
        ]);

        WaitingListEntry::factory()->create([
            'status' => 'account_created',
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/waiting-list?status=account_created');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('account_created', $data[0]['status']);
    }

    /** @test */
    public function admin_can_search_entries_by_email(): void
    {
        WaitingListEntry::factory()->create([
            'email' => 'test@example.com',
        ]);

        WaitingListEntry::factory()->create([
            'email' => 'other@example.com',
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
        $entry = WaitingListEntry::factory()->create();

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
        ]);

        WaitingListEntry::factory()->count(2)->create([
            'status' => 'account_created',
        ]);

        WaitingListEntry::factory()->create([
            'status' => 'cancelled',
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
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 6,
                    'by_status' => [
                        'pending' => 3,
                        'account_created' => 2,
                        'cancelled' => 1,
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

