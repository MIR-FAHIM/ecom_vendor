<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_saves_fcm_token_to_user_device_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'device_token' => null,
        ]);

        $fcmToken = 'fcm-token-123';

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'fcm_token' => $fcmToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $user->refresh();

        $this->assertSame($fcmToken, $user->device_token);
    }
}
