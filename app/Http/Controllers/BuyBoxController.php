<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBuyBoxRequest;
use App\Http\Resources\BuyBoxResource;
use App\Services\BuyBoxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Buy Box")]
class BuyBoxController extends Controller
{
    public function __construct(
        protected BuyBoxService $buyBoxService
    ) {}

    /**
     * Display the authenticated user's buy box
     */
    #[OA\Get(
        path: "/buy-box",
        summary: "Get user's buy box",
        description: "Get the authenticated user's buy box preferences. Creates an empty buy box if one doesn't exist. Only investors can access buy boxes.",
        tags: ["Buy Box"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Buy box retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Investor access required"),
        ]
    )]
    public function show(Request $request): JsonResponse
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
                'message' => 'Access denied. Only investors can manage buy boxes.',
            ], 403);
        }
        
        $buyBox = $this->buyBoxService->getOrCreate($user);

        return response()->json([
            'success' => true,
            'data' => new BuyBoxResource($buyBox),
        ]);
    }

    /**
     * Update the authenticated user's buy box
     */
    #[OA\Put(
        path: "/buy-box",
        summary: "Update user's buy box",
        description: "Create or update the authenticated user's buy box preferences. All fields are optional. Only investors can manage buy boxes.",
        tags: ["Buy Box"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "preferred_cities", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "preferred_zip_codes", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "target_counties", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "target_neighborhoods", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "must_have_amenities", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "min_bedrooms", type: "integer"),
                        new OA\Property(property: "max_bedrooms", type: "integer"),
                        new OA\Property(property: "min_bathrooms", type: "number"),
                        new OA\Property(property: "max_bathrooms", type: "number"),
                        new OA\Property(property: "min_square_feet", type: "integer"),
                        new OA\Property(property: "max_square_feet", type: "integer"),
                        new OA\Property(property: "min_lot_size", type: "integer"),
                        new OA\Property(property: "max_lot_size", type: "integer"),
                        new OA\Property(property: "property_conditions", type: "array", items: new OA\Items(type: "string", enum: ["Turnkey", "Retail Ready", "Rental Ready"])),
                        new OA\Property(property: "property_types", type: "array", items: new OA\Items(type: "string", enum: ["Single-Family", "Land", "Multifamily", "Commercial"])),
                        new OA\Property(property: "has_adu_potential", type: "boolean"),
                        new OA\Property(property: "construction_types", type: "array", items: new OA\Items(type: "string", enum: ["Brick Built", "Stick Built", "Block Built", "Stucco Exterior", "Other"])),
                        new OA\Property(property: "has_pool", type: "boolean"),
                        new OA\Property(property: "is_waterfront", type: "boolean"),
                        new OA\Property(property: "layout_types", type: "array", items: new OA\Items(type: "string", enum: ["Open floor plan", "Traditional", "Custom"])),
                        new OA\Property(property: "funding_methods", type: "array", items: new OA\Items(type: "string", enum: ["Cash", "Hard money", "Private money", "DSCR loans", "Conventional mortgages", "FHA loans", "VA loans"])),
                        new OA\Property(property: "min_profit", type: "number"),
                        new OA\Property(property: "min_roi", type: "number"),
                        new OA\Property(property: "target_cap_rate", type: "number"),
                        new OA\Property(property: "desired_occupancy_rate", type: "number"),
                        new OA\Property(property: "expected_monthly_cash_flow", type: "number"),
                        new OA\Property(property: "expected_annual_cash_flow", type: "number"),
                        new OA\Property(property: "investment_strategies", type: "array", items: new OA\Items(type: "string", enum: ["Fix and Flip", "Short-Term Rental", "Mid-Term Rental", "Long-Term Rental", "Lease Option", "House Hacking"])),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Buy box updated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Investor access required"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(UpdateBuyBoxRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }
        
        // Check if user is investor or admin (authorize() in request also checks, but this provides better error handling)
        if (!$user->hasRole('investor') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only investors can manage buy boxes.',
            ], 403);
        }
        
        $buyBox = $this->buyBoxService->getOrCreate($user);
        $buyBox = $this->buyBoxService->update($buyBox, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Buy box updated successfully',
            'data' => new BuyBoxResource($buyBox),
        ]);
    }
}
