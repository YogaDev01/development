@extends('layouts.app')

@section('title', $ticket->ticket_number)

@php
    use App\Models\TroubleTicket;
    use App\Models\TroubleTicketAttachment;

    $t = $ticket;
    $site = $t->site;
    $fmt = fn ($dt) => $dt ? $dt->format('d M Y H:i') : '—';
    // Tab Tiket memakai tanggal saja — jam dibaca di tab Log & SLA.
    $tgl = fn ($dt) => $dt ? $dt->format('d M Y') : '—';
    $dur = fn ($s) => TroubleTicket::formatDuration($s);
    // Batas tanggal plan visit untuk kalender (aturannya di TroubleTicket::planMinDate()):
    // hari ini untuk plan pertama, hari plan dibuat saat tanggalnya diubah.
    $minVisit = $t->planMinDate()->toDateString();
    // Tanpa target (limit 0 di Pengaturan) = tidak terhingga.
    $targetLabel = fn ($menit) => $menit ? $dur($menit * 60) : 'Tidak terhingga';

    // Teknisi tiket ini ($isTechnician dari controller): SLA dan jeda antar
    // kejadian disembunyikan darinya (keputusan 15 Okt 2026) — ukuran waktu
    // dipakai supervisor, bukan pelaksana.
    // Tombol teknisi — cermin gerbang di TroubleTicketWorkflow (server tetap memeriksa ulang).
    $diTrip        = $canActAsTech && $t->status === 'TRIP';
    // Ubah Tanggal di tahap TRIP dicabut (keputusan 20 Okt 2026): begitu
    // tiket masuk Trip, jadwal visit tidak diubah lagi — dibatalkan lewat
    // Cancel Visit yang sekaligus menjadwalkan ulang.
    $bisaPlan      = $canCreatePlan && in_array($t->status, ['REMOTE', 'PLAN'], true) && ! $t->travel_arrived_at;
    // "Swipe for travel" sekaligus yang memindahkan tiket Plan ke tahap Trip
    // (keputusan 20 Okt 2026): tersedia di tahap Plan begitu tanggal visit
    // tiba, dan di tahap Trip untuk tiket lama yang belum berangkat.
    $bisaBerangkat = $canActAsTech && ! $t->travel_started_at && ! $t->travel_arrived_at
        && ($t->status === 'TRIP' || ($t->status === 'PLAN' && $tripDue));
    $dalamJalan    = $diTrip && $t->travel_started_at && ! $t->travel_arrived_at;
    $bisaMulai     = $diTrip && $t->travel_arrived_at;
    // Form close terbuka untuk SEMUA peran yang boleh melihat tiket (20 Okt
    // 2026); gerbangnya di TroubleTicketWorkflow::canClose() — tahap Trip
    // tertutup bagi teknisi tiket itu, dan sesudah On Site tertutup bagi semua.
    $bisaTutup     = $canClose;
    // Change Teknisi: sejak plan dibuat sampai sebelum swipe "mulai perjalanan"
    // ($canReassign dari controller — TroubleTicketWorkflow::canReassign()).
    $bisaAlihkan   = $canReassign;
    $acceptFiles = collect(TroubleTicketAttachment::EXTENSIONS)->map(fn ($e) => '.' . $e)->implode(',');
