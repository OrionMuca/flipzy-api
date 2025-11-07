<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    /**
     * List all subscriptions
     */
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
