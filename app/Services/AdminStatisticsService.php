<?php

namespace App\Services;

use App\Models\Analytic;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminStatisticsService
{
    /**
     * Get system overview statistics
     */
    public function getSystemOverview(): array
    {
        $cacheKey = 'admin_system_overview';
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            // User statistics
            $usersByRole = User::with('roles')
                ->get()
                ->groupBy(function ($user) {
                    return $user->roles->first()?->name ?? 'no_role';
                })
                ->map->count();

            $totalUsers = User::count();
            $activeUsers = User::whereNotNull('email_verified_at')->count();

            // Property statistics
            $propertiesByStatus = Property::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $totalProperties = Property::count();
            $verifiedProperties = Property::where('is_verified', true)->count();
            $featuredProperties = Property::where('is_featured', true)->count();

            // Engagement statistics
            $totalViews = Analytic::where('event_type', 'view')->count();
            $totalSaves = Analytic::where('event_type', 'save')->count();
            $totalInquiries = Analytic::where('event_type', 'inquiry')->count();

            // Subscription statistics
            $subscriptionsByPlanId = Subscription::select('subscription_plan_id', DB::raw('count(*) as count'))
                ->where('status', 'active')
                ->groupBy('subscription_plan_id')
                ->pluck('count', 'subscription_plan_id')
                ->toArray();

            // Map to plan slugs
            $subscriptionsByPlan = [];
            foreach ($subscriptionsByPlanId as $planId => $count) {
                $plan = \App\Models\SubscriptionPlan::find($planId);
                $slug = $plan ? $plan->slug : 'unknown';
                $subscriptionsByPlan[$slug] = ($subscriptionsByPlan[$slug] ?? 0) + $count;
            }

            $totalSubscriptions = Subscription::where('status', 'active')->count();

            // Messages/Conversations
            $totalConversations = \App\Models\Conversation::count();
            $totalMessages = \App\Models\Message::count();

            return [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'suspended' => $totalUsers - $activeUsers,
                    'by_role' => [
                        'investor' => $usersByRole['investor'] ?? 0,
                        'wholesaler' => $usersByRole['wholesaler'] ?? 0,
                        'admin' => $usersByRole['admin'] ?? 0,
                    ],
                ],
                'properties' => [
                    'total' => $totalProperties,
                    'verified' => $verifiedProperties,
                    'featured' => $featuredProperties,
                    'by_status' => [
                        'active' => $propertiesByStatus['active'] ?? 0,
                        'pending' => $propertiesByStatus['pending'] ?? 0,
                        'sold' => $propertiesByStatus['sold'] ?? 0,
                    ],
                ],
                'engagement' => [
                    'total_views' => $totalViews,
                    'total_saves' => $totalSaves,
                    'total_inquiries' => $totalInquiries,
                ],
                'subscriptions' => [
                    'total' => $totalSubscriptions,
                    'by_plan' => [
                        'free' => $subscriptionsByPlan['free'] ?? 0,
                        'premium' => $subscriptionsByPlan['premium'] ?? 0,
                        'vip' => $subscriptionsByPlan['vip'] ?? 0,
                    ],
                ],
                'messaging' => [
                    'total_conversations' => $totalConversations,
                    'total_messages' => $totalMessages,
                ],
            ];
        });
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(int $days = 30): array
    {
        $cacheKey = "admin_user_stats_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($days) {
            $startDate = now()->subDays($days);

            $newUsers = User::where('created_at', '>=', $startDate)->count();
            $newUsersByRole = User::where('created_at', '>=', $startDate)
                ->with('roles')
                ->get()
                ->groupBy(function ($user) {
                    return $user->roles->first()?->name ?? 'no_role';
                })
                ->map->count();

            return [
                'period_days' => $days,
                'new_users' => $newUsers,
                'new_users_by_role' => [
                    'investor' => $newUsersByRole['investor'] ?? 0,
                    'wholesaler' => $newUsersByRole['wholesaler'] ?? 0,
                    'admin' => $newUsersByRole['admin'] ?? 0,
                ],
            ];
        });
    }

    /**
     * Get property statistics
     */
    public function getPropertyStatistics(int $days = 30): array
    {
        $cacheKey = "admin_property_stats_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($days) {
            $startDate = now()->subDays($days);

            $newProperties = Property::where('created_at', '>=', $startDate)->count();
            $propertiesByStatus = Property::where('created_at', '>=', $startDate)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return [
                'period_days' => $days,
                'new_properties' => $newProperties,
                'by_status' => [
                    'active' => $propertiesByStatus['active'] ?? 0,
                    'pending' => $propertiesByStatus['pending'] ?? 0,
                    'sold' => $propertiesByStatus['sold'] ?? 0,
                ],
            ];
        });
    }

    /**
     * Get engagement statistics
     */
    public function getEngagementStatistics(int $days = 30): array
    {
        $cacheKey = "admin_engagement_stats_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($days) {
            $startDate = now()->subDays($days);

            $views = Analytic::where('event_type', 'view')
                ->where('created_at', '>=', $startDate)
                ->count();

            $saves = Analytic::where('event_type', 'save')
                ->where('created_at', '>=', $startDate)
                ->count();

            $inquiries = Analytic::where('event_type', 'inquiry')
                ->where('created_at', '>=', $startDate)
                ->count();

            return [
                'period_days' => $days,
                'views' => $views,
                'saves' => $saves,
                'inquiries' => $inquiries,
            ];
        });
    }

    /**
     * Get trends over time
     */
    public function getTrends(int $days = 30): array
    {
        $cacheKey = "admin_trends_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($days) {
            $startDate = now()->subDays($days);

            // Daily trends
            $userTrends = User::where('created_at', '>=', $startDate)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $propertyTrends = Property::where('created_at', '>=', $startDate)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $viewTrends = Analytic::where('event_type', 'view')
                ->where('created_at', '>=', $startDate)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $saveTrends = Analytic::where('event_type', 'save')
                ->where('created_at', '>=', $startDate)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            $inquiryTrends = Analytic::where('event_type', 'inquiry')
                ->where('created_at', '>=', $startDate)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            // Generate date range
            $dates = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dates[] = now()->subDays($i)->format('Y-m-d');
            }

            return [
                'period_days' => $days,
                'dates' => $dates,
                'users' => [
                    'new_users' => array_map(function ($date) use ($userTrends) {
                        return $userTrends[$date] ?? 0;
                    }, $dates),
                ],
                'properties' => [
                    'new_properties' => array_map(function ($date) use ($propertyTrends) {
                        return $propertyTrends[$date] ?? 0;
                    }, $dates),
                ],
                'engagement' => [
                    'views' => array_map(function ($date) use ($viewTrends) {
                        return $viewTrends[$date] ?? 0;
                    }, $dates),
                    'saves' => array_map(function ($date) use ($saveTrends) {
                        return $saveTrends[$date] ?? 0;
                    }, $dates),
                    'inquiries' => array_map(function ($date) use ($inquiryTrends) {
                        return $inquiryTrends[$date] ?? 0;
                    }, $dates),
                ],
            ];
        });
    }

    /**
     * Get top properties by engagement
     */
    public function getTopProperties(int $limit = 10, int $days = 30): array
    {
        $cacheKey = "admin_top_properties_{$limit}_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($limit, $days) {
            $startDate = now()->subDays($days);

            $topProperties = Property::with('wholesaler')
                ->withCount(['analytics as total_views' => function ($query) use ($startDate) {
                    $query->where('event_type', 'view')
                        ->where('created_at', '>=', $startDate);
                }])
                ->withCount(['analytics as total_saves' => function ($query) use ($startDate) {
                    $query->where('event_type', 'save')
                        ->where('created_at', '>=', $startDate);
                }])
                ->withCount(['analytics as total_inquiries' => function ($query) use ($startDate) {
                    $query->where('event_type', 'inquiry')
                        ->where('created_at', '>=', $startDate);
                }])
                ->orderBy('total_views', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'title' => $property->title,
                        'address' => $property->address,
                        'city' => $property->city,
                        'state' => $property->state,
                        'asking_price' => $property->asking_price,
                        'wholesaler' => $property->wholesaler ? [
                            'id' => $property->wholesaler->id,
                            'name' => $property->wholesaler->name,
                            'email' => $property->wholesaler->email,
                        ] : null,
                        'engagement' => [
                            'views' => $property->total_views ?? 0,
                            'saves' => $property->total_saves ?? 0,
                            'inquiries' => $property->total_inquiries ?? 0,
                        ],
                    ];
                })
                ->toArray();

            return [
                'period_days' => $days,
                'properties' => $topProperties,
            ];
        });
    }

    /**
     * Get top wholesalers by credibility
     */
    public function getTopWholesalers(int $limit = 10): array
    {
        $cacheKey = "admin_top_wholesalers_{$limit}";
        
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($limit) {
            $wholesalers = User::whereHas('roles', function ($q) {
                    $q->where('name', 'wholesaler');
                })
                ->withCount('properties')
                ->get()
                ->map(function ($wholesaler) {
                    $credibility = app(\App\Services\AnalyticsService::class)
                        ->calculateCredibilityScore($wholesaler, 90);
                    
                    return [
                        'id' => $wholesaler->id,
                        'name' => $wholesaler->name,
                        'email' => $wholesaler->email,
                        'credibility_score' => $credibility['normalized_score'] ?? 0,
                        'property_count' => $wholesaler->properties_count,
                        'engagement' => $credibility['breakdown'] ?? [],
                    ];
                })
                ->sortByDesc('credibility_score')
                ->take($limit)
                ->values()
                ->toArray();

            return [
                'wholesalers' => $wholesalers,
            ];
        });
    }

    /**
     * Get geographic distribution
     */
    public function getGeographicDistribution(): array
    {
        $cacheKey = 'admin_geographic_distribution';
        
        return Cache::remember($cacheKey, now()->addHours(1), function () {
            $byState = Property::select('state', DB::raw('count(*) as count'))
                ->whereNotNull('state')
                ->groupBy('state')
                ->orderByDesc('count')
                ->pluck('count', 'state')
                ->toArray();

            $byCity = Property::select('city', 'state', DB::raw('count(*) as count'))
                ->whereNotNull('city')
                ->whereNotNull('state')
                ->groupBy('city', 'state')
                ->orderByDesc('count')
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    return [
                        'city' => $item->city,
                        'state' => $item->state,
                        'count' => $item->count,
                    ];
                })
                ->toArray();

            return [
                'by_state' => $byState,
                'top_cities' => $byCity,
            ];
        });
    }

    /**
     * Clear all admin statistics cache
     */
    public function clearCache(): void
    {
        Cache::forget('admin_system_overview');
        Cache::forget('admin_top_wholesalers_10');
        Cache::forget('admin_geographic_distribution');
        
        // Clear period-based caches
        foreach ([7, 30, 90, 365] as $days) {
            Cache::forget("admin_user_stats_{$days}");
            Cache::forget("admin_property_stats_{$days}");
            Cache::forget("admin_engagement_stats_{$days}");
            Cache::forget("admin_trends_{$days}");
            Cache::forget("admin_top_properties_10_{$days}");
        }
    }
}

