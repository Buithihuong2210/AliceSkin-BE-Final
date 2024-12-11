<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    // Handle the callback from Facebook
    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $facebookUser->getEmail()],
                [
                    'name' => $facebookUser->getName(),
                    'facebook_id' => $facebookUser->getId(),
                    'image' => $facebookUser->getAvatar(),
                    'password' => Hash::make('12345678')
                ]
            );

            Auth::login($user);

            $tk = $user->createToken('authToken')->plainTextToken;

            return redirect()->to('http://localhost:3000/home?tk=' . $tk);

        } catch (\Exception $e) {
            Log::error('Error occurred: ' . $e->getMessage());
            return redirect('/login')->withErrors(['error' => 'Failed to login using Facebook.']);
        }
    }
}