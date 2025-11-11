<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Analytics & Statistics")]
class AdminAnalyticsController extends Controller
{
    public function __construct(
        protected AdminStatisticsService $statisticsService
    ) {}

    /**
     * Get system overview statistics
     */
    #[OA\Get(
        path: "/admin/analytics/overview",
        summary: "Get system overview statistics (Admin only)",
        description: "Get comprehensive system-wide statistics including user counts, property counts, and engagement metrics",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Overview statistics retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function overview(): JsonResponse
    {
        $stats = $this->statisticsService->getSystemOverview();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get user statistics
     */
    #[OA\Get(
        path: "/admin/analytics/users",
        summary: "Get user statistics (Admin only)",
        description: "Get user statistics for a specific time period",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "days",
                in: "query",
                description: "Time period in days",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 30)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "User statistics retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function users(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

        $stats = $this->statisticsService->getUserStatistics($days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get property statistics
     */
    #[OA\Get(
        path: "/admin/analytics/properties",
        summary: "Get property statistics (Admin only)",
        description: "Get property statistics for a specific time period",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "days",
                in: "query",
                description: "Time period in days",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 30)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Property statistics retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function properties(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

        $stats = $this->statisticsService->getPropertyStatistics($days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get engagement statistics
     */
    #[OA\Get(
        path: "/admin/analytics/engagement",
        summary: "Get engagement statistics (Admin only)",
        description: "Get user engagement statistics including views, saves, and inquiries for a specific time period",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "days",
                in: "query",
                description: "Time period in days",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 30)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Engagement statistics retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function engagement(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

        $stats = $this->statisticsService->getEngagementStatistics($days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get trends over time
     */
    #[OA\Get(
        path: "/admin/analytics/trends",
        summary: "Get trends over time (Admin only)",
        description: "Get trends data showing changes over time for users, properties, and engagement",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "days",
                in: "query",
                description: "Time period in days",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 30)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Trends data retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function trends(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

        $trends = $this->statisticsService->getTrends($days);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get top properties by engagement
     */
    #[OA\Get(
        path: "/admin/analytics/top-properties",
        summary: "Get top properties by engagement (Admin only)",
        description: "Get the top performing properties ranked by engagement metrics",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "limit",
                in: "query",
                description: "Number of properties to return (1-50)",
                schema: new OA\Schema(type: "integer", default: 10, minimum: 1, maximum: 50)
            ),
            new OA\Parameter(
                name: "days",
                in: "query",
                description: "Time period in days",
                schema: new OA\Schema(type: "integer", enum: [7, 30, 90, 365], default: 30)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Top properties retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function topProperties(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 10);
        $limit = min(max($limit, 1), 50); // Between 1 and 50

        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

        $topProperties = $this->statisticsService->getTopProperties($limit, $days);

        return response()->json([
            'success' => true,
            'data' => $topProperties,
        ]);
    }

    /**
     * Get top wholesalers by credibility
     */
    #[OA\Get(
        path: "/admin/analytics/top-wholesalers",
        summary: "Get top wholesalers by credibility (Admin only)",
        description: "Get the top performing wholesalers ranked by credibility score",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "limit",
                in: "query",
                description: "Number of wholesalers to return (1-50)",
                schema: new OA\Schema(type: "integer", default: 10, minimum: 1, maximum: 50)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Top wholesalers retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function topWholesalers(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 10);
        $limit = min(max($limit, 1), 50); // Between 1 and 50

        $topWholesalers = $this->statisticsService->getTopWholesalers($limit);

        return response()->json([
            'success' => true,
            'data' => $topWholesalers,
        ]);
    }

    /**
     * Get geographic distribution
     */
    #[OA\Get(
        path: "/admin/analytics/geographic",
        summary: "Get geographic distribution (Admin only)",
        description: "Get property distribution by geographic location (state/city)",
        tags: ["Admin - Analytics & Statistics"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Geographic distribution retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function geographicDistribution(): JsonResponse
    {
        $distribution = $this->statisticsService->getGeographicDistribution();

        return response()->json([
            'success' => true,
            'data' => $distribution,
        ]);
    }
}
