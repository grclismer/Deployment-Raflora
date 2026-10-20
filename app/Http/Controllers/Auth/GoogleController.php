<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google after authentication.
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // 1. Find user by google_id
            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                // 2. Find user by existing email
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    // Security guard: Never authenticate or link Google OAuth to Admin or Staff accounts
                    if (in_array($user->role, ['admin', 'staff'], true)) {
                        return redirect()->route('login')->with('error', 'Google sign-in is not permitted for staff or administrator accounts. Please log in using your credentials.');
                    }

                    // Link Google ID for client account
                    $user->update([
                        'google_id'         => $googleUser->getId(),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]);
                } else {
                    // Parse full name into first_name and last_name
                    $fullName = trim($googleUser->getName() ?? '');
                    $nameParts = explode(' ', $fullName);
                    $firstName = $nameParts[0] ?? null;
                    $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

                    // 3. Register brand new client account
                    $user = User::create([
                        'name'              => $fullName ?: 'Client',
                        'first_name'        => $firstName,
                        'last_name'         => $lastName,
                        'email'             => $googleUser->getEmail(),
                        'google_id'         => $googleUser->getId(),
                        'role'              => 'client',
                        'email_verified_at' => now(),
                        'password'          => bcrypt(Str::random(16)),
                    ]);
                }
            }

            // Security guard: Ensure non-client accounts can never be authenticated via Google OAuth
            if (in_array($user->role, ['admin', 'staff'], true)) {
                return redirect()->route('login')->with('error', 'Google sign-in is not permitted for staff or administrator accounts. Please log in using your credentials.');
            }

            Auth::login($user);
            request()->session()->regenerate();

            return redirect()->intended(route('client.dashboard'))
                ->with('success', 'Welcome, ' . ($user->first_name ?? $user->name) . '!');

        } catch (\Exception $e) {
            \Log::error('Google OAuth callback error: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }
    }
}
