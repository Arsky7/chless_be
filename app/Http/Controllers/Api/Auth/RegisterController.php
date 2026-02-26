<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    /**
     * Handle user registration
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'user', // users table column is 'role', not 'type'
                'is_active' => true,
            ]);

            // Auto-create profile row
            UserProfile::create([
                'user_id' => $user->id,
                'phone' => $request->phone,
                'birth_date' => $request->birthday,
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'success' => true,
                'data' => [
                    'user' => new UserResource($user->load('profile')),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);
        });
    }
}
