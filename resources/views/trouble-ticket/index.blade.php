@extends('layouts.app')

@section('title', 'Trouble Ticket')

@php
    use App\Models\TroubleTicket;
    use Illuminate\Support\Str;

    /*
     | Kolom tabel: key => [label, default tampil?]. Indeks DataTables = urutan di
     | sini; kolom Action (terakhir) selalu tampil dan dibekukan di kanan.
     */
    $kolom = [
        // Tujuh kolom pertama = tampilan bawaan, berurutan seperti di layar
        // (keputusan 16 Okt 2026). Sisanya menyusul di belakang saat dinyalakan.
        'no'         => ['No. TT', true],
        'site'       => ['Site', true],
        'teknisi'    => ['Teknisi', true],
        'aging'      => ['Aging', true],
        'severity'   => ['Severity', true],
        'rca'        => ['RCA / Sub-RCA', true],
        'status'     => ['Status', true],
        'plan'       => ['Date Plan', true],
        'reported'   => ['Date Open', false],
        'customer'   => ['Customer', false],
        'response'   => ['Response', false],
        'travel'     => ['Travel', false],
        'resolution' => ['Resolution', false],
    ];
    $colIndex   = array_flip(array_keys($kolom));
    $colDefault = array_map(fn ($k) => $k[1], $kolom);
    // Nilai SLA untuk sort: detik (berjalan maupun final); belum mulai = -1.
    $slaSort = fn ($m) => $m['seconds'] ?? -1;
