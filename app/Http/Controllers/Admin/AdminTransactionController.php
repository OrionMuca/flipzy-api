<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Transactions")]
class AdminTransactionController extends Controller
{
    /**
     * List all transactions
     */
    #[OA\Get(
        path: "/admin/transactions",
        summary: "List all transactions (Admin only)",
        description: "Get a paginated list of all transactions with optional filtering and sorting",
        tags: ["Admin - Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "page", in: "query", description: "Page number", schema: new OA\Schema(type: "integer", default: 1)),
            new OA\Parameter(name: "per_page", in: "query", description: "Items per page", schema: new OA\Schema(type: "integer", default: 15)),
            new OA\Parameter(name: "status", in: "query", description: "Filter by status", schema: new OA\Schema(type: "string", enum: ["pending", "completed", "failed", "refunded", "partially_refunded"])),
            new OA\Parameter(name: "type", in: "query", description: "Filter by type", schema: new OA\Schema(type: "string", enum: ["payment", "refund", "subscription", "one_time"])),
            new OA\Parameter(name: "user_id", in: "query", description: "Filter by user ID", schema: new OA\Schema(type: "string", format: "uuid")),
            new OA\Parameter(name: "date_from", in: "query", description: "Filter from date (YYYY-MM-DD)", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "date_to", in: "query", description: "Filter to date (YYYY-MM-DD)", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "sort_by", in: "query", description: "Sort field", schema: new OA\Schema(type: "string", default: "created_at")),
            new OA\Parameter(name: "sort_order", in: "query", description: "Sort order", schema: new OA\Schema(type: "string", enum: ["asc", "desc"], default: "desc")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Transactions retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden - Admin only"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Transaction::with(['user:id,name,email', 'subscription.plan']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $transactions = $query->paginate($perPage);

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
     * Get transaction statistics
     */
    #[OA\Get(
        path: "/admin/transactions/stats",
        summary: "Get transaction statistics (Admin only)",
        description: "Get aggregated statistics about transactions including totals, counts, and trends",
        tags: ["Admin - Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "date_from", in: "query", description: "Filter from date (YYYY-MM-DD)", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "date_to", in: "query", description: "Filter to date (YYYY-MM-DD)", schema: new OA\Schema(type: "string", format: "date")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Statistics retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden - Admin only"),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $query = Transaction::query();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $stats = [
            'total_revenue' => (float) $query->clone()->where('status', 'completed')->where('type', 'payment')->sum('amount'),
            'total_refunded' => (float) $query->clone()->where('type', 'refund')->where('status', 'completed')->sum('amount'),
            'net_revenue' => 0,
            'total_transactions' => $query->clone()->count(),
            'completed_transactions' => $query->clone()->where('status', 'completed')->count(),
            'failed_transactions' => $query->clone()->where('status', 'failed')->count(),
            'refunded_transactions' => $query->clone()->whereIn('status', ['refunded', 'partially_refunded'])->count(),
            'pending_transactions' => $query->clone()->where('status', 'pending')->count(),
            'by_status' => $query->clone()
                ->select('status', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->status => [
                        'count' => $item->count,
                        'total' => (float) $item->total,
                    ]];
                }),
            'by_type' => $query->clone()
                ->select('type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
                ->groupBy('type')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->type => [
                        'count' => $item->count,
                        'total' => (float) $item->total,
                    ]];
                }),
        ];

        $stats['net_revenue'] = $stats['total_revenue'] - $stats['total_refunded'];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get single transaction details
     */
    #[OA\Get(
        path: "/admin/transactions/{transaction}",
        summary: "Get transaction details (Admin only)",
        description: "Get detailed information about a specific transaction",
        tags: ["Admin - Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "transaction", in: "path", required: true, description: "Transaction UUID", schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Transaction retrieved successfully"),
            new OA\Response(response: 404, description: "Transaction not found"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden - Admin only"),
        ]
    )]
    public function show(string $transactionId): JsonResponse
    {
        $transaction = Transaction::with(['user:id,name,email', 'subscription.plan'])
            ->findOrFail($transactionId);

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }
}
