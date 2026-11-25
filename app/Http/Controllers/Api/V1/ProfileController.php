<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // ============================================================
    // GET PROFILE
    // GET /api/v1/profile
    // ============================================================

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => [
                'user' => $this->profileData($user),
            ],
        ]);
    }

    // ============================================================
    // UPDATE PROFILE
    // PUT /api/v1/profile
    // ============================================================

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // ========================================================
        // VALIDATE REQUEST
        // ========================================================

        $validator = Validator::make($request->all(), [
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ========================================================
        // PREPARE DATA
        // ========================================================

        $data = $validator->validated();

        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $email = trim($data['email']);

        if ($firstName === '' || $lastName === '') {
            return response()->json([
                'success' => false,
                'message' => 'First name and last name are required',
            ], 422);
        }

        // ========================================================
        // UPDATE USER
        // ========================================================

        $user->first_name = $firstName;
        $user->last_name = $lastName;
        $user->name = trim($firstName . ' ' . $lastName);
        $user->email = $email;

        $user->save();

        // ========================================================
        // RESPONSE
        // ========================================================

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $this->profileData($user),
            ],
        ]);
    }

    // ============================================================
    // UPLOAD PROFILE AVATAR
    // POST /api/v1/profile/avatar
    // ============================================================

    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // ========================================================
        // VALIDATE IMAGE
        // ========================================================

        $validator = Validator::make($request->all(), [
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Avatar validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ========================================================
        // PREPARE STORAGE
        // ========================================================

        $oldAvatarPath = $user->avatar_path;

        $directory = 'avatars/' . $user->id;

        // ========================================================
        // UPLOAD IMAGE
        // ========================================================

        try {
            $file = $request->file('avatar');

            $path = $file->store(
                $directory,
                'public'
            );

            if (!$path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to store avatar',
                ], 500);
            }

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Avatar upload failed',
            ], 500);
        }

        // ========================================================
        // UPDATE DATABASE
        // ========================================================

        try {
            $user->avatar_path = $path;

            $user->save();

        } catch (\Throwable $e) {

            // Delete newly uploaded file if database update fails
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $cleanupError) {
                report($cleanupError);
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update avatar',
            ], 500);
        }

        // ========================================================
        // DELETE OLD AVATAR
        // ========================================================

        if (
            $oldAvatarPath &&
            $oldAvatarPath !== $path &&
            str_starts_with(
                $oldAvatarPath,
                $directory . '/'
            )
        ) {
            try {
                Storage::disk('public')->delete(
                    $oldAvatarPath
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // ========================================================
        // RESPONSE
        // ========================================================

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'user' => $this->profileData($user),
            ],
        ]);
    }

    // ============================================================
    // PROFILE RESPONSE DATA
    // ============================================================

    private function profileData($user): array
    {
        $data = $user->only([
            'id',
            'name',
            'first_name',
            'last_name',
            'email',
            'phone',
            'phone_verified_at',
            'role',
        ]);

        $data['avatar_path'] = $user->avatar_path;

        $data['avatar_url'] = $user->avatar_path
            ? url(Storage::disk('public')->url($user->avatar_path))
            : null;

        return $data;
    }
}