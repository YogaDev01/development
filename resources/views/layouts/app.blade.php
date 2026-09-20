{{--
    Tesys — layout utama (shell modern: sidebar terang + topbar).

    Struktur mengikuti design system di public/css/tesys-ui.css:
        .shell > .sidebar + .main > .topbar + .content

    Markup halaman TIDAK perlu diubah: seluruh asset lama (AdminLTE, Bootstrap,
    DataTables, TomSelect, flatpickr, SweetAlert) tetap dimuat, dan
    tesys-ui-compat.css memetakan class Bootstrap/AdminLTE ke tampilan baru.

    Versi sebelumnya (shell AdminLTE + re-skin) disimpan di
    layout ini adalah satu-satunya layout aplikasi (versi lama app-adminlte
    dan app-legacy sudah dihapus; riwayatnya ada di git).
--}}
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('dist/img/favicon16.ico') }}" type="image/x-icon">

    {{-- Tema dipasang SEBELUM satu lembar gaya pun diminta.

         Kalau pemasangannya ditunda sampai skrip di kaki halaman jalan,
         seluruh aplikasi sempat tampil dengan tema terang lalu berkedip ke
         gelap di setiap perpindahan halaman. Di aplikasi yang halamannya
         dimuat ulang penuh seperti ini, kedipan itu terjadi puluhan kali
         sehari.

         Kuncinya sama dengan halaman masuk (tesys-theme), jadi pilihan yang
         dibuat di layar masuk langsung berlaku begitu orang masuk. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('tesys-theme');
                document.documentElement.setAttribute(
                    'data-theme', t === 'dark' ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <title>@yield('title', 'Dashboard') | TESYS</title>

    <!-- FONT AWESOME (ikon di dalam halaman masih memakainya) -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">

    <!-- DATATABLE -->
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">

    {{-- BOOTSTRAP 4.6.1 — grid, utilities, dan komponen dasar.

         Dulu di sini dimuat dist/css/adminlte.min.css (1,4 MB), yang
         sebetulnya adalah Bootstrap 4.6.1 disusul 1,2 MB lapisan tema
         AdminLTE. Lapisan tema itu tidak dipakai sama sekali: seluruh
         kelas strukturalnya — .main-sidebar, .content-wrapper,
         .main-header, .small-box, .info-box — nol pemakaian, karena
         Tesys punya shell sendiri. Kelas .card-* yang masih tersisa pun
         justru dinetralkan lagi oleh tesys-ui-compat.css.

         Berkas di bawah adalah separuh depan berkas itu, dipotong tepat
         di batas akhir @media print milik Bootstrap. Byte-identik dengan
         yang selama ini berjalan, jadi bukan "Bootstrap versi lain" —
         termasuk nilai variabel SCSS yang sudah disesuaikan AdminLTE.

         Hasilnya beban CSS halaman turun dari 1.780 KB ke 576 KB. --}}
    <link rel="stylesheet" href="{{ asset('css/bootstrap-4.6.1.css') }}?v={{ filemtime(public_path('css/bootstrap-4.6.1.css')) }}">

    <!-- TOM SELECT -->
    {{-- Pustaka pihak ketiga dilayani dari server sendiri, bukan CDN.
         Alasannya tiga: (1) tanpa SRI, skrip CDN yang tersusupi berjalan penuh
         di halaman yang memegang data keuangan; (2) 'tom-select' tanpa versi
         berarti pembaruan mayor bisa masuk diam-diam dan merusak halaman;
         (3) aplikasi ini dipakai di jaringan kantor yang tidak selalu punya
         akses keluar - kalau CDN tak terjangkau, dropdown-nya mati. --}}
    <link rel="stylesheet" href="{{ asset('plugins/tom-select/tom-select.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/bootstrap-select/bootstrap-select.min.css') }}">

    <!-- Flatpickr (self-hosted — sebagian jaringan kantor memblokir jsdelivr) -->
    <link rel="stylesheet" href="{{ asset('plugins/flatpickr/dist/flatpickr.min.css') }}">
    <script src="{{ asset('plugins/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('plugins/flatpickr/dist/l10n/id.js') }}"></script>

    <!-- SWEET ALERT -->
    <script src="{{ asset('plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>

    <!-- BASE STYLE (komponen app yang tidak ada di Bootstrap: tss-*, form-clean, dll) -->
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-base.css') }}?v={{ filemtime(public_path('css/tesys-ui-base.css')) }}">

    <!-- STACK STYLE -->
    @stack('styles')

    {{--
        ===== Tesys UI =====
        Urutan wajib: shell -> tokens -> compat -> pages -> motion -> luxe.
        tesys-ui.css membawa :root versi lama, jadi tokens.css harus SESUDAHNYA
        supaya shadow/radius hasil revisi yang dipakai.
    --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui.css') }}?v={{ filemtime(public_path('css/tesys-ui.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-tokens.css') }}?v={{ filemtime(public_path('css/tesys-ui-tokens.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-components.css') }}?v={{ filemtime(public_path('css/tesys-ui-components.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-compat.css') }}?v={{ filemtime(public_path('css/tesys-ui-compat.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-pages.css') }}?v={{ filemtime(public_path('css/tesys-ui-pages.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-shell.css') }}?v={{ filemtime(public_path('css/tesys-ui-shell.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-motion.css') }}?v={{ filemtime(public_path('css/tesys-ui-motion.css')) }}">

    {{-- Lapis luxe: chrome gelap (sidebar + topbar) di atas isi terang.
         Dimuat paling akhir karena beberapa aturan sidebar di tesys-ui.css
         menunjuk token permukaan, yang harus tetap terang. --}}
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-luxe.css') }}?v={{ filemtime(public_path('css/tesys-ui-luxe.css')) }}">
</head>

<body class="tesys-ui {{ request()->is('/') ? 'dashboard-page' : '' }}">

@php
    // effectiveRole() supaya sidebar konsisten dengan View As
    // (superadmin mencoba UI sebagai role lain) — lihat User model.
    $role = Auth::user()->effectiveRole();
    $can  = fn (string $key): bool => $role && $role->hasMenu($key);

    // 'project*' sengaja dihindari — juga cocok dengan 'project-monitoring'
    // (halaman berbeda, prefix kata sama), jadi harus persis 'project'/'project/...'.
    $financeProjectActive = request()->is('project', 'project/*');
    $fmActive             = request()->is('finance-summary*', 'reports*', 'invoice-cash-receipt*', 'delivery-note*', 'project', 'project/*')
        || (request()->is('budget-plan*', 'budget-request*') && request('from') !== 'delivery');

    $userName = Auth::user()->name ?: Auth::user()->username;
    $initials = collect(explode(' ', (string) $userName))
        ->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->implode('');

    /*
     | Aplikasi aktif menentukan isi sidebar.
     |
     | Urutan penentuannya: alamat /app/<key> yang sedang dibuka, lalu aplikasi
     | terakhir yang dipilih (disimpan AppShellController & beranda Finance),
     | baru bawaan config. Langkah kedua itu perlu karena beberapa modul
     | dipakai bersama — membuka Customer dari Logistic tidak boleh melempar
     | tampilan kembali ke Finance.
     */
    $apps       = config('apps.items');
    $appTerakhir = session('tesys.app');
    $currentApp = request()->is('app/*') && isset($apps[request()->segment(2)])
        ? request()->segment(2)
        : (isset($apps[$appTerakhir]) ? $appTerakhir : config('apps.default'));
    $currentAppConfig = $apps[$currentApp];
@endphp

<div class="shell">

    {{-- ===================================================== --}}
    {{-- SIDEBAR — menu modul aplikasi yang sedang dibuka       --}}
    {{-- ===================================================== --}}

    <aside class="sidebar" id="tsSidebar">

        {{-- Lambang perusahaan sekaligus tombol pindah aplikasi. Berkas
             logonya berlatar hitam pekat (bukan transparan), jadi dipakai
             blend "screen" agar latar itu melebur ke merah kotaknya dan hanya
             garis putih lambangnya yang tersisa. --}}
        <div class="brand">

            <button type="button" class="brand-mark" id="appSwitchBtn"
                    aria-haspopup="true" aria-expanded="false" aria-controls="appSwitch"
                    title="Pindah aplikasi">
                {{-- ts_icon.png, berkas yang SAMA dengan lambang di sudut kiri
                     atas halaman login. Sebelumnya di sini dipakai
                     ts_logo_dark.png (277px) yang dikecilkan ke 24px lalu
                     dilebur ke latar merah dengan mix-blend-mode — dua hal
                     yang sama-sama melunakkan tepinya. Berkas ini 702px dan
                     sudah membawa kotak merahnya sendiri, jadi ia dipasang
                     apa adanya, persis seperti di login. --}}
                <img src="{{ asset('dist/img/ts_icon.png') }}" alt="Tesys">
            </button>

            {{-- Wordmark yang SAMA dengan halaman login (dist/img/tesys.png):
                 satu berkas, satu bentuk huruf, satu swoosh. Sebelumnya di sini
                 tertulis "Terex System" sebagai teks biasa, dan itu membuat
                 merek terbaca berbeda antara layar masuk dan layar kerja.

                 Nama aplikasi yang sedang dibuka turun ke baris bawah, bersama
                 tombol perkecil/perbesar menu. --}}
            <a href="{{ $currentAppConfig['home'] }}" class="brand-text" aria-label="Terex System">
                <img class="brand-wordmark" src="{{ asset('dist/img/tesys.png') }}" alt="Terex System">
            </a>

            {{-- Enam tombol aplikasi. Muncul turun bertahap; jeda tiap tombol
                 diatur lewat --i pada masing-masing elemen. --}}
            <div class="app-switch" id="appSwitch" role="menu" aria-label="Pilih aplikasi">
                @foreach($apps as $key => $item)
                <a href="{{ $item['home'] }}" role="menuitem" @style(['--i:' . $loop->index])
                   class="app-switch-item {{ $currentApp === $key ? 'active' : '' }}"
                   @if($currentApp === $key) aria-current="true" @endif>
                    <span class="asi-ic"><i class="{{ $item['icon'] }}"></i></span>
                    <span class="asi-label">{{ $item['label'] }}</span>
                </a>
                @endforeach
            </div>

        </div>

        {{-- Baris aplikasi yang sedang dibuka, DI BAWAH garis emas.

             Dipisahkan dari blok merek di atasnya karena keduanya menjawab
             pertanyaan yang berbeda: blok merek menjawab "sistem apa ini",
             baris ini menjawab "bagian mana yang sedang saya buka". Nama
             bagian juga yang paling sering berganti, jadi ia duduk lebih
             dekat ke daftar menu yang ikut berganti bersamanya. --}}
        <div class="brand-app">
            <span class="brand-sub">{{ $currentAppConfig['sub'] ?? $currentAppConfig['label'] }}</span>
            <button type="button" class="brand-toggle" data-sidebar-toggle
                    title="Perkecil / perbesar menu" aria-label="Perkecil atau perbesar menu">
                <i class="fas fa-angle-double-left" aria-hidden="true"></i>
            </button>
        </div>

        {{-- Isi menu tiap aplikasi dibaca dari config/navigation.php oleh
             satu partial yang sama — lihat layouts/partials/nav.blade.php. --}}
        <nav class="nav">
            @include('layouts.partials.nav')
        </nav>

        {{-- Jam WIB. Nilai awal dirender server supaya tidak ada "--:--"
             yang sempat terlihat sebelum skrip jalan, dan data-epoch
             dipakai tesys-clock.js untuk mengoreksi jam perangkat yang
             meleset — lihat catatan di berkas itu. Zona ditulis tegas
             Asia/Jakarta, bukan mengikuti zona laptop pengguna: satu
             acuan waktu untuk seluruh perusahaan. --}}
        <div class="sidebar-clock" data-epoch="{{ (int) round(microtime(true) * 1000) }}">
            <span class="sc-row">
                <span class="sc-time">{{ now('Asia/Jakarta')->format('H:i') }}</span>
                <span class="sc-wib">WIB</span>
            </span>
            <span class="sc-date">{{ \Carbon\Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMM YYYY') }}</span>
        </div>

    </aside>

    {{-- ===================================================== --}}
    {{-- MAIN                                                   --}}
    {{-- ===================================================== --}}

    <div class="main">

        <div class="topbar">

            <div class="topbar-lead">
                {{-- Tombol menu di topbar hanya untuk LAYAR SEMPIT. Di lebar itu
                     sidebar berubah jadi laci tertutup, sehingga tombol yang ada
                     di dalamnya tidak bisa dipakai membukanya kembali. Di layar
                     lebar tombol ini disembunyikan lewat CSS; yang dipakai adalah
                     tombol di dalam sidebar, di samping nama aplikasi. --}}
                <button type="button" class="icon-btn sidebar-toggle" id="sidebarToggle" data-sidebar-toggle
                        title="Buka / tutup menu" aria-label="Buka atau tutup menu">
                    <i class="fas fa-bars"></i>
                </button>
                {{-- Kelas topbar-titles hanya HOOK GAYA: .page-title memakai
                     overflow:hidden untuk ellipsis judul panjang, jadi aturan
                     emas di bawahnya akan terpotong kalau dipasang di sana. --}}
                <div class="topbar-titles" style="min-width:0;">
                    {{-- Diisi oleh tesys-ui.js dari judul & breadcrumb milik halaman;
                         fallback-nya @yield('title') supaya tetap terisi tanpa JS. --}}
                    <div class="crumb" id="tsCrumb"></div>
                    <div class="page-title" id="tsTitle">@yield('title', 'Dashboard')</div>
                </div>
            </div>

            <div class="topbar-actions">

                {{-- Command palette: pindah halaman & cari dokumen lintas modul.
                     Selalu ada di semua halaman, berbeda dari pencarian dalam
                     halaman di sebelahnya yang hanya menyaring tabel di layar. --}}
                <button type="button" class="ts-cmd-trigger" data-cmd-open
                        title="Cari halaman, dokumen, dan aksi (Ctrl+K)" aria-label="Cari di seluruh TESYS">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span>Cari di TESYS…</span>
                    <span class="ts-cmd-kbd" aria-hidden="true">Ctrl K</span>
                </button>

                {{-- Pencarian cepat: menyaring tabel di halaman yang sedang dibuka.
                     Disembunyikan otomatis oleh tesys-shell.js bila halaman tidak
                     punya tabel, supaya tidak ada kolom yang tampak tapi mati. --}}
                <form class="search" id="tsQuickSearchWrap" onsubmit="return false;" hidden>
                    <i class="fas fa-search" style="font-size:12px;"></i>
                    <input type="search" id="tsQuickSearch" placeholder="Cari di halaman ini…" autocomplete="off"
                           style="border:none;background:none;outline:none;font:inherit;color:inherit;width:100%;">
                </form>

                @yield('page-actions')

                {{-- To Do Bell — ringkasan task dari TodoListController::fetchBell() --}}
                <div class="dropdown" id="todoBellWrap">
                    <button type="button" class="icon-btn" data-toggle="dropdown" data-display="static" id="todoBellToggle" title="Todo List">
                        <i class="fas fa-tasks"></i>
                        <span id="todoBadge" class="badge badge-warning" style="display:none;"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right ts-pop" id="todoDropdown">
                        <div class="ts-pop-head">
                            <strong>Todo List</strong>
                            <a href="{{ route('todo-list.index') }}">Lihat semua &rarr;</a>
                        </div>
                        <div id="todoList" class="ts-pop-body"></div>
                        <div id="todoEmpty" class="ts-pop-empty" style="display:none;">
                            <i class="fas fa-check-circle d-block mb-2"></i>
                            Semua tugas sudah selesai.
                        </div>
                    </div>
                </div>

                {{-- Notification Bell --}}
                <div class="dropdown" id="notifBellWrap">
                    <button type="button" class="icon-btn" data-toggle="dropdown" data-display="static" id="notifBellToggle" title="Notifikasi">
                        <i class="far fa-bell"></i>
                        <span id="notifBadge" class="badge badge-danger" style="display:none;"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right ts-pop" id="notifDropdown">
                        <div class="ts-pop-head">
                            <strong>Notifikasi</strong>
                            <a href="#" id="notifMarkAllRead">Tandai semua dibaca</a>
                        </div>
                        <div id="notifList" class="ts-pop-body"></div>
                        <div id="notifEmpty" class="ts-pop-empty" style="display:none;">
                            <i class="far fa-bell-slash d-block mb-2"></i>
                            Belum ada notifikasi.
                        </div>
                    </div>
                </div>

                {{-- Akun — avatar di pojok kanan atas, panelnya turun ke bawah.
                     ID #userBtn/#userPop dipertahankan supaya buka/tutupnya tetap
                     ditangani tesys-ui.js yang sudah ada. --}}
                <div class="topbar-user">

                    <button type="button" class="user-btn" id="userBtn" title="{{ $userName }}" aria-haspopup="true" aria-expanded="false">
                        <span class="avatar">
                            @if(Auth::user()->avatar_url)
                                <img src="{{ Auth::user()->avatar_url }}" alt="">
                            @else
                                {{ $initials ?: 'U' }}
                            @endif
                        </span>
                    </button>

                    <div class="user-pop" id="userPop">
                        {{-- Identitas dulu (siapa saya), baru peran (sebagai apa). --}}
                        <div class="user-pop-head">
                            <div class="n">{{ $userName }}</div>
                            <div class="e">{{ Auth::user()->username }}</div>
                            <span class="user-pop-role">{{ Str::upper($role->name ?? '—') }}{{ Auth::user()->accessGroup?->name ? ' · ' . Auth::user()->accessGroup->name : '' }}</span>
                        </div>
                        <div class="user-pop-sep"></div>

                        <a href="{{ route('profile.show') }}" class="user-pop-item" style="text-decoration:none;">
                            <i class="fas fa-id-badge"></i>Profile Saya
                        </a>

                        {{-- Panduan & General Feedback pindah ke sini dari seksi
                             "General" tiap sidebar: keduanya bantuan akun yang berlaku
                             di seluruh aplikasi, bukan navigasi modul — dan sebelumnya
                             harus diulang di enam sidebar.

                             Label, ikon, dan tujuannya tetap dibaca dari
                             config/navigation.php (sumber yang sama dengan sidebar),
                             termasuk penanganan 'route:' berikut penjagaan Route::has()
                             — disalin dari layouts/partials/nav.blade.php supaya menu
                             yang route-nya hilang tidak meledakkan seluruh layout. --}}
                        @php
                            $itemBantuan = collect(['panduan', 'feedback'])
                                ->map(fn ($k) => config('navigation.items.' . $k))
                                ->filter()
                                ->map(function ($item) {
                                    $url = $item['url'] ?? null;
                                    if (is_string($url) && str_starts_with($url, 'route:')) {
                                        $nama = substr($url, 6);
                                        $item['href'] = \Route::has($nama) ? route($nama) : null;
                                    } else {
                                        $item['href'] = $url;
                                    }
                                    return $item;
                                })
                                ->filter(fn ($item) => $item['href'] !== null);
                        @endphp
                        @foreach($itemBantuan as $bantuan)
                        <a href="{{ $bantuan['href'] }}" class="user-pop-item" style="text-decoration:none;">
                            <i class="{{ $bantuan['icon'] }}"></i>{{ $bantuan['label'] }}
                        </a>
                        @endforeach

                        {{-- Ganti tampilan role (superadmin saja). Rute, nama field, dan
                             daftar role TIDAK berubah — hanya istilah & susunannya
                             dibuat lebih mudah dipahami daripada "View As". --}}
                        @if(Auth::user()->isRealSuperadmin())
                        <div class="user-pop-sep"></div>
                        <div class="user-pop-group">
                            <div class="user-pop-group-label">Ganti Role</div>
                            @if(Auth::user()->isViewingAs())
                                <form action="{{ route('view-as.reset') }}" method="POST" class="mb-0">
                                    @csrf
                                    <button type="submit" class="btn btn-light btn-sm btn-block">Kembali ke Superadmin</button>
                                </form>
                            @else
                                <form action="{{ route('view-as') }}" method="POST" class="mb-0">
                                    @csrf
                                    <select name="role_id" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Tampilkan sebagai role">
                                        <option value="">Tampilkan sebagai…</option>
                                        @foreach(\App\Models\Role::where('status', 'ACTIVE')->whereRaw('LOWER(name) != ?', ['superadmin'])->orderBy('name')->get() as $viewAsRole)
                                        <option value="{{ $viewAsRole->id }}">{{ Str::upper($viewAsRole->name) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                        </div>
                        @endif

                        <div class="user-pop-sep"></div>

                        {{-- Pengalih tema. Ditaruh tepat di atas Logout karena
                             keduanya sama-sama setelan akun, bukan navigasi —
                             dan karena di sinilah orang mencarinya. --}}
                        <button type="button" class="user-pop-item" id="themeToggle"
                                style="width:100%;" aria-pressed="false">
                            <i class="fas fa-moon ic-to-dark" aria-hidden="true"></i>
                            <i class="fas fa-sun ic-to-light" aria-hidden="true"></i>
                            <span class="theme-label">Tema Gelap</span>
                        </button>

                        <div class="user-pop-sep"></div>

                        <form action="{{ route('logout') }}" method="POST" id="logout-form" class="mb-0">
                            @csrf
                            <button type="button" class="user-pop-item danger" style="width:100%;" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt"></i>Logout
                            </button>
                        </form>

                        <div class="user-pop-sep"></div>
                        <div class="user-pop-foot">TESYS versi {{ config('changelog.current', '1.0.0') }}</div>
                    </div>

                </div>

            </div>

        </div>

        @if(Auth::user()->isViewingAs())
        <div class="viewas-banner">
            <i class="fas fa-user-secret"></i>
            <span>Melihat sebagai <strong>{{ Str::upper($role->name) }}</strong> — ini hanya preview, bukan role sungguhan Anda.</span>
            <form action="{{ route('view-as.reset') }}" method="POST" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-dark btn-sm">Kembali ke Superadmin</button>
            </form>
        </div>
        @endif

        <div class="content">
            @yield('content')
        </div>

    </div>

</div>

<!-- ===================================================== -->
<!-- SCRIPT -->
<!-- ===================================================== -->

<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>

<!-- TESYS TABLE (kendali daftar: Page Size masuk ke bilah kendali) -->
<script src="{{ asset('js/tesys-table.js') }}?v={{ filemtime(public_path('js/tesys-table.js')) }}"></script>

<!-- TESYS COLUMN FILTER (filter header ala Excel untuk DataTables) -->
<script src="{{ asset('js/tesys-column-filter.js') }}?v={{ filemtime(public_path('js/tesys-column-filter.js')) }}"></script>

<!-- TESYS TIMELINE (riwayat workflow) -->
<script src="{{ asset('js/tesys-timeline.js') }}?v={{ filemtime(public_path('js/tesys-timeline.js')) }}"></script>

<!-- TESYS CHART (bakuan grafik + keadaan kosong; menunggu Chart.js halaman) -->
<script src="{{ asset('js/tesys-chart.js') }}?v={{ filemtime(public_path('js/tesys-chart.js')) }}"></script>

{{-- TESYS WORKFLOW — perjalanan dokumen (komponen x-workflow-flow) + drawer.
     Ditulis sebagai komentar Blade, BUKAN komentar HTML: tag komponen di dalam
     <!-- --> tetap dikompilasi Blade dan membuat komponen tak tertutup. --}}
<script src="{{ asset('js/tesys-workflow.js') }}?v={{ filemtime(public_path('js/tesys-workflow.js')) }}"></script>

<!-- TESYS DRAWER (panel tinjau dari kanan) -->
<script src="{{ asset('js/tesys-drawer.js') }}?v={{ filemtime(public_path('js/tesys-drawer.js')) }}"></script>

<!-- TESYS ROW ACTIONS (menu aksi pada kolom tabel) -->
<script src="{{ asset('js/tesys-row-actions.js') }}?v={{ filemtime(public_path('js/tesys-row-actions.js')) }}"></script>

<!-- TESYS SEARCHABLE SELECT (pengganti Tom Select) -->
<script src="{{ asset('js/tesys-searchable-select.js') }}?v={{ filemtime(public_path('js/tesys-searchable-select.js')) }}"></script>

<!-- TOM SELECT -->
<script src="{{ asset('plugins/tom-select/tom-select.complete.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-select/bootstrap-select.min.js') }}"></script>

@if(session('success'))
<script type="application/json" id="tesysFlashSuccess">@json(session('success'))</script>
<script>
Swal.fire({
    toast: true, position: 'top-end', icon: 'success',
    title: JSON.parse(document.getElementById('tesysFlashSuccess').textContent),
    showConfirmButton: false, timer: 3000, timerProgressBar: true
});
</script>
@endif

@if(session('error'))
<script type="application/json" id="tesysFlashError">@json(session('error'))</script>
<script>
Swal.fire({
    toast: true, position: 'top-end', icon: 'error',
    title: JSON.parse(document.getElementById('tesysFlashError').textContent),
    showConfirmButton: false, timer: 3000, timerProgressBar: true
});
</script>
@endif

<!-- LOGOUT CONFIRMATION -->
<script>
/* =====================================================================
   PENGALIH TEMA
   ---------------------------------------------------------------------
   Menukar atribut data-theme di <html> dan menyimpannya. Seluruh warna
   aplikasi dibaca dari token yang menempel pada atribut itu, jadi
   pergantiannya cukup satu baris — tidak ada kelas yang perlu disentuh
   dan tidak ada halaman yang perlu dimuat ulang.
   ===================================================================== */
(function () {
    'use strict';

    var tombol = document.getElementById('themeToggle');
    if (!tombol) return;

    var akar  = document.documentElement;
    var label = tombol.querySelector('.theme-label');

    function terapkan(tema) {
        akar.setAttribute('data-theme', tema);
        tombol.setAttribute('aria-pressed', tema === 'dark' ? 'true' : 'false');
        /* Labelnya menyebut tema yang akan DITUJU, sama seperti ikonnya —
           itu yang menjawab "kalau saya klik ini, saya dapat apa?". */
        if (label) label.textContent = tema === 'dark' ? 'Tema Terang' : 'Tema Gelap';
        try { localStorage.setItem('tesys-theme', tema); } catch (e) {}
    }

    terapkan(akar.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

    tombol.addEventListener('click', function (e) {
        e.stopPropagation();   /* jangan ikut menutup popup */
        terapkan(akar.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    });
})();

function confirmLogout() {
    Swal.fire({
        title: 'Logout?',
        text: 'Apakah Anda yakin ingin keluar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#92232b',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Logout',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('logout-form').submit();
        }
    });
}
</script>

@auth
<!-- NOTIFICATION BELL (polling) -->
<script>
$.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
});

$(function () {
    var fetchUrl = "{{ route('notifications.fetch') }}";

    /*
       Jawaban bisa berbentuk lain saat sesi berakhir: permintaan AJAX tetap
       dianggap berhasil tetapi isinya halaman login, bukan JSON yang
       diharapkan. Tanpa penjagaan ini lonceng berhenti bekerja diam-diam
       karena galat yang tidak tertangkap.
    */
    function renderNotifications(data) {
        if (!data || !Array.isArray(data.notifications)) {
            $('#notifBadge').hide();
            $('#notifList').empty();
            $('#notifEmpty').show();
            return;
        }
        var $badge = $('#notifBadge');
        if (data.unread_count > 0) {
            $badge.text(data.unread_count > 99 ? '99+' : data.unread_count).show();
        } else {
            $badge.hide();
        }

        var $list = $('#notifList').empty();
        if (!data.notifications.length) {
            $('#notifEmpty').show();
            return;
        }
        $('#notifEmpty').hide();

        data.notifications.forEach(function (n) {
            var $item = $('<a></a>')
                .addClass('notif-row' + (n.is_read ? '' : ' unread'))
                .attr('href', n.url || '#')
                .attr('data-id', n.id);

            $item.append($('<span class="notif-row-title"></span>').text(n.title));
            $item.append($('<span class="notif-row-msg"></span>').text(n.message));
            $item.append($('<span class="notif-row-time"></span>').text(n.created_at));

            $item.on('click', function () {
                if (!n.is_read) {
                    $.post("{{ url('/notifications') }}/" + n.id + "/read");
                }
            });

            $list.append($item);
        });
    }

    function pollNotifications() {
        $.get(fetchUrl, function (data) { renderNotifications(data); });
    }

    $('#notifMarkAllRead').on('click', function (e) {
        e.preventDefault();
        $.post("{{ route('notifications.read-all') }}", function () { pollNotifications(); });
    });

    pollNotifications();
    setInterval(pollNotifications, 60000);
});
</script>

<!-- TO DO BELL (ringkasan dari modul Todo List) -->
<script>
$(function () {
    var todoCountUrl = "{{ route('todo-list.count') }}";
    var todoFetchUrl = "{{ route('todo-list.fetch') }}";
    var todoListUrl  = "{{ route('todo-list.index') }}";

    function renderTodoBadge(count) {
        var $badge = $('#todoBadge');
        if (count > 0) {
            $badge.text(count > 99 ? '99+' : count).show();
        } else {
            $badge.hide();
        }
    }

    /* Penjagaan yang sama seperti renderNotifications di atas. */
    function renderTodos(data) {
        if (!data || !Array.isArray(data.items)) {
            $('#todoList').empty();
            $('#todoEmpty').show();
            return;
        }

        renderTodoBadge(data.count);

        var $list = $('#todoList').empty();
        if (!data.items.length) {
            $('#todoEmpty').show();
            return;
        }
        $('#todoEmpty').hide();

        data.items.forEach(function (t) {
            // Semua item mengarah ke halaman Todo List penuh (bukan detail
            // masing-masing modul) — popup ini hanya ringkasan sekilas.
            var $item = $('<a></a>')
                .addClass('notif-row')
                .attr('href', todoListUrl);

            $item.append($('<span class="notif-row-title"></span>').text(t.title));
            $item.append(
                $('<span class="notif-row-meta"></span>').append(
                    $('<span class="badge badge-' + (t.status_color || 'secondary') + '"></span>').text(t.status_label),
                    $('<span></span>').text(t.date)
                )
            );
            $item.append($('<span class="notif-row-time text-' + (t.color || 'secondary') + '"></span>').text(t.aging_label));

            $list.append($item);
        });
    }

    /*
      Polling hanya mengambil ANGKA-nya (todo-list/count), bukan seluruh isi
      popup. Menyusun isi popup berarti membangun ulang seluruh worklist —
      ribuan query — padahal yang berubah di layar cuma satu angka kecil, dan
      sebagian besar polling terjadi saat dropdown-nya bahkan tidak dibuka.

      Isi popup baru diambil saat dropdown benar-benar dibuka (todo-list/fetch),
      lalu ditahan sebentar supaya membuka-tutup berulang tidak memanggil ulang.
    */
    function pollTodoCount() {
        $.get(todoCountUrl, function (data) {
            renderTodoBadge(data && data.count ? data.count : 0);
        }).fail(function () {
            // Diam-diam gagal — badge tetap seperti sebelumnya, tidak boleh
            // mengganggu topbar/halaman lain.
        });
    }

    var todoItemsUmur = 0;          // kapan isi popup terakhir diambil
    var todoItemsTtl  = 30000;      // tahan 30 detik, sepadan dengan cache server
    var todoItemsJalan = false;

    function muatIsiTodo(paksa) {
        if (todoItemsJalan) return;
        if (!paksa && Date.now() - todoItemsUmur < todoItemsTtl) return;

        todoItemsJalan = true;
        $.get(todoFetchUrl, function (data) {
            renderTodos(data);
            todoItemsUmur = Date.now();
        }).always(function () {
            todoItemsJalan = false;
        });
    }

    // Bootstrap 4 memancarkan show.bs.dropdown pada pembungkusnya.
    $('#todoBellWrap').on('show.bs.dropdown', function () { muatIsiTodo(false); });

    pollTodoCount();
    setInterval(pollTodoCount, 60000);
});
</script>
@endauth

<!-- PAGE SCRIPT -->
@stack('scripts')

<div class="toast-stack" id="toastStack"></div>

{{-- ================================================================ --}}
{{-- COMMAND PALETTE (Ctrl+K)                                         --}}
{{-- Daftar halaman dibaca dari tautan sidebar yang sudah dirender,   --}}
{{-- jadi otomatis mengikuti hak akses. Dokumen diambil dari endpoint --}}
{{-- global-search yang menggerbangi tiap modul di sisi server.       --}}
{{-- ================================================================ --}}
<div class="ts-cmd-root" id="tsCmd" hidden role="dialog" aria-modal="true" aria-label="Cari di seluruh Tesys">
    <div class="ts-cmd-scrim"></div>
    <div class="ts-cmd-panel">
        <div class="ts-cmd-head">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="tsCmdInput" autocomplete="off" role="combobox"
                   aria-expanded="true" aria-controls="tsCmdList"
                   placeholder="Cari Cost Request, PO, Invoice, atau halaman…">
            <span class="ts-cmd-kbd" aria-hidden="true">Esc</span>
        </div>

        <div class="ts-cmd-body">
            <div id="tsCmdList" role="listbox" aria-label="Hasil pencarian"></div>
            <div id="tsCmdLoading" class="ts-cmd-loading" hidden>
                <x-skeleton type="lines" :rows="3" />
            </div>
        </div>

        <div class="ts-cmd-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> pilih</span>
            <span><kbd>Enter</kbd> buka</span>
            <span><kbd>Esc</kbd> tutup</span>
        </div>
    </div>
</div>

@php
    /*
     | Aksi cepat untuk Command Center. Disaring di sini (bukan di browser)
     | supaya palet hanya menawarkan yang benar-benar boleh dibuka user ini:
     | route harus terdaftar, role harus cocok dengan middleware rutenya, dan
     | menunya harus terlihat. Tidak ada logika bisnis baru — hanya membaca
     | hak akses yang sudah ada.
     */
    $cmdActions = collect(config('command-actions', []))
        ->filter(fn ($a) => Route::has($a['route'] ?? ''))
        ->filter(fn ($a) => empty($a['roles']) || Auth::user()->hasRole($a['roles']))
        ->filter(fn ($a) => empty($a['menu']) || $can($a['menu']))
        ->map(fn ($a) => [
            'title' => $a['label'],
            'icon'  => $a['icon'] ?? 'fas fa-plus',
            'url'   => route($a['route']),
        ])
        ->values();
@endphp
<script type="application/json" id="tesysCommandData">@json(['searchUrl' => route('global-search'), 'actions' => $cmdActions])</script>
<script>
    (function () {
        var data = JSON.parse(document.getElementById('tesysCommandData').textContent);
        window.TESYS_SEARCH_URL = data.searchUrl;
        window.TESYS_ACTIONS    = data.actions;
    })();
</script>
<script src="{{ asset('js/tesys-export-filtered.js') }}?v={{ filemtime(public_path('js/tesys-export-filtered.js')) }}"></script>
<script src="{{ asset('js/tesys-command.js') }}?v={{ filemtime(public_path('js/tesys-command.js')) }}"></script>
<script src="{{ asset('js/tesys-ui.js') }}?v={{ filemtime(public_path('js/tesys-ui.js')) }}"></script>
<script src="{{ asset('js/tesys-shell.js') }}?v={{ filemtime(public_path('js/tesys-shell.js')) }}"></script>
<script src="{{ asset('js/tesys-clock.js') }}?v={{ filemtime(public_path('js/tesys-clock.js')) }}"></script>

{{-- Menandai tombol yang isinya HANYA ikon di dalam sel tabel, supaya
     CSS bisa menyamakan ukurannya tanpa ikut memampatkan tombol yang
     punya label. Lihat catatan di berkasnya. --}}
<script src="{{ asset('js/tesys-icon-buttons.js') }}?v={{ filemtime(public_path('js/tesys-icon-buttons.js')) }}"></script>

</body>
</html>
