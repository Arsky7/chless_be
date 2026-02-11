<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Get authenticated user profile
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'addresses']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Update user profile
     * 
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = $request->user();
            
            $user->update([
                'name' => $request->name
            ]);

            if ($user->profile) {
                $user->profile->update([
                    'phone' => $request->phone,
                    'gender' => $request->gender,
                    'birth_date' => $request->birth_date,
                    'avatar' => $request->avatar
                ]);
            }

            return response()->json([
                'message' => 'Profile updated successfully',
                'success' => true,
                'data' => new UserResource($user->load('profile'))
            ]);
        });
    }

    /**
     * Change password
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed']
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'success' => false
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please login again.',
            'success' => true
        ]);
    }

    /**
     * Upload avatar
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048']
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            
            if ($user->profile) {
                $user->profile->update(['avatar' => $path]);
            }

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'success' => true,
                'data' => [
                    'avatar_url' => asset('storage/' . $path)
                ]
            ]);
        }

        return response()->json([
            'message' => 'No file uploaded',
            'success' => false
        ], 400);
    }
}
