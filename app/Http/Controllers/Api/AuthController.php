<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'user' => $user,
                'access_token' => $token,
            ]);
        }

        return response()->json(['message' => 'Credenciales incorrectas'], 401);
    }
}
