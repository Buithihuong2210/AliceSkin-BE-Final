<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();

        return response()->json($users, 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'string|max:255',
                'dob' => 'date',
                'gender' => 'string|in:male,female,other|max:255',
                'image' => 'string|url|max:255',
                'address' => 'string|max:255',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }

        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->name = $request->input('name');
        $user->phone = $request->input('phone');
        $user->dob = $request->input('dob');
        $user->gender = $request->input('gender');
        $user->image = $request->input('image');
        $user->address = $request->input('address');

        $user->save();

        return response()->json([
                'message' => "User updated successfully",
            ]);
    }

    public function destroy($id)
    {
        try{
            $user = User::findOrFail($id);
            $user->delete();
        }
        catch (\Exception $e) {
            return response()->json([
                'message' =>"Can`t not found user with ID is {$id} to delete"
            ],400);
        }

        return response()->json(['message' => 'User deleted successfully']);
    }
    public function getUserById($id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json([
                'message' => "User with ID {$id} not found"
            ], 404);
        }

        return response()->json($user, 200);
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            $user = Auth::user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'Current password is incorrect'], 400);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json(['message' => 'Password changed successfully'], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while changing the password.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function updateRole(Request $request, $userId)
    {
        $request->validate([
            'role' => 'required|string|in:user,staff,admin',
        ]);

        $user = User::findOrFail($userId);
        $user->role = $request->input('role');
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user,
        ]);
    }

}