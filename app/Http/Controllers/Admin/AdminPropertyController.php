<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Property Management")]
class AdminPropertyController extends Controller
{
    /**
     * List all properties
     */
    #[OA\Get(
        path: "/admin/properties",
        summary: "List all properties (Admin only)",
        description: "Get a paginated list of all properties with optional filtering by status, wholesaler, city, state, and search",
        tags: ["Admin - Property Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter by status",
                schema: new OA\Schema(type: "string", enum: ["active", "pending", "sold"])
            ),
            new OA\Parameter(
                name: "wholesaler_id",
                in: "query",
                description: "Filter by wholesaler UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "city",
                in: "query",
                description: "Filter by city",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "state",
                in: "query",
                description: "Filter by state",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Search in title, address, or description",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "sort_by",
                in: "query",
                description: "Sort field",
                schema: new OA\Schema(type: "string", default: "created_at")
            ),
            new OA\Parameter(
                name: "sort_order",
                in: "query",
                description: "Sort order",
                schema: new OA\Schema(type: "string", enum: ["asc", "desc"], default: "desc")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Items per page (max 100)",
                schema: new OA\Schema(type: "integer", default: 15, maximum: 100)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Properties retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Property::with(['wholesaler', 'images']);

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if (in_array($status, ['active', 'pending', 'sold'])) {
                $query->where('status', $status);
            }
        }

        // Filter by wholesaler
        if ($request->has('wholesaler_id')) {
            $query->where('wholesaler_id', $request->get('wholesaler_id'));
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->get('city') . '%');
        }

        // Filter by state
        if ($request->has('state')) {
            $query->where('state', $request->get('state'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $properties = $query->paginate($perPage);

        return PropertyResource::collection($properties)->additional([
            'pagination' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ],
        ]);
    }

    /**
     * Get property details
     */
    #[OA\Get(
        path: "/admin/properties/{id}",
        summary: "Get property details (Admin only)",
        description: "Get detailed information about a specific property including wholesaler, images, analytics, and rehab estimates",
        tags: ["Admin - Property Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Property UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property details retrieved successfully"),
            new OA\Response(response: 404, description: "Property not found"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function show(Property $property): JsonResponse
    {
        $property->load(['wholesaler', 'images', 'analytics', 'rehabEstimates']);

        return response()->json([
            'success' => true,
            'data' => new PropertyResource($property),
        ]);
    }

    /**
     * Update property
     */
    #[OA\Put(
        path: "/admin/properties/{id}",
        summary: "Update property (Admin only)",
        description: "Update property information including title, description, status, featured status, and verification",
        tags: ["Admin - Property Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Property UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", maxLength: 255),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "status", type: "string", enum: ["active", "pending", "sold"]),
                    new OA\Property(property: "is_featured", type: "boolean"),
                    new OA\Property(property: "is_verified", type: "boolean"),
                    new OA\Property(property: "allow_inquiries", type: "boolean"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Property updated successfully"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function update(Request $request, Property $property): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|string|in:active,pending,sold',
            'is_featured' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
            'allow_inquiries' => 'sometimes|boolean',
        ]);

        $property->update($validated);

        $property->load(['wholesaler', 'images']);

        return response()->json([
            'success' => true,
            'message' => 'Property updated successfully',
            'data' => new PropertyResource($property),
        ]);
    }

    /**
     * Delete property
     */
    #[OA\Delete(
        path: "/admin/properties/{id}",
        summary: "Delete property (Admin only)",
        description: "Permanently delete a property",
        tags: ["Admin - Property Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Property UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property deleted successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function destroy(Property $property): JsonResponse
    {
        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'Property deleted successfully',
        ]);
    }

    /**
     * Approve property
     */
    #[OA\Post(
        path: "/admin/properties/{id}/approve",
        summary: "Approve property (Admin only)",
        description: "Approve a property by setting status to active and marking as verified",
        tags: ["Admin - Property Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Property UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property approved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function approve(Property $property): JsonResponse
    {
        $property->update([
            'status' => 'active',
            'is_verified' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Property approved successfully',
            'data' => new PropertyResource($property->load(['wholesaler', 'images'])),
        ]);
    }

    /**
     * Feature/unfeature property
     */
    #[OA\Post(
        path: "/admin/properties/{id}/feature",
        summary: "Feature or unfeature property (Admin only)",
        description: "Mark a property as featured or remove featured status",
        tags: ["Admin - Property Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Property UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["featured"],
                properties: [
                    new OA\Property(property: "featured", type: "boolean", description: "Set to true to feature, false to unfeature"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Property featured status updated successfully"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function feature(Request $request, Property $property): JsonResponse
    {
        $request->validate([
            'featured' => 'required|boolean',
        ]);

        $property->update([
            'is_featured' => $request->boolean('featured'),
        ]);

        return response()->json([
            'success' => true,
            'message' => $request->boolean('featured') ? 'Property featured successfully' : 'Property unfeatured successfully',
            'data' => new PropertyResource($property->load(['wholesaler', 'images'])),
        ]);
    }

    /**
     * Verify property
     */
    #[OA\Post(
        path: "/admin/properties/{id}/verify",
        summary: "Verify property (Admin only)",
        description: "Mark a property as verified",
        tags: ["Admin - Property Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Property UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property verified successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function verify(Property $property): JsonResponse
    {
        $property->update([
            'is_verified' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Property verified successfully',
            'data' => new PropertyResource($property->load(['wholesaler', 'images'])),
        ]);
    }
}
