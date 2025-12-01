<?php

namespace App\Http\Controllers;

use App\Http\Resources\WaitingListEntryResource;
use App\Models\WaitingListEntry;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Services\WaitingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Waiting List")]
class WaitingListController extends Controller
{
    protected WaitingListService $waitingListService;
    protected CouponService $couponService;

    public function __construct(WaitingListService $waitingListService, CouponService $couponService)
    {
        $this->waitingListService = $waitingListService;
        $this->couponService = $couponService;
    }

    /**
     * Validate coupon code
     */
    #[OA\Post(
        path: "/api/v1/waiting-list/validate-coupon",
        summary: "Validate coupon code",
        description: "Validate a coupon code for future use",
        tags: ["Waiting List"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["code"],
                properties: [
                    new OA\Property(property: "code", type: "string", example: "EARLYBIRD50", description: "Coupon code"),
                    new OA\Property(property: "email", type: "string", format: "email", description: "User email (optional, for user limit checking)"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Coupon validated successfully"),
            new OA\Response(response: 400, description: "Validation error or invalid coupon"),
        ]
    )]
    public function validateCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'email' => 'sometimes|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $coupon = Coupon::where('code', strtoupper($request->input('code')))->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'error' => 'Coupon code not found',
            ], 400);
        }

        // Check if coupon is active
        if (!$coupon->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'This coupon is no longer active',
            ], 400);
        }

        // Check validity (dates, usage limits)
        if (!$coupon->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'This coupon has expired or reached its usage limit',
            ], 400);
        }

        // Check user usage limit if email provided
        if ($request->has('email') && $coupon->hasReachedUserLimit($request->input('email'))) {
            return response()->json([
                'success' => false,
                'error' => 'You have already used this coupon the maximum number of times',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'description' => $coupon->description,
                ],
            ],
        ]);
    }

    /**
     * Register for waiting list
     */
    #[OA\Post(
        path: "/api/v1/waiting-list/register",
        summary: "Register for waiting list",
        description: "Register email and name for waiting list. Account will be created when platform launches.",
        tags: ["Waiting List"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "name"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", description: "User email"),
                    new OA\Property(property: "name", type: "string", description: "User name"),
                    new OA\Property(property: "coupon_code", type: "string", description: "Optional coupon code for future use"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Registration successful"),
            new OA\Response(response: 400, description: "Validation error"),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:255',
            'coupon_code' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $result = $this->waitingListService->register($request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        // Load the entry with relationships
        $entry = WaitingListEntry::with(['coupon'])->find($result['data']['id']);

        return response()->json([
            'success' => true,
            'message' => 'Successfully registered for waiting list',
            'data' => new WaitingListEntryResource($entry),
        ]);
    }

    /**
     * Get waiting list entry status
     */
    #[OA\Get(
        path: "/api/v1/waiting-list/status",
        summary: "Get waiting list status",
        description: "Get registration status by email and verification token",
        tags: ["Waiting List"],
        parameters: [
            new OA\Parameter(name: "email", in: "query", required: true, schema: new OA\Schema(type: "string", format: "email")),
            new OA\Parameter(name: "token", in: "query", required: true, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Status retrieved successfully"),
            new OA\Response(response: 404, description: "Entry not found"),
        ]
    )]
    public function status(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $entry = $this->waitingListService->getEntryByToken(
            $request->input('email'),
            $request->input('token')
        );

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Waiting list entry not found',
            ], 404);
        }

        $entry->load(['coupon']);

        return response()->json([
            'success' => true,
            'data' => new WaitingListEntryResource($entry),
        ]);
    }
}
