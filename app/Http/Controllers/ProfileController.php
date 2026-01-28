<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Profile")]
class ProfileController extends Controller
{
    /**
     * Update authenticated user's profile
     */
    #[OA\Put(
        path: "/profile",
        summary: "Update user profile",
        description: "Update the authenticated user's profile information including name, phone number, and photo",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "name", type: "string", example: "John Doe"),
                        new OA\Property(property: "phone_number", type: "string", example: "+1234567890"),
                        new OA\Property(property: "photo", type: "string", format: "binary", description: "Profile photo image"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Profile updated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Update name if provided
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        // Update phone number if provided
        if (isset($validated['phone_number'])) {
            $user->phone_number = $validated['phone_number'];
        }

        // Handle photo upload if provided
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo) {
                $this->deletePhoto($user->photo);
            }

            // Store new photo
            $user->photo = $this->storePhoto($request->file('photo'), $user->id);
        }

        $user->save();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Store uploaded photo
     */
    protected function storePhoto(UploadedFile $file, string $userId): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = "profiles/{$userId}/{$filename}";

        $file->storeAs("profiles/{$userId}", $filename, 'public');

        return $path;
    }

    /**
     * Delete photo from storage
     */
    protected function deletePhoto(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