@endphp

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid">
        {{-- ============ TAMPILAN HP (< 900px) ============
             Daftar kartu, bukan tabel yang dikecilkan: satu tiket satu kartu,
             pencarian & filter tahap menempel di atas, tombol Buat Tiket
             mengambang di jangkauan jempol. Tidak ada penghitung durasi di
             sini — SLA dibaca di halaman detail. --}}
        <div class="tt-mob">
            <div class="tt-mob-bar">
                <div class="tt-mob-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" id="ttMobSearch" class="form-control form-control-sm"
                           placeholder="Cari No. TT, site, RCA, teknisi…" aria-label="Cari tiket">
                </div>
                <div class="tt-mob-chips" role="tablist" aria-label="Filter tahap">
                    <button type="button" class="tt-chip-f is-on" data-status="">Semua <span>{{ $tickets->count() }}</span></button>
                    @foreach(['OPEN' => 'TT Open', 'REMOTE' => 'Remote', 'PLAN' => 'Plan', 'TRIP' => 'Trip',
                              'IN_PROGRESS' => 'Progress', 'PROGRESS_DONE' => 'Waiting Approve', 'CLOSED' => 'Closed'] as $kode => $label)
                        <button type="button" class="tt-chip-f" data-status="{{ $kode }}">{{ $label }}
                            <span>{{ $tickets->where('status', $kode)->count() }}</span></button>
                    @endforeach
                </div>
            </div>

            <div class="tt-mob-list" id="ttMobList">
                @foreach($tickets as $t)
                    @php $siteM = $t->site?->sitename ?? '-'; @endphp
                    <a href="{{ route('trouble-ticket.show', $t) }}" class="tt-mcard"
                       data-id="{{ $t->id }}"
                       data-status="{{ $t->status }}"
                       data-cari="{{ Str::lower($t->ticket_number.' '.$siteM.' '.($t->site?->tid ?? '').' '.($t->rca_label ?? '').' '.($t->sub_rca_label ?? '').' '.($t->technician?->name ?? '')) }}">
                        <div class="tt-mcard-top">
                            <span class="tt-mcard-no">{{ $t->ticket_number }}</span>
                            <x-status :status="$t->status" context="trouble-ticket" />
                            @if($t->financialState() === \App\Models\TroubleTicket::FINANCE_OPEN)
                                <span class="tt-fin-badge" title="{{ implode(' ', $t->financialBlockers()) }}"><i class="fas fa-coins" aria-hidden="true"></i> Keuangan belum selesai</span>
                            @endif
                        </div>
                        <div class="tt-mcard-site">{{ $siteM }}</div>
                        <div class="tt-mcard-tid">{{ $t->site?->tid ?? '-' }}@if($t->customer?->name) · {{ $t->customer->name }}@endif</div>
                        <div class="tt-mcard-issue">{{ $t->rca_label ?: '-' }}{{ $t->sub_rca_label ? ' / ' . $t->sub_rca_label : '' }}</div>
                        <div class="tt-mcard-foot">
                            <span class="tt-mcard-tech"><i class="fas fa-user-hard-hat" aria-hidden="true"></i> {{ $t->technician?->name ?? 'Belum ada teknisi' }}</span>
                            <span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
                        </div>
                        @if(isset($overdueIds[$t->id]))
                            <span class="tt-mcard-flag" title="Lewat SLA"><i class="fas fa-exclamation-circle"></i> Lewat SLA</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="tt-mob-empty" id="ttMobEmpty" hidden>
                <x-empty title="Tidak ada tiket" message="Ubah kata pencarian atau filter tahap." icon="fas fa-ticket-alt" compact />
            </div>

            @if($canCreate)
                <a href="{{ route('trouble-ticket.create') }}" class="tt-fab" aria-label="Buat tiket">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                </a>
            @endif
        </div>

        {{-- ============ TAMPILAN LAYAR LEBAR ============ --}}
        <div class="tt-desk">

        {{-- Filter tanggal: di atas strip tahap, rata kanan — posisi sama dengan
             Cost Request (dropdown rentang cepat; "Custom…" memunculkan kotak
             rentang (dua kalender berdampingan) dan tombol Terapkan di kirinya).
             Bawaannya tanggal 1 s/d akhir bulan berjalan. Penyaringan di sini
             client-side (DataTables), jadi tidak ada muat ulang halaman. --}}
        <div class="d-flex align-items-center flex-wrap justify-content-end tt-head">
            <div id="ttCustomRange" class="d-none align-items-center flex-wrap" style="gap:6px;">
                <span class="text-muted" style="font-size:12px;">Periode</span>
                <div class="tt-date-range" title="Tanggal dilaporkan">
                    <input type="text" id="ttDateRange" class="form-control form-control-sm tdr-input" readonly>
                    <input type="hidden" id="ttDateFrom" value="{{ now()->startOfMonth()->toDateString() }}">
                    <input type="hidden" id="ttDateTo" value="{{ now()->endOfMonth()->toDateString() }}">
                </div>
                <x-tw.button id="btnTtApplyRange" size="sm"><i class="fas fa-check" aria-hidden="true"></i> Terapkan</x-tw.button>
            </div>
            <select id="ttQuickRange" class="form-control form-control-sm" style="width:auto;" aria-label="Rentang tanggal">
                <option value="">Last Update Time</option>
                <option value="1d">1 hari terakhir</option>
                <option value="3d">3 hari terakhir</option>
                <option value="7d">7 hari terakhir</option>
                <option value="30d">30 hari terakhir</option>
                <option value="1y">1 tahun terakhir</option>
                <option value="custom">Custom…</option>
            </select>
            <x-tw.button id="btnTtRefresh" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
        </div>

        {{-- Strip tahap alur — pola sama dengan Cost Request (<x-workflow-stepper>):
             angka per tahap, dan klik tahap memfilter tabel di bawahnya.
             Closed dibatasi bulan berjalan supaya angkanya tetap berarti. --}}
        <x-workflow-stepper :compact="true" table="tblTt" field="status" :steps="[
            ['Open',          $stats['OPEN'],          'warning',   'OPEN'],
            ['Remote',        $stats['REMOTE'],        'info',      'REMOTE'],
            ['Plan',          $stats['PLAN'],          'warning',   'PLAN'],
            ['Trip',          $stats['TRIP'],          'info',      'TRIP'],
            ['Progress',      $stats['IN_PROGRESS'],   'primary',   'IN_PROGRESS'],
            ['Waiting Approve', $stats['PROGRESS_DONE'], 'success',   'PROGRESS_DONE'],
            ['Closed',        $stats['CLOSED'],        'dark',      'CLOSED'],
        ]" :groups="[['Fase 1: Penanganan Awal', ['OPEN','REMOTE']], ['Fase 2: Kunjungan', ['PLAN','TRIP','IN_PROGRESS']], ['Fase 3: Penutupan', ['PROGRESS_DONE','CLOSED']]]" />

        {{-- Kartu tunggal toolbar + tabel — sama dengan Cost Request (x-tw.card, div murni). --}}
        <x-tw.card padding="sm" class="tw:mt-3">
                @if($terpotong)
                    <div role="status" class="tt-trunc mb-3">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <span>Menampilkan <strong>1.000</strong> tiket terbaru dari total <strong>{{ number_format($total, 0, ',', '.') }}</strong>. Filter &amp; urutan berlaku pada 1.000 tiket ini.</span>
                    </div>
                @endif

                {{-- Toolbar (pola Cost Request: tanpa kotak latar). Kiri: cari · clear · Filter
                     (filter kolom) · Kolom · lewat SLA · keuangan terbuka. Kanan: Export · Import ·
                     Buat Tiket. (Pengaturan pindah ke Master Data > Operation Master > Custom Rules.) --}}
                <div class="tt-toolbar mb-3">
                    <div class="tt-toolbar-left">
                        <div class="tt-search">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="search" id="ttSearch" class="form-control form-control-sm" placeholder="Cari No. TT, site, RCA, alarm..." aria-label="Cari">
                        </div>
                        <x-tw.button id="btnTtClear" variant="ghost" size="sm" title="Clear Filter" aria-label="Clear Filter"><i class="fas fa-eraser" aria-hidden="true"></i></x-tw.button>
                        {{-- TesysColumnFilter merender tombol "Filter" di sini; klik = daftar filter kolom. --}}
                        <div id="ttRowFilters" class="d-flex align-items-center"></div>
                        <div class="dropdown" id="ttColVis">
                            <x-tw.button variant="secondary" size="sm" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-columns" aria-hidden="true"></i> Kolom
                            </x-tw.button>
                            <div class="dropdown-menu p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                                @foreach($kolom as $key => [$label])
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input tt-col-toggle" id="ttCol_{{ $key }}" data-key="{{ $key }}">
                                        <label class="custom-control-label" for="ttCol_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                                    </div>
                                @endforeach
                                <div class="dropdown-divider"></div>
                                <button type="button" class="btn btn-link btn-sm p-0" id="btnTtColReset" style="font-size:12px;">Kembalikan kolom default</button>
                            </div>
                        </div>
                        <div class="custom-control custom-switch ml-1">
                            <input type="checkbox" class="custom-control-input" id="ttOnlyOverdue">
                            <label class="custom-control-label" for="ttOnlyOverdue" style="font-size:12.5px;">Lewat SLA</label>
                        </div>
                        <x-tw.button href="{{ route('trouble-ticket.index', $filterKeuangan ? [] : ['keuangan' => 'open']) }}" :variant="$filterKeuangan ? 'primary' : 'secondary'" size="sm"><i class="fas fa-coins" aria-hidden="true"></i> Keuangan terbuka</x-tw.button>
                    </div>
                    <div class="tt-toolbar-right">
                        <x-tw.button href="{{ route('trouble-ticket.export') }}" variant="secondary" size="sm" id="btnTtExport"><i class="fas fa-file-excel" aria-hidden="true"></i> Export Excel</x-tw.button>
                        @if($canCreate)
                            <x-tw.button variant="secondary" size="sm" data-toggle="modal" data-target="#mdlTtImport"><i class="fas fa-file-import" aria-hidden="true"></i> Import Excel</x-tw.button>
                            <x-tw.button href="{{ route('trouble-ticket.create') }}" size="sm"><i class="fas fa-plus" aria-hidden="true"></i> Buat Tiket</x-tw.button>
                        @endif
                    </div>
                </div>

                <div class="table-responsive tt-dt-wrap">
                    <table id="tblTt" data-ts-card="off" class="table table-bordered mb-0 tt-dt">
                        <thead class="bg-light">
                            <tr>
                                @foreach($kolom as $key => [$label])
                                    <th scope="col" class="{{ $key === 'site' ? 'col-site' : 'col-fit' }}">{{ $label }}</th>
                                @endforeach
                                <th scope="col" class="text-center col-action tt-freeze">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tickets as $t)
                                @php
                                    $siteLabel = $t->site?->sitename ?? '';
                                    $mResp = $t->slaMetric('response');
                                    $mTrav = $t->slaMetric('travel');
                                    $mReso = $t->slaMetric('resolution');
                                    $tglLapor = $t->reported_at ?? $t->created_at;
                                    /* Visit terlewat: tanggal plan sudah lewat sementara tiket masih
                                       menunggu kunjungan (Plan/Trip). Begitu teknisi On Site tiketnya
                                       pindah tahap, jadi tandanya hilang sendiri. */
                                    $visitLewat = $t->visit_site
                                        && in_array($t->status, ['PLAN', 'TRIP'], true)
                                        && $t->visit_site->copy()->startOfDay()->isBefore(today());
                                    /* Budget plan belum diputus supervisor (20 Okt 2026): penanda
                                       sendiri di kolom site, sewarna peringatan ringan supaya tidak
                                       tertukar dengan tanda merah "visit terlewat". */
                                    $budgetMenunggu = $t->plan_budget_status === TroubleTicket::PLAN_BUDGET_PENDING;
                                @endphp
                                <tr data-id="{{ $t->id }}" data-status="{{ $t->status }}"
                                    data-href="{{ route('trouble-ticket.show', $t) }}"
                                    data-severity="{{ $t->severity_label }}"
                                    data-rca="{{ $t->rca_label ?: '-' }}"
                                    data-subrca="{{ $t->sub_rca_label ?: '-' }}"
                                    data-customer="{{ $t->customer?->name ?? '-' }}"
                                    data-site="{{ $siteLabel ?: '-' }}"
                                    data-teknisi="{{ $t->technician?->name ?? '-' }}"
                                    data-reported="{{ $tglLapor->format('Y-m-d') }}"
                                    data-overdue="{{ isset($overdueIds[$t->id]) ? 1 : 0 }}">
                                    <td class="col-fit" data-order="{{ $t->id }}">
                                        {{-- Klik No. TT = salin ke clipboard (.ts-copy-code, tesys-ui.js); klik bagian lain baris = buka detail. --}}
                                        <button type="button" class="ts-copy-code" data-code="{{ $t->ticket_number }}" title="Klik untuk menyalin No. TT">{{ $t->ticket_number }}</button>
                                    </td>
                                    <td class="col-site">
                                        <span class="cell-trunc" style="max-width:60ch;" title="{{ $siteLabel }}">{{ Str::limit($siteLabel, 60, '...') ?: '-' }}</span>
                                        @if($visitLewat)<i class="fas fa-exclamation-circle text-danger ml-1" title="Tanggal visit terlewat"></i>@endif
                                        @if($budgetMenunggu)<i class="fas fa-exclamation-circle text-warning ml-1" title="Budget plan belum disetujui supervisor"></i>@endif
                                    </td>
                                    <td class="col-fit">{{ $t->technician?->name ?? '-' }}</td>
                                    <td class="col-fit" data-order="{{ $t->aging_days }}">{{ $t->aging_days }} hari</td>
                                    <td class="col-fit" data-order="{{ 9 - $t->severity_level }}"><span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span></td>
                                    <td class="col-fit" data-order="{{ $t->rca_label }} {{ $t->sub_rca_label }}">
                                        <span class="tt-rca-main">{{ $t->rca_label ?: '-' }}</span>
                                        <span class="tt-rca-sub">{{ $t->sub_rca_label ?: '-' }}</span>
                                    </td>
                                    <td class="col-fit" data-order="{{ array_search($t->status, TroubleTicket::STATUSES, true) }}">
                                        <x-status :status="$t->status" context="trouble-ticket" />
                                        @if($t->financialState() === \App\Models\TroubleTicket::FINANCE_OPEN)
                                            <span class="tt-fin-badge" title="{{ implode(' ', $t->financialBlockers()) }}"><i class="fas fa-coins" aria-hidden="true"></i> Keuangan belum selesai</span>
                                        @endif
                                        @if(isset($overdueIds[$t->id]))<i class="fas fa-exclamation-circle text-danger ml-1" title="Lewat SLA"></i>@endif
                                    </td>
                                    {{-- Plan = tanggal kunjungan saja; tiket tanpa plan (termasuk Non-Visit) kosong. --}}
                                    <td class="col-fit {{ $visitLewat ? 'text-danger font-weight-bold' : '' }}" data-order="{{ $t->visit_site?->format('Y-m-d') ?? '' }}">{{ $t->visit_site?->format('d M Y') ?? '-' }}</td>
                                    <td class="col-fit" data-order="{{ $tglLapor->timestamp }}">{{ $tglLapor->format('d M Y') }}</td>
                                    <td class="col-fit">{{ $t->customer?->name ?? '-' }}</td>
                                    <td class="col-fit" data-order="{{ $slaSort($mResp) }}">@include('trouble-ticket._sla', ['m' => $mResp, 'compact' => true])</td>
                                    <td class="col-fit" data-order="{{ $slaSort($mTrav) }}">@include('trouble-ticket._sla', ['m' => $mTrav, 'compact' => true])</td>
                                    <td class="col-fit" data-order="{{ $slaSort($mReso) }}">@include('trouble-ticket._sla', ['m' => $mReso, 'compact' => true])</td>
                                    <td class="text-center action-column tt-freeze">
                                        {{-- Tanpa tombol Detail: membuka tiket cukup dengan klik baris. --}}
                                        <div class="action-buttons ts-actions">
                                            <button type="button" class="ts-action-primary is-neutral btn-tt-info" data-url="{{ route('trouble-ticket.info', $t) }}" data-no="{{ $t->ticket_number }}" data-open="{{ route('trouble-ticket.show', $t) }}" title="Info" aria-label="Info">
                                                <i class="fas fa-info" aria-hidden="true"></i><span class="ts-al">Info</span>
                                            </button>
                                            {{-- Close langsung dari daftar, hanya untuk tiket Progress Done
                                                 dan pemegang hak approve yang bukan teknisinya sendiri.
                                                 Evidence-nya dibaca di halaman detail. --}}
                                            @if(($canApprove || in_array((int) $t->assigned_to, $bawahanIds, true)) && $t->status === 'PROGRESS_DONE' && (int) $t->assigned_to !== $userId)
                                                <form method="POST" action="{{ route('trouble-ticket.close.approve', $t) }}" class="d-inline"
                                                      data-confirm="Close tiket {{ $t->ticket_number }}? Pastikan evidence sudah diperiksa di halaman detail.">
                                                    @csrf
                                                    <button type="submit" class="ts-action-primary" title="Close tiket" aria-label="Close tiket">
                                                        <i class="fas fa-check" aria-hidden="true"></i><span class="ts-al">Close</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        </x-tw.card>

        </div>{{-- /.tt-desk --}}
    </div>
