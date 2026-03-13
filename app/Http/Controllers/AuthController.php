<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'pronoun' => ['nullable', 'string', 'in:He/Him,She/Her,They/Them,Other'],
        ]);

        $user = User::create([
            ...$validated,
            'global_rating' => 0,
            'tier' => 0,
        ]);
        $token = $user->createToken('auth-token')->plainTextToken;
        $expiresAt = $this->tokenExpiresAt();

        return $this->apiSuccess('Registration successful.', [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('web')->attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;
        $expiresAt = $this->tokenExpiresAt();

        return $this->apiSuccess('Login successful.', [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->apiSuccess('Logged out successfully.');
    }

    /**
     * Token expiry time (ISO 8601) from Sanctum config, or null if tokens do not expire.
     */
    private function tokenExpiresAt(): ?string
    {
        $minutes = config('sanctum.expiration');
        if ($minutes === null || (int) $minutes <= 0) {
            return null;
        }

        return now()->addMinutes((int) $minutes)->toIso8601String();
    }
}
