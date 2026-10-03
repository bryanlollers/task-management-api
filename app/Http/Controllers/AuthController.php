<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Register a user and issue an API token. */
    public function register(RegisterRequest $request): AuthTokenResource
    {
        $user = User::create($request->validated());

        $token = $user->createToken('api-token')->plainTextToken;

        return new AuthTokenResource([
            'message' => 'Registration successful.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /** Authenticate a user and issue an API token. */
    public function login(LoginRequest $request): AuthTokenResource
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (
            ! $user ||
            ! Hash::check($validated['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.',
                ],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return new AuthTokenResource([
            'message' => 'Login successful.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /** Revoke the authenticated user's current API token. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    /** Return the authenticated user's public profile. */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
