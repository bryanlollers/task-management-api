<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '  Portfolio User  ',
            'email' => '  PORTFOLIO@example.com  ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'Portfolio User')
            ->assertJsonPath('data.user.email', 'portfolio@example.com')
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'portfolio@example.com']);
        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertApiResponseDoesNotExposePassword($response, ['password123', $user->password]);
    }

    public function test_registration_validates_required_fields_unique_email_and_password_confirmation(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $duplicateEmail = $this->postJson('/api/register', [
            'name' => 'Another User',
            'email' => 'TAKEN@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertStringNotContainsString('password123', $duplicateEmail->getContent());

        $mismatchedPassword = $this->postJson('/api/register', [
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertStringNotContainsString('password123', $mismatchedPassword->getContent());
        $this->assertStringNotContainsString('different123', $mismatchedPassword->getContent());
    }

    public function test_user_can_log_in_with_normalized_email_and_read_their_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => '  MEMBER@EXAMPLE.COM ',
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonStructure([
            'message',
            'data' => ['user' => ['id', 'name', 'email', 'created_at', 'updated_at'], 'token'],
        ]);
        $this->assertApiResponseDoesNotExposePassword($login, ['password123', $user->password]);

        $profile = $this->withToken($login->json('data.token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'member@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertApiResponseDoesNotExposePassword($profile, ['password123', $user->password]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'member@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertStringNotContainsString('wrong-password', $response->getContent());
    }

    public function test_user_can_log_out_and_revoke_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertApiResponseDoesNotExposePassword($response, [$user->password]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_endpoints_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->getJson('/api/tasks')->assertUnauthorized();
    }
}
