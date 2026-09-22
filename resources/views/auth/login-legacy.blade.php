<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login | Tesys</title>

    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">

    <!-- ADMIN LTE -->
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <link rel="icon" href="{{ asset('dist/img/favicon16.ico') }}" type="image/x-icon">

    <style>

        /*
        |--------------------------------------------------------------------------
        | DESIGN TOKENS — same as app.blade
        |--------------------------------------------------------------------------
        */

        :root {
            --navy:        #0D1B2A;
            --navy-mid:    #162030;
            --navy-lt:     #1D2D3F;
            --amber:       #F5A623;
            --amber-dk:    #C97E0A;
            --amber-tint:  rgba(245, 166, 35, 0.08);
            --amber-border:rgba(245, 166, 35, 0.25);
            --radius-sm:   6px;
            --radius-md:   10px;
            --radius-lg:   14px;
        }

        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        *, *::before, *::after { box-sizing: border-box; }

        body,
        .login-page {
            background: var(--navy) !important;
            min-height: 100vh;
        }

        /* Subtle grid overlay — same signature as landing page */
        .login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }

        .login-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 20%, transparent 80%);
            pointer-events: none;
        }

        /* Ambient glow — top-left blue, bottom-right amber */
        .login-page::after {
            content: '';
            position: absolute;
            top: -120px; left: -120px;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(27,79,138,0.30) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-glow-2 {
            position: absolute;
            bottom: -80px; right: -80px;
            width: 380px; height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245,166,35,0.10) 0%, transparent 70%);
            pointer-events: none;
        }

        /*
        |--------------------------------------------------------------------------
        | CONTAINER
        |--------------------------------------------------------------------------
        */

        .login-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }

        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo img {
            width: 200px;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 14px;
        }

        .login-logo p {
            color: #5A7080;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .card {
            border: 1px solid rgba(255,255,255,0.07) !important;
            border-radius: var(--radius-lg) !important;
            background: var(--navy-mid) !important;
            box-shadow:
                0 24px 64px rgba(0,0,0,0.40),
                0 0 0 1px rgba(255,255,255,0.04) !important;
        }

        /* Amber top accent line */
        .card::before {
            content: '';
            display: block;
            height: 3px;
            background: linear-gradient(90deg, var(--amber), transparent);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .card-body {
            padding: 32px 32px 36px;
        }

        /*
        |--------------------------------------------------------------------------
        | CARD HEADER TEXT
        |--------------------------------------------------------------------------
        */

        .login-heading {
            margin-bottom: 28px;
        }

        .login-heading h2 {
            font-size: 20px;
            font-weight: 700;
            color: #FFFFFF;
            margin: 0 0 4px;
            letter-spacing: -0.01em;
        }

        .login-heading p {
            font-size: 13px;
            color: #5A7080;
            margin: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #7A90A4;
            margin-bottom: 8px;
            display: block;
        }

        /* Input group */
        .input-group-text {
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid rgba(255,255,255,0.10) !important;
            border-right: 0 !important;
            color: #5A7080 !important;
            width: 44px;
            justify-content: center;
            border-radius: var(--radius-sm) 0 0 var(--radius-sm) !important;
            transition: border-color 0.15s, color 0.15s;
        }

        .form-control {
            height: 46px !important;
            font-size: 14px !important;
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid rgba(255,255,255,0.10) !important;
            border-left: 0 !important;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0 !important;
            color: #FFFFFF !important;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-control::placeholder {
            color: #3A5060 !important;
        }

        /* Joined focus — highlight entire group */
        .input-group:focus-within .input-group-text {
            border-color: var(--amber) !important;
            color: var(--amber) !important;
        }

        .input-group:focus-within .form-control {
            border-color: var(--amber) !important;
            box-shadow: 0 0 0 3px rgba(245,166,35,0.12) !important;
            outline: none !important;
        }

        .form-control.is-invalid {
            border-color: #EF4444 !important;
        }

        /*
        |--------------------------------------------------------------------------
        | REMEMBER ME
        |--------------------------------------------------------------------------
        */

        .icheck-primary label {
            font-size: 13px;
            font-weight: 400;
            color: #5A7080;
            cursor: pointer;
        }

        .icheck-primary input[type="checkbox"] {
            accent-color: var(--amber);
        }

        /*
        |--------------------------------------------------------------------------
        | SUBMIT BUTTON
        |--------------------------------------------------------------------------
        */

        .btn-login {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 48px;
            background: var(--amber) !important;
            border: none !important;
            border-radius: var(--radius-sm) !important;
            color: var(--navy) !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s, transform 0.12s;
        }

        .btn-login:hover {
            background: var(--amber-dk) !important;
            box-shadow: 0 8px 24px rgba(245,166,35,0.25) !important;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /*
        |--------------------------------------------------------------------------
        | ALERTS
        |--------------------------------------------------------------------------
        */

        .alert {
            border-radius: var(--radius-sm) !important;
            margin-bottom: 20px;
            font-size: 13px;
            border: none !important;
        }

        .alert-success {
            background: rgba(16,185,129,0.12) !important;
            color: #6EE7B7 !important;
            border-left: 3px solid #10B981 !important;
        }

        .alert-danger {
            background: rgba(239,68,68,0.10) !important;
            color: #FCA5A5 !important;
            border-left: 3px solid #EF4444 !important;
        }

        .alert .close {
            color: inherit !important;
            opacity: 0.6;
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER NOTE
        |--------------------------------------------------------------------------
        */

        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 11px;
            color: #2D4050;
            letter-spacing: 0.05em;
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 480px) {
            .login-page   { padding: 20px 16px; }
            .card-body    { padding: 24px 20px 28px; }
            .login-logo img { width: 160px; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }

    </style>

</head>

<body class="hold-transition login-page">

    <div class="login-glow-2"></div>

    <div class="login-container">

        <!-- LOGO -->
        <div class="login-logo">
            <img
                src="{{ asset('dist/img/TesysLogo.png') }}"
                alt="Terex System"
            >
            <p>Internal Management Platform</p>
        </div>

        <!-- CARD -->
        <div class="card">

            <div class="card-body">

                <!-- Heading -->
                <div class="login-heading">
                    <h2>Selamat Datang</h2>
                    <p>Masuk untuk melanjutkan ke sistem</p>
                </div>

                <!-- SUCCESS -->
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-2"></i>
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                @endif

                <!-- ERROR -->
                @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong><i class="fas fa-exclamation-triangle mr-2"></i>Terjadi kesalahan:</strong>
                    @if($errors->has('login'))
                        <div class="mt-1">{{ $errors->first('login') }}</div>
                    @else
                        <ul class="mb-0 mt-1 pl-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                @endif

                <!-- FORM -->
                <form action="{{ url('/login') }}" method="POST">

                    @csrf

                    <!-- USERNAME -->
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-user" style="font-size:13px;"></i>
                                </span>
                            </div>
                            <input
                                type="text"
                                name="username"
                                class="form-control @error('username') is-invalid @enderror"
                                placeholder="Masukkan username"
                                value="{{ old('username') }}"
                                required
                                autofocus
                            >
                        </div>
                        @error('username')
                            <small class="text-danger" style="font-size:12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <!-- PASSWORD -->
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-lock" style="font-size:13px;"></i>
                                </span>
                            </div>
                            <input
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Masukkan password"
                                required
                            >
                        </div>
                        @error('password')
                            <small class="text-danger" style="font-size:12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <!-- REMEMBER ME -->
                    <div class="form-group mb-4">
                        <div class="icheck-primary d-flex align-items-center" style="gap:8px;">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember" style="margin:0;">Remember Me</label>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <div class="form-group mb-0">
                        <button type="submit" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            LOGIN
                        </button>
                    </div>

                </form>

            </div>

        </div>

        <!-- FOOTER -->
        <div class="login-footer">
            &copy; {{ date('Y') }} Terex System &mdash; Internal Use Only
        </div>

    </div>

    <!-- JQUERY -->
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>

    <!-- BOOTSTRAP -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <!-- ADMIN LTE -->
    <script src="{{ asset('dist/js/adminlte.min.js') }}"></script>

</body>

</html>