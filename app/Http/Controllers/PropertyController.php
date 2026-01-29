<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyCollection;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\WholesalerInvestorProfileResource;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\WholesalerInvestorProfile;
use App\Services\BuyBoxService;
use App\Services\PropertyService;
use App\Services\PropertyEnrichmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Properties")]
class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService,
        protected PropertyEnrichmentService $enrichmentService,
        protected BuyBoxService $buyBoxService
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
 * Preview property data from external APIs without storing
 */
#[OA\Post(
    path: "/wholesaler/properties/search/preview",
    summary: "Preview property data",
    description: "Fetch property data from external APIs (ATTOM) without creating a property record. Useful for previewing data before creating a listing.",
    tags: ["Properties"],
    security: [["bearerAuth" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                required: ["address"],
                properties: [
                    new OA\Property(property: "address", type: "string", example: "123 Main St"),
                    new OA\Property(property: "city", type: "string", example: "Denver"),
                    new OA\Property(property: "state", type: "string", example: "CO"),
                    new OA\Property(property: "zip_code", type: "string", example: "80202"),
                    new OA\Property(
                        property: "endpoints", 
                        type: "array", 
                        items: new OA\Items(type: "string", enum: ["detail", "sale_history", "comparable_sales", "events", "snapshot"]),
                        example: ["detail", "sale_history"]
                    ),
                    new OA\Property(property: "force_fresh", type: "boolean", example: false),
                    new OA\Property(property: "extract", type: "boolean", example: true, description: "Return extracted/formatted data instead of raw API response"),
                ]
            )
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Property data preview"),
        new OA\Response(response: 401, description: "Unauthenticated"),
        new OA\Response(response: 422, description: "Validation error"),
    ]
)]
public function preview(Request $request): JsonResponse
{
    $validated = $request->validate([
        'address' => 'required|string|max:255',
        'city' => 'nullable|string|max:100',
        'state' => 'nullable|string|max:2',
        'zip_code' => 'nullable|string|max:10',
        'endpoints' => 'nullable|array',
        'endpoints.*' => 'string|in:detail,sale_history,comparable_sales,events,snapshot',
        'force_fresh' => 'nullable|boolean',
        'extract' => 'nullable|boolean',
    ]);

    $address = $validated['address'];
    $city = $validated['city'] ?? null;
    $state = $validated['state'] ?? null;
    $zipCode = $validated['zip_code'] ?? null;
    $endpoints = $validated['endpoints'] ?? ['detail'];
    $forceFresh = $validated['force_fresh'] ?? false;
    $extract = $validated['extract'] ?? true;

    // Validate address is complete enough
    if (!$this->enrichmentService->validateAddress($address, $city, $state, $zipCode)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid or incomplete address provided',
        ], 422);
    }

    try {
        if ($extract && count($endpoints) === 1 && $endpoints[0] === 'detail') {
            // Simple case: just get extracted detail data
            $data = $this->enrichmentService->fetchAndExtractPropertyData(
                $address,
                $city,
                $state,
                $zipCode,
                $forceFresh
            );
        } else {
            // Complex case: multiple endpoints or raw data
            $data = $this->enrichmentService->fetchPropertyData(
                $address,
                $city,
                $state,
                $zipCode,
                $endpoints,
                $forceFresh
            );
        }

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No property data found for the given address',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Property data fetched successfully',
            'data' => [
                'address' => $this->enrichmentService->formatAddress($address, $city, $state, $zipCode),
                'property_data' => $data,
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('Property preview failed', [
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zipCode,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch property data',
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching property data',
        ], 500);
    }
}
    /**
 * Lookup property data by address
 */
