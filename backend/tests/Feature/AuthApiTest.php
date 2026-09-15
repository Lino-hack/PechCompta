<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@peche.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@peche.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_login_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@peche.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@peche.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_protected_route_requires_token(): void
    {
        $this->getJson('/api/stats')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->getJson('/api/user', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()->assertJsonPath('email', $user->email);
    }
}
