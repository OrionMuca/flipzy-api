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
            new OA\Response(
                response: 200,
                description: "Coupon validated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "coupon",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "string", format: "uuid"),
                                        new OA\Property(property: "code", type: "string", example: "EARLYBIRD50"),
                                        new OA\Property(property: "name", type: "string"),
                                        new OA\Property(property: "description", type: "string", nullable: true),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Validation error or invalid coupon",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            description: "Validation errors (when validation fails)",
                            nullable: true
                        ),
                        new OA\Property(
                            property: "error",
                            type: "string",
                            description: "Error message (when coupon is invalid)",
                            example: "Coupon code not found",
                            nullable: true
                        ),
                    ]
                )
            ),
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
                required: ["email", "name", "phone_number"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", description: "User email"),
                    new OA\Property(property: "name", type: "string", description: "User name"),
                    new OA\Property(property: "phone_number", type: "string", description: "User phone number"),
                    new OA\Property(property: "company_name", type: "string", description: "Company name (optional)"),
                    new OA\Property(property: "selected_roles", type: "array", description: "Selected roles (optional)", items: new OA\Items(type: "string", enum: ["wholesaler", "investor"])),
                    new OA\Property(property: "coupon_code", type: "string", description: "Optional coupon code for future use"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Registration successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Successfully registered for waiting list"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "string", format: "uuid"),
                                new OA\Property(property: "email", type: "string", format: "email"),
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "phone_number", type: "string", nullable: true),
                                new OA\Property(property: "company_name", type: "string", nullable: true),
                                new OA\Property(property: "selected_roles", type: "array", nullable: true, items: new OA\Items(type: "string", enum: ["wholesaler", "investor"])),
                                new OA\Property(property: "status", type: "string", enum: ["pending", "account_created", "cancelled"]),
                                new OA\Property(property: "coupon_code", type: "string", nullable: true),
                                new OA\Property(property: "email_verified", type: "boolean"),
                                new OA\Property(property: "email_verified_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "account_created", type: "boolean"),
                                new OA\Property(property: "account_created_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Validation error or registration failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            description: "Validation errors (when validation fails)",
                            nullable: true
                        ),
                        new OA\Property(
                            property: "error",
                            type: "string",
                            description: "Error message (when registration fails)",
                            example: "This email is already registered on the waiting list",
                            nullable: true
                        ),
                    ]
                )
            ),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'selected_roles' => 'nullable|array',
            'selected_roles.*' => 'string|in:wholesaler,investor',
            'coupon_code' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        // Set default selected_roles to both roles if not provided
        $data = $request->all();
        if (empty($data['selected_roles']) || !is_array($data['selected_roles'])) {
            $data['selected_roles'] = ['wholesaler', 'investor'];
        }

        $result = $this->waitingListService->register($data);

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
        ], 201);
    }

    /**
     * Get waiting list entry status
     */
    #[OA\Get(
        path: "/api/v1/waiting-list/status",
        summary: "Get waiting list status",
        description: "Get registration status by email and verification token. This endpoint only retrieves status and does not verify the email.",
        tags: ["Waiting List"],
        parameters: [
            new OA\Parameter(name: "email", in: "query", required: true, schema: new OA\Schema(type: "string", format: "email")),
            new OA\Parameter(name: "token", in: "query", required: true, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Status retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "string", format: "uuid"),
                                new OA\Property(property: "email", type: "string", format: "email"),
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "phone_number", type: "string", nullable: true),
                                new OA\Property(property: "company_name", type: "string", nullable: true),
                                new OA\Property(property: "selected_roles", type: "array", nullable: true, items: new OA\Items(type: "string", enum: ["wholesaler", "investor"])),
                                new OA\Property(property: "status", type: "string", enum: ["pending", "account_created", "cancelled"]),
                                new OA\Property(property: "coupon_code", type: "string", nullable: true),
                                new OA\Property(property: "email_verified", type: "boolean"),
                                new OA\Property(property: "email_verified_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "account_created", type: "boolean"),
                                new OA\Property(property: "account_created_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Entry not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Waiting list entry not found"),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Validation error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
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

    /**
     * Verify email address
     */
    #[OA\Post(
        path: "/api/v1/waiting-list/verify-email",
        summary: "Verify email address",
        description: "Verify email address using token from email link. This will mark the email as verified.",
        tags: ["Waiting List"],
        parameters: [
            new OA\Parameter(name: "email", in: "query", required: true, schema: new OA\Schema(type: "string", format: "email")),
            new OA\Parameter(name: "token", in: "query", required: true, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Email verified successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Email verified successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "string", format: "uuid"),
                                new OA\Property(property: "email", type: "string", format: "email"),
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "phone_number", type: "string", nullable: true),
                                new OA\Property(property: "company_name", type: "string", nullable: true),
                                new OA\Property(property: "selected_roles", type: "array", nullable: true, items: new OA\Items(type: "string", enum: ["wholesaler", "investor"])),
                                new OA\Property(property: "status", type: "string", enum: ["pending", "account_created", "cancelled"]),
                                new OA\Property(property: "email_verified", type: "boolean", example: true),
                                new OA\Property(property: "email_verified_at", type: "string", format: "date-time"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Entry not found or invalid token",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Waiting list entry not found or invalid verification token"),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Validation error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function verifyEmail(Request $request): JsonResponse
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
                'message' => 'Waiting list entry not found or invalid verification token',
            ], 404);
        }

        // Check if already verified
        if ($entry->isEmailVerified()) {
            $entry->load(['coupon']);
            return response()->json([
                'success' => true,
                'message' => 'Email is already verified',
                'data' => new WaitingListEntryResource($entry),
            ]);
        }

        // Verify email
        $entry->markEmailAsVerified();
        $entry->load(['coupon']);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'data' => new WaitingListEntryResource($entry),
        ]);
    }
}
