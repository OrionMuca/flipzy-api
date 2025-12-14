<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Services\EmailCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Email Campaigns")]
class AdminEmailCampaignController extends Controller
{
    public function __construct(
        protected EmailCampaignService $campaignService
    ) {}

    /**
     * List all email campaigns
     */
    #[OA\Get(
        path: "/admin/email-campaigns",
        summary: "List all email campaigns (Admin only)",
        description: "Get a paginated list of all email campaigns with statistics",
        tags: ["Admin - Email Campaigns"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Campaigns retrieved successfully"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = EmailCampaign::query();
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $campaigns = $query->paginate($perPage);

        $data = $campaigns->map(function ($campaign) {
            $stats = $campaign->getStats();
            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'status' => $campaign->status,
                'recipients_count' => $campaign->recipients_count,
                'sent_count' => $campaign->sent_count,
                'scheduled_at' => $campaign->scheduled_at?->format('m/d/Y, g:i A'),
                'sent_at' => $campaign->sent_at?->format('m/d/Y, g:i A'),
                'created_at' => $campaign->created_at->format('m/d/Y, g:i A'),
                'open_rate' => $stats['open_rate'],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
            ],
        ]);
    }

    /**
     * Create a new email campaign
     */
    #[OA\Post(
        path: "/admin/email-campaigns",
        summary: "Create email campaign (Admin only)",
        tags: ["Admin - Email Campaigns"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 201, description: "Campaign created successfully"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $campaign = $this->campaignService->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully',
            'data' => $campaign,
        ], 201);
    }

    /**
     * Get campaign details
     */
    #[OA\Get(
        path: "/admin/email-campaigns/{id}",
        summary: "Get campaign details (Admin only)",
        tags: ["Admin - Email Campaigns"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Campaign retrieved successfully"),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $campaign = EmailCampaign::findOrFail($id);
        $stats = $campaign->getStats();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'content' => $campaign->content,
                'status' => $campaign->status,
                'recipients_count' => $campaign->recipients_count,
                'sent_count' => $campaign->sent_count,
                'scheduled_at' => $campaign->scheduled_at?->format('m/d/Y, g:i A'),
                'sent_at' => $campaign->sent_at?->format('m/d/Y, g:i A'),
                'created_at' => $campaign->created_at->format('m/d/Y, g:i A'),
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Update campaign
     */
    #[OA\Put(
        path: "/admin/email-campaigns/{id}",
        summary: "Update campaign (Admin only)",
        tags: ["Admin - Email Campaigns"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Campaign updated successfully"),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $campaign = EmailCampaign::findOrFail($id);

        if ($campaign->status === 'sent') {
            return response()->json([
                'success' => false,
                'error' => 'Cannot update a campaign that has already been sent',
            ], 400);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'subject' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $campaign = $this->campaignService->update($campaign, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated successfully',
            'data' => $campaign,
        ]);
    }

    /**
     * Delete campaign
     */
    #[OA\Delete(
        path: "/admin/email-campaigns/{id}",
        summary: "Delete campaign (Admin only)",
        tags: ["Admin - Email Campaigns"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Campaign deleted successfully"),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $campaign = EmailCampaign::findOrFail($id);
        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully',
        ]);
    }

    /**
     * Send campaign
     */
    #[OA\Post(
        path: "/admin/email-campaigns/{id}/send",
        summary: "Send campaign to waiting list members (Admin only)",
        tags: ["Admin - Email Campaigns"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Campaign sent successfully"),
        ]
    )]
    public function send(Request $request, string $id): JsonResponse
    {
        $campaign = EmailCampaign::findOrFail($id);

        $filters = $request->only(['status', 'user_type']);

        $result = $this->campaignService->send($campaign, $filters);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign sent successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get campaign statistics
     */
    #[OA\Get(
        path: "/admin/email-campaigns/stats",
        summary: "Get campaign statistics (Admin only)",
        tags: ["Admin - Email Campaigns"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Statistics retrieved successfully"),
        ]
    )]
    public function stats(): JsonResponse
    {
        $stats = $this->campaignService->getStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
