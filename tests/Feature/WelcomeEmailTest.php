<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_dispatches_a_welcome_email_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->artisan('welcome-email:send', ['user' => $user->id])
            ->expectsOutput('Welcome email queued for '.$user->email.'.')
            ->assertSuccessful();

        Queue::assertPushed(SendWelcomeEmail::class, fn ($job) => $job->user->is($user));
    }

    public function test_the_command_rejects_an_unknown_user(): void
    {
        Queue::fake();

        $this->artisan('welcome-email:send', ['user' => 99999])
            ->expectsOutput('User not found.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_the_job_sends_a_welcome_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        SendWelcomeEmail::dispatchSync($user);

        Mail::assertSent(WelcomeMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->user->is($user));
    }

    public function test_the_email_contains_the_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);
        $mail = new WelcomeMail($user);

        $mail->assertHasSubject('Welcome to Juicebox');
        $mail->assertSeeInHtml('Jane Doe');
        $mail->assertSeeInText('Welcome to Juicebox');
    }
}
