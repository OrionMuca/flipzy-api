<?php

namespace App\Services;

use App\Models\Analytic;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Track a property view
     */
    public function trackView(Property $property, ?User $user = null, array $metadata = []): Analytic
    {
        return $this->trackEvent($property, 'view', $user, $metadata);
    }

    /**
     * Track a property save
     */
    public function trackSave(Property $property, User $user, array $metadata = []): Analytic
    {
        return $this->trackEvent($property, 'save', $user, $metadata);
    }

    /**
     * Track a property inquiry
     */
    public function trackInquiry(Property $property, User $user, array $metadata = []): Analytic
    {
        return $this->trackEvent($property, 'inquiry', $user, $metadata);
    }

    /**
     * Track a generic analytics event
     */
    public function trackEvent(
        Property $property,
        string $eventType,
        ?User $user = null,
        array $metadata = []
    ): Analytic {
        $analytic = Analytic::create([
            'property_id' => $property->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);

        // Clear relevant caches
        $this->clearAnalyticsCache($property);
        if ($user) {
            $this->clearCredibilityCache($user);
        }

        return $analytic;
    }

    /**
     * Get analytics for a property
     */
    public function getPropertyAnalytics(Property $property, ?int $days = 30): array
    {
        $cacheKey = "property_analytics_{$property->id}_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($property, $days) {
            $startDate = now()->subDays($days);

            $analytics = Analytic::where('property_id', $property->id)
                ->where('created_at', '>=', $startDate)
                ->select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray();

            $totalViews = $analytics['view'] ?? 0;
            $totalSaves = $analytics['save'] ?? 0;
            $totalInquiries = $analytics['inquiry'] ?? 0;

            // Get unique counts (users who performed the action)
            $uniqueViews = Analytic::where('property_id', $property->id)
                ->where('event_type', 'view')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');

            $uniqueSaves = Analytic::where('property_id', $property->id)
                ->where('event_type', 'save')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');

            $uniqueInquiries = Analytic::where('property_id', $property->id)
                ->where('event_type', 'inquiry')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');

            return [
                'property_id' => $property->id,
                'period_days' => $days,
                'total_views' => $totalViews,
                'unique_views' => $uniqueViews,
                'total_saves' => $totalSaves,
                'unique_saves' => $uniqueSaves,
                'total_inquiries' => $totalInquiries,
                'unique_inquiries' => $uniqueInquiries,
                'breakdown' => $analytics,
            ];
        });
    }

    /**
     * Calculate credibility score for a wholesaler
     * 
     * Formula: score = (views * 0.3) + (saves * 0.5) + (inquiries * 0.2)
     * Normalized to 0-100 scale
     */
    public function calculateCredibilityScore(User $wholesaler, ?int $days = 90): array
    {
        $cacheKey = "credibility_score_{$wholesaler->id}_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($wholesaler, $days) {
            $startDate = now()->subDays($days);

            // Get all properties owned by this wholesaler
            $propertyIds = Property::where('wholesaler_id', $wholesaler->id)
                ->pluck('id');

            if ($propertyIds->isEmpty()) {
                return [
                    'user_id' => $wholesaler->id,
                    'score' => 0,
                    'normalized_score' => 0,
                    'breakdown' => [
                        'total_views' => 0,
                        'total_saves' => 0,
                        'total_inquiries' => 0,
                    ],
                    'period_days' => $days,
                ];
            }

            // Aggregate analytics across all properties
            $analytics = Analytic::whereIn('property_id', $propertyIds)
                ->where('created_at', '>=', $startDate)
                ->select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray();

            $totalViews = $analytics['view'] ?? 0;
            $totalSaves = $analytics['save'] ?? 0;
            $totalInquiries = $analytics['inquiry'] ?? 0;

            // Calculate raw score using the formula
            $rawScore = ($totalViews * 0.3) + ($totalSaves * 0.5) + ($totalInquiries * 0.2);

            // Normalize to 0-100 scale
            // We'll use a dynamic max score based on the highest possible engagement
            // For normalization, we'll use a reasonable maximum (e.g., 1000 views, 100 saves, 50 inquiries)
            $maxPossibleScore = (1000 * 0.3) + (100 * 0.5) + (50 * 0.2);
            $maxPossibleScore = max($maxPossibleScore, $rawScore); // Ensure we don't divide by a smaller number
            
            $normalizedScore = $maxPossibleScore > 0 
                ? min(100, ($rawScore / $maxPossibleScore) * 100)
                : 0;

            // Get property count for context
            $propertyCount = $propertyIds->count();

            return [
                'user_id' => $wholesaler->id,
                'score' => round($rawScore, 2),
                'normalized_score' => round($normalizedScore, 2),
                'breakdown' => [
                    'total_views' => $totalViews,
                    'total_saves' => $totalSaves,
                    'total_inquiries' => $totalInquiries,
                ],
                'property_count' => $propertyCount,
                'period_days' => $days,
            ];
        });
    }

    /**
     * Get analytics summary for a wholesaler's properties
     */
    public function getWholesalerAnalytics(User $wholesaler, ?int $days = 30): array
    {
        $cacheKey = "wholesaler_analytics_{$wholesaler->id}_{$days}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($wholesaler, $days) {
            $startDate = now()->subDays($days);

            $propertyIds = Property::where('wholesaler_id', $wholesaler->id)
                ->pluck('id');

            if ($propertyIds->isEmpty()) {
                return [
                    'user_id' => $wholesaler->id,
                    'total_properties' => 0,
                    'total_views' => 0,
                    'total_saves' => 0,
                    'total_inquiries' => 0,
                    'period_days' => $days,
                ];
            }

            $analytics = Analytic::whereIn('property_id', $propertyIds)
                ->where('created_at', '>=', $startDate)
                ->select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray();

            return [
                'user_id' => $wholesaler->id,
                'total_properties' => $propertyIds->count(),
                'total_views' => $analytics['view'] ?? 0,
                'total_saves' => $analytics['save'] ?? 0,
                'total_inquiries' => $analytics['inquiry'] ?? 0,
                'period_days' => $days,
            ];
        });
    }

    /**
     * Clear analytics cache for a property
     */
    protected function clearAnalyticsCache(Property $property): void
    {
        // Clear all period variations (common periods: 7, 30, 90 days)
        foreach ([7, 30, 90] as $days) {
            Cache::forget("property_analytics_{$property->id}_{$days}");
        }
    }

    /**
     * Clear credibility cache for a user
     */
    protected function clearCredibilityCache(User $user): void
    {
        // Clear all period variations
        foreach ([7, 30, 90] as $days) {
            Cache::forget("credibility_score_{$user->id}_{$days}");
            Cache::forget("wholesaler_analytics_{$user->id}_{$days}");
        }
    }
}

