<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                try {
                    $event = json_decode($payload, true);
                    if (!$event || !isset($event['type'])) {
                        return response()->json(['error' => 'Invalid webhook payload'], 400);
                    }
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

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'invoice.paid':
                    $this->handleInvoicePaid($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

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

                case 'identity.verification_session.verified':
                    $this->handleIdentityVerified($event->data->object);
                    break;

                case 'identity.verification_session.requires_input':
                    $this->handleIdentityFailed($event->data->object);
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
     * Handle checkout session completed.
     * This is the primary entry point for the Checkout Session subscription flow.
     * Creates the Subscription and Transaction records in our DB.
     */
    protected function handleCheckoutSessionCompleted($session): void
    {
        $mode = $session->mode ?? null;

        // Handle one-time property publish payments
        if ($mode === 'payment' && ($session->metadata->type ?? null) === 'property_publish') {
            $this->handlePropertyPublishPayment($session);
            return;
        }

        // Only process subscription-mode checkouts
        if ($mode !== 'subscription' || empty($session->subscription)) {
            return;
        }

        $userId = $session->metadata->user_id ?? null;
        $planId = $session->metadata->plan_id ?? null;

        if (!$userId || !$planId) {
            Log::warning('Checkout session completed but metadata is missing', [
                'session_id' => $session->id,
            ]);
            return;
        }

        // Idempotency guard — don't create a duplicate if webhook fires twice
        if (Subscription::where('stripe_subscription_id', $session->subscription)->exists()) {
            Log::info('Subscription already exists for checkout session, skipping', [
                'stripe_subscription_id' => $session->subscription,
            ]);
            return;
        }

        $user = User::find($userId);
        $plan = SubscriptionPlan::find($planId);

        if (!$user || !$plan) {
            Log::error('Checkout session completed but user or plan not found', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'session_id' => $session->id,
            ]);
            return;
        }

        // Retrieve the Stripe subscription to get its current status
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));
        $stripeSubscription = $stripe->subscriptions->retrieve($session->subscription);

        $status = match($stripeSubscription->status) {
            'active', 'trialing' => 'active',
            'past_due' => 'past_due',
            default => 'pending',
        };

        DB::beginTransaction();
        try {
            // Cancel any existing active subscription for this user
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => now(),
                'stripe_subscription_id' => $session->subscription,
                'stripe_customer_id' => $session->customer,
            ]);

            // Create an initial transaction record.
            // invoice.paid will also fire and can be used for renewal tracking.
            if (($session->amount_total ?? 0) > 0) {
                Transaction::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'type' => 'subscription',
                    'status' => $status === 'active' ? 'completed' : 'pending',
                    'amount' => $session->amount_total / 100,
                    'currency' => $session->currency ?? 'usd',
                    'stripe_customer_id' => $session->customer,
                    'description' => "Subscription: {$plan->name}",
                    'processed_at' => $status === 'active' ? now() : null,
                    'metadata' => [
                        'plan_id' => $plan->id,
                        'checkout_session_id' => $session->id,
                    ],
                ]);
            }

            DB::commit();

            Log::info('Subscription created from checkout session', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'stripe_subscription_id' => $session->subscription,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle property publish payment (one-time $199 fee)
     */
    protected function handlePropertyPublishPayment($session): void
    {
        $userId = $session->metadata->user_id ?? null;
        $propertyId = $session->metadata->property_id ?? null;

        if (!$userId || !$propertyId) {
            Log::warning('Property publish payment: missing metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $user = User::find($userId);
        $property = Property::find($propertyId);

        if (!$user || !$property) {
            Log::error('Property publish payment: user or property not found', [
                'user_id' => $userId,
                'property_id' => $propertyId,
            ]);
            return;
        }

        // Idempotency: skip if already paid
        if ($property->isPaid()) {
            Log::info('Property already paid, skipping', ['property_id' => $propertyId]);
            return;
        }

        DB::beginTransaction();
        try {
            $property->update([
                'payment_status' => 'paid',
                'status' => 'active',
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'property_id' => $property->id,
                'type' => 'one_time',
                'status' => 'completed',
                'amount' => ($session->amount_total ?? 19900) / 100,
                'currency' => $session->currency ?? 'usd',
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'stripe_customer_id' => $session->customer,
                'description' => "Property listing fee: {$property->title}",
                'processed_at' => now(),
                'metadata' => [
                    'property_id' => $property->id,
                    'checkout_session_id' => $session->id,
                    'type' => 'property_publish',
                ],
            ]);

            DB::commit();

            Log::info('Property published after payment', [
                'property_id' => $property->id,
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle invoice paid.
     * Fires on first payment and on every renewal. Keeps ends_at and status in sync.
     */
    protected function handleInvoicePaid($invoice): void
    {
        if (empty($invoice->subscription)) {
            return;
        }

        $dbSubscription = Subscription::with('plan')
            ->where('stripe_subscription_id', $invoice->subscription)
            ->first();

        if (!$dbSubscription) {
            Log::warning('invoice.paid: subscription not found', [
                'stripe_subscription_id' => $invoice->subscription,
            ]);
            return;
        }

        // Re-activate if it was past_due
        $dbSubscription->update(['status' => 'active']);

        // Avoid duplicate transactions (checkout.session.completed already created one for the first payment)
        $alreadyExists = Transaction::where('stripe_payment_intent_id', $invoice->payment_intent)->exists();

        if (!$alreadyExists && !empty($invoice->payment_intent) && ($invoice->amount_paid ?? 0) > 0) {
            Transaction::create([
                'user_id' => $dbSubscription->user_id,
                'subscription_id' => $dbSubscription->id,
                'type' => 'subscription',
                'status' => 'completed',
                'amount' => $invoice->amount_paid / 100,
                'currency' => $invoice->currency ?? 'usd',
                'stripe_payment_intent_id' => $invoice->payment_intent,
                'stripe_customer_id' => $invoice->customer,
                'description' => 'Subscription renewal: ' . ($dbSubscription->plan->name ?? 'Plan'),
                'processed_at' => now(),
            ]);
        }

        Log::info('invoice.paid handled', [
            'subscription_id' => $dbSubscription->id,
            'amount_paid' => ($invoice->amount_paid ?? 0) / 100,
        ]);
    }

    /**
     * Handle invoice payment failed.
     * Fires when a renewal charge fails. Marks the subscription as past_due.
     */
    protected function handleInvoicePaymentFailed($invoice): void
    {
        if (empty($invoice->subscription)) {
            return;
        }

        $dbSubscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();

        if ($dbSubscription) {
            $dbSubscription->update(['status' => 'past_due']);

            Log::warning('Subscription marked as past_due due to failed invoice payment', [
                'subscription_id' => $dbSubscription->id,
                'stripe_subscription_id' => $invoice->subscription,
            ]);
        }
    }

    /**
     * Handle subscription created/updated.
     * Syncs status and cancellation dates. Does NOT create new subscriptions —
     * that is handled by handleCheckoutSessionCompleted.
     */
    protected function handleSubscriptionUpdated($subscription): void
    {
        $dbSubscription = Subscription::where('stripe_subscription_id', $subscription->id)->first();

        if (!$dbSubscription) {
            // Not found means it was created through a flow we don't track, or checkout.session.completed
            // hasn't fired yet. Safe to skip.
            return;
        }

        $status = match($subscription->status) {
            'active', 'trialing' => 'active',
            'canceled', 'unpaid' => 'cancelled',
            'past_due' => 'past_due',
            default => 'active',
        };

        // ends_at is only meaningful when the subscription is scheduled to end.
        // For a normally-renewing subscription it stays null.
        $endsAt = null;
        if ($subscription->cancel_at_period_end && !empty($subscription->current_period_end)) {
            $endsAt = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);
        } elseif (!empty($subscription->cancel_at)) {
            $endsAt = \Carbon\Carbon::createFromTimestamp($subscription->cancel_at);
        }

        $dbSubscription->update([
            'status' => $status,
            'ends_at' => $endsAt,
            'cancelled_at' => !empty($subscription->canceled_at)
                ? \Carbon\Carbon::createFromTimestamp($subscription->canceled_at)
                : null,
        ]);

        Log::info('Subscription updated via webhook', [
            'subscription_id' => $dbSubscription->id,
            'stripe_status' => $subscription->status,
            'local_status' => $status,
            'ends_at' => $endsAt,
        ]);
    }

    /**
     * Handle subscription deleted (immediately cancelled on Stripe).
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
     * Handle successful payment intent (one-time payments).
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
     * Handle failed payment intent (one-time payments).
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
     * Handle charge refunded.
     */
    protected function handleChargeRefunded($charge): void
    {
        $transaction = Transaction::where('stripe_charge_id', $charge->id)->first();

        if ($transaction) {
            $refundAmount = $charge->amount_refunded / 100;
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
     * Handle identity verification session verified.
     * Fires when Stripe confirms the user's identity documents are valid.
     */
    protected function handleIdentityVerified($session): void
    {
        $userId = $session->metadata->user_id ?? null;

        if (!$userId) {
            Log::warning('identity.verification_session.verified: missing user_id in metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $user = User::find($userId);

        if (!$user) {
            Log::warning('identity.verification_session.verified: user not found', [
                'user_id' => $userId,
            ]);
            return;
        }

        $user->update([
            'id_verification_status' => 'verified',
            'id_verified_at' => now(),
        ]);

        Log::info('User identity verified', [
            'user_id' => $user->id,
            'session_id' => $session->id,
        ]);
    }

    /**
     * Handle identity verification session requires_input.
     * Fires when verification failed or was rejected (bad document, liveness fail, etc).
     */
    protected function handleIdentityFailed($session): void
    {
        $userId = $session->metadata->user_id ?? null;

        if (!$userId) {
            return;
        }

        $user = User::find($userId);

        if (!$user) {
            return;
        }

        // Only mark as failed if not already verified
        if ($user->id_verification_status !== 'verified') {
            $user->update([
                'id_verification_status' => 'failed',
            ]);
        }

        Log::warning('User identity verification failed', [
            'user_id' => $user->id,
            'session_id' => $session->id,
            'last_error' => $session->last_error ?? null,
        ]);
    }

    /**
     * Handle refund updated.
     */
    protected function handleRefundUpdated($refund): void
    {
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
}
