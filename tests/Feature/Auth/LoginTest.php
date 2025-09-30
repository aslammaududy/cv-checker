<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('supersecret'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'john@example.com',
            'password' => 'supersecret',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'message'])
            ->assertJson(['message' => 'Login success']);
    }

    public function test_login_email_not_found(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'anythinghere',
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Email not found',
        ]);
    }

    public function test_login_password_not_match(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('supersecret'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Password not match',
        ]);
    }
}

