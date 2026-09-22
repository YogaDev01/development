<div class="topbar">
    {{--
        Judul di topbar hanya tampil bila halaman mendefinisikan @section('breadcrumb').
        Blade lama menulis judul + breadcrumb sendiri di dalam content, jadi dibiarkan
        kosong supaya tidak dobel.
    --}}
    @hasSection('breadcrumb')
        <div>
            <div class="crumb">@yield('breadcrumb')</div>
            <div class="page-title">@yield('title', 'Tesys')</div>
        </div>
    @else
        <div style="min-width:1px;"></div>
    @endif

    <div class="topbar-actions">
        @hasSection('topbar-search')
            @yield('topbar-search')
        @else
            <form action="{{ Route::has('search') ? route('search') : '#' }}" method="GET" class="search" style="gap:8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
                <input type="text" name="q" placeholder="Cari CR, PO, invoice…"
                       style="border:none;background:none;outline:none;font:inherit;color:inherit;width:100%;">
            </form>
        @endif

        {{-- Bell notifikasi: mengambil data dari route notifications.fetch yang sudah ada --}}
        <div style="position:relative;">
            <button type="button" class="icon-btn" id="notifBtn" aria-label="Notifikasi">
                @include('ui.partials.icon', ['name' => 'bell'])
                <span class="notif-dot" id="notifDot" hidden></span>
            </button>

            <div class="notif-pop" id="notifPop">
                <div class="notif-pop-head">
                    <span>Notifikasi</span>
                    @if (Route::has('notifications.read-all'))
                        <span class="link-more" style="cursor:pointer;" id="notifReadAll">Tandai dibaca</span>
                    @endif
                </div>
                <div id="notifList">
                    <div class="empty-state" style="padding:26px 16px;">
                        <div class="ed">Memuat notifikasi…</div>
                    </div>
                </div>
            </div>
        </div>

        @yield('page-actions')
    </div>
</div>
