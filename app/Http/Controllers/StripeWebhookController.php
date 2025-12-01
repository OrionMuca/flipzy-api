<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle Stripe webhook events
     * 
     * This endpoint should be excluded from CSRF protection in VerifyCsrfToken middleware
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        // Allow testing without webhook secret (development only)
        if (!$webhookSecret) {
            if (config('app.debug')) {
                Log::warning('Stripe webhook secret not configured - skipping signature verification (DEBUG MODE)');
                // In debug mode, try to parse the event without verification
                try {
                    $event = json_decode($payload, true);
                    if (!$event || !isset($event['type'])) {
                        return response()->json(['error' => 'Invalid webhook payload'], 400);
                    }
                    // Create a mock event object structure
                    $event = (object) [
                        'type' => $event['type'],
                        'id' => $event['id'] ?? 'evt_test',
                        'data' => (object) ['object' => (object) ($event['data']['object'] ?? [])],
                    ];
                } catch (\Exception $e) {
                    Log::error('Failed to parse webhook payload', ['error' => $e->getMessage()]);
                    return response()->json(['error' => 'Invalid payload'], 400);
                }
            } else {
                Log::error('Stripe webhook secret not configured');
                return response()->json(['error' => 'Webhook secret not configured'], 500);
            }
        } else {
            // Normal webhook verification
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\UnexpectedValueException $e) {
                Log::error('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Invalid payload'], 400);
            } catch (SignatureVerificationException $e) {
                Log::error('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        // Handle the event
        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event->data->object);
                    break;

                case 'charge.refund.updated':
                    $this->handleRefundUpdated($event->data->object);
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
            }

            return response()->json(['received' => true]);
        } catch (\Exception $e) {
            Log::error('Error handling Stripe webhook', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Webhook handling failed'], 500);
        }
    }

    /**
     * Handle successful payment intent
     */
    protected function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'completed',
                'stripe_charge_id' => $paymentIntent->charges->data[0]->id ?? null,
                'processed_at' => now(),
                'stripe_response' => $paymentIntent->toArray(),
            ]);

            Log::info('Transaction updated to completed', [
                'transaction_id' => $transaction->id,
                'payment_intent_id' => $paymentIntent->id,
            ]);
        } else {
            Log::warning('Payment intent succeeded but transaction not found', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
        }
    }

    /**
     * Handle failed payment intent
     */
    protected function handlePaymentIntentFailed($paymentIntent): void
    {
        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($transaction) {
            $failureReason = $paymentIntent->last_payment_error->message ?? 'Payment failed';
            
            $transaction->update([
                'status' => 'failed',
                'failure_reason' => $failureReason,
                'stripe_response' => $paymentIntent->toArray(),
            ]);

            Log::info('Transaction updated to failed', [
                'transaction_id' => $transaction->id,
                'payment_intent_id' => $paymentIntent->id,
                'reason' => $failureReason,
            ]);
        } else {
            Log::warning('Payment intent failed but transaction not found', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
        }
    }

    /**
     * Handle charge refunded
     */
    protected function handleChargeRefunded($charge): void
    {
        $transaction = Transaction::where('stripe_charge_id', $charge->id)->first();

        if ($transaction) {
            // Check if full or partial refund
            $refundAmount = $charge->amount_refunded / 100; // Convert from cents
            $isFullRefund = $refundAmount >= $transaction->amount;

            $transaction->update([
                'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
                'stripe_refund_id' => $charge->refunds->data[0]->id ?? null,
                'stripe_response' => $charge->toArray(),
            ]);

            Log::info('Transaction refunded via webhook', [
                'transaction_id' => $transaction->id,
                'charge_id' => $charge->id,
                'refund_amount' => $refundAmount,
                'is_full_refund' => $isFullRefund,
            ]);
        } else {
            Log::warning('Charge refunded but transaction not found', [
                'charge_id' => $charge->id,
            ]);
        }
    }

    /**
     * Handle refund updated
     */
    protected function handleRefundUpdated($refund): void
    {
        // Find transaction by refund ID
        $refundTransaction = Transaction::where('stripe_refund_id', $refund->id)->first();

        if ($refundTransaction) {
            $refundTransaction->update([
                'status' => $refund->status === 'succeeded' ? 'completed' : 'failed',
                'stripe_response' => $refund->toArray(),
            ]);

            Log::info('Refund transaction updated', [
                'refund_transaction_id' => $refundTransaction->id,
                'refund_id' => $refund->id,
                'status' => $refund->status,
            ]);
        }
    }

    /**
     * Handle subscription created/updated
     */
    protected function handleSubscriptionUpdated($subscription): void
    {
        $user = User::where('stripe_customer_id', $subscription->customer)->first();

        if ($user) {
            $dbSubscription = Subscription::where('stripe_subscription_id', $subscription->id)->first();

            if ($dbSubscription) {
                // Map Stripe status to our status
                $status = match($subscription->status) {
                    'active', 'trialing' => 'active',
                    'canceled', 'unpaid' => 'cancelled',
                    'past_due' => 'past_due',
                    default => 'active',
                };

                $dbSubscription->update([
                    'status' => $status,
                    'ends_at' => $subscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($subscription->cancel_at) : null,
                    'cancelled_at' => $subscription->canceled_at ? \Carbon\Carbon::createFromTimestamp($subscription->canceled_at) : null,
                ]);

                Log::info('Subscription updated via webhook', [
                    'subscription_id' => $dbSubscription->id,
                    'stripe_subscription_id' => $subscription->id,
                    'status' => $status,
                ]);
            }
        }
    }

    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted($subscription): void
    {
        $dbSubscription = Subscription::where('stripe_subscription_id', $subscription->id)->first();

        if ($dbSubscription) {
            $dbSubscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => now(),
            ]);

            Log::info('Subscription cancelled via webhook', [
                'subscription_id' => $dbSubscription->id,
                'stripe_subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Handle checkout session completed
     */
    protected function handleCheckoutSessionCompleted($session): void
    {
        // Regular checkout session - handle normally if needed
        Log::info('Checkout session completed', [
            'checkout_session_id' => $session->id,
        ]);
    }
}
