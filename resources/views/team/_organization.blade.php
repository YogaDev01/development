{{--
    Tab "Organisasi" pada daftar Team (team.index): ringkasan organisasi,
    chart orang per department, dan BAGAN ORGANISASI dengan dua mode:
      - Leader View: pohon atas → bawah, hanya orang yang memimpin team.
      - Detail Member: satu team terpilih (leader + anggota per level).

    Variabel (TeamController::organizationData): $orgChart, $orgTeams, $people,
    $meId, $myTeams, $orgSummary.

    Bagan berbasis orang: team tersambung lewat orang yang sama (leader team
    A yang juga anggota team B → team A di bawah kartunya di team B), lalu
    Induk Team. HANYA tampilan: tidak memberi akses data (blueprint K3).
    Garis penghubung Leader View digambar JS sebagai SVG dari atribut data-up,
    jadi markup tetap berupa daftar bersarang biasa. Drawer profil (#osPerson)
    dirender di team.index, di luar .tab-pane.
--}}
@push('styles')
<style>
    /* Ringkasan organisasi */
    .os-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--sp-3); margin-bottom: var(--sp-4); }
    .os-kpi { border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--sp-3) var(--sp-4); background: var(--surface); }
    .os-kpi-label { font-size: var(--fs-caption); font-weight: 600; letter-spacing: var(--ls-micro); text-transform: uppercase; color: var(--text-faint); }
    .os-kpi-value { font-size: 20px; font-weight: 700; color: var(--text); font-variant-numeric: tabular-nums; }
    .os-kpi-sub { font-size: var(--fs-caption); color: var(--text-dim); }

    /* Chart: orang per department — satu seri, bar mendatar tipis. */
    .os-chart { border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--sp-3) var(--sp-4); margin-bottom: var(--sp-4); background: var(--surface); }
    .os-chart-title { font-size: var(--fs-body); font-weight: 700; color: var(--text); }
    .os-chart-sub { font-size: var(--fs-caption); color: var(--text-dim); margin-bottom: var(--sp-3); }
    .os-bar-row { display: grid; grid-template-columns: minmax(110px, 200px) 1fr minmax(150px, auto); align-items: center; gap: var(--sp-3); padding: 3px 0; }
    .os-bar-row:hover .os-bar-fill { opacity: .85; }
    .os-bar-name { font-size: var(--fs-label); font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .os-bar-track { height: 10px; border-radius: 0 4px 4px 0; background: var(--surface-2); }
    .os-bar-fill { height: 100%; min-width: 2px; border-radius: 0 4px 4px 0; background: var(--accent); }
    .os-bar-val { font-size: var(--fs-caption); color: var(--text-dim); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .os-bar-val strong { color: var(--text); }

    .os-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sp-2); margin-bottom: var(--sp-3); }
    .os-toolbar .form-control { max-width: 260px; }
    .os-toolbar .os-zoom { display: inline-flex; gap: 4px; margin-left: auto; }
    .os-teampick { flex: 0 1 280px; min-width: 200px; }
    .os-hits { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-bottom: var(--sp-3); font-size: var(--fs-caption); color: var(--text-dim); }
    .os-me { background: var(--surface); border: 1px solid var(--border); border-left: 3px solid var(--accent); border-radius: var(--radius-md); padding: var(--sp-3) var(--sp-4); margin-bottom: var(--sp-4); font-size: var(--fs-body); color: var(--text); }
    .os-me-label { font-size: var(--fs-caption); font-weight: 600; letter-spacing: var(--ls-micro); text-transform: uppercase; color: var(--text-faint); margin-right: var(--sp-2); }
    .os-me a { display: inline-block; margin: 2px 6px 2px 0; padding: 1px 10px; border-radius: var(--radius-pill); background: var(--accent-tint); color: var(--accent-text); font-size: var(--fs-label); font-weight: 600; }
    .os-note { font-size: var(--fs-caption); color: var(--text-faint); margin-bottom: var(--sp-3); }
    .os-legend { display: inline-flex; align-items: center; gap: 6px; margin-right: var(--sp-3); }
    .os-legend i { display: inline-block; width: 22px; border-top: 2px solid var(--accent); vertical-align: middle; }
    .os-legend i.is-dash { border-top: 2px dashed var(--text-faint); }

    /* ---------- Bagan (atas → bawah). Scroll mendatar HANYA di dalam bingkai. ---------- */
    .oc-viewport { position: relative; overflow: auto; max-height: 78vh; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface); }
    .oc-canvas { position: relative; display: inline-flex; gap: 48px; align-items: flex-start; padding: var(--sp-6) var(--sp-6) var(--sp-8); min-width: 100%; justify-content: center; transform-origin: 0 0; }
    .oc-lines { position: absolute; left: 0; top: 0; pointer-events: none; overflow: visible; }
    .oc-lines path { fill: none; stroke: var(--accent); stroke-width: 2; stroke-linejoin: round; }
    .oc-lines path.is-dash { stroke: var(--text-faint); stroke-dasharray: 5 4; stroke-width: 1.5; }
    .oc-lines path.is-hot { stroke: var(--accent); stroke-width: 2.5; }

    .oc-node { display: flex; flex-direction: column; align-items: center; position: relative; }
    .oc-children { display: flex; gap: 32px; align-items: flex-start; justify-content: center; margin-top: 40px; }
    .oc-children[hidden] { display: none; }
    .oc-empty { font-size: var(--fs-caption); color: var(--text-faint); padding: var(--sp-2); }

    .oc-card { width: 150px; display: flex; flex-direction: column; align-items: center; background: transparent; border: 0; padding: 0; color: var(--text); position: relative; z-index: 1; }
    .oc-photo { width: 64px; height: 64px; border-radius: 50%; border: 3px solid var(--accent); background: var(--surface-2); color: var(--text-dim); display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 16px; font-weight: 700; box-shadow: 0 0 0 3px var(--surface); }
    .oc-photo img { width: 100%; height: 100%; object-fit: cover; }
    /* Label pill di bawah foto: jabatan tebal + nama. Putih di atas aksen = pola yang sama dengan tombol primer TESYS. */
    .oc-label { margin-top: -10px; width: 100%; background: var(--accent); color: #fff; border-radius: 12px; padding: 5px 8px 6px; line-height: 1.2; display: flex; flex-direction: column; box-shadow: 0 0 0 2px var(--surface); }
    .oc-label strong { font-size: 11.5px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-label span { font-size: 11px; opacity: .92; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-card.is-leader .oc-photo { width: 76px; height: 76px; }
    .oc-card:not(.is-leader) .oc-label { background: var(--surface); color: var(--text); border: 1.5px solid var(--accent); }
    .oc-card:not(.is-leader) .oc-label span { color: var(--text-dim); opacity: 1; }
    .oc-card:hover .oc-photo, .oc-card:focus-visible .oc-photo { box-shadow: 0 0 0 3px var(--accent-tint-2); }
    .oc-card.is-me .oc-photo { box-shadow: 0 0 0 3px var(--accent-tint-2), 0 0 0 6px var(--accent-soft); }
    .oc-card.is-hit .oc-label, .oc-card.is-twin .oc-label { outline: 2px solid var(--warning); outline-offset: 2px; }
    .oc-tags { display: flex; flex-wrap: wrap; justify-content: center; gap: 3px; margin-top: 4px; max-width: 150px; }
    .oc-tag { display: inline-block; padding: 0 6px; border-radius: var(--radius-pill); background: var(--surface-2); border: 1px solid var(--border); color: var(--text-dim); font-size: 9.5px; font-weight: 600; line-height: 15px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-tag.is-me { background: var(--accent-tint); border-color: var(--accent-tint-2); color: var(--accent-text); }
    .oc-tag.is-lead { background: var(--info-bg); border-color: var(--info-bg); color: var(--info); }
    .oc-tag.is-dup { background: var(--warning-bg); border-color: var(--warning-bg); color: var(--warning); }
    .oc-toggle { position: relative; z-index: 2; margin-top: 6px; width: 20px; height: 20px; padding: 0; border-radius: 50%; border: 1px solid var(--border-strong); background: var(--surface); color: var(--text-dim); font-size: 9px; display: inline-flex; align-items: center; justify-content: center; }
    .oc-toggle:hover { color: var(--accent-text); border-color: var(--accent-soft); }
    .oc-toggle + .oc-children { margin-top: 26px; }

    /* Tombol team: buka team itu di mode Detail Member. */
    .oc-teams { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; margin-top: 4px; max-width: 170px; }
    .oc-orphan { font-size: var(--fs-caption); font-weight: 600; color: var(--text-faint); }
    .oc-teamlink { max-width: 170px; padding: 1px 8px; border: 1px solid var(--border-strong); border-radius: var(--radius-pill); background: var(--surface); color: var(--text); font-size: var(--fs-caption); font-weight: 600; line-height: 18px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-teamlink span { color: var(--text-dim); font-weight: 500; }
    .oc-teamlink:hover, .oc-teamlink:focus-visible { border-color: var(--accent-soft); background: var(--accent-tint); color: var(--accent-text); }

    /* ---------- Detail Member: satu team, anggota membungkus ke bawah (tanpa scroll mendatar). ---------- */
    .ot-view { border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface); padding: var(--sp-4); }
    .ot-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: var(--sp-2); }
    .ot-title { font-size: var(--fs-body); font-weight: 700; color: var(--text); }
    .ot-sub { font-size: var(--fs-caption); color: var(--text-dim); }
    .ot-nav { display: flex; flex-wrap: wrap; gap: var(--sp-1) var(--sp-4); margin-top: var(--sp-2); }
    .ot-nav-group { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .ot-nav-label { font-size: var(--fs-caption); color: var(--text-faint); }
    .ot-body { display: flex; flex-direction: column; align-items: center; margin-top: var(--sp-4); }
    .ot-tier { position: relative; width: 100%; display: flex; flex-direction: column; align-items: center; padding-top: 28px; margin-top: 6px; }
    .ot-tier::before { content: ''; position: absolute; top: 0; left: 50%; height: 28px; border-left: 2px solid var(--accent); }
    .ot-tier-label { padding: 0 10px; border: 1px solid var(--border); border-radius: var(--radius-pill); background: var(--surface-2); color: var(--text-dim); font-size: var(--fs-caption); font-weight: 600; line-height: 20px; }
    .ot-cards { display: flex; flex-wrap: wrap; justify-content: center; gap: var(--sp-4) 20px; margin-top: var(--sp-3); }
    .ot-view .oc-card { width: 170px; }
    .ot-view .oc-tags, .ot-view .oc-tag { max-width: 170px; }

    /* Drawer profil */
    .os-dr-head { display: flex; align-items: center; gap: var(--sp-3); margin-bottom: var(--sp-4); }
    .os-dr-photo { flex: 0 0 48px; width: 48px; height: 48px; border-radius: 50%; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; background: var(--surface-2); border: 2px solid var(--accent); color: var(--text-dim); font-weight: 700; }
    .os-dr-photo img { width: 100%; height: 100%; object-fit: cover; }
    .os-dr-name { font-size: var(--fs-body); font-weight: 700; color: var(--text); }
    .os-dr-sub { font-size: var(--fs-caption); color: var(--text-dim); }
    .os-dr-team { padding: var(--sp-3) 0; border-top: 1px solid var(--border); }
    .os-dr-team a { font-weight: 600; color: var(--text); }
    .os-dr-team a:hover { color: var(--accent-text); }
    .os-badge { display: inline-block; margin: 2px 4px 0 0; padding: 0 6px; border-radius: var(--radius-pill); background: var(--surface-2); border: 1px solid var(--border); color: var(--text-dim); font-size: 10px; font-weight: 600; line-height: 16px; }
    .os-badge.is-me { background: var(--accent-tint); border-color: var(--accent-tint-2); color: var(--accent-text); }
    .os-code { display: inline-block; margin-left: 6px; padding: 0 8px; border-radius: var(--radius-pill); border: 1px solid var(--border); color: var(--text-dim); font-size: var(--fs-caption); font-weight: 600; }

    @media (max-width: 900px) {
        .os-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .os-bar-row { grid-template-columns: 1fr; gap: 2px; padding: var(--sp-1) 0; }
        .os-toolbar .form-control { max-width: none; flex: 1 1 100%; }
        .os-toolbar .os-zoom { margin-left: 0; }
        .os-teampick { flex: 1 1 100%; }
        .oc-viewport { max-height: 70vh; }
        .oc-canvas { padding: var(--sp-4); }
    }
</style>
@endpush

{{-- RINGKASAN --}}
<div class="os-kpis">
    <div class="os-kpi">
        <div class="os-kpi-label">Team Aktif</div>
        <div class="os-kpi-value">{{ $orgSummary['teams'] }}</div>
        <div class="os-kpi-sub">{{ $orgSummary['departments']->count() }} department</div>
    </div>
    <div class="os-kpi">
        <div class="os-kpi-label">Orang</div>
        <div class="os-kpi-value">{{ $orgSummary['people'] }}</div>
        <div class="os-kpi-sub">anggota aktif, dihitung unik</div>
    </div>
    <div class="os-kpi">
        <div class="os-kpi-label">Leader</div>
        <div class="os-kpi-value">{{ $orgSummary['leaders'] }}</div>
        <div class="os-kpi-sub">orang yang memimpin team</div>
    </div>
    <div class="os-kpi">
        <div class="os-kpi-label">Multi-Team</div>
        <div class="os-kpi-value">{{ $orgSummary['multi_team'] }}</div>
        <div class="os-kpi-sub">orang di lebih dari 1 team</div>
    </div>
</div>

@if($orgSummary['departments']->isNotEmpty())
<div class="os-chart" role="group" aria-label="Orang per department">
    <div class="os-chart-title">Orang per Department</div>
    <div class="os-chart-sub">Anggota aktif unik per department, beserta jumlah team dan leader-nya.</div>
    @foreach($orgSummary['departments'] as $d)
    @php $pct = round($d['people'] / $orgSummary['max_people'] * 100, 1); @endphp
    <div class="os-bar-row" title="{{ $d['name'] }}: {{ $d['people'] }} orang · {{ $d['teams'] }} team · {{ $d['leaders'] }} leader">
        <div class="os-bar-name">{{ $d['name'] }}</div>
        <div class="os-bar-track" aria-hidden="true"><div class="os-bar-fill" style="width: {{ $pct }}%;"></div></div>
        <div class="os-bar-val"><strong>{{ $d['people'] }}</strong> orang · {{ $d['teams'] }} team · {{ $d['leaders'] }} leader</div>
    </div>
    @endforeach
</div>
@endif

{{-- POSISI ANDA --}}
@if($myTeams->isNotEmpty())
<div class="os-me">
    <span class="os-me-label">Posisi Anda</span>
    @foreach($myTeams as $t)
    <a href="#team-{{ $t['id'] }}" class="os-jump" data-team="{{ $t['id'] }}">
        {{ $t['name'] }} · {{ $t['is_leader'] ? 'Leader' : ($t['position'] ?: 'Anggota') }}@if($t['is_primary']) · Primary @endif
    </a>
    @endforeach
</div>
@endif

{{-- BAGAN ORGANISASI --}}
@if(empty($orgChart))
    <x-empty icon="fas fa-sitemap" title="Belum ada team aktif" message="Tambahkan team untuk membentuk bagan organisasi." />
@else
<div class="os-toolbar">
    <div class="btn-group btn-group-sm" role="group" aria-label="Mode bagan organisasi">
        <button type="button" class="btn btn-outline-secondary active" data-org-mode="leader" aria-pressed="true"><i class="fas fa-user-tie mr-1"></i> Leader View</button>
        <button type="button" class="btn btn-outline-secondary" data-org-mode="member" aria-pressed="false"><i class="fas fa-users mr-1"></i> Detail Member</button>
    </div>
    <div class="os-teampick" data-org-only="member" hidden>
        <select id="osTeam" class="form-control form-control-sm" aria-label="Pilih team">
            @foreach($orgTeams as $t)
            <option value="{{ $t['id'] }}" data-search="{{ $t['code'] }}">{{ $t['name'] }}</option>
            @endforeach
        </select>
    </div>
    <input type="search" id="osSearch" class="form-control form-control-sm" placeholder="Cari nama / jabatan / team" aria-label="Cari di bagan organisasi">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="osExpand" data-org-only="leader"><i class="fas fa-expand-alt mr-1"></i> Bentangkan semua</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="osCollapse" data-org-only="leader"><i class="fas fa-compress-alt mr-1"></i> Ciutkan semua</button>
    <span class="os-zoom" role="group" aria-label="Perbesar / perkecil bagan" data-org-only="leader">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-zoom="-1" title="Perkecil bagan"><i class="fas fa-search-minus"></i></button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-zoom="0" title="Ukuran normal">100%</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-zoom="1" title="Perbesar bagan"><i class="fas fa-search-plus"></i></button>
    </span>
</div>

<p class="os-note" data-org-only="leader">
    <span class="os-legend"><i></i> atasan → bawahan</span>
    Hanya leader yang tampil. Klik nama team di bawah kartu untuk melihat anggotanya (angka = jumlah orang).
    Hanya team ACTIVE &middot; bagan <strong>tidak</strong> memberi akses data.
</p>
<p class="os-note" data-org-only="member" hidden>
    Menampilkan satu team: leader dan anggotanya per level. Hanya team ACTIVE &middot; bagan <strong>tidak</strong> memberi akses data.
</p>

<div class="os-hits" id="osHits" hidden></div>

<div class="oc-viewport" id="ocViewport">
    <div class="oc-canvas" id="ocCanvas">
        <svg class="oc-lines" id="ocLines" aria-hidden="true"></svg>
        @foreach($orgChart as $root)
            @if(! empty($root['orphan']))
                <div class="oc-node">
                    <span class="oc-orphan">Tanpa leader</span>
                    @include('team._org_team_links', ['teams' => $root['teams']])
                    @if($root['children'])
                    <div class="oc-children">
                        @foreach($root['children'] as $anak)
                            @include('team._org_card', ['card' => $anak, 'up' => null])
                        @endforeach
                    </div>
                    @endif
                </div>
            @else
                @include('team._org_card', ['card' => $root, 'up' => null])
            @endif
        @endforeach
    </div>
</div>
<div class="ot-view" id="ocTeamView" hidden></div>
<div id="ocTeamTemplates">
    @foreach($orgTeams as $t)
        @include('team._org_team', ['team' => $t])
    @endforeach
</div>
<div id="osNoResult" hidden class="mt-2">
    <x-empty icon="fas fa-search" title="Tidak ada yang cocok" message="Coba kata kunci lain." compact />
</div>
@endif

@push('scripts')
<script>
$(function () {
    var people = @json((object) $people);
    var canvas = document.getElementById('ocCanvas');
    if (!canvas) return;
    var svg = document.getElementById('ocLines');
    var viewport = document.getElementById('ocViewport');
    var view = document.getElementById('ocTeamView');
    var $canvas = $(canvas);
    var $team = $('#osTeam');
    var skala = 1;
    var mode = 'leader';
    var sudahPas = false;
    var NS = 'http://www.w3.org/2000/svg';

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html().replace(/"/g, '&quot;'); }

    // Posisi elemen relatif terhadap kanvas, dalam satuan kanvas. Rasio diukur
    // dari kanvas supaya mencakup skala bagan DAN zoom CSS pada layout.
    function kotak(el) {
        var c = canvas.getBoundingClientRect(), r = el.getBoundingClientRect();
        var f = c.width / canvas.offsetWidth;
        return {
            x: (r.left - c.left) / f, y: (r.top - c.top) / f,
            w: r.width / f, h: r.height / f
        };
    }
    function terlihat(el) { return el.offsetParent !== null; }
    function garis(d, kelas) {
        var p = document.createElementNS(NS, 'path');
        p.setAttribute('d', d);
        if (kelas) p.setAttribute('class', kelas);
        svg.appendChild(p);
        return p;
    }

    /*
     | Gambar ulang seluruh garis:
     |  - atasan → bawahan: dari bawah kartu induk turun, lalu "bus" mendatar
     |    di atas anak-anaknya, lalu turun ke tiap anak;
     |  - leader yang sama di beberapa tempat: garis putus-putus antar-kartu.
     */
    function gambar() {
        svg.innerHTML = '';
        svg.setAttribute('width', canvas.scrollWidth);
        svg.setAttribute('height', canvas.scrollHeight);

        var grup = {};
        canvas.querySelectorAll('.oc-card[data-up]').forEach(function (el) {
            if (!terlihat(el)) return;
            (grup[el.dataset.up] = grup[el.dataset.up] || []).push(el);
        });

        Object.keys(grup).forEach(function (up) {
            var label = canvas.querySelector('.oc-card[data-key="' + up.slice(5) + '"] .oc-label');
            if (!label || !terlihat(label)) return;
            var a = kotak(label);
            var px = a.x + a.w / 2, py = a.y + a.h;
            var anak = grup[up].map(function (el) { var b = kotak(el.querySelector('.oc-photo')); return { x: b.x + b.w / 2, y: b.y }; });
            var atasAnak = Math.min.apply(null, anak.map(function (k) { return k.y; }));
            var bus = Math.max(py + 8, atasAnak - 14);
            var minX = Math.min.apply(null, anak.map(function (k) { return k.x; }).concat(px));
            var maxX = Math.max.apply(null, anak.map(function (k) { return k.x; }).concat(px));
            var d = 'M' + px + ' ' + py + ' V' + bus + ' M' + minX + ' ' + bus + ' H' + maxX;
            anak.forEach(function (k) { d += ' M' + k.x + ' ' + bus + ' V' + k.y; });
            garis(d);
        });

        // Orang yang sama di beberapa kartu.
        var perUser = {};
        canvas.querySelectorAll('.oc-card').forEach(function (el) {
            if (terlihat(el)) (perUser[el.dataset.user] = perUser[el.dataset.user] || []).push(el);
        });
        Object.keys(perUser).forEach(function (u) {
            var daftar = perUser[u];
            if (daftar.length < 2) return;
            for (var i = 1; i < daftar.length; i++) {
                var a = kotak(daftar[0].querySelector('.oc-photo')), b = kotak(daftar[i].querySelector('.oc-photo'));
                var ax = a.x + a.w / 2, ay = a.y + a.h / 2, bx = b.x + b.w / 2, by = b.y + b.h / 2;
                var my = Math.min(ay, by) - 40;
                var p = garis('M' + ax + ' ' + ay + ' C' + ax + ' ' + my + ', ' + bx + ' ' + my + ', ' + bx + ' ' + by, 'is-dash');
                p.dataset.user = u;
            }
        });
    }

    // Tandai orang yang muncul di beberapa tempat.
    (function tandaiGanda() {
        var hitung = {};
        canvas.querySelectorAll('.oc-card').forEach(function (el) { hitung[el.dataset.user] = (hitung[el.dataset.user] || 0) + 1; });
        canvas.querySelectorAll('.oc-card').forEach(function (el) {
            var n = hitung[el.dataset.user];
            if (n > 1) {
                var t = el.querySelector('.oc-tag.is-dup');
                t.hidden = false;
                t.textContent = 'Di ' + n + ' tempat';
            }
        });
    })();

    // Sorot semua kemunculan orang yang sama saat kartu di-hover/fokus.
    $canvas.on('mouseenter focusin', '.oc-card', function () {
        var u = this.dataset.user;
        $canvas.find('.oc-card[data-user="' + u + '"]').addClass('is-twin');
        $(svg).find('path.is-dash[data-user="' + u + '"]').addClass('is-hot');
    }).on('mouseleave focusout', '.oc-card', function () {
        $canvas.find('.oc-card.is-twin').removeClass('is-twin');
        $(svg).find('path.is-hot').removeClass('is-hot');
    });

    function setOpen(node, open) {
        var kids = node.querySelector(':scope > .oc-children');
        var btn = node.querySelector(':scope > .oc-toggle');
        if (!kids || !btn) return;
        kids.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.innerHTML = open ? '<i class="fas fa-minus" aria-hidden="true"></i>' : '<i class="fas fa-plus" aria-hidden="true"></i>';
    }
    $canvas.on('click', '.oc-toggle', function () {
        setOpen(this.parentNode, this.getAttribute('aria-expanded') !== 'true');
        gambar();
    });
    $('#osExpand').on('click', function () { canvas.querySelectorAll('.oc-node').forEach(function (n) { setOpen(n, true); }); gambar(); });
    $('#osCollapse').on('click', function () {
        // Akar tetap terbuka satu tingkat supaya bagan tidak kosong.
        canvas.querySelectorAll('.oc-children .oc-node').forEach(function (n) { setOpen(n, false); });
        gambar();
    });

    // Perbesar / perkecil (transform pada kanvas; garis ikut karena satu kanvas).
    function setSkala(s) {
        skala = Math.max(0.4, Math.min(1.4, s));
        canvas.style.transform = skala === 1 ? '' : 'scale(' + skala + ')';
        canvas.style.marginBottom = skala < 1 ? (-(1 - skala) * canvas.scrollHeight) + 'px' : '';
        $('[data-zoom="0"]').text(Math.round(skala * 100) + '%');
        gambar();
    }
    $('[data-zoom]').on('click', function () {
        var arah = parseInt($(this).data('zoom'), 10);
        setSkala(arah === 0 ? 1 : skala + arah * 0.1);
    });

    // Tampilan awal: kecilkan sampai seluruh lebar bagan muat, lalu ke tengah.
    function pasKan() {
        if (sudahPas || !terlihat(canvas)) return;
        sudahPas = true;
        setSkala(Math.min(1, Math.floor(viewport.clientWidth / canvas.scrollWidth * 10) / 10));
        viewport.scrollLeft = (viewport.scrollWidth - viewport.clientWidth) / 2;
    }

    function setMode(m) {
        mode = m;
        $('[data-org-mode]').each(function () {
            var aktif = this.dataset.orgMode === m;
            $(this).toggleClass('active', aktif).attr('aria-pressed', aktif ? 'true' : 'false');
        });
        $('[data-org-only]').each(function () { this.hidden = this.dataset.orgOnly !== m; });
        viewport.hidden = m !== 'leader';
        view.hidden = m !== 'member';
        if (m === 'leader') { gambar(); pasKan(); }
    }

    // Detail Member: salin isi team terpilih dari <template>-nya.
    function bukaTeam(id) {
        var tpl = document.getElementById('ocTeamTpl-' + id);
        if (!tpl) return;
        $team.val(String(id)).trigger('tss:sync');
        view.innerHTML = '';
        view.appendChild(tpl.content.cloneNode(true));
        view.querySelectorAll('.oc-card').forEach(function (el) {
            var p = people[el.dataset.user], t = el.querySelector('.oc-tag.is-dup');
            if (p && p.teams.length > 1) {
                t.hidden = false;
                t.textContent = 'Di ' + p.teams.length + ' team';
            }
        });
        setMode('member');
        cari(false);
    }

    function bukaJalur(el) {
        $(el).parents('.oc-node').each(function () { setOpen(this, true); });
    }
    function gulirKe(el) {
        el.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
    }

    /*
     | Pencarian: sorot kartu yang cocok di tampilan aktif. Orang yang tidak
     | tampil di situ (anggota saat Leader View, team lain saat Detail Member)
     | didaftar sebagai tombol yang membuka team-nya.
     */
    function cari(gulir) {
        var q = $('#osSearch').val().trim().toLowerCase();
        var wadah = mode === 'leader' ? canvas : view;
        var cocok = [];
        wadah.querySelectorAll('.oc-card').forEach(function (el) {
            var hit = !!q && String(el.dataset.search).indexOf(q) !== -1;
            el.classList.toggle('is-hit', hit);
            if (hit) cocok.push(el);
        });
        if (mode === 'leader') cocok.forEach(bukaJalur);

        var lain = [];
        if (q) {
            Object.keys(people).forEach(function (u) {
                if (wadah.querySelector('.oc-card[data-user="' + u + '"]')) return;
                people[u].teams.forEach(function (t) {
                    var teks = [people[u].name, t.position, t.name, t.code].join(' ').toLowerCase();
                    if (teks.indexOf(q) !== -1) lain.push({ orang: people[u].name, team: t });
                });
            });
        }
        var html = lain.slice(0, 8).map(function (x) {
            return '<button type="button" class="oc-teamlink" data-team="' + esc(x.team.id) + '">' + esc(x.orang) + ' <span>' + esc(x.team.name) + '</span></button>';
        }).join('');
        $('#osHits').html(lain.length ? 'Ada di team: ' + html + (lain.length > 8 ? ' +' + (lain.length - 8) + ' lainnya' : '') : '').prop('hidden', !lain.length);

        $('#osNoResult').prop('hidden', !q || cocok.length > 0 || lain.length > 0);
        if (mode === 'leader') gambar();
        if (gulir && cocok.length) gulirKe(cocok[0]);
    }
    $('#osSearch').on('input', function () { cari(true); });

    // Ganti mode. Detail Member dibuka di team terakhir, lalu team sendiri.
    var timSaya = @json($myTeams->sortByDesc('is_primary')->pluck('id')->values());
    $('[data-org-mode]').on('click', function () {
        if (this.dataset.orgMode === 'leader') {
            setMode('leader');
            cari(false);
        } else {
            bukaTeam(view.firstChild ? $team.val() : (timSaya[0] || $team.val()));
        }
    });
    if (window.TesysSearchableSelect) {
        TesysSearchableSelect.init('#osTeam', { placeholder: '— Pilih team —', searchPlaceholder: 'Cari team…' });
    }
    $team.on('change', function () { bukaTeam(this.value); });

    // Nama team (kartu leader, navigasi, hasil cari) & "Posisi Anda" → Detail Member.
    $('#paneOrganisasi').on('click', '.oc-teamlink[data-team]', function () { bukaTeam(this.dataset.team); });
    $('.os-jump').on('click', function (e) {
        e.preventDefault();
        bukaTeam($(this).data('team'));
    });

    // Drawer profil organisasi (data dari server, tanpa kontak).
    $canvas.add(view).on('click', '.oc-card', function () {
        var p = people[this.dataset.user];
        if (!p) return;
        var foto = p.avatar
            ? '<span class="os-dr-photo"><img src="' + esc(p.avatar) + '" alt=""></span>'
            : '<span class="os-dr-photo">' + esc((p.name || '?').substr(0, 2).toUpperCase()) + '</span>';
        var html = '<div class="os-dr-head">' + foto +
            '<div><div class="os-dr-name">' + esc(p.name) + '</div>' +
            '<div class="os-dr-sub">' + esc(p.username) + ' · Role ' + esc(p.role) + '</div></div></div>';
        p.teams.forEach(function (t) {
            html += '<div class="os-dr-team">' +
                '<a href="{{ url('/team') }}/' + encodeURIComponent(t.id) + '">' + esc(t.name) + '</a> <span class="os-code">' + esc(t.code) + '</span>' +
                '<div class="os-dr-sub">' + esc(t.department || 'Tanpa department') + ' · ' +
                    esc(t.is_leader ? 'Leader' : (t.position || 'Anggota')) + ' · Level ' + esc(t.level) + '</div>' +
                '<div>' + (t.is_primary ? '<span class="os-badge is-me">Primary</span>' : '') +
                    (t.is_leader ? '<span class="os-badge">Leader</span>' : '') + '</div>' +
            '</div>';
        });
        $('#osPerson-title').text('Profil Organisasi');
        $('#osPerson-eyebrow').text(p.teams.length + ' team aktif');
        $('#osPersonBody').html(html);
        TesysDrawer.open('osPerson', this);
    });

    // Garis digambar saat tab Organisasi terlihat (ukuran 0 saat tersembunyi),
    // setelah foto termuat, dan saat ukuran layar berubah.
    $('#tabOrganisasi').on('shown.bs.tab', function () { gambar(); pasKan(); });
    if (terlihat(canvas)) { gambar(); pasKan(); }
    canvas.querySelectorAll('.oc-photo img').forEach(function (img) { if (!img.complete) img.addEventListener('load', gambar, { once: true }); });
    var tunda;
    $(window).on('resize', function () { clearTimeout(tunda); tunda = setTimeout(gambar, 120); });
});
</script>
@endpush
