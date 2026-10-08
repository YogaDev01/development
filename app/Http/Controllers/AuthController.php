<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\UserActivityLog;

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
        // Jika sudah login, redirect ke dashboard. Dashboard dilayani '/',
        // bukan '/dashboard' (route itu tidak ada) - cabang ini praktis tidak
        // pernah tercapai karena middleware 'guest' sudah lebih dulu
        // mengalihkan, tapi kalau gerbang itu suatu saat dicabut, tujuan yang
        // salah berarti 404 alih-alih dashboard.
        if (Auth::check()) {
            return redirect('/');
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

        /*
        | 'status' ikut jadi bagian kredensial.
        |
        | Kolom users.status sudah ada sejak awal dan menu User & Access
        | memvalidasi serta menyimpannya (ACTIVE/INACTIVE), tapi sebelum ini
        | tidak ada satu pun tempat yang MEMBACA-nya untuk memutuskan akses.
        | Akibatnya menonaktifkan user tidak mencabut apa pun: yang
        | bersangkutan tetap bisa login dan menyetujui pembayaran. Yang
        | membuatnya berbahaya: UserController::destroy() menolak penghapusan
        | user yang sudah punya transaksi dengan pesan "Nonaktifkan saja",
        | jadi admin memang diarahkan ke jalan yang ternyata tidak berfungsi.
        |
        | Diletakkan di kredensial Auth::attempt(), bukan sebagai pemeriksaan
        | terpisah setelah login berhasil: dengan begini tidak ada sesi yang
        | pernah terbentuk untuk akun nonaktif.
        */
        $credentials = $request->only('username', 'password') + ['status' => 'ACTIVE'];
        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);

            // Regenerate session untuk security
            $request->session()->regenerate();

            // Audit trail: catat login (actor dari session yang baru terbentuk).
            UserActivityLog::record('auth', 'login', 'Login ke aplikasi');

            // Aplikasi aktif mengikuti App Default pilihan user sejak awal,
            // bukan selalu Finance.
            session(['tesys.app' => Auth::user()->defaultApp()]);

            // Redirect ke halaman yang dituju atau halaman utama (Profil).
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

        /*
        | Akun nonaktif dibedakan dari password salah.
        |
        | Tanpa ini keduanya berbunyi "Username atau password salah", sehingga
        | karyawan yang aksesnya memang sudah dicabut akan melapor "lupa
        | password" dan admin ikut kebingungan mencari sebab yang tidak ada.
        | Yang dibocorkan pesan ini hanya keberadaan akun yang sudah
        | dinonaktifkan - bukan password, dan bukan akun yang aktif - sementara
        | manfaat operasionalnya nyata.
        */
        $akunNonaktif = User::where('username', $request->input('username'))
            ->where('status', '!=', 'ACTIVE')
            ->exists();

        if ($akunNonaktif) {
            return redirect()
                ->back()
                ->withErrors(['login' => 'Akun ini tidak aktif. Hubungi administrator.'])
                ->withInput($request->only('username'));
        }

        return redirect()
            ->back()
            ->withErrors(['login' => 'Username atau password salah.'])
            ->withInput($request->only('username'));
    }

    /*
    |--------------------------------------------------------------------------
    | LUPA SANDI (tamu) — reset password via kode yang dikirim ke email.
    |
    | Alur: user isi Username + Email -> dicek cocok pada akun AKTIF -> kirim
    | kode 6 digit ke email (hash di cache 15 menit) -> user isi kode +
    | password baru -> diverifikasi lalu diperbarui.
    |
    | Semua endpoint di-throttle per IP (route) + rate-limit tambahan di sini.
    | Catatan: mengkonfirmasi kecocokan username+email adalah oracle enumerasi
    | yang disengaja sesuai permintaan; throttle membatasi penyalahgunaannya.
    |--------------------------------------------------------------------------
    */

    private const FORGOT_TTL = 900;   // 15 menit

    private function forgotCacheKey(int $userId): string
    {
        return "auth:forgot-token:{$userId}";
    }

    /** User AKTIF yang username & email-nya cocok, atau null. */
    private function matchForgotUser(Request $request): ?User
    {
        $data = $request->validate([
            'username' => 'required|string',
            'email'    => 'required|email',
        ]);

        return User::where('username', $data['username'])
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])
            ->where('status', 'ACTIVE')
            ->first();
    }

    /** POST /forgot/verify — cek kecocokan username + email. */
    public function forgotVerify(Request $request)
    {
        $match = $this->matchForgotUser($request) !== null;

        return response()->json(['match' => $match]);
    }

    /** POST /forgot/send — kirim kode verifikasi ke email bila cocok. */
    public function forgotSendCode(Request $request)
    {
        $limiterKey = 'forgot-send:' . $request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $sec = RateLimiter::availableIn($limiterKey);
            return response()->json(['ok' => false, 'message' => "Terlalu banyak permintaan. Coba lagi dalam {$sec} detik."], 429);
        }
        RateLimiter::hit($limiterKey, 600);

        $user = $this->matchForgotUser($request);
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Username dan email tidak cocok.'], 422);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($this->forgotCacheKey($user->id), ['hash' => Hash::make($code), 'attempts' => 0], self::FORGOT_TTL);

        try {
            Mail::raw(
                "Kode reset password TESYS Anda: {$code}\n\n"
                . "Kode berlaku 15 menit. Abaikan email ini bila Anda tidak meminta reset password.",
                function ($m) use ($user) {
                    $m->to($user->email)->subject('Kode Reset Password - TESYS');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Gagal kirim kode lupa sandi', ['user_id' => $user->id, 'err' => $e->getMessage()]);
            Cache::forget($this->forgotCacheKey($user->id));
            return response()->json(['ok' => false, 'message' => 'Gagal mengirim email. Hubungi Superadmin.'], 500);
        }

        return response()->json(['ok' => true, 'message' => 'Kode verifikasi telah dikirim ke ' . $this->maskEmail($user->email) . '.']);
    }

    /** POST /forgot/reset — verifikasi kode lalu ganti password. */
    public function forgotReset(Request $request)
    {
        $limiterKey = 'forgot-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 10)) {
            $sec = RateLimiter::availableIn($limiterKey);
            throw ValidationException::withMessages(['code' => "Terlalu banyak percobaan. Coba lagi dalam {$sec} detik."]);
        }
        RateLimiter::hit($limiterKey, 600);

        $request->validate([
            'username' => 'required|string',
            'email'    => 'required|email',
            'code'     => 'required|string',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = $this->matchForgotUser($request);
        if (!$user) {
            throw ValidationException::withMessages(['code' => 'Username dan email tidak cocok.']);
        }

        $cached = Cache::get($this->forgotCacheKey($user->id));
        if (!is_array($cached)) {
            throw ValidationException::withMessages(['code' => 'Kode kedaluwarsa atau belum diminta. Minta kode baru.']);
        }

        if (($cached['attempts'] ?? 0) >= 5) {
            Cache::forget($this->forgotCacheKey($user->id));
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan kode. Minta kode baru.']);
        }

        if (!Hash::check(trim($request->code), $cached['hash'])) {
            $cached['attempts'] = ($cached['attempts'] ?? 0) + 1;
            Cache::put($this->forgotCacheKey($user->id), $cached, self::FORGOT_TTL);
            throw ValidationException::withMessages(['code' => 'Kode verifikasi salah.']);
        }

        $user->update(['password' => Hash::make($request->password)]);
        Cache::forget($this->forgotCacheKey($user->id));

        // Audit trail — pelaku belum login, jadi user terkait dicatat sebagai
        // subjek (actor_* akan null karena guest).
        UserActivityLog::record('auth', 'update', 'Reset password via Lupa Sandi (user: ' . $user->username . ')', $user, 'Password diubah');

        return response()->json(['ok' => true, 'message' => 'Password berhasil direset. Silakan masuk dengan password baru.']);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $maskedName = mb_strlen($name) <= 2
            ? mb_substr($name, 0, 1) . '*'
            : mb_substr($name, 0, 2) . str_repeat('*', max(1, mb_strlen($name) - 2));

        return $maskedName . '@' . $domain;
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT PROCESS
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        // Catat dulu selagi actor masih ada di session.
        UserActivityLog::record('auth', 'logout', 'Logout dari aplikasi');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')
            ->with('success', 'Anda berhasil logout.');
    }
}