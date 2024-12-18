<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email:rfc,dns|max:255|unique:users',
                'password' => 'required|string|min:8',
                'phone' => 'required|string|max:15',
                'dob' => 'required|date',
                'gender' => 'required|string|in:male,female,other',
                'address' => 'required|string|max:255',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 400);
        }

        // Create user
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'phone' => $validatedData['phone'],
            'dob' => $validatedData['dob'],
            'gender' => $validatedData['gender'],
            'address' => $validatedData['address'],
        ]);

        Mail::to($user->email)->send(new WelcomeEmail($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'dob' => $user->dob,
            'gender' => $user->gender,
            'address' => $user->address,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid login details'
            ], 401);
        }

        $user = User::where('email', $request['email'])->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'id' => $user->id,
            'role' => $user->role,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

}