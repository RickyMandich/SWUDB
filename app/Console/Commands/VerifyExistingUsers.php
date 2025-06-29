<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class VerifyExistingUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:verify-existing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark all existing users as email verified';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Marking all existing users as email verified...');

        $users = User::whereNull('email_verified_at')->get();
        $count = 0;

        foreach ($users as $user) {
            $user->markEmailAsVerified();
            $count++;
            $this->line("Verified: {$user->email}");
        }

        $this->info("Successfully verified {$count} users.");

        return Command::SUCCESS;
    }
}
