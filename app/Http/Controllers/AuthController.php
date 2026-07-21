<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW LOGIN FORM
    |--------------------------------------------------------------------------
    */

    public function showLoginForm()
    {
        // Jika sudah login, redirect ke dashboard
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('auth.login');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN PROCESS
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Attempt login
        $credentials = $request->only('username', 'password');
        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            
            // Regenerate session untuk security
            $request->session()->regenerate();

            // Redirect ke halaman yang dituju atau dashboard
            return redirect()
                ->intended('/')
                ->with('success', 'Selamat datang, ' . Auth::user()->name . '!');
        }

        // Jika login gagal
        return redirect()
            ->back()
            ->withErrors(['login' => 'Username atau password salah.'])
            ->withInput($request->only('username'));
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT PROCESS
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')
            ->with('success', 'Anda berhasil logout.');
    }
}