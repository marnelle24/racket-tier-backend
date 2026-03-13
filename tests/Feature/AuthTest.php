<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'token_type']])
            ->assertJson(['success' => true, 'data' => ['token_type' => 'Bearer']]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'global_rating' => 0,
            'tier' => 0,
        ]);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['errors' => ['email']]]);
    }

    public function test_login_returns_token_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'player@example.com',
            'password' => 'secret',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'player@example.com',
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'token_type']])
            ->assertJson(['success' => true, 'data' => ['token_type' => 'Bearer']]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'player@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'player@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['errors' => ['email']]]);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized()
            ->assertJson(['success' => false, 'message' => 'Unauthenticated.']);
    }
}
