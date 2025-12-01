<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WaitingListEntryResource;
use App\Models\WaitingListEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Waiting List Management")]
class AdminWaitingListController extends Controller
{
    /**
     * List all waiting list entries
     */
    #[OA\Get(
        path: "/api/v1/admin/waiting-list",
        summary: "List all waiting list entries (Admin only)",
        description: "Get a paginated list of all waiting list entries",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Entries retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = WaitingListEntry::with(['coupon']);

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if (in_array($status, ['pending', 'account_created', 'cancelled'])) {
                $query->where('status', $status);
            }
        }

        // Search by email
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $entries = $query->paginate($perPage);

        return WaitingListEntryResource::collection($entries)->additional([
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * Get waiting list entry details
     */
    #[OA\Get(
        path: "/api/v1/admin/waiting-list/{id}",
        summary: "Get waiting list entry details (Admin only)",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Entry retrieved successfully"),
            new OA\Response(response: 404, description: "Entry not found"),
        ]
    )]
    public function show(Request $request, string $id): WaitingListEntryResource
    {
        $entry = WaitingListEntry::with(['coupon'])->findOrFail($id);

        return new WaitingListEntryResource($entry);
    }

    /**
     * Get waiting list statistics
     */
    #[OA\Get(
        path: "/api/v1/admin/waiting-list/stats",
        summary: "Get waiting list statistics (Admin only)",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Statistics retrieved successfully"),
        ]
    )]
    public function stats(): JsonResponse
    {
        $total = WaitingListEntry::count();
        $pending = WaitingListEntry::where('status', 'pending')->count();
        $accountCreated = WaitingListEntry::where('status', 'account_created')->count();
        $cancelled = WaitingListEntry::where('status', 'cancelled')->count();

        // Count entries with coupons
        $withCoupons = WaitingListEntry::whereNotNull('coupon_id')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'by_status' => [
                    'pending' => $pending,
                    'account_created' => $accountCreated,
                    'cancelled' => $cancelled,
                ],
                'with_coupons' => $withCoupons,
            ],
        ]);
    }
}