@endphp

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid tt-detail" id="ttDetail">

        {{-- Kepala: kembali · site · status · severity (No. TT ada di judul topbar) --}}
        <div class="d-flex align-items-center mb-2 mt-2 tt-head" style="gap:8px;min-width:0;">
            <a href="{{ route('trouble-ticket.index') }}" class="btn btn-outline-secondary btn-xs" title="Kembali ke daftar"><i class="fas fa-arrow-left"></i></a>
            <div class="tt-title tt-head-site" title="{{ $site?->tid }} — {{ $site?->sitename }}">{{ $site?->tid }} — {{ $site?->sitename }}</div>
            <x-status :status="$t->status" context="trouble-ticket" size="md" />
        </div>
        @php $actionVars = compact('t', 'canActAsTech', 'isTechnician', 'canCreatePlan', 'bisaPlan', 'bisaBerangkat', 'dalamJalan', 'bisaMulai', 'bisaTutup', 'canApproveClose', 'bisaAlihkan', 'canApprovePlanBudget', 'canSettle'); @endphp

        {{-- Desktop: chat kiri + satu kartu informasi bertab di kanan.
             Mobile (< 992px): satu panel tampil bergantian lewat bilah bawah
             Chat | Info | Action (lihat .tt-mbar). --}}
        @php
            // Di HP halaman detail selalu dibuka pada panel Info: ringkasan tiket
            // sekaligus tombol tahapnya ada di sana.
            $paneAwal = 'info';
        @endphp
        <div class="row tt-panes" id="ttPanes" data-pane="{{ $paneAwal }}">
            {{-- KIRI: timeline / chat; kepala kartu = tombol aksi (desktop) --}}
            <div class="col-lg-7 tt-pane tt-pane-chat">
                <div class="card shadow-sm mb-0 tt-fill">
                    <div class="card-header d-none d-lg-flex justify-content-between align-items-center py-1 tt-card-head">
                        <span class="tt-card-ticket">{{ $t->ticket_number }}</span>
                        <div class="tt-actions d-flex flex-wrap justify-content-end" style="gap:6px;">
                            @include('trouble-ticket._actions', $actionVars)
                        </div>
                    </div>
                    <div class="tt-chat" id="ttChat">
                        <div class="tt-drop" id="ttDrop" hidden><div><i class="fas fa-cloud-upload-alt"></i><span>Lepas berkas di sini</span></div></div>
                        <div class="tt-chat-list" id="ttChatList">
                            <div class="text-center mb-2" id="ttOlderWrap" @if(! $hasOlder) style="display:none;" @endif>
                                <button type="button" class="btn btn-outline-secondary btn-xs" id="btnTtOlder"><i class="fas fa-history mr-1"></i> Muat yang lebih lama</button>
                            </div>
                            <div id="ttChatItems">
                                @include('trouble-ticket._messages', ['messages' => $messages, 'ctx' => $ctx])
                            </div>
                            @if($messages->isEmpty())
                                <x-empty title="Belum ada aktivitas" icon="fas fa-comments" compact />
                            @endif
                        </div>
                        <div class="alert alert-warning m-0 py-1 px-3 rounded-0" id="ttStaleNotice" style="display:none;font-size:12px;">
                            <i class="fas fa-sync-alt mr-1"></i> Status tiket berubah. <a href="{{ request()->url() }}">Muat ulang</a> untuk melihat aksi terbaru.
                        </div>
                        @if($canMessage)
                            <form class="tt-chat-form" id="frmTtMessage" action="{{ route('trouble-ticket.messages.store', $t) }}" enctype="multipart/form-data">
                                {{-- Berkas yang akan dikirim (dari tombol, tempel, atau seret) — masing-masing bisa dibatalkan. --}}
                                <div class="tt-pending" id="ttPending" hidden></div>
                                <div class="tt-composer">
                                    <label class="tt-icon-btn mb-0" title="Lampirkan berkas (maks. 10 MB per berkas) — bisa juga tempel (Ctrl+V) atau seret ke chat">
                                        <i class="fas fa-paperclip"></i>
                                        <input type="file" id="ttFiles" multiple hidden accept="{{ $acceptFiles }}">
                                    </label>
                                    <textarea name="body" id="ttBody" class="tt-input" maxlength="5000" rows="1" placeholder="Tulis pesan…  (Enter kirim · Shift+Enter baris baru)"></textarea>
                                    <button type="submit" class="btn btn-primary tt-send" id="btnTtSend" title="Kirim"><i class="fas fa-paper-plane"></i></button>
                                </div>
                                <div class="text-danger mt-1" id="ttMsgError" style="font-size:12px;display:none;"></div>
                            </form>
                        @else
                            <div class="tt-chat-form text-muted text-center" style="font-size:12px;">
                                <i class="fas fa-lock mr-1"></i> Anda tidak dapat mengirim pesan pada tiket ini.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- KANAN: satu kartu, 5 tab --}}
            <div class="col-lg-5 tt-pane tt-pane-info">
                <div class="card shadow-sm tt-info-card mb-0 tt-fill">
                    @php
                        // Nomor HP → tautan WhatsApp (08xx → 628xx). Hanya angka yang dipakai.
                        $wa = function (?string $hp) {
                            $d = preg_replace('/\D/', '', (string) $hp);
                            if ($d === '') return null;
                            if (str_starts_with($d, '0')) $d = '62' . substr($d, 1);
                            return 'https://wa.me/' . $d;
                        };
                        // Mode arah (bukan sekadar pin): origin sengaja dikosongkan supaya
                        // Google Maps memakai posisi terkini teknisi, lalu menampilkan rute,
                        // jarak tempuh lewat jalan, dan estimasi waktu ke site.
                        $mapUrl = ($site?->latitude !== null && $site?->longitude !== null)
                            ? 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' . rawurlencode($site->latitude . ',' . $site->longitude)
                            : null;
                        $slaRows = [
                            'response'   => ['Response', 'Tiket dibuat → form close dikirim', $t->sla_response_minutes, $t->created_at, $t->acknowledged_at],
                            // Sama dengan TroubleTicket::slaMetric('travel'): mulai perjalanan → on site.
                            'travel'     => ['Travel', 'Mulai perjalanan → on site', $t->sla_travel_minutes, $t->travel_started_at, $t->travel_arrived_at],
                            'resolution' => ['Resolution', 'Mulai progres → approve supervisor', $t->sla_resolution_minutes, $t->progress_started_at, $t->closed_at],
                        ];
                        $nonVisit = $t->visit_decision === TroubleTicket::NON_VISIT;
                    @endphp
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs nav-fill tt-tabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#ttTabSummary" role="tab"><i class="fas fa-th-large"></i><span>Summary</span></a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttTabSite" role="tab"><i class="fas fa-broadcast-tower"></i><span>Site</span></a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttTabTiket" role="tab"><i class="fas fa-ticket-alt"></i><span>Tiket</span></a></li>
                            @unless($isTechnician)
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttTabSla" role="tab"><i class="fas fa-stopwatch"></i><span>SLA</span></a></li>
                            @endunless
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttTabLog" role="tab"><i class="fas fa-history"></i><span>Log</span><span class="tt-tab-count">{{ $statusLog->count() }}</span></a></li>
                        </ul>
                    </div>
                    <div class="card-body tab-content tt-tab-body">

                        {{-- ================= SUMMARY ================= --}}
                        <div class="tab-pane fade show active" id="ttTabSummary" role="tabpanel">
                            <div class="tt-hero">
                                <div class="tt-hero-main">
                                    <div class="tt-hero-title">{{ $site?->sitename ?? '—' }}</div>
                                    <div class="tt-hero-sub">{{ $site?->tid }} · {{ $site?->customer?->name ?? '—' }}</div>
                                </div>
                                <div class="tt-hero-act">
                                    @if($mapUrl)<a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-xs" title="Rute ke site dari lokasi Anda"><i class="fas fa-map-marked-alt"></i></a>@endif
                                </div>
                            </div>

                            <div class="tt-person">
                                <span class="tt-avatar">{{ mb_strtoupper(mb_substr($t->technician?->name ?? '?', 0, 1)) }}</span>
                                <div class="tt-person-main">
                                    <div class="tt-person-name">{{ $t->technician?->name ?? '—' }}</div>
                                    <div class="tt-person-sub">Teknisi</div>
                                </div>
                                @if($t->technician?->phone)
                                    <a href="tel:{{ $t->technician->phone }}" class="btn btn-outline-secondary btn-xs" title="Telepon"><i class="fas fa-phone"></i></a>
                                    @if($wa($t->technician->phone))<a href="{{ $wa($t->technician->phone) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-xs" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>@endif
                                @endif
                            </div>

                            <div class="tt-chips">
                                <span class="tt-chip"><i class="fas fa-bug"></i>{{ $t->rca_label ?: ($t->problem?->name ?? 'RCA —') }}{{ $t->sub_rca_label ? ' / ' . $t->sub_rca_label : '' }}</span>
                                <span class="tt-chip tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
                                <span class="tt-chip" title="Aging sejak Date Open"><i class="far fa-clock"></i>{{ $t->aging_days }} hari</span>
                                @if($t->device_label)<span class="tt-chip"><i class="fas fa-hdd"></i>{{ $t->device_label }}</span>@endif
                            </div>

                            {{-- Aksi tahap (Remote / Create Plan / perjalanan / form close /
                                 approval) duduk di KAKI panel Summary — satu tempat, dan
                                 paling dekat dengan jempol di layar kecil. --}}
                            @include('trouble-ticket._stage', $actionVars)
                        </div>

                        {{-- ================= SITE ================= --}}
                        <div class="tab-pane fade" id="ttTabSite" role="tabpanel">
                            <dl class="m-0">
                                <div class="tt-kv"><dt>Site ID</dt><dd><strong>{{ $site?->tid }}</strong>
                                    <button type="button" class="tt-copy" data-copy="{{ $site?->tid }}" title="Salin"><i class="far fa-copy"></i></button></dd></div>
                                <div class="tt-kv"><dt>Nama Site</dt><dd>{{ $site?->sitename }}</dd></div>
                                <div class="tt-kv"><dt>Customer</dt><dd>{{ $site?->customer?->name ?? '—' }}</dd></div>
                                <div class="tt-kv"><dt>Alamat</dt><dd>{{ $site?->address ?: '—' }}</dd></div>
                                <div class="tt-kv"><dt>Koordinat</dt><dd>
                                    @if($mapUrl)
                                        {{ $site->latitude }}, {{ $site->longitude }}
                                        <button type="button" class="tt-copy" data-copy="{{ $site->latitude }}, {{ $site->longitude }}" title="Salin"><i class="far fa-copy"></i></button>
                                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="tt-copy" title="Rute ke site dari lokasi Anda"><i class="fas fa-map-marked-alt"></i></a>
                                    @else
                                        —
                                    @endif
                                </dd></div>
                            </dl>
                            <div class="tt-sec-title">PIC Site</div>
                            @forelse($site?->picLinks ?? [] as $link)
                                <div class="tt-person tt-person-sm">
                                    <span class="tt-avatar">{{ mb_strtoupper(mb_substr($link->pic?->pic ?? '?', 0, 1)) }}</span>
                                    <div class="tt-person-main">
                                        <div class="tt-person-name">{{ $link->pic?->pic }}</div>
                                        <div class="tt-person-sub">{{ \App\Models\MasterPic::TYPES[$link->pic?->type] ?? $link->pic?->type }}{{ $link->pic?->phone ? ' · ' . $link->pic->phone : '' }}</div>
                                    </div>
                                    @if($link->pic?->phone)
                                        <a href="tel:{{ $link->pic->phone }}" class="btn btn-outline-secondary btn-xs" title="Telepon"><i class="fas fa-phone"></i></a>
                                        @if($wa($link->pic->phone))<a href="{{ $wa($link->pic->phone) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-xs" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>@endif
                                    @endif
                                </div>
                            @empty
                                <x-empty title="Belum ada PIC" icon="fas fa-user-tag" compact />
                            @endforelse
                        </div>

                        {{-- ================= TIKET ================= --}}
                        <div class="tab-pane fade" id="ttTabTiket" role="tabpanel">
                            <details class="tt-acc" open>
                                <summary>Identitas</summary>
                                <dl class="m-0">
                                    <div class="tt-kv"><dt>No. TT</dt><dd><strong>{{ $t->ticket_number }}</strong>
                                        <button type="button" class="tt-copy" data-copy="{{ $t->ticket_number }}" title="Salin"><i class="far fa-copy"></i></button></dd></div>
                                    <div class="tt-kv"><dt>Date Open</dt><dd>{{ $tgl($t->reported_at) }}</dd></div>
                                    <div class="tt-kv"><dt>Dibuat oleh</dt><dd>{{ $t->creator?->name ?: '—' }}</dd></div>
                                    @if($t->visit_decision === TroubleTicket::VISIT)
                                        <div class="tt-kv"><dt>Plan visit</dt><dd>{{ $tgl($t->visit_site) }}</dd></div>
                                    @endif
                                </dl>
                            </details>
                            <details class="tt-acc" open>
                                <summary>Gangguan</summary>
                                <dl class="m-0">
                                    <div class="tt-kv"><dt>Aging</dt><dd>{{ $t->aging_days }} hari</dd></div>
                                    <div class="tt-kv"><dt>Severity</dt><dd><span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span></dd></div>
                                    {{-- Problem diwakili RCA & Sub-RCA. Begitu tiket ditutup keduanya
                                         dibaca di Resolution, jadi tidak ditampilkan dua kali. --}}
                                    @unless($t->closed_at)
                                        <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca_label ?: '—' }}</dd></div>
                                        <div class="tt-kv"><dt>Sub-RCA</dt><dd>{{ $t->sub_rca_label ?: '—' }}</dd></div>
                                    @endunless
                                    <div class="tt-kv"><dt>Keterangan</dt><dd style="white-space:pre-wrap;">{{ $t->description }}</dd></div>
                                </dl>
                            </details>
                            <details class="tt-acc" @if($t->closed_at) open @endif>
                                <summary>Resolution</summary>
                                <dl class="m-0">
                                    {{-- Hanya hasil akhir; jejak waktu tiap tahap dibaca di tab Log. --}}
                                    <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Sub-RCA</dt><dd>{{ $t->sub_rca ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Keterangan</dt><dd style="white-space:pre-wrap;">{{ $t->resolution_summary ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Close by</dt><dd>{{ $t->closer?->name ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Date closed</dt><dd>{{ $tgl($t->closed_at) }}</dd></div>
                                </dl>
                            </details>
                        </div>

                        {{-- ================= SLA — tidak untuk teknisi tiket ini ================= --}}
                        @unless($isTechnician)
                        <div class="tab-pane fade" id="ttTabSla" role="tabpanel">
                            {{-- Tanpa target & bar (keputusan 12 Okt 2026): yang ditampilkan
                                 hanya durasinya dan rentang waktunya. --}}
                            @foreach($slaRows as $key => [$label, $rumus, $target, $mulai, $selesai])
                                @php $m = $t->slaMetric($key); @endphp
                                <div class="tt-slab">
                                    <div class="tt-slab-head">
                                        <strong>{{ $label }} time</strong>
                                        @if(in_array($key, ['travel', 'resolution'], true) && $nonVisit)
                                            <span class="text-muted">Tidak berlaku (Non-Visit)</span>
                                        @else
                                            @include('trouble-ticket._sla', ['m' => $m])
                                        @endif
                                    </div>
                                    @unless(in_array($key, ['travel', 'resolution'], true) && $nonVisit)
                                        <div class="tt-slab-meta">
                                            <span>{{ $mulai ? $mulai->format('d/m H:i') : '—' }} → {{ $selesai ? $selesai->format('d/m H:i') : ($m['state'] === 'running' ? 'berjalan' : '—') }}</span>
                                        </div>
                                    @endunless
                                </div>
                            @endforeach
                            @if($t->travel_cancelled_at && ! $t->travel_arrived_at)
                                <div class="text-muted" style="font-size:11.5px;"><i class="fas fa-ban mr-1"></i>Perjalanan terakhir dibatalkan.</div>
                            @endif
                        </div>
                        @endunless

                        {{-- ================= LOG ================= --}}
                        <div class="tab-pane fade" id="ttTabLog" role="tabpanel">
                            {{-- Terbaru di atas; isi sama dengan modal Info dari daftar. --}}
                            @include('trouble-ticket._log', ['statusLog' => $statusLog, 'tautChat' => true])
                        </div>
                    </div>
                </div>
            </div>

            {{-- MOBILE: panel Action --}}
            <div class="col-12 mb-3 tt-pane tt-pane-action">
                <div class="card shadow-sm mb-0">
                    <div class="card-body">
                        <div class="tt-actions tt-actions-stack">
                            @include('trouble-ticket._actions', $actionVars)
                        </div>
                        @unless($bisaAlihkan)
                            <x-empty title="Aksi tiket ada di tab Info" message="Tombol tahap (Remote, Create Plan, perjalanan, form close, approval) ada di kaki ringkasan tiket." icon="fas fa-hand-pointer" compact />
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        {{-- MOBILE: bilah navigasi bawah --}}
        <nav class="tt-mbar" aria-label="Panel tiket">
            <button type="button" data-pane="chat"><i class="fas fa-comments"></i><span>Chat</span></button>
            <button type="button" data-pane="info" class="{{ $paneAwal === 'info' ? 'active' : '' }}"><i class="fas fa-info-circle"></i><span>Info</span></button>
            <button type="button" data-pane="action"><i class="fas fa-bolt"></i><span>Action</span></button>
        </nav>
    </div>
</div>

{{-- ===================== MODAL ===================== --}}

@if($bisaPlan)
    {{-- Create Plan — site & teknisi terkunci (ikut tiket); teknisi hanya mengisi tanggal. --}}
    <div class="modal fade" id="mdlTtPlan" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.plan.create', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-check mr-1"></i> {{ ['PLAN' => 'Ubah Tanggal Visit', 'TRIP' => 'Reschedule Visit'][$t->status] ?? 'Create Plan' }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="form-row">
                        <div class="form-group col-md-7 mb-2">
                            <label class="font-weight-bold">Site / Tiket</label>
                            <input type="text" class="form-control" value="{{ $site?->tid }} — {{ $site?->sitename }} ({{ $t->ticket_number }})" readonly>
                        </div>
                        <div class="form-group col-md-5 mb-2">
                            <label class="font-weight-bold">Pelaksana</label>
                            <input type="text" class="form-control" value="{{ $t->technician?->name ?? '—' }}" readonly>
                        </div>
                        <div class="form-group col-12 mb-2">
                            <label class="font-weight-bold" for="fldPlanVisit">Plan Visit <span class="text-danger">*</span></label>
                            <div class="tt-date-field">
                                <input type="text" name="visit_site" id="fldPlanVisit" class="form-control tt-date"
                                       data-min-date="{{ $minVisit }}"
                                       value="{{ old('visit_site', $t->visit_site?->toDateString() ?? now()->toDateString()) }}" required>
                                <i class="far fa-calendar-alt tt-date-ico"></i>
                            </div>
                        </div>
                    </div>
                    @if(in_array($t->status, ['PLAN', 'TRIP'], true))
                        {{-- Mengubah tanggal yang sudah ada wajib beralasan; plan pertama tidak. --}}
                        <div class="form-group mb-2">
                            <label class="font-weight-bold" for="fldPlanReason">Keterangan <span class="text-danger">*</span></label>
                            <textarea name="reason" id="fldPlanReason" class="form-control" rows="2" maxlength="500" required
                                      placeholder="Alasan tanggal visit diubah">{{ old('reason') }}</textarea>
                        </div>
                    @endif
                    {{-- Biaya plan (keputusan 20 Okt 2026): sumber dana + baris
                         item. Total tiap baris dan total keseluruhan dihitung
                         ulang di server; angka di sini hanya bantuan baca. --}}
                    <hr class="my-2">
                    <div class="form-row">
                        <div class="form-group col-md-6 mb-2">
                            <label class="font-weight-bold" for="fldPlanBudget">Budget Plan <span class="text-danger">*</span></label>
                            {{-- Daftarnya panjang, jadi dipasangi pencarian
                                 (TesysSearchableSelect) seperti dropdown
                                 panjang lain di app. --}}
                            <select name="budget_plan_id" id="fldPlanBudget" class="form-control" data-searchable required>
                                <option value="">— Pilih Budget Plan —</option>
                                @foreach($budgetPlans as $bp)
                                    <option value="{{ $bp->id }}" @selected(old('budget_plan_id', $t->budget_plan_id) == $bp->id)>
                                        {{ $bp->code }}@if($bp->description) — {{ \Illuminate\Support\Str::limit($bp->description, 40) }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @if($budgetPlans->isEmpty())
                                <small class="text-danger">Belum ada Budget Plan ACTIVE untuk customer Anda.</small>
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm tt-cost-table mb-1">
                            <thead>
                                <tr>
                                    <th style="width:26%">COA / Sub-item <span class="text-danger">*</span></th>
                                    <th>Description <span class="text-danger">*</span></th>
                                    <th style="width:18%">Harga <span class="text-danger">*</span></th>
                                    <th style="width:10%">Qty <span class="text-danger">*</span></th>
                                    <th style="width:18%" class="text-right">Total</th>
                                    <th style="width:36px"></th>
                                </tr>
                            </thead>
                            <tbody id="ttCostRows">
                                @foreach(($planCosts->isNotEmpty() ? $planCosts : collect([null])) as $row)
                                    <tr class="tt-cost-row">
                                        <td>
                                            {{-- Pilihan disaring di browser mengikuti Budget Plan
                                                 terpilih (data-plan); server memeriksa ulang bahwa
                                                 sub-item memang milik plan itu. --}}
                                            <select name="costs[{{ $loop->index }}][budget_detail_id]" class="form-control form-control-sm tt-cost-item" data-searchable required>
                                                <option value="">— Sub-item —</option>
                                                @foreach($budgetDetails as $bd)
                                                    <option value="{{ $bd->id }}" data-plan="{{ $bd->budget_plan_id }}"
                                                        @selected($row && $row->budget_detail_id == $bd->id)>
                                                        {{ $bd->account?->name ?? '-' }}@if($bd->deskripsi) — {{ \Illuminate\Support\Str::limit($bd->deskripsi, 40) }}@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="text" name="costs[{{ $loop->index }}][description]" class="form-control form-control-sm" maxlength="255" required value="{{ $row?->description }}"></td>
                                        {{-- Uang ditulis bertitik ribuan seperti form Budget Plan
                                             & Cost Request; titiknya dibuang lagi di server. --}}
                                        <td><input type="text" inputmode="numeric" name="costs[{{ $loop->index }}][amount]"
                                                   class="form-control form-control-sm text-right tt-cost-amount" required
                                                   value="{{ $row ? number_format($row->amount, 0, ',', '.') : '' }}"></td>
                                        {{-- Qty bilangan BULAT (20 Okt 2026): satuannya trip,
                                             orang, hari, unit — tidak ada setengah trip. --}}
                                        <td><input type="number" name="costs[{{ $loop->index }}][qty]" class="form-control form-control-sm tt-cost-qty" min="1" step="1" required value="{{ $row ? (int) $row->qty : 1 }}"></td>
                                        <td class="text-right align-middle tt-cost-total">0</td>
                                        <td class="align-middle text-center">
                                            <button type="button" class="btn btn-link btn-sm text-danger p-0 tt-cost-del" aria-label="Hapus baris"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">Total Estimasi Biaya</th>
                                    <th class="text-right" id="ttCostGrand">0</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ttCostAdd"><i class="fas fa-plus mr-1"></i> Tambah Baris</button>
                    {{-- Alur sesudah Simpan, ditulis apa adanya supaya tidak
                         ada yang mengira biaya ini langsung memotong pagu. --}}
                    <div class="tt-stage-note mt-2">
                        Setelah di-submit: tiket <strong>tetap di tahap Plan</strong> menunggu
                        <strong>approval supervisor</strong> atas tanggal &amp; biaya.
                        Begitu disetujui, sistem membuat <strong>Cost Request berstatus Draft</strong>
                        yang selanjutnya menempuh alur Cost Request seperti biasa — pagu Budget Plan
                        baru terpakai di sana, bukan oleh plan ini.
                        Tahap <strong>Trip</strong> dimulai saat teknisi menggeser
                        “Swipe for travel” pada tanggal plan visit.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    {{-- "Submit", bukan "Simpan": menekannya mengajukan plan & biaya untuk
                         disetujui supervisor, bukan sekadar menyimpan draft. --}}
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($bisaBerangkat)
    {{-- Mulai perjalanan — evidence perjalanan wajib (keputusan 15 Okt 2026). --}}
    <div class="modal fade" id="mdlTtTravel" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.travel.start', $t) }}"
                  enctype="multipart/form-data" id="frmTtTravel">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-car mr-1"></i> Mulai Perjalanan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <label class="font-weight-bold">Evidence perjalanan <span class="text-danger">*</span></label>
                    <input type="file" name="attachments[]" id="fldTravelFiles" multiple accept="{{ $acceptFiles }}" hidden>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-block" id="btnTtTravelFiles">
                        <i class="fas fa-paperclip mr-1"></i> Pilih evidence
                    </button>
                    <div class="tt-eviz-list small text-muted mt-2" id="ttTravelFileList"></div>
                    <small class="text-muted">Maks. 10 MB per berkas.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-car mr-1"></i> Mulai Perjalanan</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($dalamJalan)
    <div class="modal fade" id="mdlTtCancelTrip" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.travel.cancel', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban mr-1"></i> Cancel Visit</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <label class="font-weight-bold" for="fldCancelTrip">Alasan cancel visit <span class="text-danger">*</span></label>
                    <textarea name="reason" id="fldCancelTrip" class="form-control mb-2" rows="3" maxlength="500" required></textarea>
                    {{-- Tanggal plan wajib diisi ulang: pembatalan selalu meninggalkan
                         jadwal kunjungan berikutnya. --}}
                    <label class="font-weight-bold" for="fldCancelVisit">Replan visit date <span class="text-danger">*</span></label>
                    <div class="tt-date-field">
                        <input type="text" name="visit_site" id="fldCancelVisit" class="form-control tt-date"
                               data-min-date="{{ $minVisit }}"
                               value="{{ now()->toDateString() }}" required>
                        <i class="far fa-calendar-alt tt-date-ico"></i>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-ban mr-1"></i> Ya, Cancel</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($bisaTutup)
    {{-- Form close — dipakai jalur Remote maupun In Progress. Evidence bisa
         dipilih dari galeri/berkas, ditempel (Ctrl+V) di keterangan, atau
         diseret ke kotak evidence. --}}
    <div class="modal fade" id="mdlTtClosure" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.close', $t) }}" enctype="multipart/form-data" id="frmTtClosure">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-flag-checkered mr-1"></i> Form Close{{ $t->status === 'REMOTE' ? ' — Remote' : '' }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="form-row">
                        <div class="form-group col-md-6 mb-2">
                            <label class="font-weight-bold" for="fldRca">RCA Resolution <span class="text-danger">*</span></label>
                            {{-- Tanpa atribut required: select disembunyikan oleh searchable select,
                                 kontrol tersembunyi yang required memblokir submit di browser.
                                 Wajibnya tetap dijaga server (TroubleTicketController::close). --}}
                            <select name="rca_id" id="fldRca" class="form-control">
                                <option value="">— Pilih RCA Resolution —</option>
                                @foreach($rcas as $r)
                                    <option value="{{ $r->id }}" data-name="{{ $r->name }}" @selected((int) old('rca_id', $t->rca_id) === (int) $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">RCA Resolution.</small>
                            <input type="hidden" name="rca" id="fldRcaName" value="{{ old('rca', $t->rca_label) }}">
                        </div>
                        <div class="form-group col-md-6 mb-2">
                            <label class="font-weight-bold" for="fldSubRca">Rincian RCA Resolution</label>
                            <select name="sub_rca_id" id="fldSubRca" class="form-control">
                                <option value="">— Pilih Rincian RCA Resolution —</option>
                                @foreach($subRcas as $sr)
                                    <option value="{{ $sr->id }}" data-rca="{{ $sr->rca_id }}" data-name="{{ $sr->name }}" @selected((int) old('sub_rca_id', $t->sub_rca_id) === (int) $sr->id)>{{ $sr->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Rincian RCA Resolution.</small>
                            <input type="hidden" name="sub_rca" id="fldSubRcaName" value="{{ old('sub_rca', $t->sub_rca_label) }}">
                        </div>
                    </div>
                    <label class="font-weight-bold" for="fldResolution">Keterangan <span class="text-danger">*</span></label>
                    <textarea name="resolution_summary" id="fldResolution" class="form-control" rows="3" maxlength="5000" required placeholder="Tulis keterangan penyelesaian">{{ old('resolution_summary') }}</textarea>
                    <label class="font-weight-bold mt-2">Evidence <span class="text-danger">*</span></label>
                    <div class="tt-eviz" id="ttEviz">
                        <input type="file" name="attachments[]" id="fldClosureFiles" multiple accept="{{ $acceptFiles }}" hidden>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-block" id="btnTtEviz">
                            <i class="fas fa-paperclip mr-1"></i> Pilih evidence
                        </button>
                        <div class="tt-eviz-list" id="ttEvizList"></div>
                    </div>
                    <small class="text-muted">Maks. 10 MB per berkas.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($canApproveClose)
    <div class="modal fade" id="mdlTtApprove" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once tt-confirm" method="POST" action="{{ route('trouble-ticket.close.approve', $t) }}"
                  data-title="Close tiket ini?" data-text="Tiket {{ $t->ticket_number }} akan ditutup.">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check mr-1"></i> Close</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="mb-2"><span class="text-muted">Close by {{ $t->closer?->name ?: $t->technician?->name }}</span>
                        <dl class="m-0 mt-1">
                            <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca_label ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Sub-RCA</dt><dd>{{ $t->sub_rca_label ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Keterangan</dt><dd style="white-space:pre-wrap;">{{ $t->resolution_summary ?: '—' }}</dd></div>
                        </dl>
                        @if($closureFiles->isNotEmpty())
                            <div class="font-weight-bold mt-2" style="font-size:12px;">Evidence</div>
                            @include('trouble-ticket._attachments', ['items' => $closureFiles])
                        @endif
                    </div>
                    <label for="fldApproveNotes" class="font-weight-bold">Catatan approval (opsional)</label>
                    <textarea name="notes" id="fldApproveNotes" class="form-control" rows="2" maxlength="1000"></textarea>
                    <small class="text-muted">Tiket menjadi Closed setelah disetujui.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Ya, Close</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="mdlTtReject" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.close.reject', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-undo mr-1"></i> Reject Close</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <label for="fldRejectNotes" class="font-weight-bold">Alasan penolakan <span class="text-danger">*</span></label>
                    <textarea name="notes" id="fldRejectNotes" class="form-control" rows="3" maxlength="1000" required></textarea>
                    <small class="text-muted">Tiket kembali ke teknisi untuk diperbaiki.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-undo mr-1"></i> Reject Close</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($canSettle)
    {{-- Settlement dari halaman tiket (20 Okt 2026).

         Form ini MENGIRIM KE ENDPOINT SETTLEMENT YANG SUDAH ADA
         (budget-request.submit-realization), jadi seluruh aturan uang,
         validasi lampiran per baris, transisi status REALIZED, dan notifikasi
         Finance tetap satu sumber — tidak ada perhitungan yang ditulis ulang
         di modul Trouble Ticket.

         Tambahannya hanya tanda tangan PENERIMA DANA di lapangan. --}}
    <div class="modal fade" id="mdlTtSettle" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            {{-- Menyimpan sebagai DRAFT settlement (20 Okt 2026): status CR tidak
                 berubah, pengajuan resminya tetap dari modul Cost Request. --}}
            <form class="modal-content tt-submit-once" id="frmTtSettle" method="POST"
                  action="{{ route('budget-request.save-realization-draft', $t->budgetRequest->id) }}"
                  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="from_ticket" value="1">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice-dollar mr-1"></i> Settlement — {{ $t->budgetRequest->code }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="table-responsive">
                        <table class="table table-sm tt-cost-table mb-1">
                            <thead>
                                <tr>
                                    <th>Description Request</th>
                                    <th style="width:22%">Description Settle</th>
                                    <th class="text-right" style="width:8%">Qty</th>
                                    <th class="text-right" style="width:12%">Request</th>
                                    <th class="text-right" style="width:14%">Settle <span class="text-danger">*</span></th>
                                    <th class="text-right" style="width:10%">Variance</th>
                                    <th class="text-center" style="width:52px">Lampiran</th>
                                    <th class="text-center" style="width:52px">E-sign</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($settleDetails as $i => $d)
                                    {{-- Draft yang sudah tersimpan dipakai sebagai isi awal
                                         (20 Okt 2026): modal boleh diisi sebagian, disimpan,
                                         lalu dilanjutkan. Tanpa draft, Settle jatuh ke nilai
                                         Request seperti pengisian pertama. --}}
                                    @php($draft = $settleDrafts[$d->id] ?? null)
                                    <tr class="tt-settle-row">
                                        <td>
                                            {{ $d->description }}
                                            <input type="hidden" name="realizations[{{ $i }}][request_detail_id]" value="{{ $d->id }}">
                                            {{-- Wajib hanya bila Settle melebihi Request; diisi lewat kolom Description Settle. --}}
                                            <input type="hidden" name="realizations[{{ $i }}][change_note]" class="tt-settle-change" value="{{ $draft->change_note ?? '' }}">
                                        </td>
                                        <td><input type="text" name="realizations[{{ $i }}][notes]" class="form-control form-control-sm" maxlength="255" placeholder="Catatan settle..." value="{{ $draft->notes ?? '' }}"></td>
                                        <td class="text-right align-middle">{{ (int) $d->qty }}</td>
                                        <td class="text-right align-middle tt-settle-req" data-req="{{ (int) $d->total }}">{{ number_format((int) $d->total, 0, ',', '.') }}</td>
                                        <td><input type="text" inputmode="numeric" name="realizations[{{ $i }}][amount]" class="form-control form-control-sm text-right tt-settle-amount" value="{{ number_format((int) ($draft->total ?? $d->total), 0, ',', '.') }}"></td>
                                        <td class="text-right align-middle tt-settle-var">0</td>
                                        {{-- Dua kolom aksi, ikon saja: kotak input berkas
                                             bawaan peramban dan tombol berteks membuat baris
                                             melebar jauh melebihi isinya. --}}
                                        <td class="text-center">
                                            <input type="file" id="ttAtt{{ $i }}" name="realizations[{{ $i }}][attachments][]" class="d-none tt-settle-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                            <button type="button" class="btn btn-outline-secondary btn-sm tt-icon-btn" title="Lampiran item" onclick="document.getElementById('ttAtt{{ $i }}').click()">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <div class="tt-file-count text-muted" style="font-size:10px;">{{ ($draft->attachments_count ?? 0) ? $draft->attachments_count . ' tersimpan' : '' }}</div>
                                        </td>
                                        <td class="text-center">
                                            @php($sudahTtd = in_array($i + 1, $settleSignedRows ?? [], true))
                                            <button type="button" class="btn btn-sm tt-icon-btn tt-esign-btn {{ $sudahTtd ? 'btn-success' : 'btn-outline-secondary' }}" title="E-sign penerima dana" data-row="{{ $i }}">
                                                <i class="fas fa-signature"></i>
                                            </button>
                                            <input type="hidden" name="signatures[{{ $i }}]" class="tt-esign-value">
                                            <div class="tt-esign-state text-muted" style="font-size:10px;">{{ $sudahTtd ? 'tertanda' : '' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right">SUBTOTAL</th>
                                    <th class="text-right">{{ number_format((int) $t->budgetRequest->amount, 0, ',', '.') }}</th>
                                    <th class="text-right" id="ttSettleTotal">0</th>
                                    <th class="text-right" id="ttSettleVar">0</th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="text-danger d-none" id="ttSettleErr"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm mr-auto" data-dismiss="modal">Batal</button>
                    {{-- Label "Simpan", bukan "Settlement": tombol ini menyimpan DRAFT
                         dan boleh ditekan berkali-kali; pengajuan resminya tetap dari
                         modul Cost Request (20 Okt 2026). --}}
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Pad e-sign dipakai bersama semua baris; baris yang sedang ditandatangani
         disimpan di data-row tombolnya. "Upload" menyalin coretan ke input
         tersembunyi baris itu — berkasnya baru ditulis server saat settlement
         disimpan. --}}
    <div class="modal fade" id="mdlTtEsign" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-signature mr-1"></i> E-sign penerima dana</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="text-muted mb-1" id="ttEsignRowLabel"></div>
                    <canvas id="ttSignPad" class="tt-signpad"></canvas>
                    <small class="text-muted d-block mt-1">Tanda tangani di kotak di atas.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm mr-auto" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="ttSignClear">Ulang</button>
                    <button type="button" class="btn btn-primary btn-sm" id="ttSignSave"><i class="fas fa-upload mr-1"></i> Upload</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($canApprovePlanBudget)
    {{-- Review plan & biaya (20 Okt 2026). SATU modal memuat seluruh rincian
         pengajuan beserta kedua keputusannya: dua modal terpisah memaksa
         supervisor menutup yang satu untuk melihat angka di yang lain.
         Approve & Reject memakai formaction, jadi satu form melayani dua
         endpoint tanpa menduplikasi isinya. --}}
    <div class="modal fade" id="mdlTtPlanReview" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <form class="modal-content tt-submit-once" id="frmTtPlanReview" method="POST"
                  action="{{ route('trouble-ticket.plan.approve', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check mr-1"></i> Review Plan &amp; Budget</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="m-0">
                                <div class="tt-kv"><dt>TT</dt><dd><strong>{{ $t->ticket_number }}</strong></dd></div>
                                <div class="tt-kv"><dt>Site</dt><dd>{{ $site?->sitename ?? '—' }}</dd></div>
                                <div class="tt-kv"><dt>Customer</dt><dd>{{ $t->customer?->name ?? '—' }}</dd></div>
                                <div class="tt-kv"><dt>Pelaksana</dt><dd>{{ $t->technician?->name ?? '—' }}</dd></div>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="m-0">
                                <div class="tt-kv"><dt>Plan visit</dt><dd>{{ $t->visit_site?->format('d/m/Y') ?? '—' }}</dd></div>
                                <div class="tt-kv"><dt>Budget Plan</dt><dd>{{ $t->budgetPlan?->code ?? '—' }}</dd></div>
                                <div class="tt-kv"><dt>Diajukan</dt><dd>{{ $t->visit_decided_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                                <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca_label ?: '—' }}</dd></div>
                            </dl>
                        </div>
                    </div>

                    {{-- Rincian biaya yang diajukan — angka yang sama dengan yang
                         akan dipindahkan ke rincian Cost Request. --}}
                    <div class="tt-sec-title">Rincian biaya</div>
                    <div class="table-responsive">
                        <table class="table table-sm tt-cost-table mb-0">
                            <thead>
                                <tr>
                                    <th>COA</th>
                                    <th>Description</th>
                                    <th class="text-right">Harga</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($planCosts as $c)
                                    <tr>
                                        <td>{{ $c->account?->name ?? $c->account_name ?? '—' }}</td>
                                        <td>{{ $c->description }}</td>
                                        <td class="text-right">Rp {{ number_format($c->amount, 0, ',', '.') }}</td>
                                        <td class="text-right">{{ (int) $c->qty }}</td>
                                        <td class="text-right">Rp {{ number_format($c->total, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Belum ada baris biaya.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">Total Estimasi Biaya</th>
                                    <th class="text-right">Rp {{ number_format((int) $t->plan_cost_total, 0, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if($planClusters->count() > 1)
                        <div class="form-group mt-3 mb-2">
                            <label class="font-weight-bold" for="fldPlanCluster">Cluster <span class="text-danger">*</span></label>
                            <select name="cluster_id" id="fldPlanCluster" class="form-control">
                                <option value="">— Pilih cluster —</option>
                                @foreach($planClusters as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Wajib diisi saat Approve — budget plan ini bersumber dari proyek.</small>
                        </div>
                    @endif

                    <div class="form-group mb-0 mt-3">
                        <label class="font-weight-bold" for="fldPlanReviewNotes">Catatan</label>
                        <textarea name="notes" id="fldPlanReviewNotes" class="form-control" rows="2" maxlength="500"
                                  placeholder="Lengkapi catatan ..."></textarea>
                        <small class="text-muted">Opsional saat Approve, wajib saat Reject.</small>
                        <div class="text-danger mt-1 d-none" id="ttPlanReviewErr"></div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm mr-auto" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-outline-danger btn-sm" id="btnTtPlanReject"
                            formaction="{{ route('trouble-ticket.plan.reject', $t) }}">
                        <i class="fas fa-times mr-1"></i> Reject
                    </button>
                    <button type="submit" class="btn btn-success btn-sm" id="btnTtPlanApprove">
                        <i class="fas fa-check mr-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($bisaAlihkan)
    <div class="modal fade" id="mdlTtReassign" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.reassign', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt mr-1"></i> Change Teknisi</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    @if($reassignTo->isEmpty())
                        <x-empty title="Belum ada teknisi backup" message="Site ini belum punya Teknisi Backup. Daftarkan lebih dulu di Master Data &rsaquo; Site, tab Teknisi Backup." icon="fas fa-user-cog" compact />
                    @else
                        {{-- Pilihannya = Teknisi Backup site ini, diisi manusia
                             di Master Data > Site (16 Okt 2026). --}}
                        <label for="fldReassign" class="font-weight-bold">Teknisi baru <span class="text-danger">*</span></label>
                        <select name="assigned_to" id="fldReassign" class="form-control mb-2" required>
                            <option value="">— Pilih —</option>
                            @foreach($reassignTo as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @if(in_array($t->status, ['PLAN', 'TRIP'], true) && ! $t->travel_arrived_at)
                            {{-- Reschedule tahap Plan & Trip dilakukan dari sini: ganti
                                 teknisi dan tanggal kunjungannya sekaligus, satu form. --}}
                            <label for="fldReassignVisit" class="font-weight-bold">Plan Visit <span class="text-danger">*</span></label>
                            <div class="tt-date-field mb-2">
                                <input type="text" name="visit_site" id="fldReassignVisit" class="form-control tt-date"
                                       data-min-date="{{ $minVisit }}"
                                       value="{{ $t->visit_site?->toDateString() ?? now()->toDateString() }}" required>
                                <i class="far fa-calendar-alt tt-date-ico"></i>
                            </div>
                        @endif
                        <label for="fldReassignReason" class="font-weight-bold">Alasan <span class="text-danger">*</span></label>
                        <textarea name="reason" id="fldReassignReason" class="form-control" rows="2" maxlength="500" required></textarea>
                        <small class="text-muted">Respons pertama tiket tetap tercatat; penugasan lama disimpan di riwayat.@if(in_array($t->status, ['PLAN', 'TRIP'], true) && ! $t->travel_arrived_at) Tiket kembali ke tahap Plan dengan tanggal di atas.@endif</small>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($reassignTo->isEmpty())>Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Penampil evidence: satu untuk seluruh halaman, termasuk modal approval. --}}
<div class="tt-viewer" id="ttViewer" aria-hidden="true" role="dialog" aria-label="Evidence">
    <button type="button" class="tt-viewer-x" data-viewer-close aria-label="Tutup">&times;</button>
    <button type="button" class="tt-viewer-nav tt-viewer-prev" data-viewer-prev aria-label="Sebelumnya"><i class="fas fa-chevron-left"></i></button>
    <img id="ttViewerImg" src="" alt="">
    <button type="button" class="tt-viewer-nav tt-viewer-next" data-viewer-next aria-label="Berikutnya"><i class="fas fa-chevron-right"></i></button>
    <div class="tt-viewer-cap" id="ttViewerCap"></div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    var MAX_BYTES = {{ TroubleTicketAttachment::MAX_KB * 1024 }}, MAX_FILES = {{ TroubleTicketAttachment::MAX_FILES }};
    var pageStatus = @json($t->status);
    var messagesUrl = @json(route('trouble-ticket.messages', $t));
    var $list = $('#ttChatList'), $items = $('#ttChatItems');

    /* ---------- Kalender (flatpickr) ----------
       Kalender bawaan peramban diganti flatpickr yang sudah dimuat di layout:
       satu kalender sederhana berbahasa Indonesia, tampilannya sama di semua
       peramban dan di HP. Nilai yang terkirim tetap Y-m-d seperti sebelumnya;
       yang dibaca pengguna d/m/Y lewat altInput. Tanggal sebelum data-min-date
       dimatikan di kalender; aturan yang sama tetap diperiksa di server. */
    if (typeof flatpickr === 'function') {
        $('.tt-date').each(function () {
            flatpickr(this, {
                locale: 'id',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                disableMobile: true,
                minDate: this.dataset.minDate || null,
            });
        });
    }


    /* ---------- Mobile: Chat | Info | Action ---------- */
    $('.tt-mbar').on('click', 'button[data-pane]', function () {
        var pane = this.dataset.pane;
        $('#ttPanes').attr('data-pane', pane);
        $('.tt-mbar button').removeClass('active').filter('[data-pane="' + pane + '"]').addClass('active');
        window.scrollTo(0, 0);
        if (pane === 'chat') scrollBottom();
    });

    @if($errors->any())
        window.tsToast('error', @json($errors->first()));
    @endif

    function scrollBottom() { $list.scrollTop($list[0].scrollHeight); }
    function newestId() { var $l = $items.children('[data-id]').last(); return $l.length ? $l.data('id') : 0; }
    function oldestId() { var $f = $items.children('[data-id]').first(); return $f.length ? $f.data('id') : 0; }
    scrollBottom();

    function checkFiles(input, $info) {
        var pesan = [];
        if (input.files.length > MAX_FILES) pesan.push('Paling banyak ' + MAX_FILES + ' berkas.');
        Array.prototype.forEach.call(input.files, function (f) { if (f.size > MAX_BYTES) pesan.push(f.name + ' melebihi 10 MB.'); });
        if (pesan.length) {
            input.value = '';
            window.tsToast('error', pesan.join(' '));
        }
        if ($info) $info.text(input.files.length ? Array.prototype.map.call(input.files, function (f) { return f.name; }).join(', ') : '');
        return !pesan.length;
    }
    $(document).on('change', '.tt-file-check', function () { checkFiles(this); });

    /* ---------- Form close: RCA → Sub-RCA & evidence ---------- */
    (function () {
        var $rca = $('#fldRca'), $sub = $('#fldSubRca');
        if (!$rca.length) return;

        function syncSub() {
            var id = $rca.val();
            $('#fldRcaName').val($rca.find('option:selected').data('name') || '');
            $sub.find('option').each(function () {
                var cocok = !this.value || String($(this).data('rca')) === String(id);
                $(this).prop('hidden', !cocok).prop('disabled', !cocok);
            });
            if ($sub.find('option:selected').prop('disabled')) $sub.val('');
            $('#fldSubRcaName').val($sub.find('option:selected').data('name') || '');
        }
        $rca.on('change', syncSub);
        $sub.on('change', function () { $('#fldSubRcaName').val($(this).find('option:selected').data('name') || ''); });
        syncSub();

        /* Kedua dropdown dibungkus searchable select (kotak cari di panel).
           <select> asli tetap sumber nilai; 'tss:sync' dipanggil setelah
           daftar Sub-RCA disaring ulang supaya label tombol ikut diperbarui. */
        if (window.TesysSearchableSelect) {
            TesysSearchableSelect.init('#fldRca', {
                placeholder: '— Pilih RCA Resolution —', searchPlaceholder: 'Cari RCA Resolution...',
            });
            TesysSearchableSelect.init('#fldSubRca', {
                placeholder: '— Pilih Rincian RCA Resolution —', searchPlaceholder: 'Cari rincian...',
            });
            $rca.on('change', function () { $sub.trigger('tss:sync'); });
            $sub.trigger('tss:sync');
        }

        /* Evidence: galeri/berkas, tempel di keterangan, seret ke kotak. */
        var input = document.getElementById('fldClosureFiles');
        var $list = $('#ttEvizList'), berkas = [];

        function fmtSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

        function sync() {
            var dt = new DataTransfer();
            berkas.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
            $list.empty();
            berkas.forEach(function (f, i) {
                var $c = $('<span class="tt-eviz-item"></span>');
                if (/^image\//.test(f.type)) {
                    var url = URL.createObjectURL(f);
                    $c.append($('<img alt="">').attr('src', url).on('load', function () { URL.revokeObjectURL(url); }));
                }
                $c.append($('<span class="tt-eviz-name"></span>').text(f.name + ' · ' + fmtSize(f.size)));
                $c.append($('<button type="button" class="tt-eviz-x" title="Hapus">&times;</button>').attr('data-i', i));
                $list.append($c);
            });
        }

        function tambah(daftar) {
            Array.prototype.forEach.call(daftar, function (f) {
                if (f.size > MAX_BYTES) { window.tsToast('error', f.name + ' melebihi 10 MB.'); return; }
                if (berkas.length >= MAX_FILES) { window.tsToast('error', 'Paling banyak ' + MAX_FILES + ' berkas.'); return; }
                berkas.push(f);
            });
            sync();
        }

        $('#btnTtEviz').on('click', function () { input.click(); });
        $(input).on('change', function () { tambah(this.files); });
        $list.on('click', '.tt-eviz-x', function () { berkas.splice(+this.dataset.i, 1); sync(); });

        // Evidence wajib: form tidak dikirim bila kosong.
        $('#frmTtClosure').on('submit', function (e) {
            if (!berkas.length) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.tsToast('error', 'Evidence wajib dilampirkan.');
            }
        });
    })();

    /* ---------- Evidence perjalanan: wajib sebelum form dikirim ---------- */
    (function () {
        var input = document.getElementById('fldTravelFiles');
        if (!input) return;
        var $daftar = $('#ttTravelFileList');

        $('#btnTtTravelFiles').on('click', function () { input.click(); });
        $(input).on('change', function () { checkFiles(this, $daftar); });

        $('#frmTtTravel').on('submit', function (e) {
            if (!input.files.length) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.tsToast('error', 'Evidence perjalanan wajib dilampirkan.');
            }
        });
    })();

    /* ---------- Swipe: mulai perjalanan / mulai progres ---------- */
    $('.tt-swipe').each(function () {
        var el = this, $el = $(el), $knob = $el.find('.tt-swipe-knob'), geser = false, x0 = 0, maks = 0;

        function lebar() { return el.clientWidth - $knob[0].offsetWidth - 8; }
        function reset() { $knob.css('transform', 'translateX(0)'); $el.removeClass('is-dragging'); }

        function selesai() {
            // Swipe yang membuka modal (mis. evidence perjalanan): modal yang
            // mengirim formnya, jadi swipe dikembalikan ke posisi semula.
            var modal = $el.data('modal');
            if (modal) { $(modal).modal('show'); reset(); return; }

            $el.addClass('is-done');
            var $form = $('#' + $el.data('form'));
            // data-confirm="0" = langsung kirim tanpa dialog (mis. Swipe for travel).
            if (String($el.data('confirm')) === '0') { $form.trigger('submit'); return; }
            Swal.fire({
                title: $el.data('confirm') || 'Lanjutkan?',
                text: $el.data('text') || '',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Iya', cancelButtonText: 'Tidak',
            }).then(function (r) {
                if (r.isConfirmed) { $form.trigger('submit'); } else { $el.removeClass('is-done'); reset(); }
            });
        }

        $knob.on('pointerdown', function (e) {
            geser = true; x0 = e.clientX; maks = lebar();
            $el.addClass('is-dragging');
            $knob[0].setPointerCapture(e.pointerId);
        });
        $knob.on('pointermove', function (e) {
            if (!geser) return;
            var dx = Math.max(0, Math.min(maks, e.clientX - x0));
            $knob.css('transform', 'translateX(' + dx + 'px)');
        });
        $knob.on('pointerup pointercancel', function (e) {
            if (!geser) return;
            geser = false;
            var dx = Math.max(0, Math.min(maks, e.clientX - x0));
            if (dx >= maks * 0.85) { selesai(); } else { reset(); }
        });
        // Papan ketik / aksesibilitas: Enter atau klik ganda = sama dengan geser penuh.
        $knob.on('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selesai(); } });
    });

    /* Satu penanda "sedang mengirim" untuk semua form di halaman ini:
       tombol kirim diganti pemutar, tombol lain hanya dimatikan. */
    function tandaiMengirim(form) {
        var $f = $(form);
        if ($f.data('tt-sending')) return;
        $f.data('tt-sending', true);
        $f.find('button').prop('disabled', true);
        $f.find('button[type=submit]').html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses…');
    }

    $('form.tt-confirm').on('submit', function (e) {
        var form = this;
        if (form.dataset.ok) return;
        e.preventDefault();
        Swal.fire({
            title: form.dataset.title, text: form.dataset.text, icon: 'question',
            showCancelButton: true, confirmButtonText: 'Iya', cancelButtonText: 'Tidak',
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || undefined
        }).then(function (r) {
            if (!r.isConfirmed) return;
            form.dataset.ok = '1';
            // Satu pemutar saja, di tombol kirim. Sebelumnya setiap tombol di
            // form (termasuk Batal dan tombol tutup) ikut mendapat pemutar.
            tandaiMengirim(form);
            form.submit();
        });
    });
    $('form.tt-submit-once').on('submit', function () {
        if (this.checkValidity && !this.checkValidity()) return;
        tandaiMengirim(this);
    });

    /* ---------- Muat pesan lama ---------- */
    $('#btnTtOlder').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var tinggiLama = $list[0].scrollHeight;
        $.getJSON(messagesUrl, { before_id: oldestId() }).done(function (res) {
            $items.prepend(res.html);
            $list.scrollTop($list[0].scrollHeight - tinggiLama);
            $('#ttOlderWrap').toggle(!!res.has_older);
        }).always(function () { $btn.prop('disabled', false); });
    });

    /* ---------- Polling pesan baru (ringan; hanya saat tab terlihat) ---------- */
    function poll() {
        if (document.hidden) return;
        $.getJSON(messagesUrl, { after_id: newestId() }).done(function (res) {
            if (res.html) {
                var dekatBawah = $list[0].scrollHeight - $list.scrollTop() - $list.innerHeight() < 80;
                appendUnique(res.html);
                if (dekatBawah) scrollBottom();
            }
            // Status tiket berpindah (mis. supervisor menyetujui penutupan):
            // halaman dimuat ulang sendiri supaya teknisi langsung melihat
            // tahap Closed, bukan panel tahap lama (keputusan 19 Okt 2026).
            if (res.status !== pageStatus) {
                $('#ttStaleNotice').show();
                window.location.reload();
            }
        });
    }
    function appendUnique(html) {
        $('<div>').html(html).children('[data-id]').each(function () {
            if (!$items.children('[data-id="' + $(this).data('id') + '"]').length) $items.append(this);
        });
    }
    setInterval(poll, 20000);

    /* ---------- Kirim pesan: teks 1–4 baris, lampiran via tombol / tempel / seret ---------- */
    var $form = $('#frmTtMessage');
    if ($form.length) {
        var token = newToken();
        var pending = [];               // File[] yang menunggu dikirim
        var $body = $('#ttBody'), $pend = $('#ttPending'), $btn = $('#btnTtSend');
        var ALLOWED = @json(TroubleTicketAttachment::EXTENSIONS);

        function newToken() {
            if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        }

        // Tinggi otomatis: mulai 1 baris, maksimal 4 baris, lalu menggulir di dalam.
        function autosize() {
            var el = $body[0], cs = getComputedStyle(el);
            var lh = parseFloat(cs.lineHeight) || 20;
            var tepi = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
            var max = lh * 4 + tepi;
            el.style.height = 'auto';
            var tinggi = el.scrollHeight;            // termasuk padding
            el.style.height = Math.min(Math.max(tinggi, lh + tepi), max) + 'px';
            el.style.overflowY = tinggi > max ? 'auto' : 'hidden';
        }
        $body.on('input', autosize);
        autosize();

        function fmtSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
        function esc(s) { return $('<i>').text(s).html(); }

        function renderPending() {
            $pend.empty().prop('hidden', !pending.length);
            pending.forEach(function (f, i) {
                var $c = $('<div class="tt-pend"></div>');
                if (/^image\//.test(f.type)) {
                    var url = URL.createObjectURL(f);
                    $c.append($('<img alt="">').attr('src', url).on('load', function () { URL.revokeObjectURL(url); }));
                } else {
                    $c.append('<span class="tt-pend-ic"><i class="fas fa-file"></i></span>');
                }
                $c.append('<span class="tt-pend-name" title="' + esc(f.name) + '">' + esc(f.name) + '<small>' + fmtSize(f.size) + '</small></span>');
                $c.append($('<button type="button" class="tt-pend-x" title="Batalkan"><i class="fas fa-times"></i></button>').attr('data-i', i));
                $pend.append($c);
            });
            if (pending.length > 1) {
                $pend.append('<button type="button" class="tt-pend-clear">Batalkan semua</button>');
            }
        }

        // Validasi di browser (server tetap memeriksa ulang ukuran & isi berkas).
        function addFiles(list) {
            var pesan = [];
            Array.prototype.forEach.call(list, function (f) {
                var name = f.name || ('tempel-' + Date.now() + '.png');
                if (!f.name) f = new File([f], name, { type: f.type });
                var ext = (name.split('.').pop() || '').toLowerCase();
                if (ALLOWED.indexOf(ext) === -1) { pesan.push(name + ': jenis berkas tidak diizinkan.'); return; }
                if (f.size > MAX_BYTES) { pesan.push(name + ' melebihi 10 MB.'); return; }
                if (pending.length >= MAX_FILES) { pesan.push('Paling banyak ' + MAX_FILES + ' berkas.'); return; }
                pending.push(f);
            });
            if (pesan.length) window.tsToast('error', pesan.join(' '));
            renderPending();
        }

        $('#ttFiles').on('change', function () { addFiles(this.files); this.value = ''; });
        $pend.on('click', '.tt-pend-x', function () { pending.splice(+this.dataset.i, 1); renderPending(); });
        $pend.on('click', '.tt-pend-clear', function () { pending = []; renderPending(); });

        // Tempel (Ctrl+V) berkas/foto dari clipboard.
        $body.on('paste', function (e) {
            var items = (e.originalEvent.clipboardData || {}).files;
            if (items && items.length) { e.preventDefault(); addFiles(items); }
        });

        // Seret & lepas ke area chat.
        var $chat = $('#ttChat'), $drop = $('#ttDrop'), seret = 0;
        $chat.on('dragenter', function (e) {
            if (!e.originalEvent.dataTransfer || [].indexOf.call(e.originalEvent.dataTransfer.types, 'Files') === -1) return;
            e.preventDefault(); seret++; $drop.prop('hidden', false);
        }).on('dragover', function (e) { e.preventDefault(); })
          .on('dragleave', function () { if (--seret <= 0) { seret = 0; $drop.prop('hidden', true); } })
          .on('drop', function (e) {
            e.preventDefault(); seret = 0; $drop.prop('hidden', true);
            if (e.originalEvent.dataTransfer) addFiles(e.originalEvent.dataTransfer.files);
        });

        // Enter = kirim, Shift+Enter = baris baru.
        $body.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); $form.trigger('submit'); }
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            if ($btn.prop('disabled')) return;
            if (!$.trim($body.val()) && !pending.length) return;

            var fd = new FormData();
            fd.append('body', $body.val());
            pending.forEach(function (f) { fd.append('attachments[]', f, f.name); });
            fd.append('client_token', token);
            fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
            $('#ttMsgError').hide();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({ url: $form.attr('action'), method: 'POST', data: fd, processData: false, contentType: false, headers: { Accept: 'application/json' } })
                .done(function (res) {
                    appendUnique(res.html);
                    scrollBottom();
                    $body.val(''); autosize();
                    pending = []; renderPending();
                    token = newToken();   // token baru hanya setelah berhasil
                })
                .fail(function (xhr) {
                    var pesan = 'Pesan gagal dikirim. Coba lagi.';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        pesan = Object.values(xhr.responseJSON.errors)[0][0];
                    } else if (xhr.status === 403) {
                        pesan = 'Anda tidak memiliki akses untuk mengirim pesan.';
                    } else if (xhr.status === 413) {
                        pesan = 'Berkas terlalu besar.';
                    }
                    $('#ttMsgError').text(pesan).show();
                })
                .always(function () { $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>'); $body.trigger('focus'); });
        });
    }

    // Petak "+N" pada grid lampiran: tampilkan seluruh gambar pesan itu.
    $(document).on('click', '.tt-att-moretile', function () {
        $(this).closest('.tt-att-grid').addClass('is-open');
    });

    /* ---------- Penampil evidence ----------
       Gambar lampiran dibuka di tab yang sama sebagai lapisan di atas halaman
       (termasuk saat modal approval terbuka), bukan tab baru: ada tombol tutup
       di kanan atas, panah kiri/kanan bila gambarnya lebih dari satu, dan bisa
       digeser dengan jari. Esc / panah papan ketik juga jalan. */
    (function () {
        var $box = $('#ttViewer'), $img = $('#ttViewerImg'), $cap = $('#ttViewerCap');
        if (!$box.length) return;
        var daftar = [], ke = 0;

        function tampil() {
            var it = daftar[ke];
            if (!it) return;
            $img.attr('src', it.src).attr('alt', it.nama);
            $cap.text(daftar.length > 1 ? (ke + 1) + ' / ' + daftar.length + ' · ' + it.nama : it.nama);
            $box.find('.tt-viewer-nav').toggle(daftar.length > 1);
        }
        function buka($thumb) {
            var $grid = $thumb.closest('.tt-att-grid');
            daftar = $grid.find('.tt-att-thumb img').map(function () {
                return { src: this.src, nama: this.alt || '' };
            }).get();
            ke = Math.max(0, $grid.find('.tt-att-thumb img').index($thumb.find('img')));
            if (!daftar.length) return;
            $box.addClass('is-on').attr('aria-hidden', 'false');
            tampil();
        }
        function tutup() { $box.removeClass('is-on').attr('aria-hidden', 'true'); $img.attr('src', ''); }
        function geser(arah) { if (daftar.length < 2) return; ke = (ke + arah + daftar.length) % daftar.length; tampil(); }

        $(document).on('click', '.tt-att-thumb', function (e) {
            if ($(this).is('.tt-att-moretile') || !$(this).find('img').length) return;
            e.preventDefault();
            buka($(this));
        });
        $box.on('click', '[data-viewer-close]', tutup);
        $box.on('click', function (e) { if (e.target === this) tutup(); });
        $box.on('click', '[data-viewer-prev]', function () { geser(-1); });
        $box.on('click', '[data-viewer-next]', function () { geser(1); });
        $(document).on('keydown', function (e) {
            if (!$box.hasClass('is-on')) return;
            if (e.key === 'Escape') tutup();
            if (e.key === 'ArrowLeft') geser(-1);
            if (e.key === 'ArrowRight') geser(1);
        });
        // Geser dengan jari.
        var x0 = null;
        $box.on('touchstart', function (e) { x0 = e.originalEvent.touches[0].clientX; });
        $box.on('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.originalEvent.changedTouches[0].clientX - x0;
            x0 = null;
            if (Math.abs(dx) > 50) geser(dx < 0 ? 1 : -1);
        });
    })();

    /* ---------- Interaksi kartu info ---------- */
    // Lompat ke tab lain (kartu SLA di Summary, tombol detail site).
    $(document).on('click', '[data-tt-tab]', function () {
        $('.tt-tabs a[href="' + this.dataset.ttTab + '"]').tab('show');
    });
    /* Kotak tab bisa digulir. Tanpa ini, gulir dari tab sebelumnya terbawa
       sehingga isi tab berikutnya terlihat "mulai jauh di bawah". */
    $('.tt-tabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
        var body = document.querySelector('.tt-tab-body');
        if (body) body.scrollTop = 0;
        window.scrollTo(0, 0);
    });
    // Salin ke clipboard.
    $(document).on('click', '[data-copy]', function () {
        var teks = this.dataset.copy;
        (navigator.clipboard ? navigator.clipboard.writeText(teks) : Promise.reject())
            .then(function () { window.tsToast('success', 'Disalin: ' + teks); })
            .catch(function () { window.tsToast('error', 'Gagal menyalin.'); });
    });
    // Log → sorot kejadian yang sama di timeline chat.
    $(document).on('click', '.tt-step[data-msg-id]', function () {
        var $m = $('#ttChatItems [data-id="' + this.dataset.msgId + '"]');
        if (window.matchMedia('(max-width: 991.98px)').matches) $('.tt-mbar button[data-pane="chat"]').trigger('click');
        if (!$m.length) { window.tsToast('info', 'Kejadian ini ada di riwayat lama — klik "Muat yang lebih lama".'); return; }
        $list.animate({ scrollTop: $list.scrollTop() + $m.position().top - 40 }, 200);
        $m.addClass('tt-flash'); setTimeout(function () { $m.removeClass('tt-flash'); }, 1600);
    });

    /* ---------- Review plan & biaya: dua keputusan, satu form ---------- */
    /* Catatan wajib saat Reject, cluster wajib saat Approve. Keduanya tetap
       diperiksa ulang di server; ini hanya supaya supervisor tidak perlu
       memuat ulang halaman untuk tahu isiannya kurang. */
    $('#frmTtPlanReview').on('click', '#btnTtPlanReject, #btnTtPlanApprove', function (e) {
        var reject = this.id === 'btnTtPlanReject';
        var $err = $('#ttPlanReviewErr');
        var pesan = '';

        if (reject && !$.trim($('#fldPlanReviewNotes').val())) {
            pesan = 'Alasan penolakan wajib diisi.';
        } else if (!reject && $('#fldPlanCluster').length && !$('#fldPlanCluster').val()) {
            pesan = 'Cluster wajib dipilih untuk budget proyek.';
        }

        if (pesan) {
            e.preventDefault();
            $err.text(pesan).removeClass('d-none');
            (reject ? $('#fldPlanReviewNotes') : $('#fldPlanCluster')).focus();

            return;
        }

        $err.addClass('d-none');
    });

    /* ---------- Create Plan: baris estimasi biaya ---------- */
    /* Angka di layar hanya bantuan baca; server tetap menghitung ulang
       harga x qty, jadi hasil di sini tidak pernah jadi nilai tersimpan. */
    var rupiah = new Intl.NumberFormat('id-ID');

    /** "5.342.376" -> 5342376. Titik ribuan dibuang, bukan dianggap desimal. */
    function angkaUang(teks) {
        return parseInt(String(teks || '').replace(/[^\d]/g, ''), 10) || 0;
    }

    /** 5342376 -> "5.342.376" (dipakai saat diketik, jadi bukan dari Number). */
    function tulisUang(teks) {
        return String(teks || '').replace(/[^\d]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    $('#ttCostRows').on('input', '.tt-cost-amount', function () {
        var posisiDariKanan = this.value.length - (this.selectionStart || 0);
        this.value = tulisUang(this.value);
        // Kursor dikembalikan relatif dari KANAN supaya titik yang baru
        // disisipkan tidak melompatkan kursor ke ujung.
        var kursor = Math.max(0, this.value.length - posisiDariKanan);
        try { this.setSelectionRange(kursor, kursor); } catch (e) { /* input lama */ }
    });

    function hitungBiaya() {
        var total = 0;
        $('#ttCostRows .tt-cost-row').each(function () {
            var harga = angkaUang($(this).find('.tt-cost-amount').val());
            var qty   = parseInt($(this).find('.tt-cost-qty').val(), 10) || 0;
            var baris = harga * qty;
            total += baris;
            $(this).find('.tt-cost-total').text(rupiah.format(baris));
        });
        $('#ttCostGrand').text(rupiah.format(total));
    }

    /* Nama input diberi nomor ulang sesudah tiap perubahan supaya indeks
       costs[] tetap rapat walau baris tengah dihapus. */
    function nomoriBiaya() {
        $('#ttCostRows .tt-cost-row').each(function (i) {
            $(this).find('select, input').each(function () {
                this.name = this.name.replace(/costs\[\d+\]/, 'costs[' + i + ']');
            });
        });
    }

    /* Sub-item yang bukan milik Budget Plan terpilih disembunyikan sekaligus
       dinonaktifkan: opsi disabled tidak ikut terkirim walau sempat terpilih. */
    function saringSubItem() {
        var plan = $('#fldPlanBudget').val();
        $('.tt-cost-item').each(function () {
            var $sel = $(this);
            $sel.find('option[data-plan]').each(function () {
                var cocok = String(this.dataset.plan) === String(plan);
                this.hidden = !cocok;
                this.disabled = !cocok;
            });
            if ($sel.find('option:selected').prop('disabled')) {
                $sel.val('');
                // Label tombol pencarian ikut dikembalikan ke placeholder.
                $sel.trigger('tss:sync');
            }
        });
    }

    /* Pencarian untuk dua dropdown panjang. Opsi yang dinonaktifkan
       saringSubItem() otomatis tidak muncul di panel: TesysSearchableSelect
       membangun daftarnya setiap kali dibuka dan melewati option disabled. */
    function pasangPencarian($scope) {
        if (typeof TesysSearchableSelect === 'undefined') return;
        TesysSearchableSelect.init(($scope || $('#mdlTtPlan')).find('select[data-searchable]'), {
            searchPlaceholder: 'Ketik untuk mencari...',
        });
    }

    $('#fldPlanBudget').on('change', saringSubItem);
    saringSubItem();
    pasangPencarian();

    $('#ttCostAdd').on('click', function () {
        var $baru = $('#ttCostRows .tt-cost-row').first().clone();
        $baru.find('select').val('');
        $baru.find('input').val('');
        $baru.find('.tt-cost-qty').val(1);
        $baru.find('.tt-cost-total').text('0');
        /* Baris baru adalah kloningan baris pertama, termasuk tombol pencarian
           yang sudah terpasang di sana. Tombol salinan itu dibuang dan penanda
           'tss-init' dilepas supaya select-nya dipasangi tombol baru miliknya
           sendiri — bukan tombol yang menunjuk select baris lain. */
        $baru.find('.tss-btn').remove();
        $baru.find('select[data-searchable]').removeData('tss-init').show();
        $('#ttCostRows').append($baru);
        nomoriBiaya();
        saringSubItem();
        pasangPencarian($baru);
        hitungBiaya();
    });

    $('#ttCostRows').on('click', '.tt-cost-del', function () {
        // Baris terakhir tidak dihapus: biaya wajib minimal satu baris.
        if ($('#ttCostRows .tt-cost-row').length <= 1) { $(this).closest('tr').find('input').val(''); $(this).closest('tr').find('select').val(''); }
        else { $(this).closest('tr').remove(); nomoriBiaya(); }
        hitungBiaya();
    });

    $('#ttCostRows').on('input change', '.tt-cost-amount, .tt-cost-qty', hitungBiaya);
    hitungBiaya();

    /* Blok ini HARUS berada sesudah rupiah()/angkaUang()/tulisUang()
       didefinisikan: keduanya var, bukan function, jadi nilainya belum
       ada bila dipanggil lebih awal — dan satu error di sini membuat
       seluruh skrip halaman berhenti, termasuk yang menata panelnya. */
    /* ---------- Settlement: variance & tanda tangan ---------- */
    (function () {
        var $form = $('#frmTtSettle');
        if (!$form.length) return;

        function hitungSettle() {
            var settle = 0, request = 0;
            $('.tt-settle-row').each(function () {
                var $r = $(this);
                var req = parseInt($r.find('.tt-settle-req').data('req'), 10) || 0;
                var amt = angkaUang($r.find('.tt-settle-amount').val());
                settle += amt; request += req;
                $r.find('.tt-settle-var').text(rupiah.format(amt - req));
            });
            $('#ttSettleTotal').text(rupiah.format(settle));
            $('#ttSettleVar').text(rupiah.format(settle - request));
        }

        $form.on('input', '.tt-settle-amount', function () {
            this.value = tulisUang(this.value);
            hitungSettle();
            periksaLampiran();
        });
        hitungSettle();

        /* ---------- HP: nominal Settle membesar saat diisi ---------- */
        /* Kolom Settle terlalu sempit di layar HP sehingga angka yang sedang
           diketik terpotong. Input yang difokus diangkat ke tengah layar
           (kelas .tt-amount-zoom); selesai dengan mengetuk di mana saja di
           luar input — ketukan itu melepas fokus, dan blur yang
           mengembalikannya ke dalam tabel (20 Okt 2026). Tanpa latar gelap:
           isi modal di belakangnya tetap harus terbaca. */
        function diHp() {
            return window.matchMedia('(max-width: 991.98px)').matches;
        }

        function tutupZoom() {
            $('.tt-settle-amount.tt-amount-zoom').removeClass('tt-amount-zoom').css({ top: '', left: '' });
        }

        $form.on('focus', '.tt-settle-amount', function () {
            if (!diHp()) return;
            tutupZoom();

            /* Kotak besar muncul TEPAT di baris yang sedang diisi dan melebar
               KE KIRI dari tepi kanan input aslinya — tombol Lampiran &
               E-sign di sebelah kanannya tetap terlihat dan bisa ditekan.
               Ukuran input asli diambil dulu: setelah kelasnya dipasang,
               input sudah keluar dari alur dan ukurannya bukan ukuran selnya
               lagi. */
            var kotak = this.getBoundingClientRect();

            $(this).addClass('tt-amount-zoom');

            var besar  = this.getBoundingClientRect();
            var tinggi = besar.height || 44;
            var atas   = Math.max(8, kotak.top + (kotak.height / 2) - (tinggi / 2));

            $(this).css({
                top: Math.min(atas, window.innerHeight - tinggi - 8) + 'px',
                left: Math.max(8, kotak.right - besar.width) + 'px'
            });
        });

        $form.on('blur', '.tt-settle-amount', tutupZoom);

        /* Modal ditutup selagi input masih fokus (mis. lewat tombol silang):
           tanpa ini latar gelapnya bisa tertinggal di layar. */
        $('#mdlTtSettle').on('hide.bs.modal', tutupZoom);

        /* Jumlah berkas terpilih ditulis di bawah ikon — tanpa itu ikon
           klip tidak pernah menunjukkan apakah lampirannya sudah diisi. */
        $form.on('change', '.tt-settle-file', function () {
            var n = this.files ? this.files.length : 0;
            $(this).closest('td').find('.tt-file-count').text(n ? n + ' berkas' : '');
            periksaLampiran();
        });

        /* ---------- Bukti wajib per baris ---------- */
        /* Dua aturan (20 Okt 2026):
             a. Baris bernilai > 0 yang SUDAH di-e-sign wajib berlampiran —
                tanda tangan penerima dana tanpa bukti belanja tidak bisa
                dipertanggungjawabkan.
             b. Baris yang VARIANCE-nya tidak nol (Settle beda dari Request)
                wajib berlampiran DAN di-e-sign — selisih uang harus berbukti
                dan diakui penerimanya.
           Penegakannya lewat setCustomValidity pada input berkas baris itu:
           tt-submit-once memeriksa checkValidity() lebih dulu, sehingga form
           tidak terkirim dan tombolnya pun tidak terkunci berputar. Input
           berkas dipakai sebagai penampung pesan untuk KEDUA aturan karena
           input tanda tangannya type=hidden — elemen hidden tidak ikut
           constraint validation sama sekali. Pesannya ditulis sendiri di bawah
           tabel: input berkasnya d-none, jadi balon bawaan peramban tidak bisa
           muncul. Aturan yang sama ditegakkan ulang di server. */
        function punyaLampiran($r) {
            var inp = $r.find('.tt-settle-file')[0];
            if (inp && inp.files && inp.files.length) return true;

            // Lampiran dari simpanan sebelumnya ikut dihitung.
            return /tersimpan/.test($r.find('.tt-file-count').text());
        }

        function sudahTtd($r) {
            return !!$r.find('.tt-esign-value').val()
                || /tertanda/.test($r.find('.tt-esign-state').text());
        }

        function periksaLampiran() {
            var kurangLampiran = [], kurangTtd = [];

            $('.tt-settle-row').each(function (i) {
                var $r = $(this);
                var amt = angkaUang($r.find('.tt-settle-amount').val());
                var req = parseInt($r.find('.tt-settle-req').data('req'), 10) || 0;
                var adaSelisih = amt !== req;
                var ttd = sudahTtd($r);

                var lampiranKurang = (adaSelisih || (amt > 0 && ttd)) && !punyaLampiran($r);
                var ttdKurang      = adaSelisih && !ttd;
                var inp            = $r.find('.tt-settle-file')[0];

                if (inp && inp.setCustomValidity) {
                    inp.setCustomValidity(lampiranKurang || ttdKurang ? 'Bukti baris ini belum lengkap.' : '');
                }

                $r.find('.tt-icon-btn').first()
                    .toggleClass('btn-outline-danger', lampiranKurang)
                    .toggleClass('btn-outline-secondary', !lampiranKurang);

                // Tombol e-sign yang sudah hijau (tertanda) tidak diwarnai ulang.
                $r.find('.tt-esign-btn').not('.btn-success')
                    .toggleClass('btn-outline-danger', ttdKurang)
                    .toggleClass('btn-outline-secondary', !ttdKurang);

                if (lampiranKurang) kurangLampiran.push(i + 1);
                if (ttdKurang) kurangTtd.push(i + 1);
            });

            var pesan = [];
            if (kurangLampiran.length) pesan.push('Lampiran belum diisi pada baris no. ' + kurangLampiran.join(', ') + '.');
            if (kurangTtd.length) pesan.push('E-sign belum diisi pada baris no. ' + kurangTtd.join(', ') + '.');

            $('#ttSettleErr').toggleClass('d-none', !pesan.length).text(pesan.join(' '));

            return !pesan.length;
        }

        /* Penjaga terakhir sebelum form terkirim. setCustomValidity saja tidak
           cukup diandalkan: input berkasnya d-none, dan peramban yang tidak
           bisa menyorot kontrol tak terlihat berbeda-beda sikapnya. Pendengar
           ini dipasang pada fase CAPTURE di document, jadi ia berjalan sebelum
           handler submit mana pun yang terpasang pada form — termasuk
           tt-submit-once — sehingga tombolnya tidak pernah terkunci berputar
           saat pengiriman dibatalkan (20 Okt 2026). */
        document.addEventListener('submit', function (e) {
            if (e.target !== $form[0]) return;
            if (periksaLampiran()) return;

            e.preventDefault();
            e.stopPropagation();
            $('#ttSettleErr')[0].scrollIntoView({ block: 'nearest' });
        }, true);

        /* ---------- E-sign per baris ---------- */
        /* Satu kanvas dipakai bergantian; baris yang sedang ditandatangani
           disimpan di $barisEsign. Coretan baru ditulis ke input tersembunyi
           baris itu saat "Upload" ditekan, dan berkasnya baru dibuat server
           ketika settlement disimpan. */
        var pad = document.getElementById('ttSignPad');
        var ctx = pad.getContext('2d');
        var menggambar = false, adaCoretan = false;
        var $barisEsign = null;

        function siapkanPad() {
            var lebar = pad.parentElement.clientWidth;
            if (lebar && pad.width !== lebar) {
                pad.width = lebar;
                pad.height = 140;
            }
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#212529';
        }

        function titik(e) {
            var kotak = pad.getBoundingClientRect();
            var t = e.touches ? e.touches[0] : e;

            return { x: t.clientX - kotak.left, y: t.clientY - kotak.top };
        }

        function mulai(e) { e.preventDefault(); menggambar = true; var p = titik(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        function gerak(e) { if (!menggambar) return; e.preventDefault(); var p = titik(e); ctx.lineTo(p.x, p.y); ctx.stroke(); adaCoretan = true; }
        function henti() { menggambar = false; }

        pad.addEventListener('mousedown', mulai);
        pad.addEventListener('mousemove', gerak);
        document.addEventListener('mouseup', henti);
        pad.addEventListener('touchstart', mulai, { passive: false });
        pad.addEventListener('touchmove', gerak, { passive: false });
        pad.addEventListener('touchend', henti);

        function kosongkanPad() {
            ctx.clearRect(0, 0, pad.width, pad.height);
            adaCoretan = false;
        }

        /* Satu modal saja yang terlihat: modal Settlement ditutup dulu, pad
           e-sign dibuka setelah penutupannya selesai, lalu Settlement dibuka
           lagi begitu pad ditutup — baik lewat Upload maupun Batal/silang
           (20 Okt 2026). Menumpuk dua modal Bootstrap 4 menyisakan backdrop
           ganda dan kunci gulir yang tidak dilepas. */
        var kembaliKeSettle = false;

        $form.on('click', '.tt-esign-btn', function () {
            $barisEsign = $(this).closest('tr');
            $('#ttEsignRowLabel').text($barisEsign.find('td').first().text().trim());
            kosongkanPad();
            kembaliKeSettle = true;
            $('#mdlTtSettle')
                .one('hidden.bs.modal', function () { $('#mdlTtEsign').modal('show'); })
                .modal('hide');
        });

        $('#mdlTtEsign').on('hidden.bs.modal', function () {
            if (!kembaliKeSettle) return;
            kembaliKeSettle = false;
            $('#mdlTtSettle').modal('show');
        });

        $('#mdlTtEsign').on('shown.bs.modal', function () {
            siapkanPad();
            kosongkanPad();
        });

        $('#ttSignClear').on('click', kosongkanPad);

        $('#ttSignSave').on('click', function () {
            if (!adaCoretan || !$barisEsign) return;

            $barisEsign.find('.tt-esign-value').val(pad.toDataURL('image/png'));
            $barisEsign.find('.tt-esign-state').text('tertanda');
            $barisEsign.find('.tt-esign-btn').removeClass('btn-outline-secondary').addClass('btn-success');
            $('#mdlTtEsign').modal('hide');
            periksaLampiran();
        });

        // Keadaan awal: draft yang sudah di-e-sign tapi belum berlampiran
        // langsung terlihat, tidak menunggu baris itu disentuh.
        periksaLampiran();
    })();

    /* ---------- Desktop: semua muat dalam satu layar ---------- */
    /* Kelas dipasang di <html> DAN <body>: scrollbar halaman digambar pada
       elemen yang meluap, jadi mengunci salah satunya saja masih menyisakan
       batang gulir kosong di tepi kanan (20 Okt 2026). */
    function kunciGulir(aktif) {
        [document.documentElement, document.body].forEach(function (el) {
            el.classList.toggle('tt-detail-open', aktif);
        });
    }

    function fitDetail() {
        var el = document.getElementById('ttDetail');
        if (!el) return;
        var desktop = window.matchMedia('(min-width: 992px)').matches;
        if (desktop) {
            /*
             * Tinggi pas sampai dasar layar: ukur dulu ruang yang ada DI BAWAH
             * area detail (padding .content, footer layout, dll.) dengan cara
             * membuat halaman sengaja meluap, lalu sisakan tepat ruang itu.
             * Tanpa margin tebakan, jadi tidak ada celah kosong maupun scrollbar.
             */
            // Kunci gulir dilepas dulu supaya pengukuran di bawah tetap
            // melihat luapan halaman yang sengaja dibuat.
            kunciGulir(false);
            el.style.height = (window.innerHeight * 2) + 'px';
            var atas  = el.getBoundingClientRect().top + window.scrollY;
            var bawah = document.documentElement.scrollHeight - (atas + el.offsetHeight);
            el.style.height = Math.max(360, Math.floor(window.innerHeight - atas - Math.max(0, bawah))) + 'px';
            // Halaman sudah pas setinggi layar: batang gulir halaman dimatikan
            // supaya sisa pembulatan tidak memunculkan scrollbar kosong.
            kunciGulir(true);
        } else {
            el.style.height = '';
            // Di HP halaman tetap menggulir seperti biasa.
            kunciGulir(false);
        }
    }
    fitDetail();
    $(window).on('resize', fitDetail);
    scrollBottom();
});
</script>
@endpush
