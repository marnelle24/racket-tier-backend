<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const PRIMARY_SPORTS = ['badminton', 'pickleball', 'tennis', 'ping-pong'];

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'pronoun' => ['nullable', 'string', 'in:He/Him,She/Her,They/Them,Other'],
            'primary_sport' => ['nullable', 'string', Rule::in(self::PRIMARY_SPORTS)],
            'nickname' => ['nullable', 'string', 'max:64'],
            'avatar_seed' => ['nullable', 'string', 'max:64'],
        ]);

        $user->update($validated);

        return $this->apiSuccess('Profile updated.', [
            'user' => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->password,
        ]);

        return $this->apiSuccess('Password updated.');
    }
}
