<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /**
     * Handle user registration
     * 
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'type' => 'customer',
                'is_active' => true,
                'is_verified' => false
            ]);

            // Create profile
            Profile::create([
                'user_id' => $user->id,
                'phone' => $request->phone
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'success' => true,
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);
        });
    }

    /**
     * Verify email
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail($id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification link',
                'success' => false
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
                'success' => true
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email verified successfully',
            'success' => true
        ]);
    }

    /**
     * Resend verification email
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
                'success' => true
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent',
            'success' => true
        ]);
    }
}
