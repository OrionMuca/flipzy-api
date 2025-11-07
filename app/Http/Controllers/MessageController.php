<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageCollection;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Messaging")]
class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messageService
    ) {}

    /**
     * Get messages for a conversation
     */
    #[OA\Get(
        path: "/conversations/{id}/messages",
        summary: "Get conversation messages",
        description: "Get all messages in a conversation, ordered by creation date (newest first).",
        tags: ["Messaging"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid"),
                description: "Conversation ID"
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                schema: new OA\Schema(type: "integer", default: 50)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of messages"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Not a participant"),
        ]
    )]
    public function index(Request $request, Conversation $conversation): MessageCollection|JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 50);
            $messages = $this->messageService->getMessages($conversation, $request->user(), $perPage);

            return new MessageCollection($messages);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Send a message in a conversation
     */
    #[OA\Post(
        path: "/conversations/{id}/messages",
        summary: "Send a message",
        description: "Send a new message in a conversation. Message is broadcast in real-time to the other participant.",
        tags: ["Messaging"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", format: "uuid"),
                description: "Conversation ID"
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["body"],
                properties: [
                    new OA\Property(property: "body", type: "string", example: "I'm interested in this property. Can we schedule a viewing?", maxLength: 5000),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Message sent successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Not a participant"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        try {
            $message = $this->messageService->sendMessage(
                $conversation,
                $request->user(),
                $request->body
            );

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => new MessageResource($message->load(['sender', 'receiver', 'conversation'])),
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Mark all messages in a conversation as read
     */
    #[OA\Put(
        path: "/conversations/{id}/messages/read",
        summary: "Mark conversation messages as read",
        description: "Mark all unread messages in a conversation as read for the authenticated user.",
        tags: ["Messaging"],
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
            new OA\Response(response: 200, description: "Messages marked as read"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function markConversationAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $count = $this->messageService->markMessagesAsRead($conversation, $request->user());

            return response()->json([
                'success' => true,
                'message' => "Marked {$count} message(s) as read",
                'data' => [
                    'conversation_id' => $conversation->id,
                    'messages_marked' => $count,
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Mark a specific message as read
     */
    #[OA\Put(
        path: "/messages/{id}/read",
        summary: "Mark message as read",
        description: "Mark a specific message as read.",
        tags: ["Messaging"],
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
            new OA\Response(response: 200, description: "Message marked as read"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 422, description: "Message already read"),
        ]
    )]
    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        try {
            $marked = $this->messageService->markMessageAsRead($message, $request->user());

            if (!$marked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message is already read',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read',
                'data' => new MessageResource($message->load(['sender', 'receiver'])),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get unread message count for authenticated user
     */
    #[OA\Get(
        path: "/messages/unread-count",
        summary: "Get unread message count",
        description: "Get the total number of unread messages for the authenticated user across all conversations.",
        tags: ["Messaging"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Unread count",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "unread_count", type: "integer", example: 5),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->messageService->getUnreadCount($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }
}

