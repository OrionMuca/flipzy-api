<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Refunds")]
class RefundController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create refund for a transaction
     */
    #[OA\Post(
        path: "/api/v1/refunds",
        summary: "Create refund",
        description: "Create a full or partial refund for a completed transaction. Only the transaction owner or admin can create refunds.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["transaction_id"],
                properties: [
                    new OA\Property(property: "transaction_id", type: "string", format: "uuid", example: "019a5882-c272-71eb-97c4-3d81aa7a4807", description: "Transaction UUID to refund"),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 15.00, description: "Partial refund amount (optional, defaults to full refund)"),
                    new OA\Property(property: "reason", type: "string", enum: ["duplicate", "fraudulent", "requested_by_customer"], example: "requested_by_customer", description: "Reason for refund"),
                ]
            )
        ),
        tags: ["Refunds"],
        responses: [
            new OA\Response(response: 200, description: "Refund created successfully"),
            new OA\Response(response: 400, description: "Validation error or refund not allowed"),
            new OA\Response(response: 404, description: "Transaction not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|uuid|exists:transactions,id',
            'amount' => 'sometimes|numeric|min:0.50',
            'reason' => 'sometimes|string|in:duplicate,fraudulent,requested_by_customer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = $request->user();
            $transactionId = $request->input('transaction_id');
            $refundAmount = $request->input('amount');
            $reason = $request->input('reason', 'requested_by_customer');

            $transaction = Transaction::findOrFail($transactionId);

            // Check authorization: user must own transaction or be admin
            if ($transaction->user_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to refund this transaction',
                ], 403);
            }

            // Validate transaction can be refunded
            if ($transaction->isRefunded()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction has already been refunded',
                ], 400);
            }

            if ($transaction->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only completed transactions can be refunded',
                ], 400);
            }

            // Validate refund amount
            if ($refundAmount !== null) {
                if ($refundAmount > $transaction->amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Refund amount cannot exceed transaction amount',
                    ], 400);
                }

                // Check if partial refund already exists
                if ($transaction->status === 'partially_refunded') {
                    // Calculate already refunded amount
                    $alreadyRefunded = Transaction::where('type', 'refund')
                        ->where('metadata->original_transaction_id', $transaction->id)
                        ->sum('amount');

                    if (($alreadyRefunded + $refundAmount) > $transaction->amount) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Total refund amount would exceed transaction amount',
                        ], 400);
                    }
                }
            }

            DB::beginTransaction();

            try {
                // Create refund via Stripe
                $result = $this->stripeService->createRefund(
                    $transaction,
                    $refundAmount,
                    $reason
                );

                if (!$result['success']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create refund',
                        'error' => $result['error'] ?? 'Unknown error',
                    ], 500);
                }

                // Determine if full or partial refund
                $isFullRefund = $refundAmount === null || $refundAmount == $transaction->amount;

                // Update original transaction status
                $transaction->update([
                    'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
                    'stripe_refund_id' => $result['refund_id'],
                ]);

                // Create refund transaction record
                $refundTransaction = Transaction::create([
                    'user_id' => $transaction->user_id,
                    'subscription_id' => $transaction->subscription_id,
                    'type' => 'refund',
                    'status' => 'completed',
                    'amount' => $result['amount'],
                    'currency' => $transaction->currency,
                    'stripe_refund_id' => $result['refund_id'],
                    'stripe_customer_id' => $transaction->stripe_customer_id,
                    'description' => "Refund for transaction {$transaction->id}",
                    'metadata' => [
                        'original_transaction_id' => $transaction->id,
                        'reason' => $reason,
                        'is_full_refund' => $isFullRefund,
                    ],
                    'stripe_response' => $result,
                    'processed_at' => now(),
                ]);

                DB::commit();

                $refundTransaction->load('subscription');

                return response()->json([
                    'success' => true,
                    'data' => new TransactionResource($refundTransaction),
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Refund creation failed', [
                'user_id' => $request->user()->id,
                'transaction_id' => $request->input('transaction_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create refund',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get refund details
     */
    #[OA\Get(
        path: "/api/v1/refunds/{refund}",
        summary: "Get refund details",
        description: "Get detailed information about a specific refund transaction.",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "refund", in: "path", required: true, description: "Refund transaction UUID", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        tags: ["Refunds"],
        responses: [
            new OA\Response(response: 200, description: "Refund retrieved successfully"),
            new OA\Response(response: 404, description: "Refund not found"),
        ]
    )]
    public function show(Request $request, string $refundId): JsonResponse
    {
        $user = $request->user();

        $refund = Transaction::where('id', $refundId)
            ->where('type', 'refund')
            ->where('user_id', $user->id)
            ->first();

        if (!$refund) {
            return response()->json([
                'success' => false,
                'message' => 'Refund not found',
            ], 404);
        }

        $refund->load('subscription');

        return new TransactionResource($refund);
    }
}
