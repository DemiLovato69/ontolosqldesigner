<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        return view('auth.register');
    }
    public function login(Request $request)
    {
        return view('auth.login');
    }
}
