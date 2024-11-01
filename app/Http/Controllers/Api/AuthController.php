<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use \Illuminate\Http\JsonResponse;
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|string|email|max:255|unique:users',
                'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()]
            ]);
            $user = new User([
                'email' => $request->email,
                'password' =>bcrypt($request->password),
            ]);
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Registered successfully'
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if(!Auth::attempt($credentials)){
                return response()->json([
                    'status' => false,
                    'message' => 'Wrong email or password',
                ], 401);
            }

            $user = User::where('email', $request->email)->first();

            $token = preg_replace('/(\d\|)(.+)/', '$2', $user->createToken("API TOKEN")->plainTextToken);
            return response()->json([
                'status' => true,
                'message' => 'Logged in successfully',
                'token' => $token
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        Auth::guard('web')->logout();

        return response()->json(['message' => 'Logged out successfully']);

    }
}
