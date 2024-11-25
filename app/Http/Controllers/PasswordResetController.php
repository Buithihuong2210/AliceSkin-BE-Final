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

        // Validate the email
        $request->validate(['email' => 'required|email']);

        // Check if the user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Generate a token
        $token = Str::random(10);

        // Insert into password_resets table
        DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($token), 'created_at' => Carbon::now()]
        );

        // Send email with the token
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
            // Validate the request
            $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|confirmed',
            ]);
        }catch (\Throwable $th) {
            return response()->json([
                // 'access_token' => $token,
                'message' => $th->getMessage(),
            ]);
        }

        // Find the token in the password_resets table
        $passwordReset = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
            return response()->json(['message' => 'Invalid token or email'], 400);
        }

        // Find the user and reset the password
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully'], 200);
    }
}
