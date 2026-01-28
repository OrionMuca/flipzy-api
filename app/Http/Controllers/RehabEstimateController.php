<?php

namespace App\Http\Controllers;

use App\Http\Resources\RehabEstimateResource;
use App\Models\Property;
use App\Models\PropertyRehabEstimate;
use App\Services\RehabEstimateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "AI Rehab Estimation")]
class RehabEstimateController extends Controller
{
    public function __construct(
        protected RehabEstimateService $rehabEstimateService
    ) {}

    /**
     * Generate rehab cost estimate for a property
     */
    #[OA\Post(
        path: "/properties/{id}/estimate",
        summary: "Generate AI rehab estimate",
        description: "Generate an AI-powered rehabilitation cost estimate for a property. Requires Premium/VIP subscription or admin role. Rate limited based on subscription tier.",
        tags: ["AI Rehab Estimation"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "force_refresh",
                in: "query",
                description: "Force new estimate (bypass cache)",
                schema: new OA\Schema(type: "boolean", default: false)
            ),
            new OA\Parameter(
                name: "model",
                in: "query",
                description: "OpenAI model to use",
                schema: new OA\Schema(type: "string", enum: ["gpt-3.5-turbo", "gpt-4", "gpt-4-turbo", "gpt-4o"], default: "gpt-3.5-turbo")
            ),
            new OA\Parameter(
                name: "use_calculations",
                in: "query",
                description: "Force calculation-based estimate instead of AI (useful when OpenAI API key is not available)",
                schema: new OA\Schema(type: "boolean", default: false)
            ),
        ],
        responses: [
            new OA\Response(response: 201, description: "Estimate generated successfully"),
            new OA\Response(response: 403, description: "Forbidden - Subscription required"),
            new OA\Response(response: 429, description: "Rate limit exceeded"),
            new OA\Response(response: 503, description: "Service unavailable - API key missing"),
        ]
    )]
    public function generateEstimate(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        // Check access: Admin OR (Premium/VIP subscription)
        if (!$this->canGenerateEstimate($user)) {
            return response()->json([
                'success' => false,
                'message' => 'AI rehab estimates are only available for Premium/VIP subscribers or administrators.',
            ], 403);
        }

        // Check rate limit (unless admin)
        if (!$user->hasRole('admin')) {
            $rateLimitKey = "rehab_estimate:{$user->id}";
            $maxAttempts = $this->getRateLimit($user);

            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'success' => false,
                    'message' => "Rate limit exceeded. Please try again in {$seconds} seconds.",
                ], 429);
            }

            RateLimiter::hit($rateLimitKey, 86400); // 24 hour window
        }

        // Check if API key is configured
        if (!$this->rehabEstimateService->isApiKeyConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'AI estimation service is currently unavailable. Please contact support.',
                'error_code' => 'API_KEY_MISSING',
            ], 503);
        }

        try {
            $options = [
                'model' => $request->input('model', config('services.openai.model', 'gpt-3.5-turbo')),
                'force_refresh' => $request->boolean('force_refresh', false),
                'use_calculations' => $request->boolean('use_calculations', false),
            ];

            $estimate = $this->rehabEstimateService->generateEstimate(
                $property,
                $user,
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Rehab estimate generated successfully',
                'data' => new RehabEstimateResource($estimate),
            ], 201);
        } catch (\RuntimeException $e) {
            // Handle API errors gracefully
            if (str_contains($e->getMessage(), 'API key')) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI estimation service is currently unavailable. Please contact support.',
                    'error_code' => 'API_KEY_MISSING',
                ], 503);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'ESTIMATE_GENERATION_FAILED',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Unexpected error generating rehab estimate', [
                'property_id' => $property->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while generating the estimate.',
                'error_code' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Get estimate history for a property
     */
    #[OA\Get(
        path: "/properties/{id}/estimates",
        summary: "Get estimate history",
        description: "Get history of all rehab estimates generated for a property.",
        tags: ["AI Rehab Estimation"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "limit",
                in: "query",
                schema: new OA\Schema(type: "integer", default: 10, maximum: 50)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Estimate history"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function getEstimateHistory(Request $request, Property $property): AnonymousResourceCollection
    {
        $limit = (int) $request->get('limit', 10);
        $estimates = $this->rehabEstimateService->getEstimateHistory($property, $limit);

        return RehabEstimateResource::collection($estimates);
    }

    /**
     * Get a specific estimate
     */
    #[OA\Get(
        path: "/estimates/{id}",
        summary: "Get estimate details",
        description: "Get detailed information about a specific rehab estimate. Only accessible by admin or the user who requested it.",
        tags: ["AI Rehab Estimation"],
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
            new OA\Response(response: 200, description: "Estimate details"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Not authorized"),
            new OA\Response(response: 404, description: "Estimate not found"),
        ]
    )]
    public function show(Request $request, PropertyRehabEstimate $estimate): JsonResponse
    {
        // Verify user has access (admin or estimate requester)
        $user = $request->user();

        if (!$user->hasRole('admin') && $estimate->requested_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized to view this estimate',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new RehabEstimateResource($estimate->load(['property', 'requestedBy'])),
        ]);
    }

    /**
     * Check if user can generate estimates
     */
    protected function canGenerateEstimate($user): bool
    {
        // Admin always has access
        if ($user->hasRole('admin')) {
            return true;
        }

        //TODO: Consider cases which roles will use this

        // Check subscription
//        $subscription = $user->subscription;
//        if (!$subscription || !$subscription->isActive()) {
//            return false;
//        }
//
//        $plan = $subscription->plan;
//        return $plan && $plan->has_ai_estimates;

        return true;
    }

    /**
     * Get rate limit for user based on subscription
     */
    protected function getRateLimit($user): int
    {
        // Admin has no rate limit (very high number)
        if ($user->hasRole('admin')) {
            return 10000;
        }

        $subscription = $user->subscription;
        if (!$subscription || !$subscription->isActive()) {
            return 0;
        }

        $planSlug = $subscription->plan->slug ?? 'free';

        return match ($planSlug) {
            'premium' => 10, // 10 estimates per day
            'vip' => 100, // 100 estimates per day (effectively unlimited)
            default => 0,
        };
    }

    /**
     * Generate rehab estimate for property preview (before property creation)
     */
    #[OA\Post(
        path: "/properties/preview/rehab-estimate",
        summary: "Generate preview rehab estimate",
        description: "Generate an AI-powered rehabilitation cost estimate for property data (from ATTOM lookup) before creating the property. Uses caching to prevent duplicate API calls. Requires Premium/VIP subscription or admin role.",
        tags: ["AI Rehab Estimation"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["property_data"],
                properties: [
                    new OA\Property(
                        property: "property_data",
                        type: "object",
                        description: "Property data from ATTOM lookup (same structure as PropertyPreviewResource)",
                        properties: [
                            new OA\Property(property: "address", type: "string"),
                            new OA\Property(property: "city", type: "string"),
                            new OA\Property(property: "state", type: "string"),
                            new OA\Property(property: "zip_code", type: "string"),
                            new OA\Property(property: "property_type", type: "string"),
                            new OA\Property(property: "square_feet", type: "integer"),
                            new OA\Property(property: "bedrooms", type: "integer"),
                            new OA\Property(property: "bathrooms", type: "number"),
                            new OA\Property(property: "year_built", type: "integer"),
                            new OA\Property(property: "condition", type: "string"),
                            new OA\Property(property: "lot_size", type: "integer"),
                        ]
                    ),
                    new OA\Property(
                        property: "force_refresh",
                        type: "boolean",
                        default: false,
                        description: "Force new estimate (bypass cache)"
                    ),
                    new OA\Property(
                        property: "model",
                        type: "string",
                        enum: ["gpt-3.5-turbo", "gpt-4", "gpt-4-turbo", "gpt-4o"],
                        default: "gpt-3.5-turbo"
                    ),
                    new OA\Property(
                        property: "use_calculations",
                        type: "boolean",
                        default: false,
                        description: "Force calculation-based estimate instead of AI"
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Estimate generated successfully (may be from cache)"),
            new OA\Response(response: 403, description: "Forbidden - Subscription required"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 429, description: "Rate limit exceeded"),
            new OA\Response(response: 503, description: "Service unavailable - API key missing"),
        ]
    )]
    public function previewEstimate(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check access: Admin OR (Premium/VIP subscription)
        if (!$this->canGenerateEstimate($user)) {
            return response()->json([
                'success' => false,
                'message' => 'AI rehab estimates are only available for Premium/VIP subscribers or administrators.',
            ], 403);
        }

        // Validate request
        $validated = $request->validate([
            'property_data' => 'required|array',
            'property_data.address' => 'required|string',
            'property_data.city' => 'nullable|string',
            'property_data.state' => 'nullable|string',
            'property_data.zip_code' => 'nullable|string',
            'property_data.property_type' => 'nullable|string',
            'property_data.square_feet' => 'nullable|integer|min:0',
            'property_data.bedrooms' => 'nullable|integer|min:0',
            'property_data.bathrooms' => 'nullable|numeric|min:0',
            'property_data.year_built' => 'nullable|integer|min:1800|max:' . date('Y'),
            'property_data.condition' => 'nullable|string|in:excellent,good,fair,poor,needs_repair',
            'property_data.lot_size' => 'nullable|integer|min:0',
            'force_refresh' => 'nullable|boolean',
            'model' => 'nullable|string|in:gpt-3.5-turbo,gpt-4,gpt-4-turbo,gpt-4o',
            'use_calculations' => 'nullable|boolean',
        ]);

        // Check rate limit (unless admin)
        if (!$user->hasRole('admin')) {
            $rateLimitKey = "rehab_estimate_preview:{$user->id}";
            $maxAttempts = $this->getRateLimit($user);

            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'success' => false,
                    'message' => "Rate limit exceeded. Please try again in {$seconds} seconds.",
                ], 429);
            }

            RateLimiter::hit($rateLimitKey, 86400); // 24 hour window
        }

        // Transform property_data to match expected format
        $propertyData = [
            'location' => [
                'address' => $validated['property_data']['address'],
                'city' => $validated['property_data']['city'] ?? null,
                'state' => $validated['property_data']['state'] ?? null,
                'zip' => $validated['property_data']['zip_code'] ?? null,
            ],
            'property' => [
                'type' => $validated['property_data']['property_type'] ?? null,
                'square_feet' => $validated['property_data']['square_feet'] ?? null,
                'bedrooms' => $validated['property_data']['bedrooms'] ?? null,
                'bathrooms' => $validated['property_data']['bathrooms'] ?? null,
                'year_built' => $validated['property_data']['year_built'] ?? null,
                'condition' => $validated['property_data']['condition'] ?? null,
                'lot_size' => $validated['property_data']['lot_size'] ?? null,
            ],
            'financial' => [
                'asking_price' => null, // Not available in preview
                'arv' => null, // Not available in preview
            ],
        ];

        try {
            $options = [
                'model' => $validated['model'] ?? config('services.openai.model', 'gpt-3.5-turbo'),
                'force_refresh' => $validated['force_refresh'] ?? false,
                'use_calculations' => $validated['use_calculations'] ?? true, //set to false when the API key is taken
            ];

            $estimate = $this->rehabEstimateService->generateEstimateFromData(
                $propertyData,
                $user,
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Rehab estimate generated successfully',
                'data' => $estimate,
                'from_cache' => !$options['force_refresh'] && isset($estimate['cached_at']),
            ]);
        } catch (\RuntimeException $e) {
            // Handle API errors gracefully
            if (str_contains($e->getMessage(), 'API key')) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI estimation service is currently unavailable. Please contact support.',
                    'error_code' => 'API_KEY_MISSING',
                ], 503);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'ESTIMATE_GENERATION_FAILED',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Unexpected error generating preview rehab estimate', [
                'user_id' => $user->id,
                'property_data' => $propertyData,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while generating the estimate.',
                'error_code' => 'INTERNAL_ERROR',
            ], 500);
        }
    }
}
