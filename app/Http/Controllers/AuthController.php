<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Percobaan gagal maksimal per (username + IP) sebelum dijeda. */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /** Lama jeda setelah batas tercapai, dalam detik. */
    private const LOGIN_DECAY_SECONDS = 300;

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

        /*
        | Pembatasan percobaan login.
        |
        | Kuncinya username + IP, bukan IP saja: satu kantor biasanya keluar
        | lewat satu IP publik, jadi mengunci per IP berarti satu orang salah
        | ketik lima kali bisa mengunci seluruh kantor. Menggabungkan username
        | membuat yang terkunci hanya pasangan (akun, jaringan) yang memang
        | sedang gagal berulang.
        |
        | Batas rute (throttle:20,1) tetap dipasang sebagai jaring kasar per IP;
        | yang di bawah ini yang menangani serangan terhadap satu akun.
        */
        $throttleKey = Str::lower($request->input('username')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('Login diblokir sementara (percobaan berlebihan)', [
                'username' => $request->input('username'),
                'ip'       => $request->ip(),
                'retry_in' => $seconds,
            ]);

            return redirect()
                ->back()
                ->withErrors(['login' => 'Terlalu banyak percobaan login. Coba lagi dalam ' . $seconds . ' detik.'])
                ->withInput($request->only('username'));
        }

        // Attempt login
        $credentials = $request->only('username', 'password');
        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);

            // Regenerate session untuk security
            $request->session()->regenerate();

            // Redirect ke halaman yang dituju atau dashboard
            return redirect()
                ->intended('/')
                ->with('success', 'Selamat datang, ' . Auth::user()->name . '!');
        }

        // Gagal: catat percobaan (kedaluwarsa otomatis setelah jendela lewat)
        // dan tinggalkan jejak di log — tanpa ini tidak ada cara mengetahui
        // sebuah akun sedang jadi sasaran.
        RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

        Log::info('Login gagal', [
            'username' => $request->input('username'),
            'ip'       => $request->ip(),
            'attempts' => RateLimiter::attempts($throttleKey),
        ]);

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