<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPropertyController extends Controller
{
    /**
     * List all properties
     */
    public function index(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'data' => PropertyResource::collection($properties->items()),
            'meta' => [
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
