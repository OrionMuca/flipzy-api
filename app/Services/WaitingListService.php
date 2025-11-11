<?php

namespace App\Services;

use App\Models\WaitingListEntry;
use App\Models\WaitingListTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Coupon;
use App\Mail\WaitingListWelcomeMail;
use App\Mail\PaymentConfirmationMail;
use App\Services\CouponService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WaitingListService
{
    protected CouponService $couponService;
    protected StripeService $stripeService;

    public function __construct(CouponService $couponService, StripeService $stripeService)
    {
        $this->couponService = $couponService;
        $this->stripeService = $stripeService;
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
            // Validate email doesn't already exist in waiting list
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

            $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

            // Validate and calculate discount if coupon provided
            $coupon = null;
            $originalPrice = (float) $plan->price;
            $discountedPrice = $originalPrice;
            $discountAmount = 0;

            if (!empty($data['coupon_code'])) {
                $validation = $this->couponService->validateAndCalculate(
                    $data['coupon_code'],
                    $data['subscription_plan_id'],
                    $data['email']
                );

                if (!$validation['valid']) {
                    return [
                        'success' => false,
                        'error' => $validation['error'],
                    ];
                }

                $coupon = Coupon::find($validation['coupon']['id']);
                $originalPrice = $validation['original_price'];
                $discountedPrice = $validation['discounted_price'];
                $discountAmount = $validation['discount_amount'];
            }

            // Create waiting list entry
            $entry = WaitingListEntry::create([
                'email' => $data['email'],
                'name' => $data['name'],
                'subscription_plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $data['coupon_code'] ?? null,
                'status' => 'pending',
                'original_price' => $originalPrice,
                'discounted_price' => $discountedPrice,
                'discount_amount' => $discountAmount,
                'verification_token' => Str::random(64),
                'metadata' => $data['metadata'] ?? [],
            ]);

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
     * Create Stripe checkout session for waiting list entry
     *
     * @param WaitingListEntry $entry
     * @param array $options
     * @return array
     */
    public function createCheckoutSession(WaitingListEntry $entry, array $options = []): array
    {
        try {
            $plan = $entry->plan;

            if (!$plan->stripe_price_id) {
                return [
                    'success' => false,
                    'error' => 'Subscription plan is not configured for payments',
                ];
            }

            // Create or get Stripe customer
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));
            
            // Check if customer already exists
            $customerId = $entry->stripe_customer_id;
            if (!$customerId) {
                $customer = $stripe->customers->create([
                    'email' => $entry->email,
                    'name' => $entry->name,
                    'metadata' => [
                        'waiting_list_entry_id' => $entry->id,
                        'type' => 'waiting_list',
                    ],
                ]);
                $customerId = $customer->id;
                $entry->update(['stripe_customer_id' => $customerId]);
            }

            // Prepare line items
            $lineItems = [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]];

            // Prepare checkout session parameters
            $sessionParams = [
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'subscription',
                'success_url' => $options['success_url'] ?? url('/waiting-list/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => $options['cancel_url'] ?? url('/waiting-list/cancel'),
                'metadata' => [
                    'waiting_list_entry_id' => $entry->id,
                    'plan_id' => $plan->id,
                    'type' => 'waiting_list',
                ],
            ];

            // Apply discount if coupon exists
            if ($entry->coupon_id && $entry->discount_amount > 0) {
                // Create Stripe coupon/promotion code if needed
                // For now, we'll use discounts array or create a Stripe coupon
                // Note: Stripe Checkout supports promotion codes natively
                // We can either:
                // 1. Create a Stripe coupon and use allow_promotion_codes: true
                // 2. Create a Stripe coupon and apply it directly via discounts
                
                // Option: Create Stripe coupon on-the-fly
                try {
                    $stripeCoupon = $stripe->coupons->create([
                        'id' => 'wl_' . $entry->coupon->code . '_' . $entry->id,
                        'percent_off' => $entry->coupon->discount_type === 'percentage' ? $entry->coupon->discount_value : null,
                        'amount_off' => $entry->coupon->discount_type === 'fixed_amount' ? (int)($entry->coupon->discount_value * 100) : null,
                        'currency' => 'usd',
                        'duration' => 'once', // One-time discount for subscription
                        'metadata' => [
                            'waiting_list_entry_id' => $entry->id,
                            'coupon_id' => $entry->coupon->id,
                        ],
                    ]);

                    $sessionParams['discounts'] = [[
                        'coupon' => $stripeCoupon->id,
                    ]];
                } catch (\Exception $e) {
                    Log::warning('Failed to create Stripe coupon, using manual discount calculation', [
                        'entry_id' => $entry->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Fallback: We've already calculated the discount, Stripe will charge the discounted amount
                    // We'll need to handle this in webhook or adjust the price
                }
            }

            // Create checkout session
            $checkoutSession = $stripe->checkout->sessions->create($sessionParams);

            // Update entry with checkout session ID
            $entry->update([
                'stripe_checkout_session_id' => $checkoutSession->id,
            ]);

            return [
                'success' => true,
                'data' => [
                    'checkout_url' => $checkoutSession->url,
                    'session_id' => $checkoutSession->id,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Waiting list checkout session creation failed', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create checkout session',
            ];
        }
    }

    /**
     * Handle successful payment (called from webhook)
     *
     * @param string $checkoutSessionId
     * @return array
     */
    public function handlePaymentSuccess(string $checkoutSessionId): array
    {
        try {
            // Only begin transaction if not already in one
            if (!DB::transactionLevel()) {
                DB::beginTransaction();
                $shouldCommit = true;
            } else {
                $shouldCommit = false;
            }

            $entry = WaitingListEntry::where('stripe_checkout_session_id', $checkoutSessionId)->first();

            if (!$entry) {
                return [
                    'success' => false,
                    'error' => 'Waiting list entry not found',
                ];
            }

            // Get checkout session from Stripe
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));
            $session = $stripe->checkout->sessions->retrieve($checkoutSessionId);

            if ($session->payment_status !== 'paid') {
                return [
                    'success' => false,
                    'error' => 'Payment not completed',
                ];
            }

            // Get subscription ID from session
            $subscriptionId = $session->subscription;
            if ($subscriptionId) {
                $entry->update([
                    'stripe_subscription_id' => $subscriptionId,
                ]);
            }

            // Create transaction record
            $transaction = WaitingListTransaction::create([
                'waiting_list_entry_id' => $entry->id,
                'type' => 'subscription',
                'status' => 'completed',
                'amount' => $entry->discounted_price,
                'currency' => 'usd',
                'original_amount' => $entry->original_price,
                'discount_amount' => $entry->discount_amount,
                'stripe_payment_intent_id' => $session->payment_intent,
                'description' => "Waiting List Subscription: {$entry->plan->name}",
                'metadata' => [
                    'checkout_session_id' => $checkoutSessionId,
                    'subscription_id' => $subscriptionId,
                ],
                'processed_at' => now(),
            ]);

            // Increment coupon usage if applicable
            if ($entry->coupon_id) {
                $this->couponService->applyCoupon($entry->coupon);
            }

            // Mark entry as payment completed
            $entry->markPaymentCompleted();

            // Send payment confirmation email
            try {
                Mail::to($entry->email)->send(new PaymentConfirmationMail($entry));
            } catch (\Exception $e) {
                Log::warning('Failed to send payment confirmation email', [
                    'entry_id' => $entry->id,
                    'email' => $entry->email,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail payment processing if email fails
            }

            if ($shouldCommit) {
                DB::commit();
            }

            return [
                'success' => true,
                'entry' => $entry,
                'transaction' => $transaction,
            ];
        } catch (\Exception $e) {
            if (isset($shouldCommit) && $shouldCommit) {
                DB::rollBack();
            }
            Log::error('Waiting list payment success handling failed', [
                'checkout_session_id' => $checkoutSessionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process payment',
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

