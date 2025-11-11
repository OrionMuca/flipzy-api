<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Subscription Management")]
class AdminSubscriptionController extends Controller
{
    /**
     * List all subscriptions
     */
    #[OA\Get(
        path: "/admin/subscriptions",
        summary: "List all subscriptions (Admin only)",
        description: "Get a paginated list of all subscriptions with optional filtering by plan and status",
        tags: ["Admin - Subscription Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "plan",
                in: "query",
                description: "Filter by plan slug (free, premium, vip)",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter by status",
                schema: new OA\Schema(type: "string", enum: ["active", "cancelled", "expired"])
            ),
            new OA\Parameter(
                name: "sort_by",
                in: "query",
                description: "Sort field",
                schema: new OA\Schema(type: "string", default: "created_at")
            ),
            new OA\Parameter(
                name: "sort_order",
                in: "query",
                description: "Sort order",
                schema: new OA\Schema(type: "string", enum: ["asc", "desc"], default: "desc")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Items per page (max 100)",
                schema: new OA\Schema(type: "integer", default: 15, maximum: 100)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Subscriptions retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['user', 'plan']);

        // Filter by plan
        if ($request->has('plan')) {
            $planSlug = $request->get('plan');
            $plan = SubscriptionPlan::where('slug', $planSlug)->first();
            if ($plan) {
                $query->where('subscription_plan_id', $plan->id);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if (in_array($status, ['active', 'cancelled', 'expired'])) {
                $query->where('status', $status);
            }
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $subscriptions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $subscriptions->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'user' => [
                        'id' => $subscription->user->id,
                        'name' => $subscription->user->name,
                        'email' => $subscription->user->email,
                    ],
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'slug' => $subscription->plan->slug,
                    ],
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at?->toISOString(),
                    'ends_at' => $subscription->ends_at?->toISOString(),
                    'created_at' => $subscription->created_at->toISOString(),
                ];
            }),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * Get subscription details
     */
    #[OA\Get(
        path: "/admin/subscriptions/{id}",
        summary: "Get subscription details (Admin only)",
        description: "Get detailed information about a specific subscription",
        tags: ["Admin - Subscription Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Subscription ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Subscription details retrieved successfully"),
            new OA\Response(response: 404, description: "Subscription not found"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load(['user', 'plan']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'user' => [
                    'id' => $subscription->user->id,
                    'name' => $subscription->user->name,
                    'email' => $subscription->user->email,
                ],
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                    'price' => $subscription->plan->price,
                ],
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
                'cancelled_at' => $subscription->cancelled_at?->toISOString(),
                'created_at' => $subscription->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * List subscription plans
     */
    #[OA\Get(
        path: "/admin/subscriptions/plans",
        summary: "List subscription plans (Admin only)",
        description: "Get all available subscription plans with their details and active subscription counts",
        tags: ["Admin - Subscription Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Subscription plans retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::all();

        return response()->json([
            'success' => true,
            'data' => $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'billing_interval' => $plan->billing_interval,
                    'features' => $plan->features,
                    'max_properties' => $plan->max_properties,
                    'max_messages' => $plan->max_messages,
                    'has_ai_estimates' => $plan->has_ai_estimates,
                    'has_api_access' => $plan->has_api_access,
                    'is_active' => $plan->is_active,
                    'subscription_count' => $plan->subscriptions()->where('status', 'active')->count(),
                ];
            }),
        ]);
    }

    /**
     * Get subscription statistics
     */
    #[OA\Get(
        path: "/admin/subscriptions/stats",
        summary: "Get subscription statistics (Admin only)",
        description: "Get subscription statistics including total, active, cancelled counts and breakdown by plan",
        tags: ["Admin - Subscription Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Subscription statistics retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function stats(): JsonResponse
    {
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $cancelledSubscriptions = Subscription::where('status', 'cancelled')->count();

        $byPlan = Subscription::where('status', 'active')
            ->with('plan')
            ->get()
            ->groupBy(function ($subscription) {
                return $subscription->plan->slug ?? 'unknown';
            })
            ->map->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalSubscriptions,
                'active' => $activeSubscriptions,
                'cancelled' => $cancelledSubscriptions,
                'by_plan' => [
                    'free' => $byPlan['free'] ?? 0,
                    'premium' => $byPlan['premium'] ?? 0,
                    'vip' => $byPlan['vip'] ?? 0,
                ],
            ],
        ]);
    }
}
