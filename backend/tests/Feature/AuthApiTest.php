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

    public function test_change_password_updates_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/change-password', [
            'current_password' => 'secret123',
            'new_password' => 'nouveau-mot-de-passe',
            'new_password_confirmation' => 'nouveau-mot-de-passe',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'nouveau-mot-de-passe',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertUnauthorized();
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/change-password', [
            'current_password' => 'mauvais',
            'new_password' => 'nouveau-mot-de-passe',
            'new_password_confirmation' => 'nouveau-mot-de-passe',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(422);
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->postJson('/api/change-password', [
            'current_password' => 'secret123',
            'new_password' => 'nouveau-mot-de-passe',
            'new_password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertUnauthorized();
    }
}
