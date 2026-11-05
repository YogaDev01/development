@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Profil Saya</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item active">Profil Saya</li>
            </ol>
        </div>

        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <!-- HEADER CARD: cover + avatar (ala social media) -->
        <div class="card shadow-sm mb-3" style="overflow:hidden;">

            {{-- COVER / SAMPUL — tinggi 203px (156px + 30%). Klik membuka
                 menu: Lihat / Upload / Hapus. --}}
            <div class="prof-cover" id="coverWrap" style="{{ $user->cover_url ? 'background-image:url(\''.$user->cover_url.'\');' : '' }}">
                <button type="button" class="prof-photo-btn" id="coverMenuBtn" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-camera"></i> <span class="d-none d-sm-inline">Sampul</span>
                </button>
                <div class="prof-photo-menu" id="coverMenu" role="menu">
                    @if($user->cover_url)
                    <button type="button" class="prof-photo-item" data-lightbox="{{ $user->cover_url }}"><i class="fas fa-eye"></i> Lihat foto</button>
                    @endif
                    <button type="button" class="prof-photo-item" onclick="document.getElementById('coverInput').click();"><i class="fas fa-upload"></i> Upload foto</button>
                    @if($user->cover_url)
                    <button type="button" class="prof-photo-item danger" onclick="if(confirm('Hapus foto sampul?')) document.getElementById('frmCoverDelete').submit();"><i class="fas fa-trash-alt"></i> Hapus foto</button>
                    @endif
                </div>
            </div>

            <div class="px-4 pb-3" style="margin-top:-76px;">
                <div class="d-flex align-items-end flex-wrap" style="gap:16px;">
                    {{-- AVATAR — klik membuka menu yang sama. --}}
                    <div class="position-relative d-inline-block prof-avatar-wrap">
                        <div id="avatarPreviewWrap" class="prof-avatar">
                            @if($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;" id="avatarPreviewImg">
                            @else
                                <span style="color:var(--accent);font-size:50px;font-weight:700;" id="avatarPreviewInitials">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                            @endif
                        </div>
                        <button type="button" class="prof-avatar-cam" id="avatarMenuBtn" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-camera"></i>
                        </button>
                        <div class="prof-photo-menu" id="avatarMenu" role="menu" style="left:0;top:100%;">
                            @if($user->avatar_url)
                            <button type="button" class="prof-photo-item" data-lightbox="{{ $user->avatar_url }}"><i class="fas fa-eye"></i> Lihat foto</button>
                            @endif
                            <button type="button" class="prof-photo-item" onclick="document.getElementById('avatarInput').click();"><i class="fas fa-upload"></i> Upload foto</button>
                            @if($user->avatar_url)
                            <button type="button" class="prof-photo-item danger" onclick="if(confirm('Hapus foto profil?')) document.getElementById('frmAvatarDelete').submit();"><i class="fas fa-trash-alt"></i> Hapus foto</button>
                            @endif
                        </div>
                    </div>

                    <div class="mb-2 prof-identity">
                        <h4 class="mb-0 font-weight-bold">{{ $user->name }}</h4>
                        <div style="font-size:12.5px;opacity:.85;">{{ $user->email }}</div>
                        <div class="mt-1">
                            <span class="badge badge-warning" style="font-size:10.5px;"><i class="fas fa-user-tag mr-1"></i>{{ Str::upper($user->role->name ?? '-') }}</span>
                            <span class="badge badge-secondary" style="font-size:10.5px;"><i class="fas fa-users mr-1"></i>{{ $user->accessGroup->name ?? '-' }}</span>
                            <span class="badge badge-light border" style="font-size:10.5px;"><i class="fas fa-at mr-1"></i>{{ $user->username ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form tersembunyi untuk upload & hapus foto. --}}
            <form action="{{ route('profile.avatar') }}" method="POST" enctype="multipart/form-data" id="frmAvatar" class="d-none">@csrf
                <input type="file" name="avatar" id="avatarInput" accept="image/png,image/jpeg,image/webp">
            </form>
            <form action="{{ route('profile.avatar.delete') }}" method="POST" id="frmAvatarDelete" class="d-none">@csrf @method('DELETE')</form>
            <form action="{{ route('profile.cover') }}" method="POST" enctype="multipart/form-data" id="frmCover" class="d-none">@csrf
                <input type="file" name="cover" id="coverInput" accept="image/png,image/jpeg,image/webp">
            </form>
            <form action="{{ route('profile.cover.delete') }}" method="POST" id="frmCoverDelete" class="d-none">@csrf @method('DELETE')</form>

            <div class="border-top">
                <ul class="nav nav-tabs px-3 prof-tabs" id="profileTabs" style="border-bottom:none;">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#paneInfo"><i class="fas fa-info-circle mr-1"></i> Informasi</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneTodo" id="tabTodoLink"><i class="fas fa-tasks mr-1"></i> Todo List</a></li>
                    {{-- Access disembunyikan untuk role yang bypass access group (superadmin & approver
                         melihat semua PO tanpa Access Group) — tab ini tak berarti bagi mereka. --}}
                    @unless($user->hasRole(['superadmin', 'approver']))
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneAccess"><i class="fas fa-key mr-1"></i> Access <span class="badge badge-secondary ml-1">{{ $accessibleProjects->count() }}</span></a></li>
                    @endunless
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneUserLog"><i class="fas fa-stream mr-1"></i> User Log <span class="badge badge-secondary ml-1">{{ $activityLogs->total() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneTeam"><i class="fas fa-sitemap mr-1"></i> Team <span class="badge badge-secondary ml-1">{{ $myTeams->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#paneSettings"><i class="fas fa-cog mr-1"></i> Settings</a></li>
                </ul>
            </div>
        </div>

        {{-- LIGHTBOX "Lihat foto" --}}
        <div class="prof-lightbox" id="profLightbox">
            <button type="button" class="prof-lightbox-close" aria-label="Tutup">&times;</button>
            <img src="" alt="" id="profLightboxImg">
        </div>

        {{-- MODAL SUMMARY TASK (lazy-load) --}}
        <div class="modal fade" id="mdlProfSummaryTask" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
                    <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                        <h5 class="modal-title text-white mb-0"><i class="fas fa-chart-pie mr-2" style="color:var(--accent);"></i>Summary Task</h5>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body" style="max-height:78vh;overflow-y:auto;" id="profSummaryTaskBody">
                        <div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat…</div>
                    </div>
                    <div class="modal-footer" style="background:var(--surface-2);">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB CONTENT -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="tab-content">

                    <!-- TAB INFORMASI -->
                    <div class="tab-pane fade show active" id="paneInfo">
                        @include('profile._info')
                    </div>

                    <!-- TAB TODO LIST -->
                    <div class="tab-pane fade" id="paneTodo">
                        @include('profile._todo')
                    </div>

                    <!-- TAB ACCESS -->
                    <div class="tab-pane fade" id="paneAccess">
                        <p class="text-muted" style="font-size:12px;">
                            Daftar Purchase Order (Scope of Work) yang dapat diakses melalui Access Group
                            <strong>{{ $user->accessGroup->name ?? '-' }}</strong> dan Access Group dari Team aktif Anda.
                        </p>

                        @if($accessibleProjects->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-folder-open d-block mb-2" style="font-size:22px;"></i>
                            Belum ada Purchase Order dari Access Group individu maupun Team Anda.
                        </div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:5%;" class="text-center">No</th>
                                        <th scope="col">Scope of Work</th>
                                        <th scope="col">Customer</th>
                                        <th scope="col" style="width:15%;" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($accessibleProjects as $i => $p)
                                    <tr>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td>
                                            <div style="font-weight:600;">{{ $p->name }}</div>
                                            <small class="text-muted">{{ $p->code }}</small>
                                        </td>
                                        <td>{{ $p->customer->name ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $p->status_color }}">{{ $p->status_label }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

                    <!-- TAB USER LOG (Activity / Request / Project) -->
                    <div class="tab-pane fade" id="paneUserLog">
                        @include('profile._user_log')
                    </div>

                    <!-- TAB TEAM -->
                    <div class="tab-pane fade" id="paneTeam">
                        @include('profile._team')
                    </div>

                    <!-- TAB SETTINGS -->
                    <div class="tab-pane fade" id="paneSettings">
                        @include('profile._settings')
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
/* ---- Cover / sampul ---- */
.prof-cover { position:relative; height:203px; background:var(--charcoal); background-size:cover; background-position:center; }
.prof-photo-btn { position:absolute; top:12px; right:12px; z-index:3; border:none; border-radius:var(--radius-sm); padding:5px 11px; font-size:11.5px; font-weight:600; color:#fff; background:rgba(0,0,0,.42); cursor:pointer; transition:background .15s ease; }
.prof-photo-btn:hover { background:rgba(0,0,0,.6); }
.prof-photo-menu { position:absolute; top:46px; right:12px; z-index:20; min-width:160px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); box-shadow:var(--shadow-lg); padding:5px; display:none; }
.prof-photo-menu.show { display:block; }
.prof-photo-item { display:flex; align-items:center; gap:9px; width:100%; border:none; background:none; text-align:left; padding:8px 10px; border-radius:var(--radius-sm); font-size:12.5px; color:var(--text); cursor:pointer; }
.prof-photo-item:hover { background:var(--surface-2); }
.prof-photo-item.danger { color:var(--danger); }
.prof-photo-item i { width:15px; text-align:center; }

/* ---- Avatar ---- */
.prof-avatar { width:156px; height:156px; background:var(--charcoal); border:6px solid var(--surface); border-radius:50%; display:flex; align-items:center; justify-content:center; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
.prof-avatar-cam { position:absolute; bottom:5px; right:5px; width:40px; height:40px; background:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--surface); color:#fff; font-size:15px; cursor:pointer; padding:0; }
.prof-avatar-cam:hover { background:var(--accent-dark); }

/* ---- Identitas: pill hitam transparan yang menyambung ke belakang avatar ---- */
.prof-identity { position:relative; z-index:1; background:rgba(0,0,0,.45); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); padding:10px 16px 10px 108px; margin-left:-94px; border-radius:var(--radius-md); }
.prof-identity h4 { color:#fff; }
.prof-identity > div { color:rgba(255,255,255,.9); }
/* Avatar duduk DI ATAS pill supaya sambungannya rapi. */
.prof-avatar-wrap { z-index:2; }
@media (max-width: 575px) { .prof-identity { margin-left:0; padding-left:16px; } }

/* HP: sampul lebih pendek, avatar lebih kecil dan di tengah, identitas
   sebagai teks biasa di bawahnya. Pil gelap transparan dirancang untuk
   menumpang di atas sampul; di HP ia jatuh ke latar putih dan tampak
   sebagai kotak abu yang berat. */
@media (max-width: 575px) {
    .prof-cover { height:112px; }
    .prof-cover + div { margin-top:-50px !important; padding-left:16px !important; padding-right:16px !important; }
    .prof-cover + div > .d-flex { flex-direction:column; align-items:center !important; gap:8px !important; text-align:center; }
    .prof-avatar { width:96px; height:96px; border-width:4px; }
    .prof-avatar #avatarPreviewInitials { font-size:32px !important; }
    .prof-avatar-cam { width:32px; height:32px; font-size:12px; bottom:2px; right:2px; }
    .prof-identity {
        background:transparent; backdrop-filter:none; -webkit-backdrop-filter:none;
        padding:0; margin:0 !important; width:100%;
    }
    .prof-identity h4 { color:var(--text); font-size:18px; }
    .prof-identity > div { color:var(--text-dim); }
    .prof-identity .mt-1 { display:flex; flex-wrap:wrap; justify-content:center; gap:4px; }
    .prof-tabs { flex-wrap:nowrap; overflow-x:auto; padding-left:8px !important; padding-right:8px !important; }
}

/* ---- Tabs: tanpa scrollbar, membungkus di layar sempit ---- */
.prof-tabs { flex-wrap:wrap; overflow:visible; }

/* ---- Lightbox ---- */
.prof-lightbox { position:fixed; inset:0; z-index:3000; background:rgba(0,0,0,.82); display:none; align-items:center; justify-content:center; padding:30px; }
.prof-lightbox.show { display:flex; }
.prof-lightbox img { max-width:92vw; max-height:88vh; border-radius:var(--radius-md); box-shadow:0 10px 40px rgba(0,0,0,.5); }
.prof-lightbox-close { position:absolute; top:18px; right:24px; background:none; border:none; color:#fff; font-size:34px; line-height:1; cursor:pointer; opacity:.85; }
.prof-lightbox-close:hover { opacity:1; }

/* ---- Todo summary tables ---- */
.todo-tbl-wrap { border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; }
.todo-tbl-head { background:var(--surface-2); border-bottom:1px solid var(--border); border-top:3px solid var(--accent); padding:7px 12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--text); }
.todo-tbl { font-size:12.5px; }
.todo-tbl thead th { background:var(--surface); font-size:10.5px; text-transform:uppercase; letter-spacing:.03em; color:var(--text-faint); font-weight:600; border-top:none; }
.todo-tbl tbody td { vertical-align:middle; }
.todo-tbl tfoot td { background:var(--surface-2); }

/* ---- Changelog timeline ---- */
.cl-item { position:relative; padding:0 0 14px 20px; border-left:2px solid var(--border); }
.cl-item:last-child { border-left-color:transparent; }
.cl-dot { position:absolute; left:-6px; top:3px; width:10px; height:10px; border-radius:50%; background:var(--accent); border:2px solid var(--surface); }
.cl-tag { display:inline-block; font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:1px 7px; border-radius:var(--radius-pill); }
.cl-tag-added { background:var(--success-bg); color:var(--success); }
.cl-tag-changed { background:var(--info-bg); color:var(--info); }
.cl-tag-fixed { background:var(--warning-bg); color:var(--warning); }
.cl-tag-removed, .cl-tag-security { background:var(--danger-bg); color:var(--danger); }
.cl-list { margin:4px 0 0; padding-left:18px; }
.cl-list li { font-size:11.5px; color:var(--text-dim); line-height:1.5; margin-bottom:2px; }

/* ---- Organization chart ---- */
.org-team-ic { width:38px; height:38px; border-radius:var(--radius-md); background:var(--accent-tint); color:var(--accent-text); display:flex; align-items:center; justify-content:center; font-size:16px; flex:none; }
.org-tier { text-align:center; margin-bottom:22px; position:relative; }
.org-tier + .org-tier::before { content:""; display:block; width:2px; height:16px; background:var(--border); margin:-18px auto 6px; }
.org-tier-label { display:inline-block; font-size:10px; text-transform:uppercase; font-weight:700; letter-spacing:.05em; color:var(--text-faint); background:var(--surface-2); padding:2px 10px; border-radius:var(--radius-pill); margin-bottom:12px; }
.org-tier-cards { display:flex; flex-wrap:wrap; justify-content:center; gap:12px; }
.org-card { width:150px; background:var(--surface); border:1px solid var(--border); border-top:3px solid var(--accent); border-radius:var(--radius-md); padding:12px 10px; transition:border-color .2s ease, transform .15s ease; }
.org-card:hover { border-color:var(--accent-soft); transform:translateY(-2px); }
.org-card.is-me { border-color:var(--accent); box-shadow:var(--shadow-accent); background:var(--accent-tint); }
.org-card-avatar { width:44px; height:44px; border-radius:50%; margin:0 auto 7px; background:var(--charcoal); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; overflow:hidden; }
.org-card-avatar img { width:100%; height:100%; object-fit:cover; }
.org-card-name { font-weight:700; font-size:12px; line-height:1.25; }
.org-card-pos { font-size:10.5px; color:var(--text-faint); }

/* ---- Settings ---- */
.set-wrap { display:flex; gap:20px; align-items:flex-start; }
.set-nav { width:210px; flex:none; display:flex; flex-direction:column; gap:3px; }
.set-nav-item { display:block; padding:9px 12px; border-radius:var(--radius-sm); font-size:12.5px; font-weight:600; color:var(--text-dim); text-decoration:none; transition:background .15s ease, color .15s ease; }
.set-nav-item:hover { background:var(--surface-2); color:var(--text); text-decoration:none; }
.set-nav-item.active { background:var(--accent-tint); color:var(--accent-text); }
.set-body { flex:1; min-width:0; }
.set-section { display:none; }
.set-section.active { display:block; animation:setfade .2s ease; }
@keyframes setfade { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }
.set-card { background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-md); padding:12px 14px; }
.accent-swatches { display:flex; flex-wrap:wrap; gap:9px; }
.accent-swatch { width:34px; height:34px; border-radius:50%; border:2px solid var(--surface); box-shadow:0 0 0 1px var(--border); cursor:pointer; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; padding:0; transition:transform .12s ease; }
.accent-swatch:hover { transform:scale(1.1); }
.accent-swatch i { opacity:0; transition:opacity .12s ease; }
.accent-swatch.is-active { box-shadow:0 0 0 2px var(--accent); }
.accent-swatch.is-active i { opacity:1; }
@media (max-width: 767px) {
    .set-wrap { flex-direction:column; }
    .set-nav { width:100%; flex-direction:row; flex-wrap:wrap; }
}
</style>
@endpush

@push('scripts')
<script>
// Setelah submit filter / pindah halaman Activity Log, URL membawa param.
// Aktifkan kembali tab User Log + sub-tab Activity Log supaya tidak terlempar
// ke Informasi.
(function () {
    var p = new URLSearchParams(window.location.search);
    var keys = ['alog', 'module', 'action', 'date_from', 'date_to', 'q'];
    if (keys.some(function (k) { return p.has(k); }) && window.jQuery) {
        var outer = document.querySelector('a[href="#paneUserLog"]');
        if (outer) { window.jQuery(outer).tab('show'); }
        var sub = document.querySelector('a[href="#ulActivity"]');
        if (sub) { window.jQuery(sub).tab('show'); }
    }
})();

// Avatar: preview + auto-submit.
document.getElementById('avatarInput').addEventListener('change', function () {
    if (!this.files || !this.files[0]) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        document.getElementById('avatarPreviewWrap').innerHTML =
            '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    reader.readAsDataURL(this.files[0]);
    document.getElementById('frmAvatar').submit();
});

// Cover: auto-submit saat file dipilih.
(function () {
    var ci = document.getElementById('coverInput');
    if (ci) ci.addEventListener('change', function () {
        if (this.files && this.files[0]) document.getElementById('frmCover').submit();
    });
})();

// ---- Menu foto (avatar & cover) ----
(function () {
    function wire(btnId, menuId) {
        var btn = document.getElementById(btnId), menu = document.getElementById(menuId);
        if (!btn || !menu) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.prof-photo-menu.show').forEach(function (m) {
                if (m !== menu) m.classList.remove('show');
            });
            var open = menu.classList.toggle('show');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
    wire('coverMenuBtn', 'coverMenu');
    wire('avatarMenuBtn', 'avatarMenu');
    // Klik avatar bulatnya juga membuka menu avatar.
    var av = document.getElementById('avatarPreviewWrap');
    if (av) { av.style.cursor = 'pointer'; av.addEventListener('click', function () {
        var b = document.getElementById('avatarMenuBtn'); if (b) b.click();
    }); }
    document.addEventListener('click', function () {
        document.querySelectorAll('.prof-photo-menu.show').forEach(function (m) { m.classList.remove('show'); });
    });
})();

// ---- Lightbox "Lihat foto" ----
(function () {
    var box = document.getElementById('profLightbox');
    var img = document.getElementById('profLightboxImg');
    if (!box) return;
    document.querySelectorAll('[data-lightbox]').forEach(function (el) {
        el.addEventListener('click', function () {
            img.src = el.getAttribute('data-lightbox');
            box.classList.add('show');
        });
    });
    function close() { box.classList.remove('show'); img.src = ''; }
    box.querySelector('.prof-lightbox-close').addEventListener('click', close);
    box.addEventListener('click', function (e) { if (e.target === box) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();

// ---- Settings: sub-nav ----
(function () {
    var items = document.querySelectorAll('.set-nav-item');
    items.forEach(function (it) {
        it.addEventListener('click', function (e) {
            e.preventDefault();
            var id = it.getAttribute('data-set-target');
            items.forEach(function (x) { x.classList.remove('active'); });
            it.classList.add('active');
            document.querySelectorAll('.set-section').forEach(function (s) {
                s.classList.toggle('active', s.id === id);
            });
        });
    });
})();

// ---- Settings: mode tema (pakai kunci yang sama dengan topbar) ----
(function () {
    document.querySelectorAll('[data-theme-set]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = btn.getAttribute('data-theme-set');
            document.documentElement.setAttribute('data-theme', t);
            try { localStorage.setItem('tesys-theme', t); } catch (e) {}
            tandaiTemaAktif();
        });
    });
    function tandaiTemaAktif() {
        var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-set]').forEach(function (b) {
            b.classList.toggle('btn-primary', b.getAttribute('data-theme-set') === cur);
            b.classList.toggle('btn-outline-secondary', b.getAttribute('data-theme-set') !== cur);
        });
    }
    tandaiTemaAktif();
})();

// ---- Settings: warna aksen (localStorage 'tesys-accent') ----
(function () {
    var DEFAULT = '#92232b';
    function simpanTerapkan(hex) {
        try { localStorage.setItem('tesys-accent', hex); } catch (e) {}
        if (window.TesysAccent) window.TesysAccent.apply(hex);
        tandai(hex);
    }
    function tandai(hex) {
        var norm = (hex || DEFAULT).toLowerCase();
        document.querySelectorAll('.accent-swatch').forEach(function (s) {
            s.classList.toggle('is-active', (s.getAttribute('data-accent') || '').toLowerCase() === norm);
        });
        var cust = document.getElementById('accentCustom');
        if (cust) cust.value = norm;
    }
    document.querySelectorAll('.accent-swatch').forEach(function (s) {
        s.addEventListener('click', function () { simpanTerapkan(s.getAttribute('data-accent')); });
    });
    var cust = document.getElementById('accentCustom');
    if (cust) cust.addEventListener('input', function () { simpanTerapkan(this.value); });
    var reset = document.getElementById('accentReset');
    if (reset) reset.addEventListener('click', function () {
        try { localStorage.removeItem('tesys-accent'); } catch (e) {}
        if (window.TesysAccent) window.TesysAccent.apply(DEFAULT);
        tandai(DEFAULT);
    });
    // Tandai keadaan awal dari localStorage.
    var saved = DEFAULT;
    try { saved = localStorage.getItem('tesys-accent') || DEFAULT; } catch (e) {}
    tandai(saved);
})();

// ---- Todo List ringkas (lazy-load saat tab dibuka, biar profil tetap ringan) ----
(function () {
    var box = document.getElementById('todoSummaryBox');
    if (!box) return;
    var loading = document.getElementById('todoSummaryLoading');
    var content = document.getElementById('todoSummaryContent');
    var empty   = document.getElementById('todoSummaryEmpty');

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    var foot = document.getElementById('todoSummaryFoot');

    function tableHtml(t) {
        var rowsHtml = '';
        if (!t.rows || !t.rows.length) {
            rowsHtml = '<tr><td colspan="2" class="text-center text-muted py-2" style="font-size:11.5px;">Tidak ada</td></tr>';
        } else {
            t.rows.forEach(function (r) {
                var label = r.url
                    ? '<a href="' + r.url + '">' + esc(r.label) + '</a>'
                    : esc(r.label);
                rowsHtml += '<tr><td>' + label + '</td><td class="text-center num">' + (r.count || 0) + '</td></tr>';
            });
        }
        return ''
            + '<div class="col-lg-4 mb-3">'
            + '  <div class="todo-tbl-wrap">'
            + '    <div class="todo-tbl-head">' + esc(t.module) + '</div>'
            + '    <table class="table table-sm table-bordered mb-0 todo-tbl">'
            + '      <thead><tr><th>Tahap Action</th><th class="text-center" style="width:64px;">Qty</th></tr></thead>'
            + '      <tbody>' + rowsHtml + '</tbody>'
            + '      <tfoot><tr><td class="font-weight-bold text-right">Subtotal</td>'
            + '        <td class="text-center font-weight-bold num">' + (t.subtotal || 0) + '</td></tr></tfoot>'
            + '    </table>'
            + '  </div>'
            + '</div>';
    }

    var loaded = false;
    function loadTodo() {
        if (loaded) return;
        loaded = true;
    fetch('{{ route('profile.todo-summary') }}', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            loading.style.display = 'none';
            var tables = d.tables || [];
            var anyRow = tables.some(function (t) { return t.rows && t.rows.length; });
            if (!tables.length) { empty.style.display = 'block'; return; }

            content.innerHTML = tables.map(tableHtml).join('');
            content.style.display = 'flex';

            if (!anyRow) { empty.style.display = 'block'; }

            if (typeof d.completedToday === 'number') {
                foot.innerHTML = '<i class="fas fa-check mr-1 text-success"></i> Selesai hari ini: <strong>'
                    + d.completedToday + '</strong> &nbsp;·&nbsp; Total tertunda: <strong>' + (d.total || 0) + '</strong>';
                foot.style.display = 'block';
            }
        })
        .catch(function () {
            loading.style.display = 'none';
            empty.textContent = 'Gagal memuat ringkasan todo.';
            empty.style.display = 'block';
        });
    }

    // Muat saat tab Todo pertama kali dibuka.
    var link = document.getElementById('tabTodoLink');
    if (link && window.jQuery) {
        window.jQuery(link).on('shown.bs.tab', loadTodo);
    } else if (link) {
        link.addEventListener('click', loadTodo);
    }
    // Jika halaman dibuka langsung dengan tab Todo aktif (mis. via hash).
    if (window.location.hash === '#paneTodo') { loadTodo(); }
})();

