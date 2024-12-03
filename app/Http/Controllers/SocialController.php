<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SocialController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'password' => bcrypt('12345678'),
                ]
            );

            Auth::login($user);

            $tk = $user->createToken('authToken')->plainTextToken;

            return response()->redirectTo('http://localhost:3000/home?tk='.$tk);

        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Lỗi đăng nhập Google');
        }
    }

}