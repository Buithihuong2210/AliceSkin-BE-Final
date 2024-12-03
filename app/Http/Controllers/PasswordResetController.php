<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\User;

class PasswordResetController extends Controller
{
    // Send reset link to email
    public function sendResetLink(Request $request)
    {

        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $token = Str::random(10);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($token), 'created_at' => Carbon::now()]
        );

        $this->sendResetEmail($request->email, $token);

        return response()->json(['message' => 'Password reset link sent to your email.'], 200);
    }

    // Function to send the reset email
    private function sendResetEmail($email, $token)
    {
        $resetLink = 'https://aliceskin.alhkq.live/password/reset?token=' . $token;
        Mail::send('emails.passwordReset', ['resetLink' => $resetLink], function ($message) use ($email) {
            $message->to($email)
                ->subject('Reset Password Notification');
        });
    }

    // Reset the password
    public function reset(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|confirmed',
            ]);
        }catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ]);
        }

        $passwordReset = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
            return response()->json(['message' => 'Invalid token or email'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully'], 200);
    }
}