</div>

{{-- Modal info tiket (tidak membuka tiket) --}}
<div class="modal fade" id="mdlTtInfo" tabindex="-1" role="dialog" aria-labelledby="mdlTtInfoTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="mdlTtInfoTitle"><i class="fas fa-ticket-alt mr-1"></i> <span>Trouble Ticket</span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="mdlTtInfoBody" style="font-size:13px;"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                <a href="#" class="btn btn-primary btn-sm" id="mdlTtInfoOpen"><i class="fas fa-eye mr-1"></i> Buka Tiket</a>
            </div>
        </div>
    </div>
</div>
@if($canCreate)
    @include('trouble-ticket._import')
@endif
@endsection

@push('styles')
{{-- Komponen x-tw (kartu & tombol toolbar) butuh tesys-tw.css, sama dengan Cost Request. --}}
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
.tt-page .ts-kpi-row { margin-top:16px; }
/* Baris filter tanggal di atas strip tahap (posisi sama dengan Cost Request). */
.tt-head { gap:8px; padding-top:16px; margin-bottom:12px; }
/* Semua kolom selebar isinya, kecuali Site (sisa ruang, dipotong 60 karakter).
   Tipografi & jarak sel ikut token tabel bersama (refine.css §8), sama dengan Cost Request. */
.tt-dt { width:100% !important; }
.tt-dt th, .tt-dt td { vertical-align:middle; }
.tt-dt .col-fit { white-space:nowrap; width:1%; }
.tt-dt .col-site { white-space:nowrap; }
.tt-dt .tt-rca-main { display:block; }
.tt-dt .tt-rca-sub { display:block; font-size:11.5px; color:var(--text-muted); }
.tt-dt .cell-trunc { display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:bottom; }
/* Baris = tautan ke detail (pola Cash Receipt/Invoice). No. TT memakai .ts-copy-code global. */
#tblTt tbody tr[data-href] { cursor:pointer; }
/* Kolom Action dibekukan di kanan saat tabel digulir mendatar. */
.tt-dt .tt-freeze { position:sticky; right:0; z-index:2; background:var(--card-bg); box-shadow:-1px 0 0 var(--border); width:1%; white-space:nowrap; }
.tt-dt thead .tt-freeze { background:var(--surface-2); z-index:3; }
.tt-dt tbody tr:hover .tt-freeze { background:var(--surface-2); }

