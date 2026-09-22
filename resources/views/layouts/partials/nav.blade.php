{{--
    Sidebar menu — berlaku untuk SEMUA aplikasi TESYS.

    Isinya dibaca dari config/navigation.php: 'apps.<key>' menentukan seksi dan
    urutan menunya, 'items' menentukan nama/ikon/tujuan/hak aksesnya. Dengan
    begitu memindahkan sebuah modul antar aplikasi cukup satu baris di config,
    tanpa menyalin markup menu ke mana-mana.

    Hak akses TIDAK diputuskan di sini: 'menu' dan 'roles' memanggil
    Role::hasMenu() dan User::hasRole() yang sama dengan middleware route.

    Variabel dari layout: $can, $currentApp, $currentAppConfig,
    $fmActive, $financeProjectActive.
--}}
@php
    $navItems    = config('navigation.items', []);
    $navSections = config('navigation.apps.' . $currentApp, []);

    $homeUrl = $currentAppConfig['home'];

    /*
     | Sedang dibuka? Cocokkan pola request()->is(). Dua menu memakai halaman
     | yang dipakai bersama konteks lain, jadi dipisahkan lewat 'scope':
     |   fm : /budget-plan & /budget-request bisa dibuka dari Surat Jalan juga
     |   po : /project (Purchase Order) vs /project-monitoring
     */
    $navAktif = function (array $item) use ($fmActive, $financeProjectActive, $homeUrl): bool {
        $scope = $item['scope'] ?? null;

        if ($scope === 'po') {
            return $financeProjectActive;
        }

        $pola = $item['active'] ?? [];
        if ($pola === ['app-home']) {
            return request()->is(trim($homeUrl, '/') ?: '/');
        }
        if (empty($pola)) {
            return false;
        }

        $cocok = request()->is(...$pola);

        return $scope === 'fm' ? ($cocok && $fmActive) : $cocok;
    };

    $navUrl = function (array $item) use ($homeUrl): ?string {
        $url = $item['url'] ?? null;
        if ($url === 'app-home') {
            return $homeUrl;
        }
        if (is_string($url) && str_starts_with($url, 'route:')) {
            $nama = substr($url, 6);
            return Route::has($nama) ? route($nama) : null;
        }
        return $url;
    };
@endphp

@foreach ($navSections as $labelSeksi => $kunciMenu)
    @php
        $terlihat = collect($kunciMenu)
            ->map(fn ($k) => $navItems[$k] ?? null)
            ->filter()
            ->filter(function ($item) use ($can) {
                if (!empty($item['menu']))  return $can($item['menu']);
                if (!empty($item['roles'])) return Auth::user()->hasRole($item['roles']);
                return true;
            })
            ->filter(fn ($item) => $navUrl($item) !== null);
    @endphp

    @if ($terlihat->isNotEmpty())
        <div class="nav-label">{{ $labelSeksi }}</div>

        @foreach ($terlihat as $item)
            <a href="{{ $navUrl($item) }}" class="nav-item {{ $navAktif($item) ? 'active' : '' }}">
                <i class="{{ $item['icon'] }} ic"></i>{{ $item['label'] }}
            </a>
        @endforeach
    @endif
@endforeach

{{-- Bagian yang masih disiapkan untuk aplikasi yang belum lengkap. Daftarnya
     dari config/apps.php ('modules') — hanya penanda rencana, bukan route. --}}
@if (!($currentAppConfig['ready'] ?? false) && !empty($currentAppConfig['modules']))
    <div class="nav-label">Segera Hadir</div>
    @foreach ($currentAppConfig['modules'] as $modul)
        <span class="nav-item is-disabled" aria-disabled="true" title="Belum tersedia">
            <i class="fas fa-lock ic"></i>{{ $modul }}
        </span>
    @endforeach
@endif
