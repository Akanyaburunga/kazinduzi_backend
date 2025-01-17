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
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        // Generate a referral code for the new user
    $user->generateReferralCode();

    // Check if a referral code was used
    if (!empty($data['referral_code'])) {
        $referrer = User::where('referral_code', $data['referral_code'])->first();
        if ($referrer) {
            $user->referred_by = $referrer->id;
            $user->save();
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
