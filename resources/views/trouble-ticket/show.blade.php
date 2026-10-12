@extends('layouts.app')

@section('title', $ticket->ticket_number)

@php
    use App\Models\TroubleTicket;
    use App\Models\TroubleTicketAttachment;
    use App\Services\TroubleTicketWorkflow;

    $t = $ticket;
    $site = $t->site;
    $fmt = fn ($dt) => $dt ? $dt->format('d/m/Y H:i:s') : '—';
    $dur = fn ($s) => TroubleTicket::formatDuration($s);
    $targetLabel = fn ($menit) => $menit ? $dur($menit * 60) : 'Belum diatur';

    // Tombol teknisi — cermin gerbang di TroubleTicketWorkflow (server tetap memeriksa ulang).
    $bisaMulai   = $isTechnician && $t->status === 'ON_PLAN';
    $bisaTutup   = $isTechnician && $t->status === 'IN_PROGRESS';
    $bisaAlihkan = $canSupervise && in_array($t->status, TroubleTicketWorkflow::REASSIGNABLE, true);
    $bisaKoreksi = $canSupervise && in_array($t->status, ['APPROVED', 'ON_PLAN'], true);
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
            <span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
        </div>
        @php $actionVars = compact('t', 'bisaMulai', 'bisaTutup', 'canReviewPlan', 'bisaAlihkan', 'bisaKoreksi'); @endphp

        {{-- Desktop: chat kiri + satu kartu informasi bertab di kanan.
             Mobile (< 992px): satu panel tampil bergantian lewat bilah bawah
             Chat | Info | Action (lihat .tt-mbar). --}}
        <div class="row tt-panes" id="ttPanes" data-pane="chat">
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
                                <i class="fas fa-lock mr-1"></i> {{ $t->isFinal() ? 'Tiket sudah ' . strtolower(\App\Support\WorkflowStatus::label($t->status, 'trouble-ticket')) . '; diskusi ditutup.' : 'Anda tidak dapat mengirim pesan pada tiket ini.' }}
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
                        $mapUrl = ($site?->latitude !== null && $site?->longitude !== null)
                            ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($site->latitude . ',' . $site->longitude)
                            : null;
                        $slaRows = [
                            'response'   => ['Response', 'Tiket dibuat → acknowledged', $t->sla_response_minutes, $t->created_at, $t->acknowledged_at],
                            // Sama dengan TroubleTicket::slaMetric('travel'): ditugaskan → start progress.
                            'travel'     => ['Travel', 'Ditugaskan → start progress', $t->sla_travel_minutes, $t->assigned_at, $t->progress_started_at],
                            'resolution' => ['Resolution', 'Start progress → closed', $t->sla_resolution_minutes, $t->progress_started_at, $t->closed_at],
                        ];
                        $nonVisit = $t->visit_decision === TroubleTicket::NON_VISIT;
                        $ikonLog = [
                            'created' => 'fa-plus', 'assigned' => 'fa-user-plus', 'reassigned' => 'fa-exchange-alt',
                            'responded' => 'fa-eye', 'plan_submitted' => 'fa-route', 'plan_corrected' => 'fa-edit',
                            'plan_approved' => 'fa-check', 'plan_rejected' => 'fa-times', 'progress_started' => 'fa-tools',
                            'closed' => 'fa-flag-checkered', 'departed' => 'fa-car', 'arrived' => 'fa-map-marker-alt',
                        ];
                    @endphp
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs nav-fill tt-tabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#ttTabSummary" role="tab"><i class="fas fa-th-large"></i><span>Summary</span></a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttTabSite" role="tab"><i class="fas fa-broadcast-tower"></i><span>Site</span></a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttTabTiket" role="tab"><i class="fas fa-ticket-alt"></i><span>Tiket</span></a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttTabSla" role="tab"><i class="fas fa-stopwatch"></i><span>SLA</span></a></li>
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
                                    @if($mapUrl)<a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-xs" title="Buka peta"><i class="fas fa-map-marked-alt"></i></a>@endif
                                    <button type="button" class="btn btn-outline-secondary btn-xs" data-tt-tab="#ttTabSite" title="Detail site"><i class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>

                            <div class="tt-person">
                                <span class="tt-avatar">{{ mb_strtoupper(mb_substr($t->technician?->name ?? '?', 0, 1)) }}</span>
                                <div class="tt-person-main">
                                    <div class="tt-person-name">{{ $t->technician?->name ?? '—' }}</div>
                                    <div class="tt-person-sub">Teknisi · {{ $supervisors->pluck('name')->implode(', ') ?: 'leader belum ada' }}</div>
                                </div>
                                @if($t->technician?->phone)
                                    <a href="tel:{{ $t->technician->phone }}" class="btn btn-outline-secondary btn-xs" title="Telepon"><i class="fas fa-phone"></i></a>
                                    @if($wa($t->technician->phone))<a href="{{ $wa($t->technician->phone) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-xs" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>@endif
                                @endif
                            </div>

                            <div class="tt-chips">
                                <span class="tt-chip"><i class="fas fa-bug"></i>{{ $t->problem?->name ?? 'Problem —' }}</span>
                                <span class="tt-chip tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
                                <span class="tt-chip" title="Aging sejak Date Open"><i class="far fa-clock"></i>{{ $t->aging_days }} hari</span>
                                @if($t->device_label)<span class="tt-chip"><i class="fas fa-hdd"></i>{{ $t->device_label }}</span>@endif
                                @if($t->flag_alarm)<span class="tt-chip"><i class="fas fa-bell"></i>{{ $t->flag_alarm }}</span>@endif
                            </div>
                            <div class="tt-issue">{{ $t->issue_label }}</div>

                            <div class="tt-sum-sla">
                                @foreach($slaRows as $key => [$label])
                                    <button type="button" class="tt-sla-card" data-tt-tab="#ttTabSla" title="Lihat detail SLA">
                                        <span class="tt-sla-card-label">{{ $label }}</span>
                                        @if($key === 'travel' && $nonVisit)
                                            <span class="tt-sla tt-sla-na">n/a</span>
                                        @else
                                            @include('trouble-ticket._sla', ['m' => $t->slaMetric($key), 'compact' => true])
                                        @endif
                                    </button>
                                @endforeach
                            </div>

                            <div class="tt-next">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>{{ $t->visit_decision_label ?? 'Plan belum diajukan' }}</strong>{{ $t->visit_site ? ' · kunjungan ' . $t->visit_site->format('d/m/Y') : '' }}{{ $t->target_online ? ' · target online ' . $t->target_online->format('d/m/Y') : '' }}
                                    @if($ket = \App\Support\WorkflowStatus::description($t->status, 'trouble-ticket'))<br><span class="text-muted">{{ $ket }}</span>@endif
                                </span>
                            </div>
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
                                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="tt-copy" title="Buka peta"><i class="fas fa-map-marked-alt"></i></a>
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
                                    <div class="tt-kv"><dt>Date Open</dt><dd>{{ $fmt($t->reported_at) }}</dd></div>
                                    <div class="tt-kv"><dt>Date Update</dt><dd>{{ $fmt($t->updated_at) }}</dd></div>
                                    <div class="tt-kv"><dt>Dibuat oleh</dt><dd>{{ $t->creator?->name }} · {{ $fmt($t->created_at) }}</dd></div>
                                </dl>
                            </details>
                            <details class="tt-acc" open>
                                <summary>Gangguan</summary>
                                <dl class="m-0">
                                    <div class="tt-kv"><dt>Problem</dt><dd>{{ $t->problem?->name ?? '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Aging</dt><dd>{{ $t->aging_days }} hari</dd></div>
                                    <div class="tt-kv"><dt>Severity</dt><dd><span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
                                        <small class="text-muted ml-1">otomatis dari aging</small></dd></div>
                                    <div class="tt-kv"><dt>Initial Problem</dt><dd>{{ $t->issue_label ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca_label ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Sub-RCA</dt><dd>{{ $t->sub_rca_label ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Device</dt><dd>{{ $t->device_label ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Flag alarm</dt><dd>{{ $t->flag_alarm ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Deskripsi</dt><dd style="white-space:pre-wrap;">{{ $t->description }}</dd></div>
                                </dl>
                            </details>
                            <details class="tt-acc">
                                <summary>Penugasan</summary>
                                <dl class="m-0">
                                    <div class="tt-kv"><dt>Teknisi</dt><dd>{{ $t->technician?->name }} · {{ $fmt($t->assigned_at) }}</dd></div>
                                    <div class="tt-kv"><dt>Leader team</dt><dd>{{ $supervisors->pluck('name')->implode(', ') ?: 'Belum ada (superadmin)' }}</dd></div>
                                    <div class="tt-kv"><dt>Acknowledged</dt><dd>{{ $fmt($t->acknowledged_at) }}{{ $t->acknowledger ? ' · ' . $t->acknowledger->name : '' }}</dd></div>
                                    @if($t->assignments->count() > 1)
                                        <div class="tt-kv"><dt>Riwayat</dt><dd>
                                            @foreach($t->assignments as $a)
                                                <div style="font-size:12px;">{{ $a->technician?->name }} · {{ $a->assigned_at->format('d/m H:i') }}{{ $a->unassigned_at ? ' – ' . $a->unassigned_at->format('d/m H:i') : ' (aktif)' }}
                                                    @if($a->reason)<span class="text-muted">— {{ $a->reason }}</span>@endif</div>
                                            @endforeach
                                        </dd></div>
                                    @endif
                                </dl>
                            </details>
                            <details class="tt-acc">
                                <summary>Plan</summary>
                                <dl class="m-0">
                                    <div class="tt-kv"><dt>Keputusan</dt><dd>{{ $t->visit_decision_label ?? 'Belum diajukan' }}
                                        @if($t->visit_decided_at)<div class="text-muted" style="font-size:12px;">{{ $t->decider?->name }} · {{ $fmt($t->visit_decided_at) }}</div>@endif
                                        @if($t->visit_decision_reason)<div style="white-space:pre-wrap;font-size:12px;">{{ $t->visit_decision_reason }}</div>@endif
                                    </dd></div>
                                    @if($t->visit_decision === TroubleTicket::VISIT)
                                        <div class="tt-kv"><dt>Rencana kunjungan</dt><dd>{{ $t->visit_site?->format('d/m/Y') ?? '—' }}</dd></div>
                                    @endif
                                    <div class="tt-kv"><dt>Target online</dt><dd>{{ $t->target_online?->format('d/m/Y') ?? '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Approval</dt><dd>{{ $t->approval ? ucfirst(strtolower($t->approval)) : '—' }}
                                        @if($t->plan_approved_at)<div class="text-muted" style="font-size:12px;">{{ $t->planApprover?->name }} · {{ $fmt($t->plan_approved_at) }}</div>@endif
                                    </dd></div>
                                </dl>
                            </details>
                            <details class="tt-acc" @if($t->closed_at) open @endif>
                                <summary>Penyelesaian</summary>
                                <dl class="m-0">
                                    <div class="tt-kv"><dt>Mulai progress</dt><dd>{{ $fmt($t->progress_started_at) }}</dd></div>
                                    <div class="tt-kv"><dt>Closed</dt><dd>{{ $fmt($t->closed_at) }}{{ $t->closer ? ' · ' . $t->closer->name : '' }}</dd></div>
                                    <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca ?: '—' }}{{ $t->sub_rca ? ' / ' . $t->sub_rca : '' }}</dd></div>
                                    <div class="tt-kv"><dt>Action</dt><dd>{{ $t->action ?: '—' }}</dd></div>
                                    <div class="tt-kv"><dt>Catatan</dt><dd style="white-space:pre-wrap;">{{ $t->resolution_summary ?: '—' }}</dd></div>
                                </dl>
                            </details>
                        </div>

                        {{-- ================= SLA ================= --}}
                        <div class="tab-pane fade" id="ttTabSla" role="tabpanel">
                            @foreach($slaRows as $key => [$label, $rumus, $target, $mulai, $selesai])
                                @php
                                    $m = $t->slaMetric($key);
                                    $pct = ($target && $m['seconds'] !== null) ? min(100, round($m['seconds'] / ($target * 60) * 100)) : null;
                                @endphp
                                <div class="tt-slab">
                                    <div class="tt-slab-head">
                                        <strong>{{ $label }} time</strong>
                                        @if($key === 'travel' && $nonVisit)
                                            <span class="text-muted">Tidak berlaku (Non-Visit)</span>
                                        @else
                                            @include('trouble-ticket._sla', ['m' => $m])
                                        @endif
                                    </div>
                                    @unless($key === 'travel' && $nonVisit)
                                        @if($pct !== null)
                                            <div class="tt-bar"><span class="tt-bar-fill tt-bar-{{ $m['level'] }}" style="width:{{ $pct }}%"
                                                @if($m['state'] === 'running') data-since="{{ $m['since']->toIso8601String() }}" data-target="{{ $target * 60 }}" @endif></span></div>
                                        @endif
                                        <div class="tt-slab-meta">
                                            <span>{{ $rumus }}</span>
                                            <span>Target: <strong>{{ $targetLabel($target) }}</strong></span>
                                        </div>
                                        <div class="tt-slab-meta">
                                            <span>{{ $mulai ? $mulai->format('d/m H:i') : '—' }} → {{ $selesai ? $selesai->format('d/m H:i') : ($m['state'] === 'running' ? 'berjalan' : '—') }}</span>
                                        </div>
                                    @endunless
                                </div>
                            @endforeach
                            <div class="tt-slab-meta mt-1"><span>Tunggu approval</span><strong>{{ $dur($t->approvalWaitSeconds()) }}</strong></div>
                            @unless($t->sla_response_minutes || $t->sla_travel_minutes || $t->sla_resolution_minutes)
                                <div class="text-muted mt-2" style="font-size:11.5px;"><i class="fas fa-info-circle mr-1"></i>Belum ada target SLA saat tiket dibuat; durasi tetap dicatat, tanpa status terlambat.</div>
                            @endunless
                        </div>

                        {{-- ================= LOG ================= --}}
                        <div class="tab-pane fade" id="ttTabLog" role="tabpanel">
                            {{-- Terbaru di atas; +durasi = jarak dari kejadian sebelumnya (baris di bawahnya). --}}
                            <ol class="tt-steps">
                                @forelse($statusLog as $ev)
                                    <li class="tt-step" data-msg-id="{{ $ev->id }}" title="Klik untuk melihat di timeline chat">
                                        <span class="tt-step-ic"><i class="fas {{ $ikonLog[$ev->event] ?? 'fa-circle' }}"></i></span>
                                        <div class="tt-step-body">
                                            <div class="tt-step-head">
                                                <strong>{{ $ev->event_label }}</strong>
                                                @php $lebihLama = $statusLog[$loop->index + 1] ?? null; @endphp
                                                @if($lebihLama)<span class="tt-step-gap">+{{ $dur(max(0, $ev->created_at->getTimestamp() - $lebihLama->created_at->getTimestamp())) }}</span>@endif
                                            </div>
                                            <div class="tt-step-meta">{{ $ev->created_at?->format('d/m/Y H:i') }} · {{ $ev->actor_name ?? 'Sistem' }}</div>
                                            @if($ev->body)<div class="tt-step-note">{{ \Illuminate\Support\Str::limit($ev->body, 140) }}</div>@endif
                                        </div>
                                    </li>
                                @empty
                                    <x-empty title="Belum ada perubahan status" icon="fas fa-history" compact />
                                @endforelse
                            </ol>
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
                        @unless($bisaMulai || $bisaTutup || $canReviewPlan || $bisaAlihkan || $bisaKoreksi)
                            <x-empty title="Tidak ada aksi untuk Anda" message="Aksi tersedia sesuai peran dan status tiket." icon="fas fa-hand-pointer" compact />
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        {{-- MOBILE: bilah navigasi bawah --}}
        <nav class="tt-mbar" aria-label="Panel tiket">
            <button type="button" data-pane="chat" class="active"><i class="fas fa-comments"></i><span>Chat</span></button>
            <button type="button" data-pane="info"><i class="fas fa-info-circle"></i><span>Info</span></button>
            <button type="button" data-pane="action"><i class="fas fa-bolt"></i><span>Action</span></button>
        </nav>
    </div>
</div>

{{-- ===================== MODAL ===================== --}}

@if($mustDecide)
    {{-- Wajib: tidak bisa ditutup (tanpa tombol tutup, backdrop static, Esc mati).
         Server menolak aksi lain selama plan belum diajukan. --}}
    <div class="modal fade" id="mdlTtDecide" tabindex="-1" role="dialog" aria-labelledby="mdlTtDecideTitle" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="{{ route('trouble-ticket.plan.submit', $t) }}" id="frmTtDecide">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="mdlTtDecideTitle"><i class="fas fa-route mr-1"></i> Ajukan Plan Penanganan</h5>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <p class="mb-2">Respons Anda untuk <strong>{{ $t->ticket_number }}</strong> sudah tercatat. Ajukan rencana penanganan untuk disetujui leader team sebelum pengerjaan dimulai.</p>
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" class="custom-control-input" id="decVisit" name="decision" value="VISIT" @checked(old('decision') === 'VISIT')>
                        <label class="custom-control-label" for="decVisit"><strong>Plan Visit</strong> — saya akan mengunjungi site.</label>
                    </div>
                    <div class="custom-control custom-radio mb-3">
                        <input type="radio" class="custom-control-input" id="decNonVisit" name="decision" value="NON_VISIT" @checked(old('decision') === 'NON_VISIT')>
                        <label class="custom-control-label" for="decNonVisit"><strong>Non-Visit</strong> — troubleshooting jarak jauh / tanpa kunjungan.</label>
                    </div>
                    <label for="decReason" class="font-weight-bold mb-1">Alasan / rencana awal <span class="text-danger" id="decReasonReq" style="display:none;">*</span></label>
                    <textarea name="reason" id="decReason" class="form-control" rows="3" maxlength="1000">{{ old('reason') }}</textarea>
                    <small class="text-muted">Wajib untuk Non-Visit.</small>
                    <div class="form-row mt-2">
                        <div class="col-6" id="decVisitSiteWrap" style="display:none;">
                            <label for="decVisitSite" class="font-weight-bold mb-1">Rencana kunjungan <span class="text-danger">*</span></label>
                            <input type="date" name="visit_site" id="decVisitSite" class="form-control" min="{{ now()->toDateString() }}" value="{{ old('visit_site') }}">
                        </div>
                        <div class="col-6">
                            <label for="decTargetOnline" class="font-weight-bold mb-1">Target online</label>
                            <input type="date" name="target_online" id="decTargetOnline" class="form-control" min="{{ now()->toDateString() }}" value="{{ old('target_online') }}">
                        </div>
                    </div>
                    @error('decision')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    @error('reason')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    @error('visit_site')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    @error('target_online')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm" id="btnTtDecide" disabled><i class="fas fa-paper-plane mr-1"></i> Ajukan Plan</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($bisaTutup)
    <div class="modal fade" id="mdlTtClosure" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.close', $t) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-flag-checkered mr-1"></i> Tutup Tiket</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="form-row">
                        <div class="form-group col-md-5 mb-2">
                            <label class="font-weight-bold" for="fldRca">RCA <span class="text-danger">*</span></label>
                            <input type="text" name="rca" id="fldRca" class="form-control" maxlength="40" required value="{{ old('rca') }}" placeholder="mis. Power, Transmisi">
                        </div>
                        <div class="form-group col-md-7 mb-2">
                            <label class="font-weight-bold" for="fldSubRca">Sub RCA</label>
                            <input type="text" name="sub_rca" id="fldSubRca" class="form-control" maxlength="150" value="{{ old('sub_rca') }}">
                        </div>
                        <div class="form-group col-12 mb-2">
                            <label class="font-weight-bold" for="fldAction">Action <span class="text-danger">*</span></label>
                            <input type="text" name="action" id="fldAction" class="form-control" maxlength="150" required value="{{ old('action') }}" placeholder="Tindakan yang dilakukan">
                        </div>
                    </div>
                    <label class="font-weight-bold" for="fldResolution">Catatan resolusi</label>
                    <textarea name="resolution_summary" id="fldResolution" class="form-control" rows="3" maxlength="5000">{{ old('resolution_summary') }}</textarea>
                    <label class="font-weight-bold mt-2" for="fldClosureFiles">Bukti penyelesaian @if($t->problem?->requires_evidence)<span class="text-danger">* (wajib untuk problem ini)</span>@endif</label>
                    <input type="file" name="attachments[]" id="fldClosureFiles" class="form-control-file tt-file-check" multiple accept="{{ $acceptFiles }}" @if($t->problem?->requires_evidence) required @endif>
                    <small class="text-muted">Maks. 10 MB per berkas. Tiket langsung berstatus Closed setelah disimpan; tidak ada approval.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-flag-checkered mr-1"></i> Tutup Tiket</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($canReviewPlan)
    <div class="modal fade" id="mdlTtApprove" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.plan.approve', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check mr-1"></i> Setujui Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="mb-2"><span class="text-muted">Plan diajukan {{ $t->decider?->name }}:</span>
                        <div><strong>Keputusan:</strong> {{ $t->visit_decision_label }}</div>
                        @if($t->visit_site)<div><strong>Rencana kunjungan:</strong> {{ $t->visit_site->format('d/m/Y') }}</div>@endif
                        @if($t->target_online)<div><strong>Target online:</strong> {{ $t->target_online->format('d/m/Y') }}</div>@endif
                        @if($t->visit_decision_reason)<div style="white-space:pre-wrap;">{{ $t->visit_decision_reason }}</div>@endif
                    </div>
                    <label for="fldApproveNotes" class="font-weight-bold">Catatan (opsional)</label>
                    <textarea name="notes" id="fldApproveNotes" class="form-control" rows="2" maxlength="1000"></textarea>
                    <small class="text-muted">Tiket menjadi On Plan dan teknisi boleh memulai pengerjaan.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Setujui</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="mdlTtReject" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.plan.reject', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times mr-1"></i> Tolak Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <label for="fldRejectNotes" class="font-weight-bold">Alasan penolakan <span class="text-danger">*</span></label>
                    <textarea name="notes" id="fldRejectNotes" class="form-control" rows="3" maxlength="1000" required></textarea>
                    <small class="text-muted">Tiket kembali Open dan plan dikosongkan; teknisi mengajukan plan baru.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times mr-1"></i> Tolak</button>
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
                    <h5 class="modal-title"><i class="fas fa-exchange-alt mr-1"></i> Alihkan Teknisi</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    @if($reassignTo->isEmpty())
                        <x-empty title="Tidak ada teknisi lain" message="Tambahkan PIC internal lain di site ini lebih dulu." icon="fas fa-user-cog" compact />
                    @else
                        <label for="fldReassign" class="font-weight-bold">Teknisi baru <span class="text-danger">*</span></label>
                        <select name="assigned_to" id="fldReassign" class="form-control mb-2" required>
                            <option value="">— Pilih —</option>
                            @foreach($reassignTo as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                        <label for="fldReassignReason" class="font-weight-bold">Alasan <span class="text-danger">*</span></label>
                        <textarea name="reason" id="fldReassignReason" class="form-control" rows="2" maxlength="500" required></textarea>
                        <small class="text-muted">Respons pertama tiket tetap tercatat; penugasan lama disimpan di riwayat.</small>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($reassignTo->isEmpty())>Alihkan</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($bisaKoreksi)
    <div class="modal fade" id="mdlTtCorrect" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.plan.correct', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-1"></i> Koreksi Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <p class="mb-2">Saat ini: <strong>{{ $t->visit_decision_label }}</strong>. Keputusan lama tetap tersimpan di timeline.</p>
                    <select name="decision" id="fldCorrectDecision" class="form-control mb-2" required>
                        @foreach(TroubleTicket::VISIT_DECISIONS as $k => $v)
                            <option value="{{ $k }}" @selected($k !== $t->visit_decision)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <div id="fldCorrectVisitWrap" class="mb-2" style="display:none;">
                        <label for="fldCorrectVisit" class="font-weight-bold">Rencana kunjungan <span class="text-danger">*</span></label>
                        <input type="date" name="visit_site" id="fldCorrectVisit" class="form-control" min="{{ now()->toDateString() }}">
                    </div>
                    <label for="fldCorrectReason" class="font-weight-bold">Alasan koreksi <span class="text-danger">*</span></label>
                    <textarea name="reason" id="fldCorrectReason" class="form-control" rows="2" maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Koreksi</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
$(function () {
    var MAX_BYTES = {{ TroubleTicketAttachment::MAX_KB * 1024 }}, MAX_FILES = {{ TroubleTicketAttachment::MAX_FILES }};
    var pageStatus = @json($t->status);
    var messagesUrl = @json(route('trouble-ticket.messages', $t));
    var $list = $('#ttChatList'), $items = $('#ttChatItems');

    TtTimers.start(@json(now()->toIso8601String()));

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

    /* ---------- Modal keputusan wajib ---------- */
    var $decide = $('#mdlTtDecide');
    if ($decide.length) {
        $decide.modal({ backdrop: 'static', keyboard: false, show: true });
        // Jaga-jaga: bila ditutup oleh skrip lain, tampilkan lagi.
        $decide.on('hidden.bs.modal', function () { if (!$decide.data('saved')) $decide.modal('show'); });
        $decide.on('change', 'input[name=decision]', function () {
            var nonVisit = this.value === 'NON_VISIT';
            $('#decReasonReq').toggle(nonVisit);
            $('#decReason').prop('required', nonVisit);
            $('#decVisitSiteWrap').toggle(!nonVisit);
            $('#decVisitSite').prop('required', !nonVisit);
            $('#btnTtDecide').prop('disabled', false);
        });
        $decide.find('input[name=decision]:checked').trigger('change');
        $('#frmTtDecide').on('submit', function (e) {
            var v = $decide.find('input[name=decision]:checked').val();
            if (v === 'NON_VISIT' && !$.trim($('#decReason').val())) {
                e.preventDefault();
                $('#decReason').addClass('is-invalid').trigger('focus');
                return;
            }
            if (v === 'VISIT' && !$('#decVisitSite').val()) {
                e.preventDefault();
                $('#decVisitSite').addClass('is-invalid').trigger('focus');
                return;
            }
            $decide.data('saved', true);
            $('#btnTtDecide').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
        });
    }

    /* Koreksi keputusan: tanggal kunjungan hanya untuk Plan Visit. */
    $('#fldCorrectDecision').on('change', function () {
        var visit = this.value === 'VISIT';
        $('#fldCorrectVisitWrap').toggle(visit);
        $('#fldCorrectVisit').prop('required', visit);
    }).trigger('change');

    /* ---------- Konfirmasi aksi & cegah kirim ganda ---------- */
    $('form.tt-confirm').on('submit', function (e) {
        var form = this;
        if (form.dataset.ok) return;
        e.preventDefault();
        Swal.fire({
            title: form.dataset.title, text: form.dataset.text, icon: 'question',
            showCancelButton: true, confirmButtonText: 'Ya, lanjutkan', cancelButtonText: 'Batal',
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || undefined
        }).then(function (r) {
            if (!r.isConfirmed) return;
            form.dataset.ok = '1';
            $(form).find('button').prop('disabled', true).prepend('<i class="fas fa-spinner fa-spin mr-1"></i>');
            form.submit();
        });
    });
    $('form.tt-submit-once').on('submit', function () {
        if (this.checkValidity && !this.checkValidity()) return;
        $(this).find('button[type=submit]').prop('disabled', true).prepend('<i class="fas fa-spinner fa-spin mr-1"></i>');
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
            if (res.status !== pageStatus) $('#ttStaleNotice').show();
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

    /* ---------- Interaksi kartu info ---------- */
    // Lompat ke tab lain (kartu SLA di Summary, tombol detail site).
    $(document).on('click', '[data-tt-tab]', function () {
        $('.tt-tabs a[href="' + this.dataset.ttTab + '"]').tab('show');
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

    /* ---------- Desktop: semua muat dalam satu layar ---------- */
    function fitDetail() {
        var el = document.getElementById('ttDetail');
        if (!el) return;
        if (window.matchMedia('(min-width: 992px)').matches) {
            /*
             * Tinggi pas sampai dasar layar: ukur dulu ruang yang ada DI BAWAH
             * area detail (padding .content, footer layout, dll.) dengan cara
             * membuat halaman sengaja meluap, lalu sisakan tepat ruang itu.
             * Tanpa margin tebakan, jadi tidak ada celah kosong maupun scrollbar.
             */
            el.style.height = (window.innerHeight * 2) + 'px';
            var atas  = el.getBoundingClientRect().top + window.scrollY;
            var bawah = document.documentElement.scrollHeight - (atas + el.offsetHeight);
            el.style.height = Math.max(360, Math.floor(window.innerHeight - atas - Math.max(0, bawah))) + 'px';
        } else {
            el.style.height = '';
        }
    }
    fitDetail();
    $(window).on('resize', fitDetail);
    scrollBottom();
});
</script>
@endpush