#[OA\Get(
    path: "/wholesaler/properties/search/address",
    summary: "Lookup property by address",
    description: "Quick lookup of property data. Accepts either full address string OR separate address components.",
    tags: ["Properties"],
    security: [["bearerAuth" => []]],
    parameters: [
        new OA\Parameter(
            name: "address",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "string"),
            example: "468 SEQUOIA DR, SMYRNA, DE 19977",
            description: "Full address string (alternative to using separate fields)"
        ),
        new OA\Parameter(
            name: "street",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "string"),
            example: "468 SEQUOIA DR"
        ),
        new OA\Parameter(
            name: "city",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "string"),
            example: "SMYRNA"
        ),
        new OA\Parameter(
            name: "state",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "string"),
            example: "DE"
        ),
        new OA\Parameter(
            name: "zip",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "string"),
            example: "19977"
        ),
        new OA\Parameter(
            name: "force_fresh",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "boolean", default: false)
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: "Property data found"),
        new OA\Response(response: 404, description: "Property not found"),
        new OA\Response(response: 422, description: "Validation error"),
    ]
)]
public function lookup(Request $request): JsonResponse
{
    $validated = $request->validate([
        'address' => 'required_without_all:street,city,state|string|max:500',
        'street' => 'required_without:address|string|max:255',
        'city' => 'nullable|string|max:100',
        'state' => 'nullable|string|max:2',
        'zip' => 'nullable|string|max:10',
        'force_fresh' => 'nullable|boolean',
    ]);

    $forceFresh = $validated['force_fresh'] ?? false;

    // If full address provided, parse it
    if (!empty($validated['address'])) {
        $addressParts = $this->parseAddress($validated['address']);
        $street = $addressParts['street'];
        $city = $addressParts['city'];
        $state = $addressParts['state'];
        $zip = $addressParts['zip'];
    } else {
        // Use separate components
        $street = $validated['street'];
        $city = $validated['city'] ?? null;
        $state = $validated['state'] ?? null;
        $zip = $validated['zip'] ?? null;
    }

    if (!$street) {
        return response()->json([
            'success' => false,
            'message' => 'Street address is required',
        ], 422);
    }

    try {
        $data = $this->enrichmentService->fetchAndExtractPropertyData(
            $street,
            $city,
            $state,
            $zip,
            $forceFresh
        );

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No property found for the given address',
                'searched_address' => [
                    'street' => $street,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                ],
            ], 404);
        }

        // Remove raw_data before returning
        unset($data['raw_data']);
        
        // Use PropertyPreviewResource to format data consistently with PropertyResource
        return response()->json([
            'success' => true,
            'message' => 'Property data found',
            'data' => new \App\Http\Resources\PropertyPreviewResource($data),
        ]);
    } catch (\Exception $e) {
        Log::error('Property lookup failed', [
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to lookup property',
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while looking up property',
        ], 500);
    }
}

    /**
     * Parse a full address string into components
     */
    protected function parseAddress(string $fullAddress): array
    {
        // Initialize result
        $result = [
            'street' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
        ];

        // Clean the address
        $fullAddress = trim($fullAddress);

        // Try to extract ZIP code (5 digits or 5+4 format)
        if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $fullAddress, $zipMatch)) {
            $result['zip'] = $zipMatch[1];
            $fullAddress = str_replace($zipMatch[0], '', $fullAddress);
        }

        // Split by comma
        $parts = array_map('trim', explode(',', $fullAddress));
        $parts = array_filter($parts); // Remove empty parts

        if (count($parts) >= 3) {
            // Format: "Street, City, State"
            $result['street'] = $parts[0];
            $result['city'] = $parts[1];

            // Last part might be "State ZIP" or just "State"
            $lastPart = $parts[2];

            // Extract state (2 letter code)
            if (preg_match('/\b([A-Z]{2})\b/', strtoupper($lastPart), $stateMatch)) {
                $result['state'] = $stateMatch[1];
            }
        } elseif (count($parts) === 2) {
            // Format: "Street, City State" or "Street, State"
            $result['street'] = $parts[0];

            // Try to parse "City State" from second part
            $secondPart = $parts[1];
            if (preg_match('/^(.+?)\s+([A-Z]{2})$/i', $secondPart, $cityStateMatch)) {
                $result['city'] = trim($cityStateMatch[1]);
                $result['state'] = strtoupper($cityStateMatch[2]);
            } else {
                // Assume it's just city
                $result['city'] = $secondPart;
            }
        } elseif (count($parts) === 1) {
            // Only street address provided
            $result['street'] = $parts[0];
        }

        return $result;
    }

    /**
     * Get properties matching the authenticated investor's buy box
     */
    #[OA\Get(
        path: "/investor/properties/matches",
        summary: "Get properties matching buy box",
        description: "Get properties that match the authenticated investor's buy box criteria. Only investors can access this endpoint.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of matching properties"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Investor access required"),
        ]
    )]
    public function matches(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }
        
        // Check if user is investor or admin
        if (!$user->hasRole('investor') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only investors can view buy box matches.',
            ], 403);
        }
        
        // Get or create buy box for user
        $buyBox = $this->buyBoxService->getOrCreate($user);
        
        // Get matching properties
        $perPage = $request->get('per_page', 15);
        $properties = $this->buyBoxService->findMatchingProperties($buyBox, $perPage);
        
        return response()->json([
            'success' => true,
            'data' => new PropertyCollection($properties),
        ]);
    }

    /**
     * Get properties created by the authenticated wholesaler
     */
    #[OA\Get(
        path: "/wholesaler/properties/my",
        summary: "List my properties",
        description: "List properties created by the authenticated wholesaler. Requires authentication and wholesaler role.",
        tags: ["Properties"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of wholesaler's properties"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Wholesaler access required"),
        ]
    )]
    public function my(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Check if user is wholesaler or admin
        if (!$user->hasRole('wholesaler') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only wholesalers can view their properties.',
            ], 403);
        }

        $perPage = (int) $request->get('per_page', 15);

        $query = Property::with(['wholesaler', 'images', 'primaryImage'])
            ->where('wholesaler_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $properties = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => new PropertyCollection($properties),
        ]);
    }

    /**
     * Get similar properties to the specified property
     */
    #[OA\Get(
        path: "/properties/{id}/similar",
        summary: "Get similar properties",
        description: "Get properties similar to the specified property based on location, property type, price range, bedrooms, bathrooms, and square feet. Public endpoint.",
        tags: ["Properties"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid"),
                example: "550e8400-e29b-41d4-a716-446655440000"
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10),
                description: "Number of similar properties to return"
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of similar properties"),
            new OA\Response(response: 404, description: "Property not found"),
        ]
    )]
    public function similar(Request $request, Property $property): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        
        // Find similar properties
        $similarProperties = $this->propertyService->findSimilarProperties($property, $perPage);
        
        return response()->json([
            'success' => true,
            'data' => new PropertyCollection($similarProperties),
        ]);
    }

    /**
     * Get wholesaler investor profiles matching the specified property
     */
    public function investorMatches(Request $request, Property $property): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Only the wholesaler who owns the property or admin can view matches
        if (!$user->hasRole('admin') && $property->wholesaler_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You cannot view investor matches for this property.',
            ], 403);
        }

        $perPage = (int) $request->get('per_page', 15);

        $query = WholesalerInvestorProfile::where('wholesaler_id', $property->wholesaler_id);

        // City filter
        if ($property->city) {
            $query->where(function ($q) use ($property) {
                $q->whereNull('preferred_cities')
                    ->orWhereJsonContains('preferred_cities', $property->city);
            });
        }

        // ZIP code filter
        if ($property->zip_code) {
            $query->where(function ($q) use ($property) {
                $q->whereNull('preferred_zip_codes')
                    ->orWhereJsonContains('preferred_zip_codes', $property->zip_code);
            });
        }

        // Property type filter (reuse mapping from BuyBoxService)
        if ($property->property_type) {
            $typeMap = [
                'house' => 'Single-Family',
                'apartment' => 'Multifamily',
                'land' => 'Land',
                'other' => 'Commercial',
            ];

            $buyBoxType = $typeMap[$property->property_type] ?? null;

            if ($buyBoxType) {
                $query->where(function ($q) use ($buyBoxType) {
                    $q->whereNull('property_types')
                        ->orWhereJsonContains('property_types', $buyBoxType);
                });
            }
        }

        // Bedrooms filter
        if ($property->bedrooms !== null) {
            $query->where(function ($q) use ($property) {
                $q->whereNull('min_bedrooms')
                    ->orWhere('min_bedrooms', '<=', $property->bedrooms);
            });

            $query->where(function ($q) use ($property) {
                $q->whereNull('max_bedrooms')
                    ->orWhere('max_bedrooms', '>=', $property->bedrooms);
            });
        }

        // Bathrooms filter
        if ($property->bathrooms !== null) {
            $query->where(function ($q) use ($property) {
                $q->whereNull('min_bathrooms')
                    ->orWhere('min_bathrooms', '<=', $property->bathrooms);
            });

            $query->where(function ($q) use ($property) {
                $q->whereNull('max_bathrooms')
                    ->orWhere('max_bathrooms', '>=', $property->bathrooms);
            });
        }

        // Square feet filter
        if ($property->square_feet !== null) {
            $query->where(function ($q) use ($property) {
                $q->whereNull('min_square_feet')
                    ->orWhere('min_square_feet', '<=', $property->square_feet);
            });

            $query->where(function ($q) use ($property) {
                $q->whereNull('max_square_feet')
                    ->orWhere('max_square_feet', '>=', $property->square_feet);
            });
        }

        // Lot size filter
        if ($property->lot_size !== null) {
            $query->where(function ($q) use ($property) {
                $q->whereNull('min_lot_size')
                    ->orWhere('min_lot_size', '<=', $property->lot_size);
            });

            $query->where(function ($q) use ($property) {
                $q->whereNull('max_lot_size')
                    ->orWhere('max_lot_size', '>=', $property->lot_size);
            });
        }

        // Property condition filter
        if ($property->condition) {
            $conditionMap = [
                'excellent' => 'Turnkey',
                'good' => 'Retail Ready',
                'fair' => 'Rental Ready',
            ];

            $buyBoxCondition = $conditionMap[$property->condition] ?? null;

            if ($buyBoxCondition) {
                $query->where(function ($q) use ($buyBoxCondition) {
                    $q->whereNull('property_conditions')
                        ->orWhereJsonContains('property_conditions', $buyBoxCondition);
                });
            }
        }

        // Profit filter based on property's potential profit or derived from arv - asking_price - repair_estimate
        $propertyProfit = $property->potential_profit ?? ($property->arv && $property->asking_price && $property->repair_estimate 
            ? ($property->arv - $property->asking_price - $property->repair_estimate) 
            : null);

        if ($propertyProfit !== null) {
            $query->where(function ($q) use ($propertyProfit) {
                $q->whereNull('min_profit')
                    ->orWhere('min_profit', '<=', $propertyProfit);
            });
        }

        $profiles = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WholesalerInvestorProfileResource::collection($profiles),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
        ]);
    }

    /**
     * Check if the authenticated user can modify the property
     */
    protected function canModifyProperty(Property $property): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->hasRole('admin') || $property->wholesaler_id === $user->id;
    }
}
