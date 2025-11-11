<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\CouponService;
use App\Services\WaitingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * Get available subscription plans for waiting list
     */
    #[OA\Get(
        path: "/api/v1/waiting-list/plans",
        summary: "Get available subscription plans",
        description: "Get all active subscription plans available for waiting list registration",
        tags: ["Waiting List"],
        responses: [
            new OA\Response(response: 200, description: "Plans retrieved successfully"),
        ]
    )]
    public function plans(): AnonymousResourceCollection
    {
        $plans = SubscriptionPlan::active()->get();

        return SubscriptionPlanResource::collection($plans);
    }

    /**
     * Validate coupon code
     */
    #[OA\Post(
        path: "/api/v1/waiting-list/validate-coupon",
        summary: "Validate coupon code",
        description: "Validate a coupon code and get discount information",
        tags: ["Waiting List"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["code", "plan_id"],
                properties: [
                    new OA\Property(property: "code", type: "string", example: "EARLYBIRD50", description: "Coupon code"),
                    new OA\Property(property: "plan_id", type: "string", format: "uuid", description: "Subscription plan UUID"),
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
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'email' => 'sometimes|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $result = $this->couponService->validateAndCalculate(
            $request->input('code'),
            $request->input('plan_id'),
            $request->input('email')
        );

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'coupon' => $result['coupon'],
                'original_price' => $result['original_price'],
                'discount_amount' => $result['discount_amount'],
                'discounted_price' => $result['discounted_price'],
            ],
        ]);
    }

    /**
     * Register for waiting list
     */
    #[OA\Post(
        path: "/api/v1/waiting-list/register",
        summary: "Register for waiting list",
        description: "Register email and subscription plan selection for waiting list",
        tags: ["Waiting List"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "name", "subscription_plan_id"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", description: "User email"),
                    new OA\Property(property: "name", type: "string", description: "User name"),
                    new OA\Property(property: "subscription_plan_id", type: "string", format: "uuid", description: "Subscription plan UUID"),
                    new OA\Property(property: "coupon_code", type: "string", description: "Optional coupon code"),
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
            'subscription_plan_id' => 'required|uuid|exists:subscription_plans,id',
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
        $entry = WaitingListEntry::with(['plan', 'coupon'])->find($result['data']['id']);

        return response()->json([
            'success' => true,
            'message' => 'Successfully registered for waiting list',
            'data' => new WaitingListEntryResource($entry),
        ]);
    }

    /**
     * Create checkout session
     */
    #[OA\Post(
        path: "/api/v1/waiting-list/checkout",
        summary: "Create checkout session",
        description: "Create Stripe checkout session for waiting list subscription purchase",
        tags: ["Waiting List"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "subscription_plan_id"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", description: "User email"),
                    new OA\Property(property: "subscription_plan_id", type: "string", format: "uuid", description: "Subscription plan UUID"),
                    new OA\Property(property: "coupon_code", type: "string", description: "Optional coupon code"),
                    new OA\Property(property: "success_url", type: "string", format: "url", description: "Success redirect URL"),
                    new OA\Property(property: "cancel_url", type: "string", format: "url", description: "Cancel redirect URL"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Checkout session created successfully"),
            new OA\Response(response: 400, description: "Validation error"),
        ]
    )]
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'subscription_plan_id' => 'required|uuid|exists:subscription_plans,id',
            'coupon_code' => 'sometimes|string|max:50',
            'success_url' => 'sometimes|url',
            'cancel_url' => 'sometimes|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        // Find or create waiting list entry
        $entry = \App\Models\WaitingListEntry::where('email', $request->input('email'))->first();

        if (!$entry) {
            // Register first if not exists
            $registerResult = $this->waitingListService->register([
                'email' => $request->input('email'),
                'name' => $request->input('name', 'User'), // Default name if not provided
                'subscription_plan_id' => $request->input('subscription_plan_id'),
                'coupon_code' => $request->input('coupon_code'),
            ]);

            if (!$registerResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $registerResult['error'],
                ], 400);
            }

            $entry = \App\Models\WaitingListEntry::find($registerResult['data']['id']);
        } else {
            // Validate coupon if provided and different from existing
            if ($request->has('coupon_code') && $entry->coupon_code !== $request->input('coupon_code')) {
                $validation = $this->couponService->validateAndCalculate(
                    $request->input('coupon_code'),
                    $request->input('subscription_plan_id'),
                    $request->input('email')
                );

                if (!$validation['valid']) {
                    return response()->json([
                        'success' => false,
                        'error' => $validation['error'],
                    ], 400);
                }

                // Update entry with new coupon
                $coupon = \App\Models\Coupon::find($validation['coupon']['id']);
                $entry->update([
                    'coupon_id' => $coupon->id,
                    'coupon_code' => $request->input('coupon_code'),
                    'original_price' => $validation['original_price'],
                    'discounted_price' => $validation['discounted_price'],
                    'discount_amount' => $validation['discount_amount'],
                ]);
            }
        }

        // Create checkout session
        $result = $this->waitingListService->createCheckoutSession($entry, [
            'success_url' => $request->input('success_url'),
            'cancel_url' => $request->input('cancel_url'),
        ]);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
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

        $entry->load(['plan', 'coupon']);

        return new WaitingListEntryResource($entry);
    }
}
