<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Newsletter")]
class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter
     */
    #[OA\Post(
        path: "/newsletter/subscribe",
        summary: "Subscribe to newsletter",
        description: "Subscribe an email address to the newsletter. Public endpoint.",
        tags: ["Newsletter"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "source", type: "string", example: "website", description: "Optional source identifier"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Successfully subscribed"),
            new OA\Response(response: 201, description: "Successfully subscribed"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'source' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $source = $request->source ?? 'website';

        // Check if already subscribed
        $subscription = NewsletterSubscription::where('email', $email)->first();

        if ($subscription) {
            // If already exists but unsubscribed, reactivate
            if (!$subscription->is_active) {
                $subscription->update([
                    'is_active' => true,
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                    'source' => $source,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Successfully resubscribed to newsletter',
                ], 200);
            }

            // Already active
            return response()->json([
                'success' => true,
                'message' => 'Email is already subscribed to newsletter',
            ], 200);
        }

        // Create new subscription
        NewsletterSubscription::create([
            'email' => $email,
            'is_active' => true,
            'subscribed_at' => now(),
            'source' => $source,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed to newsletter',
        ], 201);
    }

    /**
     * Unsubscribe from newsletter
     */
    #[OA\Post(
        path: "/newsletter/unsubscribe",
        summary: "Unsubscribe from newsletter",
        description: "Unsubscribe an email address from the newsletter. Public endpoint.",
        tags: ["Newsletter"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Successfully unsubscribed"),
            new OA\Response(response: 404, description: "Email not found in subscriptions"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function unsubscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));

        $subscription = NewsletterSubscription::where('email', $email)->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found in subscriptions',
            ], 404);
        }

        if (!$subscription->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'Email is already unsubscribed',
            ], 200);
        }

        $subscription->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully unsubscribed from newsletter',
        ]);
    }
}
