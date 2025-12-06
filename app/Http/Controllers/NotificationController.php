<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Notifications")]
class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    #[OA\Get(
        path: "/notifications",
        summary: "Get user's notifications",
        description: "Get paginated list of notifications for the authenticated user",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Items per page (max 100)",
                schema: new OA\Schema(type: "integer", default: 15, maximum: 100)
            ),
            new OA\Parameter(
                name: "unread_only",
                in: "query",
                description: "Show only unread notifications",
                schema: new OA\Schema(type: "boolean", default: false)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Notifications retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = Auth::user();
        $query = $user->notifications();

        // Filter unread only
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        // Sort by latest first
        $query->latest();

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $notifications = $query->paginate($perPage);

        return NotificationResource::collection($notifications)->additional([
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Mark notification as read
     */
    #[OA\Put(
        path: "/notifications/{id}/read",
        summary: "Mark notification as read",
        description: "Mark a specific notification as read",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Notification marked as read"),
            new OA\Response(response: 404, description: "Notification not found"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    #[OA\Put(
        path: "/notifications/read-all",
        summary: "Mark all notifications as read",
        description: "Mark all notifications for the authenticated user as read",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "All notifications marked as read"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = Auth::user();
        $count = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    /**
     * Get unread notification count
     */
    #[OA\Get(
        path: "/notifications/unread-count",
        summary: "Get unread notification count",
        description: "Get the count of unread notifications for the authenticated user",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Unread count retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $user = Auth::user();
        $count = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Delete notification
     */
    #[OA\Delete(
        path: "/notifications/{id}",
        summary: "Delete notification",
        description: "Delete a specific notification",
        tags: ["Notifications"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Notification deleted successfully"),
            new OA\Response(response: 404, description: "Notification not found"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }
}