/* Toolbar: pola Cost Request (.cr-toolbar, refine.css §14c) — tanpa kotak latar,
   kiri pencarian & filter, kanan Export/Import/Buat Tiket. */
.tt-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
.tt-toolbar-left, .tt-toolbar-right { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
.tt-toolbar-right { margin-left:auto; }
.tt-search { position:relative; width:260px; max-width:100%; }
.tt-search > i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--text-faint); font-size:11px; pointer-events:none; z-index:2; }
/* Tinggi seragam 32px untuk input cari dan tombol bawaan TesysColumnFilter (.btn),
   sama dengan toolbar Cost Request. */
body.tesys-ui .tt-toolbar .form-control,
body.tesys-ui .tt-toolbar .btn {
    height:32px; min-height:32px; font-size:12.5px !important; font-weight:500 !important;
    padding:0 12px !important; border-radius:var(--radius-sm); box-shadow:none;
}
body.tesys-ui .tt-toolbar .tt-search input.form-control { padding-left:32px !important; background:var(--surface); }
body.tesys-ui .tt-toolbar .btn i { font-size:11.5px; }
body.tesys-ui .tt-toolbar .btn-outline-secondary { background:var(--surface); border-color:var(--border-strong); color:var(--text-dim); }
body.tesys-ui .tt-toolbar .btn-outline-secondary:hover,
body.tesys-ui .tt-toolbar .btn-outline-secondary:focus { background:var(--surface-2); border-color:var(--text-faint); color:var(--text); }
.tt-toolbar .custom-switch .custom-control-label { font-size:12.5px; }
/* Pemberitahuan daftar terpotong: kotak peringatan ringan, sama dengan Cost Request. */
.tt-trunc { display:flex; align-items:flex-start; gap:8px; padding:8px 12px; border:1px solid var(--border);
    border-radius:var(--radius-md); background:var(--warning-bg); color:var(--warning); font-size:12.5px; }
