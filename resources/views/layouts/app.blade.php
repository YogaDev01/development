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

    {{-- Kerangka halaman dipasang SEBELUM satu lembar gaya pun diminta, dengan
         alasan yang sama seperti tema di atas: keadaan sidebar (mengecil /
         tertutup di layar sempit) disimpan di localStorage, dan kalau baru
         dipasang oleh tesys-shell.js di kaki halaman, sidebar sempat tergambar
         dalam keadaan yang salah lalu melompat. tesys-shell.js tetap memasang
         ulang keadaan yang sama nanti — ini hanya mendahuluinya. --}}
    <script>
        (function () {
            var narrow = window.matchMedia('(max-width: 900px)').matches;
            var rail = false;
            try { rail = localStorage.getItem('tesys.sidebar.rail') === '1'; } catch (e) {}

            var kelas = 'tesys-booting';
            if (narrow) kelas += ' sidebar-off';
            else if (rail) kelas += ' sidebar-rail';

            // <body> belum ada di titik ini, jadi kelasnya dititipkan di <html>
            // dan dipindahkan begitu <body> lahir (lihat skrip di bawah <body>).
            document.documentElement.className += ' ' + kelas;

            /* Selubung pemuatan.

               Halaman ini digambar bertahap oleh server, jadi sempat terlihat
               separuh jadi — topbar dan isi sudah ada, sidebar belum. Selama
               <html> masih bertanda tesys-booting, isinya ditahan; tandanya
               dilepas begitu seluruh markup terbaca.

               Pelepasnya dipasang DI SINI, bukan di kaki halaman: dengan begitu
               pengaman waktunya sudah berjalan walau ada skrip lain yang gagal
               di tengah jalan — halaman tidak mungkin tertinggal kosong. */
            function tampilkan() {
                document.documentElement.classList.remove('tesys-booting');
            }
            document.addEventListener('DOMContentLoaded', tampilkan);
            window.addEventListener('load', tampilkan);
            setTimeout(tampilkan, 1500);
        })();
    </script>

    <style>
        /* Isi ditahan, bukan dihapus: tata letaknya tetap dihitung, jadi tidak
           ada lompatan saat selubungnya dilepas. <html> tetap memakai warna
           latar temanya supaya yang terlihat selama itu bukan putih polos. */
        html.tesys-booting body { visibility: hidden; }
        /* Latar tema dipasang di <html> juga, supaya yang terlihat selama
           penantian itu warna aplikasi — bukan putih yang menyilaukan di tema
           gelap. */
        html.tesys-booting { background: var(--body-bg, #f4f5f3); }
    </style>

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
    <link rel="stylesheet" href="@aset('css/bootstrap-4.6.1.css')">

    <!-- TOM SELECT -->
    {{-- Pustaka pihak ketiga dilayani dari server sendiri, bukan CDN.
         Alasannya tiga: (1) tanpa SRI, skrip CDN yang tersusupi berjalan penuh
         di halaman yang memegang data keuangan; (2) 'tom-select' tanpa versi
         berarti pembaruan mayor bisa masuk diam-diam dan merusak halaman;
         (3) aplikasi ini dipakai di jaringan kantor yang tidak selalu punya
         akses keluar - kalau CDN tak terjangkau, dropdown-nya mati. --}}
    <link rel="stylesheet" href="@aset('plugins/tom-select/tom-select.css')">

    {{-- bootstrap-select TIDAK dimuat di sini lagi.
         Ia dipakai HANYA di accessgroup/create & accessgroup/edit (class
         "selectpicker") - dua halaman dari ~144 - tapi sebelumnya dimuat di
         setiap halaman, berikut CSS dan JS-nya.
         Selain beban yang tak terpakai, dua pustaka dropdown yang sama-sama
         mengikat diri ke elemen <select> adalah penyebab klasik dropdown yang
         tidak mau menutup atau nilainya tidak terkirim. Kini dimuat lewat
         @stack('styles')/@stack('scripts') di kedua halaman itu saja. --}}

    <!-- Flatpickr (self-hosted — sebagian jaringan kantor memblokir jsdelivr) -->
    <link rel="stylesheet" href="{{ asset('plugins/flatpickr/dist/flatpickr.min.css') }}">
    <script src="{{ asset('plugins/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('plugins/flatpickr/dist/l10n/id.js') }}"></script>

    <!-- SWEET ALERT -->
    <script src="{{ asset('plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>

    <!-- BASE STYLE (komponen app yang tidak ada di Bootstrap: tss-*, form-clean, dll) -->
    <link rel="stylesheet" href="@aset('css/tesys-ui-base.css')">

    <!-- STACK STYLE -->
    @stack('styles')

    {{--
        ===== Tesys UI =====
        Urutan wajib: shell -> tokens -> compat -> pages -> motion -> luxe.
        tesys-ui.css membawa :root versi lama, jadi tokens.css harus SESUDAHNYA
        supaya shadow/radius hasil revisi yang dipakai.
    --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="@aset('css/tesys-ui.css')">
    <link rel="stylesheet" href="@aset('css/tesys-ui-tokens.css')">
    <link rel="stylesheet" href="@aset('css/tesys-ui-components.css')">
    <link rel="stylesheet" href="@aset('css/tesys-ui-compat.css')">
    <link rel="stylesheet" href="@aset('css/tesys-ui-pages.css')">
    <link rel="stylesheet" href="@aset('css/tesys-ui-shell.css')">
    <link rel="stylesheet" href="@aset('css/tesys-ui-motion.css')">

    {{-- Lapis luxe: chrome gelap (sidebar + topbar) di atas isi terang.
         Dimuat paling akhir karena beberapa aturan sidebar di tesys-ui.css
         menunjuk token permukaan, yang harus tetap terang. --}}
    <link rel="stylesheet" href="@aset('css/tesys-ui-luxe.css')">

    {{-- Lapis mobile: penyesuaian layar handphone. Harus SESUDAH luxe dan
         sesudah @stack('styles') (yang ada di atas), supaya aturannya menang
         atas <style> per halaman tanpa perlu !important. --}}
    <link rel="stylesheet" href="@aset('css/tesys-ui-mobile.css')">

    {{-- Daftar data (DataTable) + mode card mobile. Sesudah components & mobile. --}}
    <link rel="stylesheet" href="@aset('css/tesys-ui-datatable.css')">

    {{-- Warna aksen kustom per-perangkat (Profil > Settings > Preferensi
         Tampilan). Disimpan di localStorage 'tesys-accent', sama polanya
         dengan pengalih tema di atas. Diterapkan DI SINI, elemen terakhir di
         <head> sesudah seluruh stylesheet, supaya override :root menang di
         cascade tanpa !important dan sudah terpasang sebelum halaman dilukis
         (tidak ada kedipan warna). Default = Burgundy TESYS (#92232b), dan
         kalau belum pernah diset tidak ada satu pun aturan yang disuntikkan. --}}
    <script>
        (function () {
            var DEFAULT = '#92232b';

            // Turunan dari satu hex pakai color-mix — berlaku untuk terang &
            // gelap. --accent/--accent-dark/--accent-text ikut ganti; tint &
            // soft dibedakan per tema (campur putih di terang, hitam di gelap).
            function css(hex) {
                return ':root{'
                    + '--accent:' + hex + ';'
                    + '--accent-dark:color-mix(in srgb,' + hex + ' 82%, #000);'
                    + '--accent-soft:color-mix(in srgb,' + hex + ' 52%, #fff);'
                    + '--accent-tint:color-mix(in srgb,' + hex + ' 10%, #fff);'
                    + '--accent-tint-2:color-mix(in srgb,' + hex + ' 22%, #fff);'
                    + '--accent-text:color-mix(in srgb,' + hex + ' 88%, #000);'
                    + '--accent-ring:color-mix(in srgb,' + hex + ' 22%, transparent);'
                    + '--accent-on-chrome:color-mix(in srgb,' + hex + ' 60%, #fff);'
                    + '--chrome-active:color-mix(in srgb,' + hex + ' 30%, #000);'
                    + '--amber:' + hex + ';'
                    + '--amber-dk:color-mix(in srgb,' + hex + ' 82%, #000);'
                    + '--amber-tint:color-mix(in srgb,' + hex + ' 10%, #fff);'
                    + '--amber-border:color-mix(in srgb,' + hex + ' 22%, #fff);'
                    // Sidebar & topbar (chrome gelap) ikut mengambil warna
                    // aksen — tetap gelap, hanya nadanya digeser ke aksen.
                    + '--chrome:color-mix(in srgb,' + hex + ' 20%, #000);'
                    + '--chrome-2:color-mix(in srgb,' + hex + ' 28%, #000);'
                    + '--chrome-3:color-mix(in srgb,' + hex + ' 34%, #000);'
                    + '--chrome-border:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--topbar:color-mix(in srgb,' + hex + ' 30%, #000);'
                    + '--topbar-2:color-mix(in srgb,' + hex + ' 38%, #000);'
                    + '--topbar-3:color-mix(in srgb,' + hex + ' 46%, #000);'
                    + '}'
                    + 'html[data-theme="dark"]{'
                    + '--accent-soft:color-mix(in srgb,' + hex + ' 68%, #000);'
                    + '--accent-tint:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--accent-tint-2:color-mix(in srgb,' + hex + ' 36%, #000);'
                    + '--accent-text:color-mix(in srgb,' + hex + ' 55%, #fff);'
                    + '--accent-on-chrome:color-mix(in srgb,' + hex + ' 60%, #fff);'
                    + '--chrome-active:color-mix(in srgb,' + hex + ' 34%, #000);'
                    + '--amber-tint:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--amber-border:color-mix(in srgb,' + hex + ' 36%, #000);'
                    + '--chrome:color-mix(in srgb,' + hex + ' 15%, #000);'
                    + '--chrome-2:color-mix(in srgb,' + hex + ' 23%, #000);'
                    + '--chrome-3:color-mix(in srgb,' + hex + ' 29%, #000);'
                    + '--chrome-border:color-mix(in srgb,' + hex + ' 19%, #000);'
                    + '--topbar:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--topbar-2:color-mix(in srgb,' + hex + ' 32%, #000);'
                    + '--topbar-3:color-mix(in srgb,' + hex + ' 40%, #000);'
                    + '}';
            }

            function apply(hex) {
                var el = document.getElementById('tesys-accent-style');
                if (!hex || hex.toLowerCase() === DEFAULT) {
                    if (el) el.textContent = '';   // kembali ke token bawaan
                    return;
                }
                if (!el) {
                    el = document.createElement('style');
                    el.id = 'tesys-accent-style';
                    document.head.appendChild(el);
                }
                el.textContent = css(hex);
            }

            window.TesysAccent = { apply: apply, DEFAULT: DEFAULT };

            try {
                var saved = localStorage.getItem('tesys-accent');
                if (saved) apply(saved);
            } catch (e) {}
        })();
    </script>
</head>

<body class="tesys-ui {{ request()->is('/') ? 'dashboard-page' : '' }}">

<script>
    /* Keadaan sidebar sudah disimpulkan di <head> (sebelum gaya apa pun
       diminta) tapi dititipkan di <html>, karena <body> belum ada saat itu.
       Di sini kelasnya dipindahkan ke tempat yang dibaca CSS. */
    (function () {
        ['sidebar-off', 'sidebar-rail'].forEach(function (k) {
            if (document.documentElement.classList.contains(k)) {
                document.documentElement.classList.remove(k);
                document.body.classList.add(k);
            }
        });
    })();
</script>

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

        {{-- Command palette: pindah halaman & cari dokumen lintas modul.
             Tempatnya di sidebar, tepat di atas daftar menu, karena yang
             dicari orang di sini sama dengan isi daftar itu: halaman dan
             dokumen. Di topbar ia bersaing dengan pencarian dalam halaman
             yang pekerjaannya berbeda. --}}
        <div class="sidebar-cmd">
            <button type="button" class="ts-cmd-trigger" data-cmd-open
                    title="Cari halaman, dokumen, dan aksi (Ctrl+K)" aria-label="Cari di seluruh TESYS">
                <i class="fas fa-search" aria-hidden="true"></i>
                <span>Cari di TESYS…</span>
                <span class="ts-cmd-kbd" aria-hidden="true">Ctrl K</span>
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

            {{-- Penanda "sedang preview role lain" di tengah topbar. Sekaligus
                 tombol pulang: satu klik kembali ke Superadmin tanpa harus
                 membuka popup akun dulu. --}}
            @if(Auth::user()->isViewingAs())
            <form action="{{ route('view-as.reset') }}" method="POST" class="mb-0 viewas-chip">
                @csrf
                <span class="viewas-chip-label">
                    <i class="fas fa-user-secret" aria-hidden="true"></i>
                    Melihat sebagai <strong>{{ Str::upper($role->name) }}</strong>
                </span>
                <button type="submit" class="viewas-chip-btn" title="Kembali ke tampilan Superadmin">
                    <i class="fas fa-undo" aria-hidden="true"></i> Kembali ke Superadmin
                </button>
            </form>
            @endif

            <div class="topbar-actions">

                {{-- Pencarian cepat: menyaring tabel di halaman yang sedang dibuka.
                     Disembunyikan otomatis oleh tesys-shell.js bila halaman tidak
                     punya tabel, supaya tidak ada kolom yang tampak tapi mati. --}}
                <form class="search" id="tsQuickSearchWrap" onsubmit="return false;" hidden>
                    <i class="fas fa-search" style="font-size:12px;"></i>
                    <input type="search" id="tsQuickSearch" placeholder="Cari di halaman ini…" autocomplete="off"
                           style="border:none;background:none;outline:none;font:inherit;color:inherit;width:100%;">
                </form>

                @yield('page-actions')

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

                        {{-- Ganti tampilan role (superadmin saja). Rute & nama field
                             TIDAK berubah — daftar role kini tampil sebagai pilihan
                             langsung yang selalu ada, jadi superadmin bisa lompat dari
                             satu role ke role lain tanpa harus reset dulu. --}}
                        @if(Auth::user()->isRealSuperadmin())
                        @php
                            $viewAsRoles = \App\Models\Role::where('status', 'ACTIVE')
                                ->whereRaw('LOWER(name) != ?', ['superadmin'])
                                ->orderBy('name')->get();
                            $viewAsAktifId = Auth::user()->isViewingAs() ? $role?->id : null;
                        @endphp
                        <div class="user-pop-sep"></div>
                        <div class="user-pop-group role-switch">
                            <div class="user-pop-group-label">
                                Ganti Role
                                @if($viewAsAktifId)
                                <span class="role-switch-badge">preview</span>
                                @endif
                            </div>

                            <div class="role-switch-search">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control form-control-sm"
                                       placeholder="Cari role…" autocomplete="off"
                                       aria-label="Cari role" data-role-filter>
                            </div>

                            <div class="role-switch-list" role="listbox">
                                <form action="{{ route('view-as.reset') }}" method="POST" class="mb-0">
                                    @csrf
                                    <button type="submit"
                                            class="role-switch-item{{ $viewAsAktifId ? '' : ' is-active' }}"
                                            data-role-name="superadmin">
                                        <span class="role-switch-dot"><i class="fas fa-shield-alt"></i></span>
                                        <span class="role-switch-name">SUPERADMIN</span>
                                        <span class="role-switch-tag">role asli</span>
                                    </button>
                                </form>

                                <form action="{{ route('view-as') }}" method="POST" class="mb-0">
                                    @csrf
                                    @foreach($viewAsRoles as $viewAsRole)
                                    <button type="submit" name="role_id" value="{{ $viewAsRole->id }}"
                                            class="role-switch-item{{ $viewAsAktifId === $viewAsRole->id ? ' is-active' : '' }}"
                                            data-role-name="{{ Str::lower($viewAsRole->name) }}">
                                        <span class="role-switch-dot">{{ Str::upper(Str::substr($viewAsRole->name, 0, 1)) }}</span>
                                        <span class="role-switch-name">{{ Str::upper($viewAsRole->name) }}</span>
                                        @if($viewAsAktifId === $viewAsRole->id)
                                        <span class="role-switch-tag"><i class="fas fa-check"></i> aktif</span>
                                        @endif
                                    </button>
                                    @endforeach
                                </form>

                                <div class="role-switch-empty" hidden>Role tidak ditemukan.</div>
                            </div>
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

        {{-- Banner "Melihat sebagai …" dihapus: statusnya sudah terbaca dari
             pengalih role di topbar, termasuk tombol kembali ke superadmin. --}}

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

<!-- TESYS MONEY SORT (kolom nominal diurutkan sebagai angka, bukan teks) -->
<script src="@aset('js/tesys-money-sort.js')"></script>

<!-- TESYS TABLE (kendali daftar: Page Size masuk ke bilah kendali) -->

<!-- TESYS COLUMN FILTER (filter header ala Excel untuk DataTables) -->
<script src="@aset('js/tesys-column-filter.js')"></script>
<script src="@aset('js/tesys-toolbar-actions.js')"></script>

<!-- TESYS TIMELINE (riwayat workflow) -->
<script src="@aset('js/tesys-timeline.js')"></script>

<!-- TESYS CHART (bakuan grafik + keadaan kosong; menunggu Chart.js halaman) -->
<script src="@aset('js/tesys-chart.js')"></script>

{{-- TESYS WORKFLOW — perjalanan dokumen (komponen x-workflow-flow) + drawer.
     Ditulis sebagai komentar Blade, BUKAN komentar HTML: tag komponen di dalam
     <!-- --> tetap dikompilasi Blade dan membuat komponen tak tertutup. --}}
<script src="@aset('js/tesys-workflow.js')"></script>

<!-- TESYS DRAWER (panel tinjau dari kanan) -->
<script src="@aset('js/tesys-drawer.js')"></script>

<!-- TESYS ROW ACTIONS (menu aksi pada kolom tabel) -->
<script src="@aset('js/tesys-row-actions.js')"></script>

<!-- TESYS TABLE (mode card DataTable di layar <= 640px) -->
<script src="@aset('js/tesys-table.js')"></script>

<!-- TESYS SEARCHABLE SELECT (pengganti Tom Select) -->
<script src="@aset('js/tesys-searchable-select.js')"></script>
<script src="@aset('js/tesys-date-range.js')"></script>

<!-- TOM SELECT -->
<script src="@aset('plugins/tom-select/tom-select.complete.min.js')"></script>
{{-- bootstrap-select dipindah ke halaman yang memakainya - lihat catatan di <head>. --}}

{{-- =================================================================
     TOAST
     Pemberitahuan hasil aksi muncul di kanan bawah selama dua detik.
     Bila beberapa datang sekaligus, tumpukannya bertambah ke atas dan
     dibatasi tiga — yang tertua ditutup lebih dulu. SweetAlert sengaja
     tidak dipakai di sini karena ia hanya menampilkan satu toast pada
     satu waktu: pesan kedua menimpa yang pertama sebelum sempat dibaca.
================================================================= --}}
<script>
(function () {
    var MAKS  = 3;
    var UMUR  = 3000;
    var JEDA  = 450;

    function tutup(el) {
        if (!el || el.dataset.closing) return;
        el.dataset.closing = '1';
        el.classList.remove('show');
        setTimeout(function () { el.remove(); }, 250);
    }

    /* Wadahnya dirender di akhir halaman, sedangkan flash session dipanggil
       lebih awal — jadi dibuat di sini kalau belum ada. */
    function wadah() {
        var stack = document.getElementById('toastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'ts-toast-stack';
            stack.id = 'toastStack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    /* Muncul satu per satu: kalau beberapa pesan datang bersamaan, yang
       berikutnya menunggu giliran supaya tumpukannya terlihat bertambah,
       bukan langsung penuh sekaligus. */
    var antrean = [];
    var jalan   = false;

    function giliran() {
        if (jalan || !antrean.length) return;
        jalan = true;
        tampilkan.apply(null, antrean.shift());
        setTimeout(function () { jalan = false; giliran(); }, JEDA);
    }

    function tampilkan(type, title, msg) {
        var stack = wadah();

        var tampil = stack.querySelectorAll('.ts-toast:not([data-closing])');
        for (var i = 0; i <= tampil.length - MAKS; i++) tutup(tampil[i]);

        var el = document.createElement('div');
        el.className = 'ts-toast ts-toast-' + (type || 'info');
        var ikon = type === 'success' ? '✓' : ((type === 'error' || type === 'warning') ? '!' : 'i');
        el.innerHTML = '<div class="ts-toast-ic"></div>'
                     + '<div class="ts-toast-teks"><div class="ts-toast-title"></div>'
                     + '<div class="ts-toast-msg"></div></div>'
                     + '<button type="button" class="ts-toast-close" aria-label="Tutup">×</button>';
        el.querySelector('.ts-toast-ic').textContent    = ikon;
        el.querySelector('.ts-toast-title').textContent = title || '';
        el.querySelector('.ts-toast-msg').textContent   = msg || '';
        el.querySelector('.ts-toast-close').addEventListener('click', function () { tutup(el); });

        /* Ditahan selama kursor ada di atasnya: pesan yang sedang dibaca
           tidak ikut hilang saat hitungan tiga detiknya habis. */
        var waktu;
        function hitung() { waktu = setTimeout(function () { tutup(el); }, UMUR); }
        el.addEventListener('mouseenter', function () {
            clearTimeout(waktu);
            el.classList.add('is-hold');
        });
        el.addEventListener('mouseleave', function () {
            el.classList.remove('is-hold');
            hitung();
        });

        stack.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('show'); });
        hitung();
    }

    window.tsToast = function (type, title, msg) {
        antrean.push([type, title, msg]);
        giliran();
    };

    window.toast = window.toast || {
        success: function (t, m) { window.tsToast('success', t, m); },
        error:   function (t, m) { window.tsToast('error',   t, m); },
        warning: function (t, m) { window.tsToast('warning', t, m); },
        info:    function (t, m) { window.tsToast('info',    t, m); }
    };
})();
</script>

@if(session('success'))
<script type="application/json" id="tesysFlashSuccess">@json(session('success'))</script>
<script>
window.tsToast('success', JSON.parse(document.getElementById('tesysFlashSuccess').textContent));
</script>
@endif

@if(session('warning'))
{{-- Peringatan yang perlu dibaca (mis. pagu Cash Advance hampir habis) —
     sengaja modal, bukan toast 3 detik. --}}
<script type="application/json" id="tesysFlashWarning">@json(session('warning'))</script>
<script>
Swal.fire({
    icon: 'warning',
    title: 'Perhatian',
    text: JSON.parse(document.getElementById('tesysFlashWarning').textContent),
    confirmButtonColor: '#92232b'
});
</script>
@endif

@if(session('error'))
<script type="application/json" id="tesysFlashError">@json(session('error'))</script>
<script>
window.tsToast('error', JSON.parse(document.getElementById('tesysFlashError').textContent));
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

/*
  Polling bell yang sadar tab. Dulu setInterval(60 detik) jalan di SETIAP tab
  yang terbuka, termasuk yang tersembunyi — dan Chrome menyelaraskan timer tab
  latar ke detik yang sama. User dengan 7 tab = 14 request PHP+MySQL serentak
  tiap menit; di log produksi terlihat sebagai rombongan "[2002] Operation not
  permitted" dari satu user dalam satu detik, yang ikut menjatuhkan halaman
  user lain (batas koneksi/proses Hostinger dihitung per akun, bukan per user).

  Sekarang: tab tersembunyi tidak polling sama sekali; begitu tab dilihat lagi,
  polling dijalankan sekali (bila sudah lewat interval). Jitter acak mencegah
  tab-tab yang dibuka bersamaan jatuh di detik yang sama.
*/
window.tesysPoll = function (fn, intervalMs) {
    var terakhir = 0;
    function jalan() {
        if (document.hidden) return;
        terakhir = Date.now();
        fn();
    }
    jalan();
    setInterval(jalan, intervalMs + Math.floor(Math.random() * 15000));
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && Date.now() - terakhir >= intervalMs) jalan();
    });
};

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

    tesysPoll(pollNotifications, 60000);
});
</script>

