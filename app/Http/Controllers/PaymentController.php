<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Payments")]
class PaymentController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create payment intent for one-time payment
     */
    #[OA\Post(
        path: "/api/v1/payments/intent",
        summary: "Create payment intent",
        description: "Create a Stripe payment intent for a one-time payment. Returns client_secret for frontend confirmation.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 29.99, description: "Amount in dollars"),
                    new OA\Property(property: "currency", type: "string", example: "usd", description: "Currency code (default: usd)"),
                    new OA\Property(property: "description", type: "string", example: "Premium subscription payment", description: "Payment description"),
                    new OA\Property(property: "metadata", type: "object", description: "Additional metadata"),
                ]
            )
        ),
        tags: ["Payments"],
        responses: [
            new OA\Response(response: 200, description: "Payment intent created successfully"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function createIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.50|max:999999.99',
            'currency' => 'sometimes|string|size:3',
            'description' => 'sometimes|string|max:500',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = $request->user();
            $amount = (float) $request->input('amount');
            $currency = $request->input('currency', 'usd');
            $description = $request->input('description');
            $metadata = $request->input('metadata', []);

            $result = $this->stripeService->createPaymentIntent(
                $user,
                $amount,
                $currency,
                $description,
                $metadata
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment intent',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }

            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'payment',
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $currency,
                'stripe_payment_intent_id' => $result['payment_intent_id'],
                'stripe_customer_id' => $user->stripe_customer_id,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $result['payment_intent_id'],
                    'client_secret' => $result['client_secret'],
                    'amount' => $amount,
                    'currency' => $currency,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment intent creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Confirm payment intent
     */
    #[OA\Post(
        path: "/api/v1/payments/confirm",
        summary: "Confirm payment intent",
        description: "Confirm a payment intent after client-side confirmation. Updates transaction status.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_intent_id"],
                properties: [
                    new OA\Property(property: "payment_intent_id", type: "string", example: "pi_1234567890", description: "Stripe payment intent ID"),
                ]
            )
        ),
        tags: ["Payments"],
        responses: [
            new OA\Response(response: 200, description: "Payment confirmed successfully"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 404, description: "Transaction not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = $request->user();
            $paymentIntentId = $request->input('payment_intent_id');

            $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                ], 404);
            }

            $result = $this->stripeService->confirmPaymentIntent($paymentIntentId);

            if (!$result['success']) {
                $transaction->update([
                    'status' => 'failed',
                    'failure_reason' => $result['error'] ?? 'Payment not succeeded',
                    'stripe_response' => $result,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment confirmation failed',
                    'error' => $result['error'] ?? 'Payment not succeeded',
                    'status' => $result['status'] ?? 'unknown',
                ], 400);
            }

            // Update transaction
            $transaction->update([
                'status' => 'completed',
                'stripe_charge_id' => $result['charge_id'] ?? null,
                'processed_at' => now(),
                'stripe_response' => $result,
            ]);

            $transaction->load('subscription');

            return response()->json([
                'success' => true,
                'data' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            Log::error('Payment confirmation failed', [
                'user_id' => $request->user()->id,
                'payment_intent_id' => $request->input('payment_intent_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get user transaction history
     */
    #[OA\Get(
        path: "/api/v1/payments/transactions",
        summary: "Get user transaction history",
        description: "Get paginated list of all transactions for the authenticated user.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "page", in: "query", description: "Page number", schema: new OA\Schema(type: "integer", default: 1)),
            new OA\Parameter(name: "per_page", in: "query", description: "Items per page", schema: new OA\Schema(type: "integer", default: 15)),
            new OA\Parameter(name: "status", in: "query", description: "Filter by status", schema: new OA\Schema(type: "string", enum: ["pending", "completed", "failed", "refunded", "partially_refunded"])),
            new OA\Parameter(name: "type", in: "query", description: "Filter by type", schema: new OA\Schema(type: "string", enum: ["payment", "refund", "subscription", "one_time"])),
        ],
        tags: ["Payments"],
        responses: [
            new OA\Response(response: 200, description: "Transactions retrieved successfully"),
        ]
    )]
    public function transactions(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = $user->transactions()->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $transactions = $query->with('subscription.plan')->paginate($perPage);

        return TransactionResource::collection($transactions)->additional([
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * Get single transaction details
     */
    #[OA\Get(
        path: "/api/v1/payments/transactions/{transaction}",
        summary: "Get transaction details",
        description: "Get detailed information about a specific transaction.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "transaction", in: "path", required: true, description: "Transaction UUID", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        tags: ["Payments"],
        responses: [
            new OA\Response(response: 200, description: "Transaction retrieved successfully"),
            new OA\Response(response: 404, description: "Transaction not found"),
        ]
    )]
    public function show(Request $request, string $transactionId): JsonResponse
    {
        $user = $request->user();

        $transaction = Transaction::where('id', $transactionId)
            ->where('user_id', $user->id)
            ->with(['subscription.plan'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return new TransactionResource($transaction);
    }
}
