<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_a_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'Jane Doe')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name']]])
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertCount(1, $user->tokens);
        Queue::assertPushed(SendWelcomeEmail::class, fn ($job) => $job->user->is($user));
    }

    public function test_registration_requires_valid_fields_and_a_unique_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/register', [
            'name' => '',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);

        Queue::assertNothingPushed();
    }

    public function test_registration_rejects_passwords_exceeding_bcrypts_byte_limit(): void
    {
        $password = str_repeat('é', 40);

        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_user_can_log_in_and_use_the_token(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_registration_rejects_passwords_with_null_bytes(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => "abcd\0efg",
            'password_confirmation' => "abcd\0efg",
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_incorrect_login_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_input_is_validated(): void
    {
        $this->postJson('/api/login', ['email' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'password123',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])->assertTooManyRequests();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/users/'.$user->id)->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/users/'.$user->id)->assertOk();
        $this->assertCount(1, $user->tokens()->get());
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/posts')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
        $this->getJson('/api/weather')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
