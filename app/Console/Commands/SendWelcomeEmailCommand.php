<?php

namespace App\Console\Commands;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Console\Command;

class SendWelcomeEmailCommand extends Command
{
    protected $signature = 'welcome-email:send {user : The user ID}';

    protected $description = 'Queue a welcome email for an existing user';

    public function handle(): int
    {
        $user = User::find($this->argument('user'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        SendWelcomeEmail::dispatch($user);

        $this->info('Welcome email queued for '.$user->email.'.');

        return self::SUCCESS;
    }
}
