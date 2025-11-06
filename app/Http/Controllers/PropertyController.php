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

class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService,
        protected PropertyEnrichmentService $enrichmentService
    ) {}

    /**
     * Display a listing of properties with filters
     */
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
    public function destroy(Property $property): JsonResponse
    {
        // Check authorization
        $user = auth()->user();
        if (!$user->hasRole('admin') && $property->wholesaler_id !== $user->id) {
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
    public function uploadImages(Request $request, Property $property): JsonResponse
    {
        // Check authorization
        $user = auth()->user();
        if (!$user->hasRole('admin') && $property->wholesaler_id !== $user->id) {
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
    public function deleteImage(Property $property, PropertyImage $image): JsonResponse
    {
        // Verify image belongs to property
        if ($image->property_id !== $property->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image does not belong to this property',
            ], 404);
        }

        // Check authorization
        $user = auth()->user();
        if (!$user->hasRole('admin') && $property->wholesaler_id !== $user->id) {
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
    public function setPrimaryImage(Property $property, PropertyImage $image): JsonResponse
    {
        // Verify image belongs to property
        if ($image->property_id !== $property->id) {
            return response()->json([
                'success' => false,
                'message' => 'Image does not belong to this property',
            ], 404);
        }

        // Check authorization
        $user = auth()->user();
        if (!$user->hasRole('admin') && $property->wholesaler_id !== $user->id) {
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
        // Check authorization
        $user = auth()->user();
        if (!$user->hasRole('admin') && $property->wholesaler_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to enrich this property',
            ], 403);
        }

        $request->validate([
            'sync' => 'nullable|boolean', // If true, enrich synchronously
        ]);

        $sync = $request->boolean('sync', false);

        if ($sync) {
            // Synchronous enrichment
            $property = $this->enrichmentService->performEnrichment($property);
            
            return response()->json([
                'success' => true,
                'message' => 'Property enriched successfully',
                'data' => new PropertyResource($property->load('images', 'wholesaler')),
            ]);
        } else {
            // Asynchronous enrichment (queue)
            $this->enrichmentService->enrichProperty($property, true);
            
            return response()->json([
                'success' => true,
                'message' => 'Property enrichment queued. Data will be updated shortly.',
                'data' => [
                    'property_id' => $property->id,
                    'status' => 'queued',
                ],
            ], 202);
        }
    }
}
