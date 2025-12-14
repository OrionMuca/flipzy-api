<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WaitingListEntryResource;
use App\Models\WaitingListEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = WaitingListEntry::with(['coupon']);

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if (in_array($status, ['pending', 'account_created', 'cancelled'])) {
                $query->where('status', $status);
            }
        }

        // Filter by user type
        if ($request->has('user_type') && $request->user_type !== 'all') {
            if ($request->user_type === 'investor') {
                $query->whereJsonContains('selected_roles', 'investor')
                      ->where(function ($q) {
                          $q->whereJsonDoesntContain('selected_roles', 'wholesaler')
                            ->orWhereNull('selected_roles');
                      });
            } elseif ($request->user_type === 'wholesaler') {
                $query->whereJsonContains('selected_roles', 'wholesaler')
                      ->where(function ($q) {
                          $q->whereJsonDoesntContain('selected_roles', 'investor')
                            ->orWhereNull('selected_roles');
                      });
            } elseif ($request->user_type === 'both') {
                $query->whereJsonContains('selected_roles', 'wholesaler')
                      ->whereJsonContains('selected_roles', 'investor');
            }
        }

        // Filter by sign up month
        if ($request->has('sign_up_month') && $request->sign_up_month !== 'all') {
            $query->whereMonth('created_at', $request->sign_up_month);
        }

        // Filter by sign up year
        if ($request->has('sign_up_year') && $request->sign_up_year !== 'all') {
            $query->whereYear('created_at', $request->sign_up_year);
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

        // Get summary statistics before pagination
        $summaryQuery = clone $query;
        $totalRecords = $summaryQuery->count();
        
        $investors = WaitingListEntry::whereJsonContains('selected_roles', 'investor')
            ->where(function ($q) {
                $q->whereJsonDoesntContain('selected_roles', 'wholesaler')
                  ->orWhereNull('selected_roles');
            })
            ->count();
        
        $wholesalers = WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')
            ->where(function ($q) {
                $q->whereJsonDoesntContain('selected_roles', 'investor')
                  ->orWhereNull('selected_roles');
            })
            ->count();
        
        $both = WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')
            ->whereJsonContains('selected_roles', 'investor')
            ->count();

        // Apply date filters to summary if provided
        if ($request->has('sign_up_month') && $request->sign_up_month !== 'all') {
            $investors = WaitingListEntry::whereJsonContains('selected_roles', 'investor')
                ->where(function ($q) {
                    $q->whereJsonDoesntContain('selected_roles', 'wholesaler')
                      ->orWhereNull('selected_roles');
                })
                ->whereMonth('created_at', $request->sign_up_month)
                ->count();
            
            $wholesalers = WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')
                ->where(function ($q) {
                    $q->whereJsonDoesntContain('selected_roles', 'investor')
                      ->orWhereNull('selected_roles');
                })
                ->whereMonth('created_at', $request->sign_up_month)
                ->count();
            
            $both = WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')
                ->whereJsonContains('selected_roles', 'investor')
                ->whereMonth('created_at', $request->sign_up_month)
                ->count();
        }

        if ($request->has('sign_up_year') && $request->sign_up_year !== 'all') {
            $investors = WaitingListEntry::whereJsonContains('selected_roles', 'investor')
                ->where(function ($q) {
                    $q->whereJsonDoesntContain('selected_roles', 'wholesaler')
                      ->orWhereNull('selected_roles');
                })
                ->whereYear('created_at', $request->sign_up_year)
                ->count();
            
            $wholesalers = WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')
                ->where(function ($q) {
                    $q->whereJsonDoesntContain('selected_roles', 'investor')
                      ->orWhereNull('selected_roles');
                })
                ->whereYear('created_at', $request->sign_up_year)
                ->count();
            
            $both = WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')
                ->whereJsonContains('selected_roles', 'investor')
                ->whereYear('created_at', $request->sign_up_year)
                ->count();
        }

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
            'summary' => [
                'total_records' => $totalRecords,
                'investors' => $investors,
                'wholesalers' => $wholesalers,
                'both' => $both,
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

    /**
     * Get daily signups statistics
     */
    #[OA\Get(
        path: "/admin/waiting-list/daily-signups",
        summary: "Get daily signups statistics (Admin only)",
        description: "Get daily signups breakdown with 30-day total, average, today's count, and daily changes",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Daily signups retrieved successfully"),
        ]
    )]
    public function dailySignups(Request $request): JsonResponse
    {
        $cacheKey = 'admin_waiting_list_daily_signups';
        
        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $thirtyDaysAgo = now()->subDays(30)->startOfDay();
            $today = now()->endOfDay();
            
            // Get daily signups for last 30 days
            $dailySignups = WaitingListEntry::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->whereBetween('created_at', [$thirtyDaysAgo, $today])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'desc')
                ->get()
                ->keyBy('date');
            
            // Calculate totals
            $totalLast30Days = WaitingListEntry::whereBetween('created_at', [$thirtyDaysAgo, $today])->count();
            $averageDaily = $totalLast30Days > 0 ? round($totalLast30Days / 30, 2) : 0;
            $todayCount = WaitingListEntry::whereDate('created_at', today())->count();
            
            // Build daily breakdown with change indicators
            $dailyBreakdown = [];
            $previousCount = null;
            
            // Get all dates in the last 30 days
            $allDates = [];
            for ($i = 0; $i < 30; $i++) {
                $date = now()->subDays($i)->format('Y-m-d');
                $allDates[] = $date;
            }
            
            foreach ($allDates as $date) {
                $count = $dailySignups->get($date)->count ?? 0;
                $change = null;
                $changeType = null;
                
                if ($previousCount !== null) {
                    $change = $count - $previousCount;
                    $changeType = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'same');
                }
                
                $dailyBreakdown[] = [
                    'date' => $date,
                    'formatted_date' => \Carbon\Carbon::parse($date)->format('M j, Y'),
                    'signups' => $count,
                    'change' => $change,
                    'change_type' => $changeType,
                ];
                
                $previousCount = $count;
            }
            
            // Reverse to show oldest first
            $dailyBreakdown = array_reverse($dailyBreakdown);
            
            return [
                'total_last_30_days' => $totalLast30Days,
                'average_daily' => $averageDaily,
                'today_count' => $todayCount,
                'daily_breakdown' => $dailyBreakdown,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get geographic distribution of waiting list signups
     */
    #[OA\Get(
        path: "/admin/waiting-list/geographic-distribution",
        summary: "Get geographic distribution of waiting list signups (Admin only)",
        description: "Get state-by-state signup statistics including total signups, unique states count, top state, and ranked list with percentages",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Geographic distribution retrieved successfully"),
        ]
    )]
    public function geographicDistribution(): JsonResponse
    {
        $cacheKey = 'admin_waiting_list_geographic_distribution';
        
        $data = Cache::remember($cacheKey, now()->addHours(1), function () {
            // Get total signups
            $totalSignups = WaitingListEntry::count();
            
            // Get state distribution
            $stateDistribution = WaitingListEntry::select(
                'state',
                DB::raw('COUNT(*) as count')
            )
                ->whereNotNull('state')
                ->groupBy('state')
                ->orderByDesc('count')
                ->get();
            
            // Count unique states
            $uniqueStatesCount = $stateDistribution->count();
            
            // Find top state
            $topState = $stateDistribution->first();
            $topStateName = $topState ? $topState->state : null;
            
            // Build state-by-state data with percentages
            $statesData = $stateDistribution->map(function ($item) use ($totalSignups) {
                $percentage = $totalSignups > 0 ? round(($item->count / $totalSignups) * 100, 2) : 0;
                return [
                    'state' => $item->state,
                    'count' => $item->count,
                    'percentage' => $percentage,
                ];
            })->values()->toArray();
            
            // Build all states map (for frontend grid display)
            $statesMap = [];
            foreach ($stateDistribution as $item) {
                $statesMap[$item->state] = $item->count;
            }
            
            return [
                'total_signups' => $totalSignups,
                'unique_states_count' => $uniqueStatesCount,
                'top_state' => $topStateName,
                'states' => $statesMap,
                'top_states' => $statesData,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Build query with filters (reusable for exports)
     */
    protected function buildFilteredQuery(Request $request)
    {
        $query = WaitingListEntry::with(['coupon']);

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if (in_array($status, ['pending', 'account_created', 'cancelled'])) {
                $query->where('status', $status);
            }
        }

        // Filter by user type
        if ($request->has('user_type') && $request->user_type !== 'all') {
            if ($request->user_type === 'investor') {
                $query->whereJsonContains('selected_roles', 'investor')
                      ->where(function ($q) {
                          $q->whereJsonDoesntContain('selected_roles', 'wholesaler')
                            ->orWhereNull('selected_roles');
                      });
            } elseif ($request->user_type === 'wholesaler') {
                $query->whereJsonContains('selected_roles', 'wholesaler')
                      ->where(function ($q) {
                          $q->whereJsonDoesntContain('selected_roles', 'investor')
                            ->orWhereNull('selected_roles');
                      });
            } elseif ($request->user_type === 'both') {
                $query->whereJsonContains('selected_roles', 'wholesaler')
                      ->whereJsonContains('selected_roles', 'investor');
            }
        }

        // Filter by sign up month
        if ($request->has('sign_up_month') && $request->sign_up_month !== 'all') {
            $query->whereMonth('created_at', $request->sign_up_month);
        }

        // Filter by sign up year
        if ($request->has('sign_up_year') && $request->sign_up_year !== 'all') {
            $query->whereYear('created_at', $request->sign_up_year);
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

        return $query;
    }

    /**
     * Export waiting list data as CSV
     */
    #[OA\Get(
        path: "/admin/waiting-list/export/csv",
        summary: "Export waiting list data as CSV (Admin only)",
        description: "Export filtered waiting list entries as CSV file",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "CSV file download"),
        ]
    )]
    public function exportCsv(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $entries = $query->orderBy('created_at', 'desc')->get();

        $filename = 'waiting-list-' . now()->format('Y-m-d-His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($entries) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Type', 'Status', 'Signed Up']);
            
            // Data
            foreach ($entries as $entry) {
                $type = 'N/A';
                if ($entry->selected_roles) {
                    $roles = $entry->selected_roles;
                    if (in_array('wholesaler', $roles) && in_array('investor', $roles)) {
                        $type = 'Both';
                    } elseif (in_array('wholesaler', $roles)) {
                        $type = 'Wholesaler';
                    } elseif (in_array('investor', $roles)) {
                        $type = 'Investor';
                    }
                }
                
                fputcsv($file, [
                    $entry->id,
                    $entry->name,
                    $entry->email,
                    $entry->phone_number ?? 'N/A',
                    $entry->company_name ?? 'N/A',
                    $type,
                    ucfirst($entry->status),
                    $entry->created_at->format('m/d/Y'),
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export waiting list data as Excel
     */
    #[OA\Get(
        path: "/admin/waiting-list/export/excel",
        summary: "Export waiting list data as Excel (Admin only)",
        description: "Export filtered waiting list entries as Excel file",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Excel file download"),
        ]
    )]
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $entries = $query->orderBy('created_at', 'desc')->get();

        $export = new class($entries) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping {
            protected $entries;

            public function __construct($entries)
            {
                $this->entries = $entries;
            }

            public function collection()
            {
                return $this->entries;
            }

            public function headings(): array
            {
                return ['ID', 'Name', 'Email', 'Phone', 'Company', 'Type', 'Status', 'Signed Up'];
            }

            public function map($entry): array
            {
                $type = 'N/A';
                if ($entry->selected_roles) {
                    $roles = $entry->selected_roles;
                    if (in_array('wholesaler', $roles) && in_array('investor', $roles)) {
                        $type = 'Both';
                    } elseif (in_array('wholesaler', $roles)) {
                        $type = 'Wholesaler';
                    } elseif (in_array('investor', $roles)) {
                        $type = 'Investor';
                    }
                }

                return [
                    $entry->id,
                    $entry->name,
                    $entry->email,
                    $entry->phone_number ?? 'N/A',
                    $entry->company_name ?? 'N/A',
                    $type,
                    ucfirst($entry->status),
                    $entry->created_at->format('m/d/Y'),
                ];
            }
        };

        $filename = 'waiting-list-' . now()->format('Y-m-d-His') . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    /**
     * Export waiting list data as PDF
     */
    #[OA\Get(
        path: "/admin/waiting-list/export/pdf",
        summary: "Export waiting list data as PDF (Admin only)",
        description: "Export filtered waiting list entries as PDF file",
        tags: ["Admin - Waiting List Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "PDF file download"),
        ]
    )]
    public function exportPdf(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $entries = $query->orderBy('created_at', 'desc')->get();

        $data = [
            'entries' => $entries->map(function ($entry) {
                $type = 'N/A';
                if ($entry->selected_roles) {
                    $roles = $entry->selected_roles;
                    if (in_array('wholesaler', $roles) && in_array('investor', $roles)) {
                        $type = 'Both';
                    } elseif (in_array('wholesaler', $roles)) {
                        $type = 'Wholesaler';
                    } elseif (in_array('investor', $roles)) {
                        $type = 'Investor';
                    }
                }

                return [
                    'id' => $entry->id,
                    'name' => $entry->name,
                    'email' => $entry->email,
                    'phone' => $entry->phone_number ?? 'N/A',
                    'company' => $entry->company_name ?? 'N/A',
                    'type' => $type,
                    'status' => ucfirst($entry->status),
                    'signed_up' => $entry->created_at->format('m/d/Y'),
                ];
            }),
            'generated_at' => now()->format('F j, Y g:i A'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.waiting-list-pdf', $data);
        $filename = 'waiting-list-' . now()->format('Y-m-d-His') . '.pdf';
        
        return $pdf->download($filename);
    }
}
