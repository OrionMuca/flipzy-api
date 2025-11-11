<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;

class CouponService
{
    /**
     * Validate coupon for a given plan and email
     *
     * @param string $code
     * @param string $planId
     * @param string|null $email
     * @return array
     */
    public function validateCoupon(string $code, string $planId, ?string $email = null): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (!$coupon) {
            return [
                'valid' => false,
                'error' => 'Coupon code not found',
            ];
        }

        // Check if coupon is active
        if (!$coupon->is_active) {
            return [
                'valid' => false,
                'error' => 'This coupon is no longer active',
            ];
        }

        // Check validity (dates, usage limits)
        if (!$coupon->isValid()) {
            return [
                'valid' => false,
                'error' => 'This coupon has expired or reached its usage limit',
            ];
        }

        // Check if coupon applies to this plan
        if (!$coupon->isApplicableToPlan($planId)) {
            return [
                'valid' => false,
                'error' => 'This coupon is not valid for the selected plan',
            ];
        }

        // Check user usage limit if email provided
        if ($email && $coupon->hasReachedUserLimit($email)) {
            return [
                'valid' => false,
                'error' => 'You have already used this coupon the maximum number of times',
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
        ];
    }

    /**
     * Calculate discounted price for a plan with coupon
     *
     * @param SubscriptionPlan $plan
     * @param Coupon|null $coupon
     * @return array
     */
    public function calculateDiscount(SubscriptionPlan $plan, ?Coupon $coupon = null): array
    {
        $originalPrice = (float) $plan->price;
        $discountAmount = 0;
        $discountedPrice = $originalPrice;

        if ($coupon) {
            // Check minimum amount requirement
            if ($coupon->minimum_amount && $originalPrice < $coupon->minimum_amount) {
                return [
                    'original_price' => $originalPrice,
                    'discount_amount' => 0,
                    'discounted_price' => $originalPrice,
                    'error' => "Minimum purchase amount of \${$coupon->minimum_amount} required for this coupon",
                ];
            }

            $discountAmount = $coupon->calculateDiscount($originalPrice);
            $discountedPrice = max(0, $originalPrice - $discountAmount); // Ensure price doesn't go negative
        }

        return [
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'discounted_price' => $discountedPrice,
            'coupon' => $coupon,
        ];
    }

    /**
     * Validate and calculate discount in one call
     *
     * @param string $code
     * @param string $planId
     * @param string|null $email
     * @return array
     */
    public function validateAndCalculate(string $code, string $planId, ?string $email = null): array
    {
        $plan = SubscriptionPlan::find($planId);

        if (!$plan) {
            return [
                'valid' => false,
                'error' => 'Subscription plan not found',
            ];
        }

        $validation = $this->validateCoupon($code, $planId, $email);

        if (!$validation['valid']) {
            return $validation;
        }

        $coupon = $validation['coupon'];
        $calculation = $this->calculateDiscount($plan, $coupon);

        if (isset($calculation['error'])) {
            return [
                'valid' => false,
                'error' => $calculation['error'],
            ];
        }

        return [
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
            ],
            'original_price' => $calculation['original_price'],
            'discount_amount' => $calculation['discount_amount'],
            'discounted_price' => $calculation['discounted_price'],
        ];
    }

    /**
     * Apply coupon to a waiting list entry (increment usage)
     *
     * @param Coupon $coupon
     * @return void
     */
    public function applyCoupon(Coupon $coupon): void
    {
        $coupon->incrementUsage();
    }
}

