<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Subscriptions")]
class SubscriptionController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Get available subscription plans
     */
    #[OA\Get(
        path: "/api/v1/subscriptions/plans",
        summary: "Get available subscription plans",
        description: "Get all active subscription plans available for purchase",
        security: [["bearerAuth" => []]],
        tags: ["Subscriptions"],
        responses: [
            new OA\Response(response: 200, description: "Plans retrieved successfully"),
        ]
    )]
    public function plans(): AnonymousResourceCollection
    {
        $plans = SubscriptionPlan::active()->get();

        return SubscriptionPlanResource::collection($plans);
    }

    /**
     * Create subscription checkout session
     * This creates a Stripe Checkout Session for subscription payment
     */
    #[OA\Post(
        path: "/api/v1/subscriptions/checkout",
        summary: "Create subscription checkout session",
        description: "Create a Stripe Checkout Session for subscription. Returns checkout URL for redirect.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["plan_id"],
                properties: [
                    new OA\Property(property: "plan_id", type: "string", format: "uuid", example: "019a5882-c272-71eb-97c4-3d81aa7a4807", description: "Subscription plan UUID"),
                    new OA\Property(property: "success_url", type: "string", example: "https://yourapp.com/success", description: "URL to redirect after successful payment"),
                    new OA\Property(property: "cancel_url", type: "string", example: "https://yourapp.com/cancel", description: "URL to redirect if user cancels"),
                ]
            )
        ),
        tags: ["Subscriptions"],
        responses: [
            new OA\Response(response: 200, description: "Checkout session created successfully"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 404, description: "Plan not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'success_url' => 'sometimes|url',
            'cancel_url' => 'sometimes|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = $request->user();
            $plan = SubscriptionPlan::findOrFail($request->input('plan_id'));

            if (!$plan->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription plan is not available',
                ], 400);
            }

            if (!$plan->stripe_price_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan is not configured for payments',
                ], 400);
            }

            $customerId = $this->stripeService->getOrCreateCustomer($user);

            // Create Stripe Checkout Session
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));
            $checkoutSession = $stripe->checkout->sessions->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $request->input('success_url', url('/subscription/success?session_id={CHECKOUT_SESSION_ID}')),
                'cancel_url' => $request->input('cancel_url', url('/subscription/cancel')),
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'checkout_url' => $checkoutSession->url,
                    'session_id' => $checkoutSession->id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription checkout creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Create subscription directly (alternative to checkout)
     * This creates a subscription directly using payment method
     */
    #[OA\Post(
        path: "/api/v1/subscriptions",
        summary: "Create subscription",
        description: "Create a subscription directly using a payment method ID from Stripe Elements. Alternative to checkout session.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["plan_id", "payment_method_id"],
                properties: [
                    new OA\Property(property: "plan_id", type: "string", format: "uuid", example: "019a5882-c272-71eb-97c4-3d81aa7a4807", description: "Subscription plan UUID"),
                    new OA\Property(property: "payment_method_id", type: "string", example: "pm_1234567890", description: "Stripe payment method ID from frontend"),
                ]
            )
        ),
        tags: ["Subscriptions"],
        responses: [
            new OA\Response(response: 200, description: "Subscription created successfully"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 404, description: "Plan not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'payment_method_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = $request->user();
            $plan = SubscriptionPlan::findOrFail($request->input('plan_id'));

            if (!$plan->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription plan is not available',
                ], 400);
            }

            if (!$plan->stripe_price_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan is not configured for payments',
                ], 400);
            }

            // Check if user already has active subscription
            $existingSubscription = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($existingSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription. Please cancel it first.',
                ], 400);
            }

            DB::beginTransaction();

            try {
                $customerId = $this->stripeService->getOrCreateCustomer($user);

                // Attach payment method to customer
                $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));
                $stripe->paymentMethods->attach($request->input('payment_method_id'), [
                    'customer' => $customerId,
                ]);

                // Set as default payment method
                $stripe->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $request->input('payment_method_id'),
                    ],
                ]);

                // Create subscription
                $result = $this->stripeService->createSubscription($user, $plan);

                if (!$result['success']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create subscription',
                        'error' => $result['error'] ?? 'Unknown error',
                    ], 500);
                }

                // Create subscription record
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => $result['status'] === 'active' ? 'active' : 'pending',
                    'starts_at' => now(),
                    'ends_at' => $result['subscription']->current_period_end ? \Carbon\Carbon::createFromTimestamp($result['subscription']->current_period_end) : null,
                    'stripe_subscription_id' => $result['subscription_id'],
                    'stripe_customer_id' => $customerId,
                ]);

                // Create transaction record
                $transaction = \App\Models\Transaction::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'type' => 'subscription',
                    'status' => $result['status'] === 'active' ? 'completed' : 'pending',
                    'amount' => $plan->price,
                    'currency' => 'usd',
                    'stripe_customer_id' => $customerId,
                    'description' => "Subscription: {$plan->name}",
                    'metadata' => [
                        'plan_id' => $plan->id,
                        'subscription_id' => $subscription->id,
                    ],
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'status' => $subscription->status,
                        'plan' => [
                            'id' => $plan->id,
                            'name' => $plan->name,
                            'slug' => $plan->slug,
                        ],
                        'starts_at' => $subscription->starts_at->toISOString(),
                        'ends_at' => $subscription->ends_at?->toISOString(),
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Subscription creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get current user's subscription
     */
    #[OA\Get(
        path: "/api/v1/subscriptions/current",
        summary: "Get current subscription",
        description: "Get the current active subscription for the authenticated user",
        security: [["bearerAuth" => []]],
        tags: ["Subscriptions"],
        responses: [
            new OA\Response(response: 200, description: "Subscription retrieved successfully"),
            new OA\Response(response: 404, description: "No active subscription found"),
        ]
    )]
    public function current(Request $request): JsonResponse|SubscriptionResource
    {
        $user = $request->user();
        $subscription = $user->subscription()->with('plan')->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        return new SubscriptionResource($subscription);
    }

    /**
     * Cancel subscription
     */
    #[OA\Post(
        path: "/api/v1/subscriptions/cancel",
        summary: "Cancel subscription",
        description: "Cancel the current subscription. Can cancel immediately or at period end.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "immediately", type: "boolean", example: false, description: "Cancel immediately (true) or at period end (false, default)"),
                ]
            )
        ),
        tags: ["Subscriptions"],
        responses: [
            new OA\Response(response: 200, description: "Subscription cancelled successfully"),
            new OA\Response(response: 404, description: "No active subscription found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        if (!$subscription->stripe_subscription_id) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not linked to Stripe',
            ], 400);
        }

        try {
            $immediately = $request->input('immediately', false);
            $result = $this->stripeService->cancelSubscription($subscription->stripe_subscription_id, $immediately);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel subscription',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }

            if ($immediately) {
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'ends_at' => now(),
                ]);
            } else {
                $subscription->update([
                    'cancelled_at' => now(),
                    'ends_at' => $result['subscription']->current_period_end ? \Carbon\Carbon::createFromTimestamp($result['subscription']->current_period_end) : null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $immediately ? 'Subscription cancelled immediately' : 'Subscription will be cancelled at period end',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'ends_at' => $subscription->ends_at?->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get subscription history
     */
    #[OA\Get(
        path: "/api/v1/subscriptions/history",
        summary: "Get subscription history",
        description: "Get all subscriptions (active and past) for the authenticated user",
        security: [["bearerAuth" => []]],
        tags: ["Subscriptions"],
        responses: [
            new OA\Response(response: 200, description: "Subscription history retrieved successfully"),
        ]
    )]
    public function history(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $subscriptions = $user->subscriptions()->with('plan')->orderBy('created_at', 'desc')->get();

        return SubscriptionResource::collection($subscriptions);
    }
}
