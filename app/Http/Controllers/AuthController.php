<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Mail\PasswordResetMail;
use App\Mail\EmailVerificationMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Authentication")]
class AuthController extends Controller
{
    /**
     * Register a new user
     */
    #[OA\Post(
        path: "/register",
        summary: "Register a new user",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "role", type: "string", enum: ["investor", "wholesaler"], example: "investor"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "User registered successfully"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:investor,wholesaler',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => null, // Email verification required
        ]);

        // Assign role using Spatie
        $user->assignRole($request->role);

        // Send email verification
        $verificationToken = Str::random(64);
        \DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($verificationToken),
                'created_at' => now(),
            ]
        );
        
        // Use frontend URL for verification link
        $frontendUrl = config('app.frontend_url');
        $verificationUrl = rtrim($frontendUrl, '/') . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);
        
        try {
            Mail::to($user->email)->send(new EmailVerificationMail($user, $verificationUrl));
        } catch (\Exception $e) {
            Log::warning('Failed to send email verification', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            // Don't fail registration if email fails
        }

        $token = $user->createToken('Flipzy API')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user->load('roles'),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Login user and create token
     */
    #[OA\Post(
        path: "/login",
        summary: "Login user",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful. If email is not verified, a verification email will be automatically resent.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Login successful"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "user", type: "object"),
                                new OA\Property(property: "access_token", type: "string"),
                                new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                                new OA\Property(property: "email_verified", type: "boolean", example: true, description: "Whether the user's email is verified"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Invalid credentials"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // If email is not verified, resend verification email
        if (!$user->email_verified_at) {
            $verificationToken = Str::random(64);
            \DB::table('email_verification_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($verificationToken),
                    'created_at' => now(),
                ]
            );
            
            // Use frontend URL for verification link
            $frontendUrl = config('app.frontend_url');
            $verificationUrl = rtrim($frontendUrl, '/') . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);
            
            try {
                Mail::to($user->email)->send(new EmailVerificationMail($user, $verificationUrl));
            } catch (\Exception $e) {
                Log::warning('Failed to resend email verification on login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
                // Continue with login even if email fails
            }
        }

        $token = $user->createToken('Flipzy API')->accessToken;

        return response()->json([
            'success' => true,
            'message' => $user->email_verified_at 
                ? 'Login successful' 
                : 'Login successful. Please verify your email address. A verification email has been sent.',
            'data' => [
                'user' => $user->load('roles'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'email_verified' => $user->email_verified_at !== null,
            ],
        ]);
    }

    /**
     * Get authenticated user
     */
    #[OA\Get(
        path: "/user",
        summary: "Get authenticated user information",
        tags: ["Authentication"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "User information",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function user(Request $request): UserResource
    {
        $user = $request->user()->load('roles', 'permissions');
        
        return new UserResource($user);
    }

    /**
     * Logout user (Revoke the token)
     */
    #[OA\Post(
        path: "/logout",
        summary: "Logout user and revoke token",
        tags: ["Authentication"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logout successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Successfully logged out"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke all tokens for the user
        // Passport checks the 'revoked' column in oauth_access_tokens table
        $user->tokens()->update(['revoked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Send password reset link
     */
    #[OA\Post(
        path: "/api/v1/password/forgot",
        summary: "Request password reset",
        description: "Send password reset link to user's email",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password reset link sent"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Return success even if user doesn't exist (security best practice)
            return response()->json([
                'success' => true,
                'message' => 'If that email exists, we have sent a password reset link.',
            ]);
        }

        // Generate password reset token
        $token = Password::createToken($user);
        
        // Note: PasswordResetMail will construct the frontend URL itself
        // We pass the token here, but the Mailable will build the frontend URL

        // Send password reset email
        Mail::to($user->email)->send(new PasswordResetMail($user, $token, ''));

        return response()->json([
            'success' => true,
            'message' => 'Password reset link has been sent to your email.',
        ]);
    }

    /**
     * Reset password
     */
    #[OA\Post(
        path: "/api/v1/password/reset",
        summary: "Reset password",
        description: "Reset user password using token from email",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "token", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "token", type: "string", example: "reset-token-here"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "newpassword123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newpassword123"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password reset successfully"),
            new OA\Response(response: 422, description: "Validation error or invalid token"),
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Reset password using Laravel's Password facade
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired reset token.',
            'errors' => ['token' => ['The password reset token is invalid or has expired.']],
        ], 422);
    }

    /**
     * Verify email address
     */
    #[OA\Get(
        path: "/api/v1/email/verify",
        summary: "Verify email address",
        description: "Verify user email address using token from email",
        tags: ["Authentication"],
        parameters: [
            new OA\Parameter(name: "token", in: "query", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "email", in: "query", required: true, schema: new OA\Schema(type: "string", format: "email")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Email verified successfully"),
            new OA\Response(response: 422, description: "Invalid or expired token"),
        ]
    )]
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email is already verified',
            ]);
        }

        // Check token (simplified - you might want to use a proper verification tokens table)
        $verificationRecord = \DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->first();

        if (!$verificationRecord || !Hash::check($request->token, $verificationRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification token',
            ], 422);
        }

        // Verify email
        $user->email_verified_at = now();
        $user->save();

        // Delete verification token
        \DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
        ]);
    }
}

