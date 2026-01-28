<?php

namespace App\Http\Controllers;

use App\Http\Resources\PropertyCollection;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Wishlist")]
class WishlistController extends Controller
{
    /**
     * Get authenticated user's wishlist
     */
    #[OA\Get(
        path: "/wishlist",
        summary: "Get user's wishlist",
        description: "Get all properties in the authenticated user's wishlist. Only investors can access this endpoint.",
        tags: ["Wishlist"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Wishlist retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        $perPage = $request->get('per_page', 15);
        
        // Get wishlisted properties
        $properties = $user->wishlistedProperties()
            ->with(['wholesaler', 'images', 'primaryImage'])
            ->orderBy('wishlists.created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => new PropertyCollection($properties),
        ]);
    }

    /**
     * Add property to wishlist
     */
    #[OA\Post(
        path: "/wishlist/{property}",
        summary: "Add property to wishlist",
        description: "Add a property to the authenticated user's wishlist. Only investors can access this endpoint.",
        tags: ["Wishlist"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "property",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property added to wishlist"),
            new OA\Response(response: 201, description: "Property added to wishlist"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Property not found"),
            new OA\Response(response: 409, description: "Property already in wishlist"),
        ]
    )]
    public function store(Request $request, Property $property): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        // Check if already in wishlist
        $exists = Wishlist::where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Property is already in your wishlist',
            ], 409);
        }
        
        // Add to wishlist
        Wishlist::create([
            'user_id' => $user->id,
            'property_id' => $property->id,
        ]);
        
        $property->load(['wholesaler', 'images', 'primaryImage']);
        
        return response()->json([
            'success' => true,
            'message' => 'Property added to wishlist',
            'data' => new PropertyResource($property),
        ], 201);
    }

    /**
     * Remove property from wishlist
     */
    #[OA\Delete(
        path: "/wishlist/{property}",
        summary: "Remove property from wishlist",
        description: "Remove a property from the authenticated user's wishlist. Only investors can access this endpoint.",
        tags: ["Wishlist"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "property",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property removed from wishlist"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Property not found in wishlist"),
        ]
    )]
    public function destroy(Request $request, Property $property): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        $wishlistItem = Wishlist::where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->first();
        
        if (!$wishlistItem) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found in your wishlist',
            ], 404);
        }
        
        $wishlistItem->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Property removed from wishlist',
        ]);
    }

    /**
     * Check if property is in wishlist
     */
    #[OA\Get(
        path: "/wishlist/{property}/check",
        summary: "Check if property is in wishlist",
        description: "Check if a property is in the authenticated user's wishlist.",
        tags: ["Wishlist"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "property",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Check result"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function check(Request $request, Property $property): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        $inWishlist = Wishlist::where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->exists();
        
        return response()->json([
            'success' => true,
            'in_wishlist' => $inWishlist,
        ]);
    }
}
