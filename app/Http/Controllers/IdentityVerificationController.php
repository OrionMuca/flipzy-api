<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Identity Verification")]
class IdentityVerificationController extends Controller
{
    /**
     * Create a Stripe Identity verification session.
     * Returns a client_secret the frontend uses to open the Stripe Identity modal.
     */
    #[OA\Post(
        path: "/api/v1/identity/create-session",
        summary: "Create identity verification session",
        description: "Creates a Stripe Identity VerificationSession. Returns a client_secret for the frontend to open the Stripe Identity modal.",
        security: [["bearerAuth" => []]],
        tags: ["Identity Verification"],
        responses: [
            new OA\Response(response: 200, description: "Session created successfully"),
            new OA\Response(response: 400, description: "User already verified"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->id_verification_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Your identity is already verified.',
            ], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret_key'));

            $session = $stripe->identity->verificationSessions->create([
                'type' => 'document',
                'options' => [
                    'document' => [
                        'allowed_types' => ['driving_license', 'passport', 'id_card'],
                        'require_live_capture' => true,
                        'require_matching_selfie' => true,
                    ],
                ],
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            // Store the session ID so we can match it in the webhook
            $user->update([
                'stripe_identity_session_id' => $session->id,
                'id_verification_status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $session->client_secret,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe Identity session creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create verification session.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get the current identity verification status for the authenticated user.
     */
    #[OA\Get(
        path: "/api/v1/identity/status",
        summary: "Get identity verification status",
        description: "Returns the current ID verification status of the authenticated user.",
        security: [["bearerAuth" => []]],
        tags: ["Identity Verification"],
        responses: [
            new OA\Response(response: 200, description: "Status retrieved successfully"),
        ]
    )]
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $user->id_verification_status,
                'verified' => $user->id_verification_status === 'verified',
                'verified_at' => $user->id_verified_at?->toISOString(),
            ],
        ]);
    }
}
