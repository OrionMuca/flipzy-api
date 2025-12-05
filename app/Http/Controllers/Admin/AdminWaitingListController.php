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
        path: "/admin/waiting-list",
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

        // Search by email, name, phone_number, or company_name
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
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
        path: "/admin/waiting-list/{id}",
        summary: "Get waiting list entry details (Admin only)",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Entry retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "string", format: "uuid"),
                                new OA\Property(property: "email", type: "string", format: "email"),
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "phone_number", type: "string", nullable: true),
                                new OA\Property(property: "company_name", type: "string", nullable: true),
                                new OA\Property(property: "selected_roles", type: "array", nullable: true, items: new OA\Items(type: "string", enum: ["wholesaler", "investor"])),
                                new OA\Property(property: "status", type: "string", enum: ["pending", "account_created", "cancelled"]),
                                new OA\Property(property: "coupon_code", type: "string", nullable: true),
                                new OA\Property(property: "email_verified", type: "boolean"),
                                new OA\Property(property: "email_verified_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "account_created", type: "boolean"),
                                new OA\Property(property: "account_created_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "metadata", type: "object", nullable: true),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Entry not found"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $entry = WaitingListEntry::with(['coupon'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new WaitingListEntryResource($entry),
        ]);
    }

    /**
     * Get waiting list statistics
     */
    #[OA\Get(
        path: "/admin/waiting-list/stats",
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

        // Count by roles
        $wholesalers = WaitingListEntry::whereNotNull('selected_roles')
            ->whereJsonContains('selected_roles', 'wholesaler')
            ->count();
        $investors = WaitingListEntry::whereNotNull('selected_roles')
            ->whereJsonContains('selected_roles', 'investor')
            ->count();
        $bothRoles = WaitingListEntry::whereNotNull('selected_roles')
            ->whereJsonContains('selected_roles', 'wholesaler')
            ->whereJsonContains('selected_roles', 'investor')
            ->count();

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
                'by_roles' => [
                    'wholesaler' => $wholesalers,
                    'investor' => $investors,
                    'both' => $bothRoles,
                ],
            ],
        ]);
    }
}
