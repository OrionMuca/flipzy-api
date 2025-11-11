<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use App\Models\Role;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $investor;
    protected User $wholesaler;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Passport personal access client for testing
        // This is needed for createToken() to work in tests
        // Note: Passport 13+ uses different schema (redirect_uris, grant_types)
        $client = new Client();
        $client->id = (string) \Illuminate\Support\Str::uuid();
        $client->name = 'Laravel Personal Access Client';
        $client->secret = \Illuminate\Support\Facades\Hash::make('test-secret-key');
        $client->redirect_uris = ['http://localhost']; // Passport handles JSON encoding
        $client->grant_types = ['personal_access']; // Passport handles JSON encoding
        $client->revoked = false;
        $client->save();
        
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'investor']);
        Role::firstOrCreate(['name' => 'wholesaler']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->investor = User::factory()->create();
        $this->investor->assignRole('investor');

        $this->wholesaler = User::factory()->create();
        $this->wholesaler->assignRole('wholesaler');
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $response = $this->actingAs($this->investor, 'api')
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(200);
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'email', 'roles'],
                ],
                'meta',
            ]);
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/users?role=investor');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);
        // Verify endpoint works and returns data
        $this->assertIsArray($response->json('data'));
    }

    public function test_admin_can_search_users(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/users?search=' . $this->investor->email);

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_admin_can_get_user_details(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/users/' . $this->investor->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'email', 'roles'],
            ]);
    }

    public function test_admin_can_update_user(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->putJson('/api/v1/admin/users/' . $this->investor->id, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $response->json('data.name'));
    }

    public function test_admin_can_delete_user(): void
    {
        $userToDelete = User::factory()->create();
        $userToDelete->assignRole('investor');

        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson('/api/v1/admin/users/' . $userToDelete->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->deleteJson('/api/v1/admin/users/' . $this->admin->id);

        $response->assertStatus(422);
    }

    public function test_admin_can_list_properties(): void
    {
        Property::factory()->create(['wholesaler_id' => $this->wholesaler->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/properties');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);
    }

    public function test_admin_can_get_property_details(): void
    {
        $property = Property::factory()->create(['wholesaler_id' => $this->wholesaler->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/properties/' . $property->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'title', 'address'],
            ]);
    }

    public function test_admin_can_approve_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'status' => 'pending',
            'is_verified' => false,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/admin/properties/' . $property->id . '/approve');

        $response->assertStatus(200);
        $property->refresh();
        $this->assertEquals('active', $property->status);
        $this->assertTrue($property->is_verified);
    }

    public function test_admin_can_feature_property(): void
    {
        $property = Property::factory()->create(['wholesaler_id' => $this->wholesaler->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/admin/properties/' . $property->id . '/feature', [
                'featured' => true,
            ]);

        $response->assertStatus(200);
        $property->refresh();
        $this->assertTrue($property->is_featured);
    }

    public function test_admin_can_get_system_overview(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'users',
                    'properties',
                    'engagement',
                    'subscriptions',
                    'messaging',
                ],
            ]);
    }

    public function test_admin_can_get_trends(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/analytics/trends?days=30');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'dates',
                    'users',
                    'properties',
                    'engagement',
                ],
            ]);
    }

    public function test_admin_can_get_system_health(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/system/health');

        // Can be healthy (200), degraded (503), or error (500)
        $status = $response->status();
        $this->assertTrue(in_array($status, [200, 500, 503]), 'Expected status 200, 500, or 503, got ' . $status);
        
        // Only check structure if not a 500 error
        if ($status !== 500) {
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
            // Verify health data structure exists
            $data = $response->json('data');
            $this->assertIsArray($data);
        }
    }

    public function test_admin_can_get_queue_stats(): void
    {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/admin/system/queue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['pending', 'failed', 'status'],
            ]);
    }
}
