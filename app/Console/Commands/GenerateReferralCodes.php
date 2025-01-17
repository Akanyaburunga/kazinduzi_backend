<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class GenerateReferralCodes extends Command
{
    protected $signature = 'users:generate-referral-codes';
    protected $description = 'Generate referral codes for users without one';

    public function handle()
    {
        $users = User::whereNull('referral_code')->get();

        foreach ($users as $user) {
            $user->generateReferralCode();
            $this->info("Generated referral code for user ID {$user->id}");
        }

        $this->info('Referral code generation complete!');
    }
}