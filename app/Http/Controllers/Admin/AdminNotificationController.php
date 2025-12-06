<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendAdminNotificationRequest;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - Notifications")]
class AdminNotificationController extends Controller
{
    public function __construct(
        protected AdminNotificationService $notificationService
    ) {}

    /**
     * Send notification to users
     */
    #[OA\Post(
        path: "/admin/notifications/send",
        summary: "Send notification to users (Admin only)",
        description: "Send email notifications to users. Can send to all users, selected users, filtered users, or a single user.",
        tags: ["Admin - Notifications"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["subject", "message", "recipient_type"],
                properties: [
                    new OA\Property(property: "subject", type: "string", example: "Important Update", description: "Email subject"),
                    new OA\Property(property: "message", type: "string", example: "We have an important update for you...", description: "Email message/content"),
                    new OA\Property(property: "action_url", type: "string", format: "url", nullable: true, example: "https://yourapp.com/update", description: "Optional action button URL"),
                    new OA\Property(property: "action_text", type: "string", nullable: true, example: "View Update", description: "Optional action button text"),
                    new OA\Property(
                        property: "recipient_type",
                        type: "string",
                        enum: ["all", "selected", "filtered", "single"],
                        example: "all",
                        description: "Type of recipients: 'all' for all users, 'selected' for specific user IDs, 'filtered' for filtered users, 'single' for one user"
                    ),
                    new OA\Property(
                        property: "user_ids",
                        type: "array",
                        items: new OA\Items(type: "string", format: "uuid"),
                        nullable: true,
                        description: "Array of user UUIDs (required when recipient_type is 'selected')"
                    ),
                    new OA\Property(
                        property: "user_id",
                        type: "string",
                        format: "uuid",
                        nullable: true,
                        description: "Single user UUID (required when recipient_type is 'single')"
                    ),
                    new OA\Property(
                        property: "filters",
                        type: "object",
                        nullable: true,
                        properties: [
                            new OA\Property(property: "role", type: "string", enum: ["investor", "wholesaler", "admin"], nullable: true),
                            new OA\Property(property: "search", type: "string", nullable: true, description: "Search by name or email"),
                        ],
                        description: "Filter criteria (required when recipient_type is 'filtered')"
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Notification sent successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Notifications sent successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "total_users", type: "integer", example: 150),
                                new OA\Property(property: "sent", type: "integer", example: 148),
                                new OA\Property(property: "failed", type: "integer", example: 2),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function send(SendAdminNotificationRequest $request): JsonResponse
    {
        $recipientType = $request->input('recipient_type');
        $subject = $request->input('subject');
        $message = $request->input('message');
        $actionUrl = $request->input('action_url');
        $actionText = $request->input('action_text');

        $result = match ($recipientType) {
            'all' => $this->notificationService->sendToAll($subject, $message, $actionUrl, $actionText),
            'selected' => $this->notificationService->sendToSelected(
                $request->input('user_ids', []),
                $subject,
                $message,
                $actionUrl,
                $actionText
            ),
            'filtered' => $this->notificationService->sendToFiltered(
                $request->input('filters', []),
                $subject,
                $message,
                $actionUrl,
                $actionText
            ),
            'single' => $this->notificationService->sendToSingle(
                $request->input('user_id'),
                $subject,
                $message,
                $actionUrl,
                $actionText
            ),
            default => [
                'success' => false,
                'error' => 'Invalid recipient type',
            ],
        };

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send notifications',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifications sent successfully',
            'data' => $result,
        ]);
    }
}

