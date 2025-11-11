<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;
use App\Models\Role;

#[OA\Tag(name: "Admin - User Management")]
class AdminUserController extends Controller
{
    /**
     * List all users
     */
    #[OA\Get(
        path: "/admin/users",
        summary: "List all users (Admin only)",
        description: "Get a paginated list of all users with optional filtering by role, search, and sorting",
        tags: ["Admin - User Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "query",
                description: "Filter by role",
                schema: new OA\Schema(type: "string", enum: ["investor", "wholesaler", "admin"])
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Search by name or email",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "sort_by",
                in: "query",
                description: "Sort field",
                schema: new OA\Schema(type: "string", default: "created_at")
            ),
            new OA\Parameter(
                name: "sort_order",
                in: "query",
                description: "Sort order",
                schema: new OA\Schema(type: "string", enum: ["asc", "desc"], default: "desc")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Items per page (max 100)",
                schema: new OA\Schema(type: "integer", default: 15, maximum: 100)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Users retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $role = $request->get('role');
            if (in_array($role, ['investor', 'wholesaler', 'admin'])) {
                // Use whereHas to filter by role name, works with any guard
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            }
        }

        // Filter by search (name or email)
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $users = $query->with('roles')->paginate($perPage);

        return UserResource::collection($users)->additional([
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get user details
     */
    #[OA\Get(
        path: "/admin/users/{id}",
        summary: "Get user details (Admin only)",
        description: "Get detailed information about a specific user including roles, properties, and subscription",
        tags: ["Admin - User Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "User UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "User details retrieved successfully"),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'properties', 'subscription.plan']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update user
     */
    #[OA\Put(
        path: "/admin/users/{id}",
        summary: "Update user (Admin only)",
        description: "Update user information including name, email, role, and password",
        tags: ["Admin - User Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "User UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255),
                    new OA\Property(property: "email", type: "string", format: "email"),
                    new OA\Property(property: "role", type: "string", enum: ["investor", "wholesaler", "admin"]),
                    new OA\Property(property: "password", type: "string", minLength: 8),
                    new OA\Property(property: "password_confirmation", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "User updated successfully"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|string|in:investor,wholesaler,admin',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Update role if provided
        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Delete user
     */
    #[OA\Delete(
        path: "/admin/users/{id}",
        summary: "Delete user (Admin only)",
        description: "Permanently delete a user account. Cannot delete your own account.",
        tags: ["Admin - User Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "User UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "User deleted successfully"),
            new OA\Response(response: 422, description: "Cannot delete own account"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function destroy(User $user): JsonResponse
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Suspend user
     */
    #[OA\Post(
        path: "/admin/users/{id}/suspend",
        summary: "Suspend user account (Admin only)",
        description: "Suspend a user account by removing email verification. Cannot suspend your own account.",
        tags: ["Admin - User Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "User UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "User suspended successfully"),
            new OA\Response(response: 422, description: "Cannot suspend own account"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function suspend(User $user): JsonResponse
    {
        // Prevent suspending yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot suspend your own account',
            ], 422);
        }

        // For now, we'll use email_verified_at as a suspension flag
        // In production, you might want a dedicated 'suspended_at' field
        $user->email_verified_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User suspended successfully',
        ]);
    }

    /**
     * Activate user
     */
    #[OA\Post(
        path: "/admin/users/{id}/activate",
        summary: "Activate user account (Admin only)",
        description: "Activate a suspended user account by verifying their email",
        tags: ["Admin - User Management"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "User UUID",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "User activated successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function activate(User $user): JsonResponse
    {
        $user->email_verified_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully',
        ]);
    }
}
