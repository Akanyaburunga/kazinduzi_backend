<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AwardReferralReputation
{
    public function handle(Verified $event)
    {
        $user = $event->user;

        // Check if the user was referred by someone
        if ($user->referred_by) {
            $referrer = User::find($user->referred_by);

            // Award reputation to the referrer
            if ($referrer) {
                $referrer->updateReputation(5, 'Invited a new user who verified their email', $user);
            }
        }
    }
}
