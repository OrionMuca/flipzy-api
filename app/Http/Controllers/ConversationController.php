<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationCollection;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Property;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Messaging")]
class ConversationController extends Controller
{
    public function __construct(
        protected MessageService $messageService
    ) {}

    /**
     * Get all conversations for the authenticated user
     */
    #[OA\Get(
        path: "/conversations",
        summary: "List user conversations",
        description: "Get all conversations for the authenticated user, ordered by last message date.",
        tags: ["Messaging"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                schema: new OA\Schema(type: "integer", default: 15)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of conversations"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index(Request $request): ConversationCollection
    {
        $perPage = $request->get('per_page', 15);
        $conversations = $this->messageService->getConversations($request->user(), $perPage);

        return new ConversationCollection($conversations);
    }

    /**
     * Get a specific conversation
     */
    #[OA\Get(
        path: "/conversations/{id}",
        summary: "Get conversation details",
        description: "Get details of a specific conversation including participants and property.",
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
            new OA\Response(response: 200, description: "Conversation details"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Not a participant"),
            new OA\Response(response: 404, description: "Conversation not found"),
        ]
    )]
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation = $this->messageService->getConversation($conversation->id, $request->user());

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or unauthorized',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ConversationResource($conversation->load(['property', 'participantOne', 'participantTwo'])),
        ]);
    }

    /**
     * Create or get existing conversation
     */
    #[OA\Post(
        path: "/conversations",
        summary: "Create or get conversation",
        description: "Create a new conversation or retrieve existing one between two users. Optionally link to a property.",
        tags: ["Messaging"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["user_id"],
                properties: [
                    new OA\Property(property: "user_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440000", description: "ID of the other participant"),
                    new OA\Property(property: "property_id", type: "string", format: "uuid", nullable: true, example: "550e8400-e29b-41d4-a716-446655440001", description: "Optional property ID to link conversation"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Conversation created or retrieved"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'property_id' => 'nullable|uuid|exists:properties,id',
        ]);

        $currentUser = $request->user();
        $otherUser = \App\Models\User::findOrFail($request->user_id);

        // Prevent users from creating conversations with themselves
        if ($currentUser->id === $otherUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot create conversation with yourself',
            ], 422);
        }

        $property = $request->property_id 
            ? Property::find($request->property_id)
            : null;

        $conversation = $this->messageService->getOrCreateConversation(
            $currentUser,
            $otherUser,
            $property
        );

        return response()->json([
            'success' => true,
            'message' => 'Conversation retrieved or created successfully',
            'data' => new ConversationResource($conversation->load(['property', 'participantOne', 'participantTwo'])),
        ], 201);
    }
}

