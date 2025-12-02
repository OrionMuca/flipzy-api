<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Coupon Management")]
class AdminCouponController extends Controller
{
    /**
     * List all coupons
     */
    #[OA\Get(
        path: "/admin/coupons",
        summary: "List all coupons (Admin only)",
        description: "Get a paginated list of all coupons",
        tags: ["Admin - Coupon Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Coupons retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Coupon::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $coupons = $query->paginate($perPage);

        return CouponResource::collection($coupons)->additional([
            'pagination' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get coupon details
     */
    #[OA\Get(
        path: "/admin/coupons/{id}",
        summary: "Get coupon details (Admin only)",
        tags: ["Admin - Coupon Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Coupon retrieved successfully"),
            new OA\Response(response: 404, description: "Coupon not found"),
        ]
    )]
    public function show(Request $request, string $id): CouponResource
    {
        $coupon = Coupon::with('waitingListEntries')->findOrFail($id);

        return new CouponResource($coupon);
    }

    /**
     * Create new coupon
     */
    #[OA\Post(
        path: "/admin/coupons",
        summary: "Create new coupon (Admin only)",
        tags: ["Admin - Coupon Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Coupon created successfully"),
            new OA\Response(response: 400, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|nullable',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'minimum_amount' => 'sometimes|numeric|min:0|nullable',
            'maximum_discount' => 'sometimes|numeric|min:0|nullable',
            'valid_from' => 'required|date',
            'valid_until' => 'sometimes|date|nullable|after:valid_from',
            'usage_limit' => 'sometimes|integer|min:1|nullable',
            'user_limit' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'applicable_plans' => 'sometimes|array',
            'applicable_plans.*' => 'uuid|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        // Validate discount_value based on type
        if ($request->input('discount_type') === 'percentage' && $request->input('discount_value') > 100) {
            return response()->json([
                'success' => false,
                'errors' => ['discount_value' => 'Percentage discount cannot exceed 100%'],
            ], 400);
        }

        $coupon = Coupon::create([
            'code' => strtoupper($request->input('code')),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'discount_type' => $request->input('discount_type'),
            'discount_value' => $request->input('discount_value'),
            'minimum_amount' => $request->input('minimum_amount'),
            'maximum_discount' => $request->input('maximum_discount'),
            'valid_from' => $request->input('valid_from'),
            'valid_until' => $request->input('valid_until'),
            'usage_limit' => $request->input('usage_limit'),
            'user_limit' => $request->input('user_limit', 1),
            'is_active' => $request->boolean('is_active', true),
            'applicable_plans' => $request->input('applicable_plans'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => new CouponResource($coupon),
        ], 201);
    }

    /**
     * Update coupon
     */
    #[OA\Put(
        path: "/admin/coupons/{id}",
        summary: "Update coupon (Admin only)",
        tags: ["Admin - Coupon Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Coupon updated successfully"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 404, description: "Coupon not found"),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:50|unique:coupons,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'discount_type' => 'sometimes|in:percentage,fixed_amount',
            'discount_value' => 'sometimes|numeric|min:0',
            'minimum_amount' => 'sometimes|numeric|min:0|nullable',
            'maximum_discount' => 'sometimes|numeric|min:0|nullable',
            'valid_from' => 'sometimes|date',
            'valid_until' => 'sometimes|date|nullable|after:valid_from',
            'usage_limit' => 'sometimes|integer|min:1|nullable',
            'user_limit' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'applicable_plans' => 'sometimes|array',
            'applicable_plans.*' => 'uuid|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        // Validate discount_value if provided
        if ($request->has('discount_type') || $request->has('discount_value')) {
            $discountType = $request->input('discount_type', $coupon->discount_type);
            $discountValue = $request->input('discount_value', $coupon->discount_value);
            
            if ($discountType === 'percentage' && $discountValue > 100) {
                return response()->json([
                    'success' => false,
                    'errors' => ['discount_value' => 'Percentage discount cannot exceed 100%'],
                ], 400);
            }
        }

        $coupon->update($request->only([
            'code', 'name', 'description', 'discount_type', 'discount_value',
            'minimum_amount', 'maximum_discount', 'valid_from', 'valid_until',
            'usage_limit', 'user_limit', 'is_active', 'applicable_plans',
        ]));

        if ($request->has('code')) {
            $coupon->update(['code' => strtoupper($request->input('code'))]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => new CouponResource($coupon),
        ]);
    }

    /**
     * Delete/Deactivate coupon
     */
    #[OA\Delete(
        path: "/admin/coupons/{id}",
        summary: "Delete coupon (Admin only)",
        tags: ["Admin - Coupon Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Coupon deleted successfully"),
            new OA\Response(response: 404, description: "Coupon not found"),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        
        // Soft delete by deactivating
        $coupon->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon deactivated successfully',
        ]);
    }
}
