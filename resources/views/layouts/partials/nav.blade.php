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
    // Seksi aplikasi + Master Data bersama (sama di semua aplikasi).
    $navSections = \App\Support\Navigation::sections($currentApp);

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

    // Satu item navigasi: lolos hak akses (menu/roles) DAN punya tujuan.
    $bisaTampil = function (array $item) use ($can, $navUrl): bool {
        if (!empty($item['menu']))  return $can($item['menu']) && $navUrl($item) !== null;
        if (!empty($item['roles'])) return Auth::user()->hasRole($item['roles']) && $navUrl($item) !== null;
        return $navUrl($item) !== null;
    };
@endphp

@foreach ($navSections as $labelSeksi => $kunciMenu)
    @php
        /*
         | Satu seksi (mis. Workflow) boleh campur: entri berindeks angka =
         | kunci item langsung, entri berkunci string = dropdown (nama grup
         | => daftar kunci item di dalamnya). Dipetakan jadi daftar "baris"
         | supaya markup di bawah tidak perlu tahu bedanya lagi, dan grup
         | yang semua isinya tidak terlihat (hak akses) otomatis tidak
         | dirender — termasuk labelnya.
         */
        $baris = collect($kunciMenu)->map(function ($v, $k) use ($navItems, $bisaTampil, $navAktif) {
            if (is_array($v)) {
                $items = collect($v['items'] ?? $v)
                    ->map(fn ($kk) => $navItems[$kk] ?? null)
                    ->filter()
                    ->filter($bisaTampil)
                    ->values();

                return $items->isEmpty() ? null : [
                    'type'   => 'group',
                    'label'  => $k,
                    'icon'   => $v['icon'] ?? 'fas fa-layer-group',
                    'items'  => $items,
                    'active' => $items->contains($navAktif),
                ];
            }

            $item = $navItems[$v] ?? null;
            if (!$item || !$bisaTampil($item)) {
                return null;
            }

            return ['type' => 'item', 'item' => $item];
        })->filter();
    @endphp

    @if ($baris->isNotEmpty())
        @if ($labelSeksi !== '')
            <div class="nav-label">{{ $labelSeksi }}</div>
        @endif

        @foreach ($baris as $baris1)
            @if ($baris1['type'] === 'item')
                @php($item = $baris1['item'])
                <a href="{{ $navUrl($item) }}" class="nav-item {{ $navAktif($item) ? 'active' : '' }}"
                    @if ($navAktif($item)) aria-current="page" @endif
                    @if (($item['menu'] ?? null) === 'todo-list') id="navTodoList" @endif>
                    <i class="{{ $item['icon'] }} ic" aria-hidden="true"></i>{{ $item['label'] }}
                    @if (($item['menu'] ?? null) === 'todo-list')
                        <span class="nav-badge-dot" id="navTodoBadge" title="New Task Assigned"
                            data-url="{{ route('todo-list.has-pending') }}" style="display:none;"></span>
                    @endif
                </a>
            @else
                <div class="nav-group {{ $baris1['active'] ? 'open' : '' }}">
                    <button type="button" class="nav-group-toggle" aria-expanded="{{ $baris1['active'] ? 'true' : 'false' }}">
                        <span class="nav-group-toggle-main">
                            <i class="{{ $baris1['icon'] }} ic" aria-hidden="true"></i>{{ $baris1['label'] }}
                        </span>
                        <i class="fas fa-chevron-right nav-group-caret" aria-hidden="true"></i>
                    </button>
                    <div class="nav-group-items">
                        @foreach ($baris1['items'] as $item)
                            <a href="{{ $navUrl($item) }}" class="nav-item {{ $navAktif($item) ? 'active' : '' }}"
                                @if ($navAktif($item)) aria-current="page" @endif>
                                <i class="{{ $item['icon'] }} ic" aria-hidden="true"></i>{{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
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
