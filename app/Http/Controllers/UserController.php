<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email|max:255|unique:users',
                'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()]
            ]);
            $user = new User([
                'email' => $request->email,
                'password' =>bcrypt($request->password),
                'current_diagram_id' => NULL
            ]);

            $user->save();
            $diagram = Diagram::create([
                'name' => 'Default',
                'diagram' => NULL,
                'user_id' => $user->id
            ]);
            $user->current_diagram_id = $diagram->id;

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

    public function login(Request $request)
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
}
