<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyCollection;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\PropertyService;
use App\Services\PropertyEnrichmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Properties")]
class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService,
        protected PropertyEnrichmentService $enrichmentService
    ) {}

    /**
     * Display a listing of properties with filters
     */
    #[OA\Get(
        path: "/properties",
        summary: "List properties",
        tags: ["Properties"],
        parameters: [
            new OA\Parameter(name: "city", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "state", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "property_type", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "min_price", in: "query", schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "max_price", in: "query", schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "bedrooms", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "bathrooms", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of properties"),
        ]
    )]
    public function index(Request $request): PropertyCollection
    {
        $filters = $request->only([
            'city',
            'state',
            'property_type',
            'status',
            'min_price',
            'max_price',
            'bedrooms',
            'bathrooms',
            'featured',
            'verified',
            'search',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->get('per_page', 15);
        $properties = $this->propertyService->getFiltered($filters, $perPage);

        return new PropertyCollection($properties);
    }

    /**
     * Store a newly created property
     */
    #[OA\Post(
        path: "/properties",
        summary: "Create a new property",
        description: "Create a new property listing. Requires authentication and wholesaler role.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["title", "address", "city", "state", "property_type", "asking_price"],
                    properties: [
                        new OA\Property(property: "title", type: "string", example: "Beautiful Investment Property"),
                        new OA\Property(property: "description", type: "string", example: "Great investment opportunity"),
                        new OA\Property(property: "property_type", type: "string", enum: ["house", "condo", "townhouse", "apartment", "land", "other"]),
                        new OA\Property(property: "address", type: "string", example: "123 Main St"),
                        new OA\Property(property: "city", type: "string", example: "Denver"),
                        new OA\Property(property: "state", type: "string", example: "CO"),
                        new OA\Property(property: "zip_code", type: "string", example: "80202"),
                        new OA\Property(property: "bedrooms", type: "integer", example: 3),
                        new OA\Property(property: "bathrooms", type: "number", example: 2.5),
                        new OA\Property(property: "square_feet", type: "integer", example: 2000),
                        new OA\Property(property: "lot_size", type: "number", example: 0.25),
                        new OA\Property(property: "year_built", type: "integer", example: 1995),
                        new OA\Property(property: "asking_price", type: "number", example: 250000),
                        new OA\Property(property: "arv", type: "number", example: 350000),
                        new OA\Property(property: "status", type: "string", enum: ["active", "pending", "sold"], example: "active"),
                        new OA\Property(property: "images", type: "array", items: new OA\Items(type: "string", format: "binary")),
                        new OA\Property(property: "primary_image_index", type: "integer", example: 0),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Property created successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Not a wholesaler"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = $this->propertyService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Property created successfully',
            'data' => new PropertyResource($property->load('images', 'wholesaler')),
        ], 201);
    }

    /**
     * Display the specified property
     */
    #[OA\Get(
        path: "/properties/{id}",
        summary: "Get property details",
        description: "Get detailed information about a specific property. Public endpoint.",
        tags: ["Properties"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid"),
                example: "550e8400-e29b-41d4-a716-446655440000"
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property details"),
            new OA\Response(response: 404, description: "Property not found"),
        ]
    )]
    public function show(Property $property): JsonResponse
    {
        $property->load(['wholesaler', 'images', 'primaryImage']);

        return response()->json([
            'success' => true,
            'data' => new PropertyResource($property),
        ]);
    }

    /**
     * Update the specified property
     */
    #[OA\Put(
        path: "/properties/{id}",
        summary: "Update property",
        description: "Update a property. Only the property owner (wholesaler) can update. Supports partial updates.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "title", type: "string"),
                        new OA\Property(property: "description", type: "string"),
                        new OA\Property(property: "asking_price", type: "number"),
                        new OA\Property(property: "status", type: "string", enum: ["active", "pending", "sold"]),
                        new OA\Property(property: "images", type: "array", items: new OA\Items(type: "string", format: "binary")),
                        new OA\Property(property: "primary_image_index", type: "integer"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Property updated successfully"),
            new OA\Response(response: 403, description: "Forbidden - Not the property owner"),
            new OA\Response(response: 404, description: "Property not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $property = $this->propertyService->update(
            $property,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Property updated successfully',
            'data' => new PropertyResource($property),
        ]);
    }

    /**
     * Remove the specified property
     */
    #[OA\Delete(
        path: "/properties/{id}",
        summary: "Delete property",
        description: "Delete a property. Only the property owner can delete.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property deleted successfully"),
            new OA\Response(response: 403, description: "Forbidden - Not the property owner"),
            new OA\Response(response: 404, description: "Property not found"),
        ]
    )]
    public function destroy(Property $property): JsonResponse
    {
        if (!$this->canModifyProperty($property)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this property',
            ], 403);
        }

        $this->propertyService->delete($property);

        return response()->json([
            'success' => true,
            'message' => 'Property deleted successfully',
        ]);
    }

    /**
     * Upload images for a property
     */
    #[OA\Post(
        path: "/properties/{id}/images",
        summary: "Upload property images",
        description: "Upload one or more images for a property. Maximum 10 images, 5MB each.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["images"],
                    properties: [
                        new OA\Property(property: "images", type: "array", items: new OA\Items(type: "string", format: "binary"), description: "Array of image files (max 10)"),
                        new OA\Property(property: "primary_index", type: "integer", example: 0, description: "Index of primary image (0-based)"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Images uploaded successfully"),
            new OA\Response(response: 403, description: "Forbidden - Not the property owner"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function uploadImages(Request $request, Property $property): JsonResponse
    {
        if (!$this->canModifyProperty($property)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to upload images for this property',
            ], 403);
        }

        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'primary_index' => 'nullable|integer|min:0',
        ]);

        $images = $request->file('images');
        $primaryIndex = $request->get('primary_index', 0);

        $uploadedImages = $this->propertyService->uploadImages(
            $property,
            $images,
            $primaryIndex
        );

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'data' => [
                'images' => $uploadedImages,
            ],
        ], 201);
    }

    /**
     * Delete an image
     */
    #[OA\Delete(
        path: "/properties/{property_id}/images/{image_id}",
        summary: "Delete property image",
        description: "Delete a specific image from a property.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "property_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "image_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Image deleted successfully"),
            new OA\Response(response: 403, description: "Forbidden - Not the property owner"),
            new OA\Response(response: 404, description: "Image not found"),
        ]
    )]
    public function deleteImage(Property $property, PropertyImage $image): JsonResponse
    {
        // Verify image belongs to property
        if ($image->property_id !== $property->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image does not belong to this property',
            ], 404);
        }

        if (!$this->canModifyProperty($property)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this image',
            ], 403);
        }

        $this->propertyService->deleteImage($image);

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
        ]);
    }

    /**
     * Set primary image
     */
    #[OA\Put(
        path: "/properties/{property_id}/images/{image_id}/primary",
        summary: "Set primary image",
        description: "Set a specific image as the primary image for a property.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "property_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "image_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Primary image set successfully"),
            new OA\Response(response: 403, description: "Forbidden - Not the property owner"),
            new OA\Response(response: 404, description: "Image not found"),
        ]
    )]
    public function setPrimaryImage(Property $property, PropertyImage $image): JsonResponse
    {
        // Verify image belongs to property
        if ($image->property_id !== $property->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image does not belong to this property',
            ], 404);
        }

        if (!$this->canModifyProperty($property)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to set primary image',
            ], 403);
        }

        $this->propertyService->setPrimaryImage($property, $image);

        return response()->json([
            'success' => true,
            'message' => 'Primary image updated successfully',
        ]);
    }

    /**
     * Enrich property with data from external APIs
     */
    public function enrich(Request $request, Property $property): JsonResponse
    {
        if (!$this->canModifyProperty($property)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to enrich this property',
            ], 403);
        }

        $request->validate([
            'sync' => 'nullable|boolean',
            'force_fresh' => 'nullable|boolean',
        ]);

        $sync = $request->boolean('sync', false);
        $forceFresh = $request->boolean('force_fresh', false);

        if ($sync) {
            $property = $this->enrichmentService->performEnrichment($property, $forceFresh);
            
            return response()->json([
                'success' => true,
                'message' => 'Property enriched successfully',
                'data' => new PropertyResource($property->load('images', 'wholesaler')),
            ]);
        }

        $this->enrichmentService->enrichProperty($property);
        
        return response()->json([
            'success' => true,
            'message' => 'Property enrichment queued. Data will be updated shortly.',
            'data' => [
                'property_id' => $property->id,
                'status' => 'queued',
            ],
        ], 202);
    }

    /**
     * Check if the authenticated user can modify the property
     */
    protected function canModifyProperty(Property $property): bool
    {
        $user = auth()->user();
        return $user->hasRole('admin') || $property->wholesaler_id === $user->id;
    }
}
