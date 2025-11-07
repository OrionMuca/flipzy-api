<?php

namespace Tests\Feature;

use App\Models\Analytic;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $investor;
    protected User $wholesaler;
    protected string $investorToken;
    protected string $wholesalerToken;
    protected Property $property;

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

        // Create a property owned by wholesaler
        $this->property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
    }

    // ==================== Track View Tests ====================

    /** @test */
    public function authenticated_user_can_track_property_view(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/properties/{$this->property->id}/view");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'event_id',
                    'event_type',
                    'property_id',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_type' => 'view',
                    'property_id' => $this->property->id,
                ],
            ]);

        $this->assertDatabaseHas('analytics', [
            'property_id' => $this->property->id,
            'user_id' => $this->investor->id,
            'event_type' => 'view',
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_track_view(): void
    {
        $response = $this->postJson("/api/v1/properties/{$this->property->id}/view");

        $response->assertStatus(401);
    }

    /** @test */
    public function view_tracking_stores_ip_and_user_agent(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/properties/{$this->property->id}/view", [], [
            'User-Agent' => 'Test Browser',
        ]);

        $response->assertStatus(201);

        $analytic = Analytic::where('property_id', $this->property->id)
            ->where('event_type', 'view')
            ->first();

        $this->assertNotNull($analytic->ip_address);
        $this->assertNotNull($analytic->user_agent);
    }

    // ==================== Track Save Tests ====================

    /** @test */
    public function authenticated_user_can_track_property_save(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/properties/{$this->property->id}/save");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_type' => 'save',
                    'property_id' => $this->property->id,
                ],
            ]);

        $this->assertDatabaseHas('analytics', [
            'property_id' => $this->property->id,
            'user_id' => $this->investor->id,
            'event_type' => 'save',
        ]);
    }

    /** @test */
    public function saving_same_property_twice_returns_existing_save(): void
    {
        // First save
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/properties/{$this->property->id}/save");

        // Second save
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/properties/{$this->property->id}/save");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Property already saved',
            ]);

        // Should only have one save record
        $this->assertEquals(1, Analytic::where('property_id', $this->property->id)
            ->where('event_type', 'save')
            ->where('user_id', $this->investor->id)
            ->count());
    }

    // ==================== Track Inquiry Tests ====================

    /** @test */
    public function authenticated_user_can_track_property_inquiry(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/properties/{$this->property->id}/inquiry", [
            'message' => 'I am interested in this property',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_type' => 'inquiry',
                    'property_id' => $this->property->id,
                ],
            ]);

        $this->assertDatabaseHas('analytics', [
            'property_id' => $this->property->id,
            'user_id' => $this->investor->id,
            'event_type' => 'inquiry',
        ]);
    }

    /** @test */
    public function inquiry_message_is_stored_in_metadata(): void
    {
        $message = 'I would like to know more about this property';

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson("/api/v1/properties/{$this->property->id}/inquiry", [
            'message' => $message,
        ]);

        $analytic = Analytic::where('property_id', $this->property->id)
            ->where('event_type', 'inquiry')
            ->first();

        $this->assertEquals($message, $analytic->metadata['message']);
    }

    // ==================== Get Property Analytics Tests ====================

    /** @test */
    public function user_can_get_property_analytics(): void
    {
        // Create some analytics
        Analytic::factory()->count(5)->create([
            'property_id' => $this->property->id,
            'event_type' => 'view',
            'user_id' => $this->investor->id,
        ]);

        Analytic::factory()->count(3)->create([
            'property_id' => $this->property->id,
            'event_type' => 'save',
            'user_id' => $this->investor->id,
        ]);

        Analytic::factory()->count(2)->create([
            'property_id' => $this->property->id,
            'event_type' => 'inquiry',
            'user_id' => $this->investor->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/properties/{$this->property->id}/analytics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'property_id',
                    'period_days',
                    'total_views',
                    'unique_views',
                    'total_saves',
                    'unique_saves',
                    'total_inquiries',
                    'unique_inquiries',
                    'breakdown',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_views' => 5,
                    'total_saves' => 3,
                    'total_inquiries' => 2,
                ],
            ]);
    }

    /** @test */
    public function property_analytics_supports_custom_period(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/properties/{$this->property->id}/analytics?days=90");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'period_days' => 90,
                ],
            ]);
    }

    // ==================== Credibility Score Tests ====================

    /** @test */
    public function user_can_get_wholesaler_credibility_score(): void
    {
        // Create additional property for wholesaler (one already exists in setUp)
        $property2 = Property::factory()->create(['wholesaler_id' => $this->wholesaler->id]);

        // Create analytics across properties
        Analytic::factory()->count(10)->create([
            'property_id' => $this->property->id,
            'event_type' => 'view',
        ]);

        Analytic::factory()->count(5)->create([
            'property_id' => $this->property->id,
            'event_type' => 'save',
        ]);

        Analytic::factory()->count(3)->create([
            'property_id' => $property2->id,
            'event_type' => 'inquiry',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/users/{$this->wholesaler->id}/credibility");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'score',
                    'normalized_score',
                    'breakdown' => [
                        'total_views',
                        'total_saves',
                        'total_inquiries',
                    ],
                    'property_count',
                    'period_days',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->wholesaler->id,
                    'breakdown' => [
                        'total_views' => 10,
                        'total_saves' => 5,
                        'total_inquiries' => 3,
                    ],
                ],
            ]);

        // Verify property_count is at least 2
        $this->assertGreaterThanOrEqual(2, $response->json('data.property_count'));
    }

    /** @test */
    public function credibility_score_only_available_for_wholesalers(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/users/{$this->investor->id}/credibility");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Credibility scores are only available for wholesalers',
            ]);
    }

    /** @test */
    public function credibility_score_calculates_correctly(): void
    {
        $property = Property::factory()->create(['wholesaler_id' => $this->wholesaler->id]);

        // Create analytics: 100 views, 20 saves, 10 inquiries
        Analytic::factory()->count(100)->create([
            'property_id' => $property->id,
            'event_type' => 'view',
        ]);

        Analytic::factory()->count(20)->create([
            'property_id' => $property->id,
            'event_type' => 'save',
        ]);

        Analytic::factory()->count(10)->create([
            'property_id' => $property->id,
            'event_type' => 'inquiry',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson("/api/v1/users/{$this->wholesaler->id}/credibility");

        $response->assertStatus(200);

        $data = $response->json('data');
        
        // Verify score calculation: (100 * 0.3) + (20 * 0.5) + (10 * 0.2) = 30 + 10 + 2 = 42
        $expectedScore = (100 * 0.3) + (20 * 0.5) + (10 * 0.2);
        $this->assertEquals($expectedScore, $data['score']);
        $this->assertGreaterThan(0, $data['normalized_score']);
        $this->assertLessThanOrEqual(100, $data['normalized_score']);
    }

    // ==================== My Analytics Tests ====================

    /** @test */
    public function wholesaler_can_get_own_analytics(): void
    {
        // Use the property created in setUp
        Analytic::factory()->count(5)->create([
            'property_id' => $this->property->id,
            'event_type' => 'view',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->getJson('/api/v1/analytics/my-analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'total_properties',
                    'total_views',
                    'total_saves',
                    'total_inquiries',
                    'period_days',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->wholesaler->id,
                    'total_views' => 5,
                ],
            ]);

        // Verify total_properties is at least 1 (may be more if setUp property exists)
        $this->assertGreaterThanOrEqual(1, $response->json('data.total_properties'));
    }

    /** @test */
    public function non_wholesaler_cannot_get_my_analytics(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->getJson('/api/v1/analytics/my-analytics');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Analytics are only available for wholesalers',
            ]);
    }
}

