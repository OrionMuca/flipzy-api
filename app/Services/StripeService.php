<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret_key'));
    }

    /**
     * Create or retrieve Stripe customer for user
     */
    public function getOrCreateCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            try {
                $customer = $this->stripe->customers->retrieve($user->stripe_customer_id);
                return $customer->id;
            } catch (ApiErrorException $e) {
                // Customer doesn't exist, create new one
                Log::warning("Stripe customer not found, creating new one", [
                    'user_id' => $user->id,
                    'stripe_customer_id' => $user->stripe_customer_id,
                ]);
            }
        }

        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Create payment intent for one-time payment
     */
    public function createPaymentIntent(
        User $user,
        float $amount,
        string $currency = 'usd',
        ?string $description = null,
        array $metadata = []
    ): array {
        try {
            $customerId = $this->getOrCreateCustomer($user);

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int)($amount * 100), // Convert to cents
                'currency' => $currency,
                'customer' => $customerId,
                'description' => $description ?? "Payment from {$user->name}",
                'metadata' => array_merge($metadata, [
                    'user_id' => $user->id,
                ]),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment intent creation failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm payment intent
     */
    public function confirmPaymentIntent(string $paymentIntentId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status === 'succeeded') {
                return [
                    'success' => true,
                    'status' => 'succeeded',
                    'charge_id' => $paymentIntent->charges->data[0]->id ?? null,
                    'payment_intent' => $paymentIntent,
                ];
            }

            return [
                'success' => false,
                'status' => $paymentIntent->status,
                'error' => 'Payment not succeeded',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment intent confirmation failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create refund
     */
    public function createRefund(
        Transaction $transaction,
        ?float $amount = null,
        ?string $reason = null
    ): array {
        try {
            if (!$transaction->stripe_charge_id) {
                return [
                    'success' => false,
                    'error' => 'No charge ID found for this transaction',
                ];
            }

            $refundData = [
                'charge' => $transaction->stripe_charge_id,
            ];

            if ($amount !== null) {
                $refundData['amount'] = (int)($amount * 100); // Convert to cents
            }

            if ($reason) {
                $refundData['reason'] = $reason; // duplicate, fraudulent, requested_by_customer
            }

            $refund = $this->stripe->refunds->create($refundData);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status,
                'refund' => $refund,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund creation failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create subscription
     */
    public function createSubscription(
        User $user,
        SubscriptionPlan $plan
    ): array {
        try {
            if (!$plan->stripe_price_id) {
                return [
                    'success' => false,
                    'error' => 'Subscription plan does not have a Stripe price ID',
                ];
            }

            $customerId = $this->getOrCreateCustomer($user);

            $subscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    ['price' => $plan->stripe_price_id],
                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ],
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'subscription' => $subscription,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription creation failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $stripeSubscriptionId, bool $immediately = false): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            if ($immediately) {
                $subscription = $this->stripe->subscriptions->cancel($stripeSubscriptionId);
            } else {
                $subscription = $this->stripe->subscriptions->update($stripeSubscriptionId, [
                    'cancel_at_period_end' => true,
                ]);
            }

            return [
                'success' => true,
                'subscription' => $subscription,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription cancellation failed', [
                'subscription_id' => $stripeSubscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve payment intent
     */
    public function retrievePaymentIntent(string $paymentIntentId)
    {
        try {
            return $this->stripe->paymentIntents->retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment intent retrieval failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Retrieve charge
     */
    public function retrieveCharge(string $chargeId)
    {
        try {
            return $this->stripe->charges->retrieve($chargeId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe charge retrieval failed', [
                'charge_id' => $chargeId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

