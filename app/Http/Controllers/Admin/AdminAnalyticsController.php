<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(
        protected AdminStatisticsService $statisticsService
    ) {}

    /**
     * Get system overview statistics
     */
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
    public function geographicDistribution(): JsonResponse
    {
        $distribution = $this->statisticsService->getGeographicDistribution();

        return response()->json([
            'success' => true,
            'data' => $distribution,
        ]);
    }
}
