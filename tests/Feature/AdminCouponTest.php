<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use App\Models\Role;
use Tests\TestCase;

class AdminCouponTest extends TestCase
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
    public function admin_can_list_coupons(): void
    {
        Coupon::factory()->count(5)->create();

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/coupons');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'discount_type',
                        'discount_value',
                        'is_active',
                    ],
                ],
                'pagination',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function admin_can_create_coupon(): void
    {
        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/admin/coupons', [
            'code' => 'TEST50',
            'name' => 'Test Coupon',
            'description' => 'Test description',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'valid_from' => now()->toDateTimeString(),
            'valid_until' => now()->addYear()->toDateTimeString(),
            'usage_limit' => 100,
            'user_limit' => 1,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'code',
                    'name',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'TEST50',
            'name' => 'Test Coupon',
        ]);
    }

    /** @test */
    public function admin_can_get_coupon_details(): void
    {
        $coupon = Coupon::factory()->create();

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/admin/coupons/{$coupon->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'discount_type',
                    'discount_value',
                    'usage_count',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $coupon->id,
                ],
            ]);
    }

    /** @test */
    public function admin_can_update_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'name' => 'Old Name',
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/v1/admin/coupons/{$coupon->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Name',
                ],
            ]);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'name' => 'New Name',
        ]);
    }

    /** @test */
    public function admin_can_deactivate_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/v1/admin/coupons/{$coupon->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function admin_cannot_create_duplicate_coupon_code(): void
    {
        Coupon::factory()->create([
            'code' => 'DUPLICATE',
        ]);

        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/admin/coupons', [
            'code' => 'DUPLICATE',
            'name' => 'Test',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'valid_from' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function admin_cannot_create_coupon_with_invalid_percentage(): void
    {
        $token = $this->admin->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/admin/coupons', [
            'code' => 'INVALID',
            'name' => 'Test',
            'discount_type' => 'percentage',
            'discount_value' => 150, // Invalid: > 100%
            'valid_from' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function non_admin_cannot_access_coupon_endpoints(): void
    {
        $token = $this->regularUser->createToken('Test Token')->accessToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/coupons');

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_coupon_endpoints(): void
    {
        $response = $this->getJson('/api/v1/admin/coupons');

        $response->assertStatus(401);
    }
}

