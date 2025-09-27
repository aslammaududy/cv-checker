<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        request()->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'confirm_password' => 'required|same:password',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'User registered successfully'
        ]);
    }

    public function login(Request $request)
    {
        request()->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'Email not found'
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password not match'
            ]);
        }

        $token = $user->createToken('rakamin-backend-test');

        return response()->json([
            'token' => $token->plainTextToken,
            'message' => 'Login success'
        ]);
    }
}