// ---- Summary Task (lazy-load matrix ke modal) ----
(function () {
    var btn = document.getElementById('btnSummaryTask');
    if (!btn || !window.jQuery) return;
    var $ = window.jQuery;
    var loaded = false;

    btn.addEventListener('click', function () {
        $('#mdlProfSummaryTask').modal('show');
        if (loaded) return;
        var body = document.getElementById('profSummaryTaskBody');
        fetch('{{ route('profile.todo-matrix') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                body.innerHTML = html;
                loaded = true;
                initSummaryTaskFilter($);
            })
            .catch(function () {
                body.innerHTML = '<div class="text-center text-danger py-4">Gagal memuat Summary Task.</div>';
            });
    });

    // Filter modul/role sisi-klien — disalin dari todo-list/index.blade.php
    // (partial dirender tanpa JS-nya sendiri).
    function initSummaryTaskFilter($) {
        var $wrap = $('#summaryTaskWrap');
        if (!$wrap.length) return;
        var modul = '', role = '';
        function terapkan() {
            var $tbl = $('#summaryTaskTable');
            $tbl.find('[data-module]').each(function () {
                $(this).toggle(!modul || $(this).data('module') === modul);
            });
            var totalPerModul = {}, grand = 0, tampil = 0;
            $tbl.find('tbody tr[data-role]').each(function () {
                var $tr = $(this);
                var $selModul = $tr.find('[data-module]').filter(function () {
                    return !modul || $(this).data('module') === modul;
                });
                var adaAngka = $selModul.filter('[data-count]').length > 0;
                var cocok = (!role || $tr.data('role') === role) && adaAngka;
                $tr.toggle(cocok);
                if (!cocok) return;
                tampil++;
                var subtotal = 0;
                $selModul.filter('[data-count]').each(function () {
                    var n = parseInt($(this).attr('data-count'), 10) || 0;
                    subtotal += n;
                    var m = $(this).data('module');
                    totalPerModul[m] = (totalPerModul[m] || 0) + n;
                });
                grand += subtotal;
                $tr.find('[data-total-cell]').text(subtotal)
                    .toggleClass('summary-total-pos', subtotal > 0)
                    .toggleClass('summary-total-zero', subtotal <= 0);
            });
            $tbl.find('[data-foot-module]').each(function () {
                $(this).text(totalPerModul[$(this).data('module')] || 0);
            });
            $tbl.find('[data-foot-grand]').text(grand);
            $('#stEmptyRow').toggle(tampil === 0);
            $tbl.find('tfoot').toggle(tampil > 0);
            $('#stModuleBtn').toggleClass('col-mf-active', modul !== '');
            $('#stRoleBtn').toggleClass('col-mf-active', role !== '');
            $('#stModuleLabel').text(modul || 'All Module');
            $('#stRoleLabel').text(role || 'All Role');
        }
        $wrap.on('click', '.st-module-opt', function () {
            modul = $(this).data('value') || '';
            $wrap.find('.st-module-opt').removeClass('active'); $(this).addClass('active'); terapkan();
        });
        $wrap.on('click', '.st-role-opt', function () {
            role = $(this).data('value') || '';
            $wrap.find('.st-role-opt').removeClass('active'); $(this).addClass('active'); terapkan();
        });
        $('#stClearFilter').on('click', function () {
            modul = ''; role = '';
            $wrap.find('.st-module-opt, .st-role-opt').removeClass('active');
            $wrap.find('.st-module-opt[data-value=""], .st-role-opt[data-value=""]').addClass('active');
            terapkan();
        });
    }
})();
</script>
@endpush
