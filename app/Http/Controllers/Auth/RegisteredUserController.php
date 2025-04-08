<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cf-turnstile-response' => 'required',
        ]);

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('services.turnstile.secret'),
            'response' => $request->input('cf-turnstile-response'),
            'remoteip' => $request->ip(),
        ]);
    
        if (!optional($response->json())['success']) {
            return back()->withErrors(['turnstile' => 'CAPTCHA verification failed. Please try again.']);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (app()->environment('prod', 'production')) {
            
            event(new Registered($user));
        }
        

        // Generate a referral code for the new user
        $user->generateReferralCode();

    // Check if a referral code was used
    if (!empty($request['referral_code'])) {
        //First if is true
        Log::info('First if is true');
        $referrer = User::where('referral_code', $request['referral_code'])->first();
        if ($referrer) {
            //Refferer is not empty
            Log::info('Refferer is not empty');
            $user->referred_by = $referrer->id;
            $user->save();

            if (app()->environment('local', 'development') || $referredUser->hasVerifiedEmail()) {
                Log::info('In development mode');

                // Check if the user was referred by someone
                if ($user->referred_by) {
                    Log::info('Someone referred the user');
                    $referrer = User::find($user->referred_by);
        
                    // Award reputation to the referrer
                    if ($referrer) {
                        Log::info('The referrer is real');
                        $referrer->updateReputation(5, 'Invited a new user who verified their email', $user);
                    }
                }
            }
        }
    }

    Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }

    protected function showRegistrationForm(Request $request)
    {
        $referralCode = $request->query('ref', '');
        return view('auth.register', compact('referralCode'));
    }
    
}