.tt-trunc i { margin-top:3px; }
.tt-date-range { display:flex; align-items:center; gap:6px; }
.tt-date-range .form-control { width:118px; }
/* Kotak rentang bersama (.tdr-input) punya lebarnya sendiri. */
.tt-date-range .form-control.tdr-input { width:210px; }
@media (max-width: 767.98px) {
    .tt-toolbar-right { margin-left:0; }
    .tt-search { width:100%; }
}

/* =====================================================================
   TAMPILAN HP — daftar kartu (< 900px)

   Di lebar ini tabel 17 kolom tidak pernah terbaca tanpa gulir mendatar,
   jadi daftarnya diganti kartu: satu tiket satu kartu, seluruh kartu jadi
   tautan ke detail. Tampilan layar lebar tidak disentuh — keduanya hidup
   berdampingan lewat .tt-mob / .tt-desk.
   ===================================================================== */
.tt-mob { display:none; }
@media (max-width: 900px) {
    .tt-desk { display:none; }
    .tt-mob { display:block; padding-bottom:84px; }

    /* Pencarian + filter tahap menempel di atas saat daftar digulir. */
    /* Latar dibuat melebar sampai tepi layar (kontainer punya padding 15px),
       supaya kartu yang lewat di bawahnya tidak mengintip di sisi kiri/kanan. */
    /* Ikut tergulir bersama daftar. Versi menempel menutupi kartu paling atas
       saat halaman digulir — dan di layar sempit chip-nya memakan ruang baca. */
    .tt-mob-bar { margin:0 -15px; padding:10px 15px 8px; border-bottom:1px solid var(--border); }
    .tt-mob-search { position:relative; }
    .tt-mob-search > i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-faint); font-size:13px; }
    /* padding-left !important: padding .form-control di lapis tema (!important) menimpa ruang ikon. */
    .tt-mob-search input.form-control { height:40px; padding-left:34px !important; border-radius:999px; font-size:13px; }

    /* Chip tahap: TURUN BARIS, tidak digulir mendatar. Versi bergulir
       menyisakan batang scroll yang digambar di atas chip (dan chip terakhir
       terpotong di tepi kanan) di browser HP; dengan membungkus, seluruh
       tahap terbaca sekaligus dan tidak ada yang tertimpa. */
    .tt-mob-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .tt-chip-f { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px;
        border:1px solid var(--border); background:var(--surface); color:var(--text-dim); font-size:12px; line-height:1.2; }
    .tt-chip-f span { font-weight:700; color:var(--text-faint); }
    .tt-chip-f.is-on { background:var(--charcoal, #1c2128); border-color:transparent; color:#fff; }
    .tt-chip-f.is-on span { color:rgba(255,255,255,.75); }

    .tt-mob-list { display:flex; flex-direction:column; gap:8px; margin-top:10px; }
    .tt-mcard { position:relative; display:block; padding:12px 14px; border:1px solid var(--border); border-radius:var(--radius-md);
        background:var(--surface); color:var(--text); text-decoration:none; }
    .tt-mcard:active { background:var(--surface-2); }
    .tt-mcard:hover, .tt-mcard:focus { text-decoration:none; color:var(--text); }
    .tt-mcard-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .tt-mcard-no { font-weight:700; font-size:13px; }
    .tt-mcard-site { margin-top:6px; font-size:14px; font-weight:600; line-height:1.3; }
    .tt-mcard-tid { font-size:11.5px; color:var(--text-muted); }
    .tt-mcard-issue { margin-top:6px; font-size:12.5px; color:var(--text-dim); }
    .tt-mcard-foot { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:10px;
        padding-top:8px; border-top:1px dashed var(--border); font-size:12px; color:var(--text-muted); }
    .tt-mcard-tech { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .tt-mcard-flag { display:inline-block; margin-top:8px; font-size:11px; color:var(--danger); }

    /* Tombol utama di jangkauan jempol. */
    .tt-fab { position:fixed; right:16px; bottom:18px; z-index:1030; display:flex; align-items:center; justify-content:center;
        width:54px; height:54px; border-radius:50%; background:var(--danger); color:#fff; font-size:20px; box-shadow:var(--shadow-lg); }
    .tt-fab:hover, .tt-fab:focus { color:#fff; text-decoration:none; }
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    /* ---------- Daftar kartu HP: cari + filter tahap ----------
       Berjalan di atas kartu yang sudah dirender server (baris yang sama
       dengan tabel), jadi tidak ada permintaan tambahan. */
    (function () {
        var $kartu = $('#ttMobList .tt-mcard');
        if (!$kartu.length && !$('#ttMobList').length) return;

        var kata = '', tahap = '';

        function terap() {
            var tampil = 0;
            $kartu.each(function () {
                var $k = $(this);
                var cocok = (!tahap || $k.data('status') === tahap)
                    && (!kata || String($k.data('cari') || '').indexOf(kata) !== -1);
                $k.toggle(cocok);
                if (cocok) tampil++;
            });
            $('#ttMobEmpty').prop('hidden', tampil > 0);
        }

        $('#ttMobSearch').on('input', function () {
            kata = $.trim(this.value).toLowerCase();
            terap();
        });

        $('.tt-chip-f').on('click', function () {
            $('.tt-chip-f').removeClass('is-on');
            $(this).addClass('is-on');
            tahap = this.dataset.status || '';
            terap();
        });
    })();

    var colIndex   = @json($colIndex);
    var colDefault = @json($colDefault);
    var colKey     = 'tesys-colvis:trouble-ticket-index';

    var table = $('#tblTt').DataTable({
        responsive: false, autoWidth: false, pageLength: 25, ordering: true,
        lengthMenu: [10, 25, 50, 100],
        order: [[colIndex.reported, 'desc']],
        columnDefs: [{ orderable: false, targets: -1 }],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_', zeroRecords: 'Tidak ada tiket yang cocok dengan filter',
            emptyTable: 'Belum ada Trouble Ticket yang bisa Anda lihat',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ tiket', infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_)',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#ttSearch').on('keyup search', function () { table.search(this.value).draw(); });

    /* Klik di mana saja pada baris membuka detail tiket (pola Cash Receipt/Invoice).
       Kontrol di dalam baris (No. TT yang disalin, tombol Info/Close) bekerja sendiri;
       teks yang sedang diblok tidak memicu navigasi. Ctrl/Cmd+klik = tab baru. */
    $(document).on('click', '#tblTt tbody tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        var href = $(this).attr('data-href');
        if (e.ctrlKey || e.metaKey) { window.open(href, '_blank'); return; }
        window.location.href = href;
    });

    /* ---------- Tiket yang baru dibuat ----------
       Setelah simpan, daftar dibuka APA ADANYA (semua tahap, tanpa filter) dan
       baris tiketnya distabilo merah yang memudar sendiri dalam 2 detik —
       pengganti masuk ke halaman detail. */
    (function () {
        var baru = @json($baruIds ?? []);
        if (!baru.length) return;

        var pilih = baru.map(function (id) { return '[data-id="' + id + '"]'; }).join(',');
        var $baris = $('#tblTt tbody tr').filter(pilih).add($('#ttMobList .tt-mcard').filter(pilih));
        $baris.addClass('tt-row-baru');
        if ($baris.length && $baris[0].scrollIntoView) {
            $baris[0].scrollIntoView({ block: 'center' });
        }
        setTimeout(function () { $baris.removeClass('tt-row-baru'); }, 2000);
    })();

    // Export mengikuti filter aktif (cari, filter kolom, tanggal, lewat SLA).
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnTtExport', table, @json(route('trouble-ticket.export')));
    }

    /* ---------- Pilih kolom (disimpan per browser) ---------- */
    function readPref() { try { return JSON.parse(localStorage.getItem(colKey) || '{}') || {}; } catch (e) { return {}; } }
    function writePref(p) { try { localStorage.setItem(colKey, JSON.stringify(p)); } catch (e) {} }
    function applyColVis() {
        var pref = readPref();
        Object.keys(colIndex).forEach(function (k) {
            var vis = pref.hasOwnProperty(k) ? !!pref[k] : !!colDefault[k];
            table.column(colIndex[k]).visible(vis, false);
            $('#ttCol_' + k).prop('checked', vis);
        });
        table.columns.adjust().draw(false);
    }
    applyColVis();
    $('.tt-col-toggle').on('change', function () {
        var pref = readPref(); pref[$(this).data('key')] = this.checked; writePref(pref); applyColVis();
    });
    $('#btnTtColReset').on('click', function () { writePref({}); applyColVis(); });
    $('#ttColVis .dropdown-menu').on('click', function (e) { e.stopPropagation(); });

    /* ---------- Filter kolom: tombol "Filter" milik TesysColumnFilter ---------- */
    TesysColumnFilter.initRowFilters('#tblTt', '#ttRowFilters', [
        { key: 'status', label: 'Status' }, { key: 'severity', label: 'Severity' },
        { key: 'rca', label: 'RCA' }, { key: 'subrca', label: 'Sub-RCA' },
        { key: 'customer', label: 'Customer' },
        { key: 'site', label: 'Site' }, { key: 'teknisi', label: 'Teknisi' }
    ]);

    /* ---------- Rentang tanggal dilaporkan (default bulan berjalan) & lewat SLA ---------- */
    var awalBulan = @json(now()->startOfMonth()->toDateString());
    var akhirBulan = @json(now()->endOfMonth()->toDateString());
    var dari = awalBulan, sampai = akhirBulan;
    $.fn.dataTable.ext.search.push(function (settings, data, idx) {
        if (settings.nTable.id !== 'tblTt') return true;
        var tr = settings.aoData[idx].nTr;
        if (!tr) return true;
        var tgl = tr.getAttribute('data-reported');
        if (dari && tgl < dari) return false;
        if (sampai && tgl > sampai) return false;
        if ($('#ttOnlyOverdue').is(':checked') && tr.getAttribute('data-overdue') !== '1') return false;
        return true;
    });
    /* Komponen bersama TesysDateRange (public/js/tesys-date-range.js) — pola
       yang sama dengan Cost Request. Tanpa flatpickr komponennya mengembalikan
       null; filter turun ke dua input teks manual (YYYY-MM-DD). */
    var ttPeriode = (typeof TesysDateRange === 'object')
        ? TesysDateRange.init({
            input: '#ttDateRange', from: '#ttDateFrom', to: '#ttDateTo',
            onApply: function (a, b) { dari = a; sampai = b; table.draw(); },
        })
        : null;

    if (!ttPeriode) {
        $('#ttDateRange').remove();
        $('#ttDateFrom, #ttDateTo').attr('type', 'text')
            .addClass('form-control form-control-sm')
            .attr('placeholder', 'YYYY-MM-DD')
            .on('change', function () {
                dari = $('#ttDateFrom').val(); sampai = $('#ttDateTo').val(); table.draw();
            });
    }

    /* ---------- Rentang cepat ----------
       Pilihan hari/tahun menghitung mundur dari hari ini; "Custom…" membuka
       kotak rentang. Nilainya tetap ditulis ke input tersembunyi supaya satu
       jalur dengan pilihan manual. */
    function ttFmt(d) {
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    }
    function ttSetRentang(a, b) {
        dari = a; sampai = b;
        if (ttPeriode) { ttPeriode.setRange(a, b); }
        else { $('#ttDateFrom').val(a); $('#ttDateTo').val(b); }
        table.draw();
    }
    var TT_HARI = { '1d': 1, '3d': 3, '7d': 7, '30d': 30, '1y': 365 };

    $('#ttQuickRange').on('change', function () {
        var pilih = $(this).val();
        $('#ttCustomRange').toggleClass('d-none', pilih !== 'custom').toggleClass('d-flex', pilih === 'custom');

        if (pilih === 'custom') { if (ttPeriode) { ttPeriode.open(); } return; }
        if (!pilih) { ttSetRentang(awalBulan, akhirBulan); return; }

        var kini = new Date();
        var mulai = new Date(kini.getFullYear(), kini.getMonth(), kini.getDate() - TT_HARI[pilih]);
        ttSetRentang(ttFmt(mulai), ttFmt(kini));
    });

    $('#btnTtApplyRange').on('click', function () {
        dari = $('#ttDateFrom').val(); sampai = $('#ttDateTo').val();
        table.draw();
        if (ttPeriode) { ttPeriode.close(); }
    });

    $('#btnTtRefresh').on('click', function () { window.location.reload(); });
    $('#ttOnlyOverdue').on('change', function () { table.draw(); });
    table.draw();

    // Clear: kosongkan pencarian & filter kolom; tanggal kembali ke bulan berjalan.
    $('#btnTtClear').on('click', function () {
        TesysColumnFilter.clearAll('#tblTt');
        dari = awalBulan; sampai = akhirBulan;
        if (ttPeriode) { ttPeriode.setRange(awalBulan, akhirBulan); }
        else { $('#ttDateFrom').val(awalBulan); $('#ttDateTo').val(akhirBulan); }
        $('#ttQuickRange').val('');
        $('#ttCustomRange').addClass('d-none').removeClass('d-flex');
        $('#ttOnlyOverdue').prop('checked', false);
        $('#ttSearch').val('');
        table.search('').draw();
    });

    /* ---------- Modal info ---------- */
    $(document).on('click', '.btn-tt-info', function () {
        var $b = $(this);
        $('#mdlTtInfoTitle span').text($b.data('no'));
        $('#mdlTtInfoOpen').attr('href', $b.data('open'));
        $('#mdlTtInfoBody').html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</div>');
        $('#mdlTtInfo').modal('show');
        $.get($b.data('url'))
            .done(function (html) { $('#mdlTtInfoBody').html(html); })
            .fail(function () { $('#mdlTtInfoBody').html('<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Data tidak dapat dimuat.</p>'); });
    });

});
</script>
@endpush
