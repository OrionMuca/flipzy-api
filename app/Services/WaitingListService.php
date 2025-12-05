<?php

namespace App\Services;

use App\Models\WaitingListEntry;
use App\Models\User;
use App\Models\Coupon;
use App\Mail\WaitingListWelcomeMail;
use App\Services\CouponService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WaitingListService
{
    protected CouponService $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * Register a new waiting list entry
     *
     * @param array $data
     * @return array
     */
    public function register(array $data): array
    {
        try {
            $existingEntry = WaitingListEntry::where('email', $data['email'])->first();
            if ($existingEntry) {
                return [
                    'success' => false,
                    'error' => 'This email is already registered on the waiting list',
                ];
            }

            // Check if user already exists
            $existingUser = User::where('email', $data['email'])->first();
            if ($existingUser) {
                // Link to existing account (as per user's decision)
                Log::info('Waiting list registration for existing user', [
                    'email' => $data['email'],
                    'user_id' => $existingUser->id,
                ]);
            }

            // Validate coupon if provided (for future use, no price calculation)
            $coupon = null;
            if (!empty($data['coupon_code'])) {
                // Basic coupon validation without plan requirement
                $coupon = Coupon::where('code', strtoupper($data['coupon_code']))
                    ->where('is_active', true)
                    ->first();

                if (!$coupon) {
                    return [
                        'success' => false,
                        'error' => 'Invalid coupon code',
                    ];
                }

                // Check if coupon is valid (dates, usage limits)
                if (!$coupon->isValid()) {
                    return [
                        'success' => false,
                        'error' => 'This coupon has expired or reached its usage limit',
                    ];
                }

                // Check user usage limit if email provided
                if (isset($data['email']) && $coupon->hasReachedUserLimit($data['email'])) {
                    return [
                        'success' => false,
                        'error' => 'You have already used this coupon the maximum number of times',
                    ];
                }
            }

            // Normalize selected_roles array
            $selectedRoles = [];
            if (!empty($data['selected_roles']) && is_array($data['selected_roles'])) {
                // Filter valid roles and remove duplicates
                $validRoles = ['wholesaler', 'investor'];
                $selectedRoles = array_values(array_unique(
                    array_filter($data['selected_roles'], function($role) use ($validRoles) {
                        return in_array($role, $validRoles);
                    })
                ));
            }

            // Create waiting list entry
            $entry = WaitingListEntry::create([
                'email' => $data['email'],
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'company_name' => $data['company_name'] ?? null,
                'selected_roles' => !empty($selectedRoles) ? $selectedRoles : null,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $data['coupon_code'] ?? null,
                'status' => 'pending',
                'verification_token' => Str::random(64),
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Increment coupon usage count if coupon was used
            if ($coupon) {
                $this->couponService->applyCoupon($coupon);
            }

            // Send welcome email
            try {
                Mail::to($entry->email)->send(new WaitingListWelcomeMail($entry));
            } catch (\Exception $e) {
                Log::warning('Failed to send waiting list welcome email', [
                    'entry_id' => $entry->id,
                    'email' => $entry->email,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail registration if email fails
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $entry->id,
                    'email' => $entry->email,
                    'verification_token' => $entry->verification_token,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Waiting list registration failed', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to register for waiting list',
            ];
        }
    }


    /**
     * Get entry status by email and token
     *
     * @param string $email
     * @param string $token
     * @return WaitingListEntry|null
     */
    public function getEntryByToken(string $email, string $token): ?WaitingListEntry
    {
        return WaitingListEntry::where('email', $email)
            ->where('verification_token', $token)
            ->first();
    }
}