@endauth

<!-- PAGE SCRIPT -->
@stack('scripts')

{{-- Wadah toast dibuat otomatis oleh window.tsToast (lihat blok TOAST di atas). --}}

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
<script src="@aset('js/tesys-export-filtered.js')"></script>
<script src="@aset('js/tesys-command.js')"></script>

{{-- Penjaga sesi untuk halaman berform.
     Mengetik di dalam form tidak memperpanjang sesi - hanya permintaan ke
     server yang memperpanjangnya. Tanpa ini, form Cost Request yang diisi
     bertahap gagal dengan 419 saat Simpan ditekan dan seluruh isinya hilang,
     tanpa jejak galat di mana pun. Skrip ini hanya aktif kalau halamannya
     memang punya form berisi (lihat berkasnya). --}}
<script>window.TESYS_KEEPALIVE_URL = "{{ route('notifications.fetch') }}";</script>
<script src="@aset('js/tesys-session-keepalive.js')"></script>
<script src="@aset('js/tesys-ui.js')"></script>
<script src="@aset('js/tesys-shell.js')"></script>
<script src="@aset('js/tesys-clock.js')"></script>

{{-- Menandai tombol yang isinya HANYA ikon di dalam sel tabel, supaya
     CSS bisa menyamakan ukurannya tanpa ikut memampatkan tombol yang
     punya label. Lihat catatan di berkasnya. --}}
<script src="@aset('js/tesys-icon-buttons.js')"></script>

{{-- Filter daftar "Ganti Role" di popup user (superadmin saja). --}}
<script>
(function () {
    var input = document.querySelector('[data-role-filter]');
    if (!input) return;
    var list  = input.closest('.role-switch').querySelector('.role-switch-list');
    var items = Array.prototype.slice.call(list.querySelectorAll('.role-switch-item'));
    var empty = list.querySelector('.role-switch-empty');

    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var tampil = 0;
        items.forEach(function (el) {
            var cocok = !q || (el.dataset.roleName || '').indexOf(q) !== -1;
            el.hidden = !cocok;
            if (cocok) tampil++;
        });
        empty.hidden = tampil > 0;
    });

    // Klik di dalam kotak pencarian tidak boleh menutup popup user.
    input.addEventListener('click', function (e) { e.stopPropagation(); });
})();
</script>

</body>
</html>
