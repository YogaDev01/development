{{--
    Bar navigasi bawah — hanya tampil di layar sempit (<=767.98px, lihat
    tesys-ui-mobile.css bagian 19). Bukan pengganti sidebar. Lima item tetap:

      [Logo Terex] [Home] [Todo List] [Tabs] [Menu]

    - Logo Terex : lambang yang sama dengan sidebar (dist/img/ts_icon.png);
                   membuka dropdown (dropup Bootstrap) pilihan aplikasi — daftar
                   $apps & tujuan 'home' sama persis dengan pemindah aplikasi di
                   sidebar (app.blade.php, #appSwitch).
    - Home       : beranda aplikasi aktif ($currentAppConfig['home']); tanpa
                   gerbang menu, seperti item Dashboard di sidebar.
    - Todo List  : hanya dirender bila $can('todo-list') — gerbang yang sama
                   dengan sidebar. Titik [data-todo-dot] diatur initTodoBadge().
    - Tabs       : memilih sub-tab halaman saat ini. Daftarnya dibangun saat
                   diklik oleh initBottomNav() (tesys-shell.js); tanpa tab
                   tingkat halaman tombol tampil redup (aria-disabled).
    - Menu       : data-sidebar-toggle -> laci sidebar.

    Variabel dari layout: $can, $apps, $currentApp, $currentAppConfig.
--}}
@php
    $homeUrl   = $currentAppConfig['home'];
    $homeAktif = request()->is(trim($homeUrl, '/') ?: '/');

    $todo       = config('navigation.items.todo-list');
    $todoTampil = $todo && $can($todo['menu']);
    $todoAktif  = $todoTampil && request()->is(...($todo['active'] ?? []));
@endphp

<nav class="ts-bnav" aria-label="Navigasi utama">

    <div class="dropup ts-bnav-drop">
        <button type="button" class="ts-bnav-item" data-toggle="dropdown" data-display="static"
                aria-haspopup="true" aria-expanded="false"
                aria-label="Pindah aplikasi, saat ini {{ $currentAppConfig['label'] }}">
            <span class="ts-bnav-ic"><img class="ts-bnav-logo" src="{{ asset('dist/img/ts_icon.png') }}" alt=""></span>
            <span class="ts-bnav-label">{{ $currentAppConfig['label'] }}</span>
        </button>
        <div class="dropdown-menu ts-bnav-menu" role="menu" aria-label="Pilih aplikasi">
            @foreach ($apps as $key => $item)
                <a href="{{ $item['home'] }}" role="menuitem"
                   class="dropdown-item {{ $currentApp === $key ? 'is-current' : '' }}"
                   @if ($currentApp === $key) aria-current="true" @endif>
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span class="ts-bnav-opt-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <a href="{{ $homeUrl }}" class="ts-bnav-item {{ $homeAktif ? 'active' : '' }}"
       @if ($homeAktif) aria-current="page" @endif>
        <span class="ts-bnav-ic"><i class="fas fa-home" aria-hidden="true"></i></span>
        <span class="ts-bnav-label">Home</span>
    </a>

    @if ($todoTampil)
        <a href="{{ $todo['url'] }}" class="ts-bnav-item {{ $todoAktif ? 'active' : '' }}"
           @if ($todoAktif) aria-current="page" @endif>
            <span class="ts-bnav-ic">
                <i class="{{ $todo['icon'] }}" aria-hidden="true"></i>
                <span class="nav-badge-dot" data-todo-dot hidden></span>
            </span>
            <span class="ts-bnav-label">{{ $todo['label'] }}</span>
            <span class="sr-only" data-todo-dot-text hidden>Ada tugas baru</span>
        </a>
    @endif

    <div class="dropup ts-bnav-drop">
        <button type="button" class="ts-bnav-item is-disabled" id="bnavTabsBtn"
                data-toggle="dropdown" data-display="static"
                aria-haspopup="true" aria-expanded="false" aria-disabled="true">
            <span class="ts-bnav-ic"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
            <span class="ts-bnav-label">Tabs</span>
        </button>
        <div class="dropdown-menu dropdown-menu-right ts-bnav-menu" id="bnavTabsMenu" role="menu" aria-label="Pilih tab halaman"></div>
    </div>

    <button type="button" class="ts-bnav-item" data-sidebar-toggle aria-controls="tsSidebar" aria-expanded="false">
        <span class="ts-bnav-ic"><i class="fas fa-bars" aria-hidden="true"></i></span>
        <span class="ts-bnav-label">Menu</span>
    </button>

</nav>
