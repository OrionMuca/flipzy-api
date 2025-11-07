<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Client;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    protected User $wholesaler;
    protected User $admin;
    protected User $investor;
    protected string $wholesalerToken;
    protected string $adminToken;
    protected string $investorToken;

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

        $this->investor = User::factory()->create([
            'name' => 'Test Investor',
            'email' => 'investor@test.com',
        ]);
        $this->investor->assignRole('investor');
        $this->investorToken = $this->investor->createToken('Test Token')->accessToken;

        Storage::fake('public');
    }

    protected function getPropertyData(): array
    {
        return [
            'title' => 'Test Property',
            'description' => 'This is a test property description',
            'property_type' => 'house',
            'status' => 'active',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'CA',
            'zip_code' => '12345',
            'country' => 'US',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'square_feet' => 1500,
            'lot_size' => 5000,
            'year_built' => 2000,
            'condition' => 'good',
            'asking_price' => 250000.00,
            'arv' => 300000.00,
            'repair_estimate' => 20000.00,
            'is_featured' => false,
            'is_verified' => false,
            'allow_inquiries' => true,
        ];
    }

    // ==================== CREATE (Store) Tests ====================

    /** @test */
    public function wholesaler_can_create_property(): void
    {
        $data = $this->getPropertyData();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson('/api/v1/properties', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'address',
                    'wholesaler' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Property created successfully',
                'data' => [
                    'title' => $data['title'],
                    'address' => $data['address'],
                ],
            ]);

        $this->assertDatabaseHas('properties', [
            'title' => $data['title'],
            'address' => $data['address'],
            'wholesaler_id' => $this->wholesaler->id,
        ]);
    }

    /** @test */
    public function admin_can_create_property(): void
    {
        $data = $this->getPropertyData();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/properties', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('properties', [
            'title' => $data['title'],
            'wholesaler_id' => $this->admin->id,
        ]);
    }

    /** @test */
    public function investor_cannot_create_property(): void
    {
        $data = $this->getPropertyData();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->postJson('/api/v1/properties', $data);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('properties', [
            'title' => $data['title'],
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_property(): void
    {
        $data = $this->getPropertyData();

        $response = $this->postJson('/api/v1/properties', $data);

        $response->assertStatus(401);
    }

    /** @test */
    public function property_creation_requires_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson('/api/v1/properties', [
            'title' => 'Test',
            // Missing required fields: address, city, state, zip_code, asking_price
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address', 'city', 'state', 'zip_code', 'asking_price']);
    }

    /** @test */
    public function property_creation_calculates_potential_profit(): void
    {
        $data = $this->getPropertyData();
        $data['arv'] = 300000.00;
        $data['repair_estimate'] = 20000.00;
        $data['asking_price'] = 250000.00;
        // Expected: 300000 - 250000 - 20000 = 30000

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson('/api/v1/properties', $data);

        $response->assertStatus(201);
        
        $property = Property::where('title', $data['title'])->first();
        $this->assertEquals(30000.00, $property->potential_profit);
    }

    /** @test */
    public function property_creation_can_upload_images(): void
    {
        $data = $this->getPropertyData();
        $images = [
            UploadedFile::fake()->image('property1.jpg', 800, 600),
            UploadedFile::fake()->image('property2.jpg', 800, 600),
        ];
        $data['images'] = $images;
        $data['primary_image_index'] = 0;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson('/api/v1/properties', $data);

        $response->assertStatus(201);
        
        $property = Property::where('title', $data['title'])->first();
        $this->assertCount(2, $property->images);
        $this->assertTrue($property->images->first()->is_primary);
    }

    /** @test */
    public function property_images_are_returned_in_order(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        // Create images with specific orders (not sequential to test ordering)
        PropertyImage::factory()->create([
            'property_id' => $property->id,
            'order' => 2,
            'is_primary' => false,
        ]);
        PropertyImage::factory()->create([
            'property_id' => $property->id,
            'order' => 0,
            'is_primary' => true,
        ]);
        PropertyImage::factory()->create([
            'property_id' => $property->id,
            'order' => 1,
            'is_primary' => false,
        ]);

        $response = $this->getJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(200);
        $images = $response->json('data.images');
        
        // Verify images are returned in order (0, 1, 2)
        $this->assertCount(3, $images);
        $this->assertEquals(0, $images[0]['order']);
        $this->assertEquals(1, $images[1]['order']);
        $this->assertEquals(2, $images[2]['order']);
        $this->assertTrue($images[0]['is_primary']); // First image (order 0) should be primary
    }

    /** @test */
    public function property_update_can_add_images(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
        
        // Add initial image
        PropertyImage::factory()->create([
            'property_id' => $property->id,
            'is_primary' => true,
        ]);

        $newImages = [
            UploadedFile::fake()->image('new1.jpg', 800, 600),
            UploadedFile::fake()->image('new2.jpg', 800, 600),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->putJson("/api/v1/properties/{$property->id}", [
            'title' => 'Updated Title',
            'images' => $newImages,
            'primary_image_index' => 0, // First new image should be primary
        ]);

        $response->assertStatus(200);
        
        $property->refresh();
        $this->assertCount(3, $property->images); // 1 existing + 2 new
        $this->assertTrue($property->images->where('is_primary', true)->count() === 1);
    }

    // ==================== READ (Index) Tests ====================

    /** @test */
    public function anyone_can_view_properties_list(): void
    {
        Property::factory()->count(5)->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->getJson('/api/v1/properties');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'address',
                        'financial' => [
                            'asking_price',
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    /** @test */
    public function properties_list_can_be_filtered_by_city(): void
    {
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'city' => 'Los Angeles',
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'city' => 'New York',
        ]);

        $response = $this->getJson('/api/v1/properties?city=Los Angeles');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Los Angeles', $data[0]['city']);
    }

    /** @test */
    public function properties_list_can_be_filtered_by_state(): void
    {
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'state' => 'CA',
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'state' => 'NY',
        ]);

        $response = $this->getJson('/api/v1/properties?state=CA');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('CA', $data[0]['state']);
    }

    /** @test */
    public function properties_list_can_be_filtered_by_price_range(): void
    {
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'asking_price' => 100000,
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'asking_price' => 200000,
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'asking_price' => 300000,
        ]);

        $response = $this->getJson('/api/v1/properties?min_price=150000&max_price=250000');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(200000, $data[0]['financial']['asking_price']);
    }

    /** @test */
    public function properties_list_can_be_filtered_by_bedrooms(): void
    {
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'bedrooms' => 2,
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'bedrooms' => 4,
        ]);

        $response = $this->getJson('/api/v1/properties?bedrooms=3');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertGreaterThanOrEqual(3, $data[0]['details']['bedrooms']);
    }

    /** @test */
    public function properties_list_can_be_searched(): void
    {
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'title' => 'Beautiful Downtown Property',
            'address' => '123 Main St',
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'title' => 'Suburban House',
            'address' => '456 Oak Ave',
        ]);

        $response = $this->getJson('/api/v1/properties?search=Downtown');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Downtown', $data[0]['title']);
    }

    /** @test */
    public function properties_list_can_be_sorted(): void
    {
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'asking_price' => 100000,
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'asking_price' => 300000,
        ]);
        Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'asking_price' => 200000,
        ]);

        $response = $this->getJson('/api/v1/properties?sort_by=asking_price&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(100000, $data[0]['financial']['asking_price']);
        $this->assertEquals(200000, $data[1]['financial']['asking_price']);
        $this->assertEquals(300000, $data[2]['financial']['asking_price']);
    }

    /** @test */
    public function properties_list_supports_pagination(): void
    {
        Property::factory()->count(25)->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->getJson('/api/v1/properties?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
    }

    // ==================== READ (Show) Tests ====================

    /** @test */
    public function anyone_can_view_single_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->getJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'address',
                    'wholesaler' => [
                        'id',
                        'name',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $property->id,
                    'title' => $property->title,
                ],
            ]);
    }

    /** @test */
    public function property_show_includes_wholesaler_and_images(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
        PropertyImage::factory()->count(3)->create([
            'property_id' => $property->id,
        ]);

        $response = $this->getJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('wholesaler', $data);
        $this->assertArrayHasKey('images', $data);
        $this->assertCount(3, $data['images']);
    }

    /** @test */
    public function viewing_nonexistent_property_returns_404(): void
    {
        $fakeId = \Illuminate\Support\Str::uuid();

        $response = $this->getJson("/api/v1/properties/{$fakeId}");

        $response->assertStatus(404);
    }

    // ==================== UPDATE Tests ====================

    /** @test */
    public function wholesaler_can_update_own_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'title' => 'Original Title',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->putJson("/api/v1/properties/{$property->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Property updated successfully',
                'data' => [
                    'title' => 'Updated Title',
                ],
            ]);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'title' => 'Updated Title',
        ]);
    }

    /** @test */
    public function admin_can_update_any_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson("/api/v1/properties/{$property->id}", [
            'title' => 'Admin Updated Title',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'title' => 'Admin Updated Title',
        ]);
    }

    /** @test */
    public function wholesaler_cannot_update_other_wholesaler_property(): void
    {
        $otherWholesaler = User::factory()->create();
        $otherWholesaler->assignRole('wholesaler');
        $otherToken = $otherWholesaler->createToken('Test Token')->accessToken;

        $property = Property::factory()->create([
            'wholesaler_id' => $otherWholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->putJson("/api/v1/properties/{$property->id}", [
            'title' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function investor_cannot_update_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->putJson("/api/v1/properties/{$property->id}", [
            'title' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function property_update_recalculates_potential_profit(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
            'asking_price' => 200000,
            'arv' => 250000,
            'repair_estimate' => 15000,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->putJson("/api/v1/properties/{$property->id}", [
            'arv' => 300000,
            'repair_estimate' => 20000,
        ]);

        $response->assertStatus(200);
        $property->refresh();
        // Expected: 300000 - 200000 - 20000 = 80000
        $this->assertEquals(80000.00, $property->potential_profit);
    }

    // ==================== DELETE Tests ====================

    /** @test */
    public function wholesaler_can_delete_own_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->deleteJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Property deleted successfully',
            ]);

        $this->assertSoftDeleted('properties', [
            'id' => $property->id,
        ]);
    }

    /** @test */
    public function admin_can_delete_any_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('properties', [
            'id' => $property->id,
        ]);
    }

    /** @test */
    public function wholesaler_cannot_delete_other_wholesaler_property(): void
    {
        $otherWholesaler = User::factory()->create();
        $otherWholesaler->assignRole('wholesaler');

        $property = Property::factory()->create([
            'wholesaler_id' => $otherWholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->deleteJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
        ]);
    }

    /** @test */
    public function investor_cannot_delete_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->investorToken,
        ])->deleteJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
        ]);
    }

    /** @test */
    public function property_deletion_removes_associated_images(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
        $image = PropertyImage::factory()->create([
            'property_id' => $property->id,
            'path' => 'properties/test/image.jpg',
        ]);

        Storage::disk('public')->put($image->path, 'fake content');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->deleteJson("/api/v1/properties/{$property->id}");

        $response->assertStatus(200);
        
        // Refresh to get updated relationship
        $property->refresh();
        
        // Images should be deleted (cascade delete)
        $this->assertDatabaseMissing('property_images', [
            'id' => $image->id,
        ]);
        
        // Storage file should be deleted
        Storage::disk('public')->assertMissing($image->path);
    }

    // ==================== IMAGE UPLOAD Tests ====================

    /** @test */
    public function wholesaler_can_upload_images_to_own_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $images = [
            UploadedFile::fake()->image('property1.jpg', 800, 600),
            UploadedFile::fake()->image('property2.jpg', 800, 600),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson("/api/v1/properties/{$property->id}/images", [
            'images' => $images,
            'primary_index' => 0,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'images' => [
                        '*' => [
                            'id',
                            'path',
                            'url',
                            'is_primary',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('property_images', [
            'property_id' => $property->id,
            'is_primary' => true,
        ]);
        $this->assertCount(2, $property->fresh()->images);
    }

    /** @test */
    public function wholesaler_cannot_upload_images_to_other_property(): void
    {
        $otherWholesaler = User::factory()->create();
        $otherWholesaler->assignRole('wholesaler');
        $property = Property::factory()->create([
            'wholesaler_id' => $otherWholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson("/api/v1/properties/{$property->id}/images", [
            'images' => [UploadedFile::fake()->image('test.jpg')],
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function image_upload_validates_file_types(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson("/api/v1/properties/{$property->id}/images", [
            'images' => [UploadedFile::fake()->create('document.pdf', 1000)],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /** @test */
    public function image_upload_validates_file_size(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson("/api/v1/properties/{$property->id}/images", [
            'images' => [UploadedFile::fake()->image('large.jpg')->size(6000)], // 6MB > 5MB limit
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    // ==================== IMAGE DELETE Tests ====================

    /** @test */
    public function wholesaler_can_delete_image_from_own_property(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
        $image = PropertyImage::factory()->create([
            'property_id' => $property->id,
            'path' => 'properties/' . $property->id . '/image.jpg',
        ]);

        // Create the directory structure
        Storage::disk('public')->makeDirectory('properties/' . $property->id);
        Storage::disk('public')->put($image->path, 'fake content');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->deleteJson("/api/v1/properties/{$property->id}/images/{$image->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);

        $this->assertDatabaseMissing('property_images', [
            'id' => $image->id,
        ]);
        Storage::disk('public')->assertMissing($image->path);
    }

    /** @test */
    public function cannot_delete_image_from_different_property(): void
    {
        $property1 = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
        $property2 = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
        $image = PropertyImage::factory()->create([
            'property_id' => $property1->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->deleteJson("/api/v1/properties/{$property2->id}/images/{$image->id}");

        $response->assertStatus(404);
    }

    // ==================== SET PRIMARY IMAGE Tests ====================

    /** @test */
    public function wholesaler_can_set_primary_image(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);
        $image1 = PropertyImage::factory()->create([
            'property_id' => $property->id,
            'is_primary' => true,
        ]);
        $image2 = PropertyImage::factory()->create([
            'property_id' => $property->id,
            'is_primary' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->putJson("/api/v1/properties/{$property->id}/images/{$image2->id}/primary");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Primary image updated successfully',
            ]);

        $this->assertDatabaseHas('property_images', [
            'id' => $image2->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('property_images', [
            'id' => $image1->id,
            'is_primary' => false,
        ]);
    }

    // ==================== ENRICHMENT Tests ====================

    /** @test */
    public function wholesaler_can_enrich_own_property_async(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson("/api/v1/properties/{$property->id}/enrich");

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'message' => 'Property enrichment queued. Data will be updated shortly.',
                'data' => [
                    'property_id' => $property->id,
                    'status' => 'queued',
                ],
            ]);
    }

    /** @test */
    public function wholesaler_can_enrich_own_property_sync(): void
    {
        $property = Property::factory()->create([
            'wholesaler_id' => $this->wholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson("/api/v1/properties/{$property->id}/enrich", [
            'sync' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'enriched_at',
                ],
            ]);
    }

    /** @test */
    public function wholesaler_cannot_enrich_other_property(): void
    {
        $otherWholesaler = User::factory()->create();
        $otherWholesaler->assignRole('wholesaler');
        $property = Property::factory()->create([
            'wholesaler_id' => $otherWholesaler->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->wholesalerToken,
        ])->postJson("/api/v1/properties/{$property->id}/enrich");

        $response->assertStatus(403);
    }
}

