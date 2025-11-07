<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnalyticsResource;
use App\Http\Resources\CredibilityResource;
use App\Models\Property;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Analytics")]
class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Track a property view
     */
    #[OA\Post(
        path: "/properties/{id}/view",
        summary: "Track property view",
        description: "Record a view event for a property. Used for analytics and engagement tracking.",
        tags: ["Analytics"],
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
            new OA\Response(response: 201, description: "View tracked successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Property not found"),
        ]
    )]
    public function trackView(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();
        
        $analytic = $this->analyticsService->trackView(
            $property,
            $user,
            $request->only(['referrer', 'source'])
        );

        return response()->json([
            'success' => true,
            'message' => 'View tracked successfully',
            'data' => [
                'event_id' => $analytic->id,
                'event_type' => 'view',
                'property_id' => $property->id,
            ],
        ], 201);
    }

    /**
     * Track a property save
     */
    #[OA\Post(
        path: "/properties/{id}/save",
        summary: "Track property save",
        description: "Save a property to user's favorites/saved list. Optional notes and list name.",
        tags: ["Analytics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "notes", type: "string", example: "Potential flip opportunity", maxLength: 500),
                    new OA\Property(property: "list_name", type: "string", example: "Favorites", maxLength: 100),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Property already saved"),
            new OA\Response(response: 201, description: "Save tracked successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function trackSave(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();
        
        // Check if already saved (optional: prevent duplicate saves)
        $existingSave = \App\Models\Analytic::where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->where('event_type', 'save')
            ->first();

        if ($existingSave) {
            return response()->json([
                'success' => true,
                'message' => 'Property already saved',
                'data' => [
                    'event_id' => $existingSave->id,
                    'event_type' => 'save',
                    'property_id' => $property->id,
                ],
            ]);
        }

        $analytic = $this->analyticsService->trackSave(
            $property,
            $user,
            $request->only(['notes', 'list_name'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Save tracked successfully',
            'data' => [
                'event_id' => $analytic->id,
                'event_type' => 'save',
                'property_id' => $property->id,
            ],
        ], 201);
    }

    /**
     * Track a property inquiry
     */
    #[OA\Post(
        path: "/properties/{id}/inquiry",
        summary: "Track property inquiry",
        description: "Record an inquiry event for a property. Used for engagement tracking and credibility scoring.",
        tags: ["Analytics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string", example: "I'm interested in this property", maxLength: 1000),
                    new OA\Property(property: "contact_method", type: "string", example: "platform", enum: ["platform", "phone", "email"]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Inquiry tracked successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function trackInquiry(Request $request, Property $property): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        
        $analytic = $this->analyticsService->trackInquiry(
            $property,
            $user,
            [
                'message' => $request->message,
                'contact_method' => $request->input('contact_method', 'platform'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Inquiry tracked successfully',
            'data' => [
                'event_id' => $analytic->id,
                'event_type' => 'inquiry',
                'property_id' => $property->id,
            ],
        ], 201);
    }

    /**
     * Get analytics for a property
     */
    #[OA\Get(
        path: "/properties/{id}/analytics",
        summary: "Get property analytics",
        description: "Get analytics data for a property including views, saves, and inquiries. Filter by time period.",
        tags: ["Analytics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                name: "days",
                in: "query",
                description: "Time period in days",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 30)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property analytics"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function getPropertyAnalytics(Request $request, Property $property): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

        $analytics = $this->analyticsService->getPropertyAnalytics($property, $days);

        return response()->json([
            'success' => true,
            'data' => new AnalyticsResource($analytics),
        ]);
    }

    /**
     * Get credibility score for a user (wholesaler)
     */
    #[OA\Get(
        path: "/users/{id}/credibility",
        summary: "Get wholesaler credibility score",
        description: "Get credibility score for a wholesaler based on their properties' engagement metrics.",
        tags: ["Analytics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid"),
                description: "Wholesaler user ID"
            ),
            new OA\Parameter(
                name: "days",
                in: "query",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 90)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Credibility score"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "User is not a wholesaler"),
        ]
    )]
    public function getCredibilityScore(Request $request, User $user): JsonResponse
    {
        // Only wholesalers have credibility scores
        if (!$user->hasRole('wholesaler')) {
            return response()->json([
                'success' => false,
                'message' => 'Credibility scores are only available for wholesalers',
            ], 422);
        }

        $days = (int) $request->get('days', 90);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 90;

        $credibility = $this->analyticsService->calculateCredibilityScore($user, $days);

        return response()->json([
            'success' => true,
            'data' => new CredibilityResource($credibility),
        ]);
    }

    /**
     * Get analytics summary for authenticated wholesaler
     */
    #[OA\Get(
        path: "/analytics/my-analytics",
        summary: "Get my analytics (wholesaler)",
        description: "Get analytics summary for the authenticated wholesaler including total properties, views, saves, and inquiries.",
        tags: ["Analytics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "days",
                in: "query",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 30)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Analytics summary"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Not a wholesaler"),
        ]
    )]
    public function getMyAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('wholesaler')) {
            return response()->json([
                'success' => false,
                'message' => 'Analytics are only available for wholesalers',
            ], 403);
        }

        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

        $analytics = $this->analyticsService->getWholesalerAnalytics($user, $days);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }
}
