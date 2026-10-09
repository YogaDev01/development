@extends('layouts.app')

@section('title', $ticket->ticket_number)

@php
    use App\Models\TroubleTicket;
    use App\Models\TroubleTicketAttachment;
    use App\Services\TroubleTicketWorkflow;

    $t = $ticket;
    $site = $t->site;
    $visit = $t->latestVisit;
    $fmt = fn ($dt) => $dt ? $dt->format('d/m/Y H:i:s') : '—';
    $dur = fn ($s) => TroubleTicket::formatDuration($s);
    $targetLabel = fn ($menit) => $menit ? $dur($menit * 60) : 'Belum diatur';

    // Tombol teknisi — cermin gerbang di TroubleTicketWorkflow (server tetap memeriksa ulang).
    $bisaBerangkat = $isTechnician && $t->status === 'PLANNED' && $t->visit_decision === TroubleTicket::VISIT;
    $bisaTiba      = $isTechnician && $t->status === 'TRAVELING';
    $bisaMulai     = $isTechnician && (($t->status === 'ON_SITE' && $t->visit_decision === TroubleTicket::VISIT)
                        || ($t->status === 'PLANNED' && $t->visit_decision === TroubleTicket::NON_VISIT));
    $bisaAjukan    = $isTechnician && $t->status === 'IN_PROGRESS';
    $bisaAlihkan   = $canSupervise && in_array($t->status, TroubleTicketWorkflow::REASSIGNABLE, true);
    $bisaKoreksi   = $canSupervise && $t->status === 'PLANNED';
    $bisaBatal     = $canSupervise && in_array($t->status, TroubleTicketWorkflow::CANCELLABLE, true);
    $acceptFiles   = collect(TroubleTicketAttachment::EXTENSIONS)->map(fn ($e) => '.' . $e)->implode(',');
@endphp

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid">

        {{-- Kepala --}}
        <div class="d-flex justify-content-between align-items-start mb-2 mt-3 flex-wrap" style="gap:8px;">
            <div style="min-width:0;">
                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                    <a href="{{ route('trouble-ticket.index') }}" class="btn btn-outline-secondary btn-xs" title="Kembali ke daftar"><i class="fas fa-arrow-left"></i></a>
                    <h1 class="tt-title">{{ $t->ticket_number }}</h1>
                    <x-status :status="$t->status" context="trouble-ticket" size="md" />
                    <span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
                </div>
                <div class="tt-sub">{{ $site?->tid }} — {{ $site?->sitename }} · {{ $t->issue }}</div>
            </div>

            <div class="tt-actions d-flex flex-wrap" style="gap:6px;">
                @if($bisaBerangkat)
                    <form method="POST" action="{{ route('trouble-ticket.depart', $t) }}" class="tt-confirm" data-title="Berangkat ke site sekarang?" data-text="Waktu berangkat dicatat dari jam server dan tidak bisa diubah.">
                        @csrf <button class="btn btn-primary btn-sm"><i class="fas fa-car mr-1"></i> Berangkat ke Site</button>
                    </form>
                @endif
                @if($bisaTiba)
                    <form method="POST" action="{{ route('trouble-ticket.arrive', $t) }}" class="tt-confirm" data-title="Sudah tiba di site?" data-text="Waktu tiba dicatat dari jam server dan menghentikan timer perjalanan.">
                        @csrf <button class="btn btn-primary btn-sm"><i class="fas fa-map-marker-alt mr-1"></i> Tiba di Site</button>
                    </form>
                @endif
                @if($bisaMulai)
                    <form method="POST" action="{{ route('trouble-ticket.start', $t) }}" class="tt-confirm" data-title="Mulai troubleshooting?" data-text="Timer resolusi mulai berjalan sejak saat ini.">
                        @csrf <button class="btn btn-primary btn-sm"><i class="fas fa-tools mr-1"></i> Start Progress</button>
                    </form>
                @endif
                @if($bisaAjukan)
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#mdlTtClosure"><i class="fas fa-flag-checkered mr-1"></i> Ajukan Closed</button>
                @endif
                @if($canReview)
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#mdlTtApprove"><i class="fas fa-check mr-1"></i> Setujui Closed</button>
                    <button class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#mdlTtReject"><i class="fas fa-times mr-1"></i> Tolak</button>
                @endif
                @if($bisaAlihkan || $bisaKoreksi || $bisaBatal)
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-shield mr-1"></i> Leader
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" style="font-size:13px;">
                            @if($bisaAlihkan)<a class="dropdown-item" href="#" data-toggle="modal" data-target="#mdlTtReassign"><i class="fas fa-exchange-alt mr-2"></i>Alihkan Teknisi</a>@endif
                            @if($bisaKoreksi)<a class="dropdown-item" href="#" data-toggle="modal" data-target="#mdlTtCorrect"><i class="fas fa-edit mr-2"></i>Koreksi Keputusan Kunjungan</a>@endif
                            @if($bisaBatal)<a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#mdlTtCancel"><i class="fas fa-ban mr-2"></i>Batalkan Tiket</a>@endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="mb-3"><x-workflow-flow :status="$t->status" context="trouble-ticket" /></div>

        <div class="row">
            {{-- KIRI: timeline / chat --}}
            <div class="col-lg-7 mb-3">
                <div class="card shadow-sm mb-0">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <h3 class="card-title m-0"><i class="fas fa-comments mr-1"></i> Timeline & Diskusi</h3>
                        <span class="text-muted" style="font-size:11.5px;">Waktu server (WIB)</span>
                    </div>
                    <div class="tt-chat">
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
                                <textarea name="body" id="ttBody" class="form-control mb-2" maxlength="5000" rows="2" placeholder="Tulis pesan… (Ctrl+Enter untuk kirim)"></textarea>
                                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:6px;">
                                    <div class="d-flex align-items-center" style="gap:8px;min-width:0;">
                                        <label class="btn btn-outline-secondary btn-sm mb-0" title="Lampirkan berkas (maks. 10 MB per berkas)">
                                            <i class="fas fa-paperclip"></i>
                                            <input type="file" name="attachments[]" id="ttFiles" multiple hidden accept="{{ $acceptFiles }}">
                                        </label>
                                        <span class="tt-file-list text-truncate" id="ttFilesInfo"></span>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm" id="btnTtSend"><i class="fas fa-paper-plane mr-1"></i> Kirim</button>
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

            {{-- KANAN: informasi --}}
            <div class="col-lg-5">
                {{-- SLA --}}
                <div class="card shadow-sm tt-info-card mb-3">
                    <div class="card-header py-2"><h3 class="card-title m-0"><i class="fas fa-stopwatch mr-1"></i> SLA</h3></div>
                    <div class="card-body">
                        <dl class="m-0">
                            <div class="tt-kv"><dt>Response</dt><dd>@include('trouble-ticket._sla', ['m' => $t->slaMetric('response')]) <span class="text-muted">/ target {{ $targetLabel($t->sla_response_minutes) }}</span></dd></div>
                            <div class="tt-kv"><dt>Travel</dt><dd>
                                @if($t->visit_decision === TroubleTicket::NON_VISIT)
                                    <span class="text-muted">Tidak berlaku (Non-Visit)</span>
                                @else
                                    @include('trouble-ticket._sla', ['m' => $t->slaMetric('travel')]) <span class="text-muted">/ target {{ $targetLabel($t->sla_travel_minutes) }}</span>
                                @endif
                            </dd></div>
                            <div class="tt-kv"><dt>Resolution</dt><dd>@include('trouble-ticket._sla', ['m' => $t->slaMetric('resolution')]) <span class="text-muted">/ target {{ $targetLabel($t->sla_resolution_minutes) }}</span></dd></div>
                            <div class="tt-kv"><dt>Tunggu approval</dt><dd>{{ $dur($t->approvalWaitSeconds()) }}</dd></div>
                        </dl>
                        @unless($t->sla_response_minutes || $t->sla_travel_minutes || $t->sla_resolution_minutes)
                            <div class="text-muted mt-1" style="font-size:11.5px;"><i class="fas fa-info-circle mr-1"></i>Belum ada target SLA saat tiket dibuat; durasi tetap dicatat, tanpa status terlambat.</div>
                        @endunless
                    </div>
                </div>

                {{-- Tiket --}}
                <div class="card shadow-sm tt-info-card mb-3">
                    <div class="card-header py-2"><h3 class="card-title m-0"><i class="fas fa-ticket-alt mr-1"></i> Tiket</h3></div>
                    <div class="card-body">
                        <dl class="m-0">
                            <div class="tt-kv"><dt>No. TT</dt><dd><strong>{{ $t->ticket_number }}</strong></dd></div>
                            <div class="tt-kv"><dt>Ref. eksternal</dt><dd>{{ $t->external_ref ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Problem</dt><dd>{{ $t->problem?->name ?? '—' }}</dd></div>
                            <div class="tt-kv"><dt>Severity</dt><dd><span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span></dd></div>
                            <div class="tt-kv"><dt>Issue</dt><dd>{{ $t->issue }}</dd></div>
                            <div class="tt-kv"><dt>Device</dt><dd>{{ $t->device ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Flag alarm</dt><dd>{{ $t->flag_alarm ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Deskripsi</dt><dd style="white-space:pre-wrap;">{{ $t->description }}</dd></div>
                            <div class="tt-kv"><dt>Pelapor</dt><dd>{{ $t->reporter_name ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Dilaporkan</dt><dd>{{ $fmt($t->reported_at) }}</dd></div>
                            <div class="tt-kv"><dt>Dibuat oleh</dt><dd>{{ $t->creator?->name }} · {{ $fmt($t->created_at) }}</dd></div>
                            @if($t->status === 'CANCELLED')
                                <div class="tt-kv"><dt>Dibatalkan</dt><dd>{{ $t->canceller?->name }} · {{ $fmt($t->cancelled_at) }}<div class="text-muted">{{ $t->cancel_reason }}</div></dd></div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Site --}}
                <div class="card shadow-sm tt-info-card mb-3">
                    <div class="card-header py-2"><h3 class="card-title m-0"><i class="fas fa-broadcast-tower mr-1"></i> Site</h3></div>
                    <div class="card-body">
                        <dl class="m-0">
                            <div class="tt-kv"><dt>Site ID</dt><dd><strong>{{ $site?->tid }}</strong></dd></div>
                            <div class="tt-kv"><dt>Nama Site</dt><dd>{{ $site?->sitename }}</dd></div>
                            <div class="tt-kv"><dt>Customer</dt><dd>{{ $site?->customer?->name ?? '—' }}</dd></div>
                            <div class="tt-kv"><dt>Alamat</dt><dd>{{ $site?->address ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Koordinat</dt><dd>
                                @if($site?->latitude !== null && $site?->longitude !== null)
                                    {{ $site->latitude }}, {{ $site->longitude }}
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($site->latitude . ',' . $site->longitude) }}" target="_blank" rel="noopener" class="ml-1"><i class="fas fa-external-link-alt"></i> Peta</a>
                                @else
                                    —
                                @endif
                            </dd></div>
                            <div class="tt-kv"><dt>PIC Site</dt><dd>
                                @forelse($site?->picLinks ?? [] as $link)
                                    <div>{{ $link->pic?->pic }} <span class="text-muted">({{ \App\Models\MasterPic::TYPES[$link->pic?->type] ?? $link->pic?->type }})</span>
                                        @if($link->pic?->phone)· <a href="tel:{{ $link->pic->phone }}">{{ $link->pic->phone }}</a>@endif
                                    </div>
                                @empty
                                    —
                                @endforelse
                            </dd></div>
                        </dl>
                    </div>
                </div>

                {{-- Penugasan --}}
                <div class="card shadow-sm tt-info-card mb-3">
                    <div class="card-header py-2"><h3 class="card-title m-0"><i class="fas fa-user-cog mr-1"></i> Penugasan</h3></div>
                    <div class="card-body">
                        <dl class="m-0">
                            <div class="tt-kv"><dt>Teknisi</dt><dd><strong>{{ $t->technician?->name }}</strong>
                                @if($t->technician?->phone)· <a href="tel:{{ $t->technician->phone }}">{{ $t->technician->phone }}</a>@endif</dd></div>
                            <div class="tt-kv"><dt>Ditugaskan</dt><dd>{{ $fmt($t->assigned_at) }}</dd></div>
                            <div class="tt-kv"><dt>Leader team</dt><dd>{{ $supervisors->pluck('name')->implode(', ') ?: 'Belum ada (approval oleh superadmin)' }}</dd></div>
                            <div class="tt-kv"><dt>PIC (master)</dt><dd>{{ $t->pic?->pic ?? '—' }}</dd></div>
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
                    </div>
                </div>

                {{-- Kunjungan --}}
                <div class="card shadow-sm tt-info-card mb-3">
                    <div class="card-header py-2"><h3 class="card-title m-0"><i class="fas fa-route mr-1"></i> Kunjungan</h3></div>
                    <div class="card-body">
                        <dl class="m-0">
                            <div class="tt-kv"><dt>Keputusan</dt><dd>{{ $t->visit_decision_label ?? 'Belum dipilih' }}
                                @if($t->visit_decided_at)<div class="text-muted" style="font-size:12px;">{{ $t->decider?->name }} · {{ $fmt($t->visit_decided_at) }}</div>@endif
                                @if($t->visit_decision_reason)<div style="white-space:pre-wrap;font-size:12px;">{{ $t->visit_decision_reason }}</div>@endif
                            </dd></div>
                            @if($t->visit_decision === TroubleTicket::VISIT)
                                <div class="tt-kv"><dt>Rencana kunjungan</dt><dd>{{ $t->visit_site?->format('d/m/Y') ?? '—' }}</dd></div>
                            @endif
                            <div class="tt-kv"><dt>Target online</dt><dd>{{ $t->target_online?->format('d/m/Y') ?? '—' }}</dd></div>
                            @if($t->visit_decision !== TroubleTicket::NON_VISIT)
                                <div class="tt-kv"><dt>Berangkat</dt><dd>{{ $fmt($visit?->departed_at) }}</dd></div>
                                <div class="tt-kv"><dt>Tiba</dt><dd>{{ $fmt($visit?->arrived_at) }}</dd></div>
                                <div class="tt-kv"><dt>Status kunjungan</dt><dd>{{ ! $visit ? 'Belum berangkat' : ($visit->arrived_at ? 'Tiba di site' : 'Dalam perjalanan') }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Penyelesaian --}}
                <div class="card shadow-sm tt-info-card mb-3">
                    <div class="card-header py-2"><h3 class="card-title m-0"><i class="fas fa-flag-checkered mr-1"></i> Penyelesaian</h3></div>
                    <div class="card-body">
                        <dl class="m-0">
                            <div class="tt-kv"><dt>Mulai troubleshooting</dt><dd>{{ $fmt($t->progress_started_at) }}</dd></div>
                            <div class="tt-kv"><dt>Resolved (ajukan Closed)</dt><dd>{{ $fmt($t->resolved_at) }}</dd></div>
                            <div class="tt-kv"><dt>Approval</dt><dd>{{ $t->approval ? ucfirst(strtolower($t->approval)) : '—' }}</dd></div>
                            <div class="tt-kv"><dt>Closed</dt><dd>{{ $fmt($t->closed_at) }}{{ $t->closer ? ' · ' . $t->closer->name : '' }}</dd></div>
                            <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Sub RCA</dt><dd>{{ $t->sub_rca ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Action</dt><dd>{{ $t->action ?: '—' }}</dd></div>
                            <div class="tt-kv"><dt>Catatan resolusi</dt><dd style="white-space:pre-wrap;">{{ $t->resolution_summary ?: '—' }}</dd></div>
                        </dl>
                        @if($t->closureReviews->isNotEmpty())
                            <div class="mt-2" style="font-size:12px;">
                                <div class="font-weight-bold text-muted mb-1">Riwayat pengajuan</div>
                                @foreach($t->closureReviews as $r)
                                    <div class="py-1" style="border-top:1px solid var(--border-soft);">
                                        #{{ $r->attempt_no }} · {{ $r->requester?->name }} · {{ $r->requested_at->format('d/m/Y H:i') }} →
                                        <strong class="{{ $r->review_status === 'APPROVED' ? 'text-success' : ($r->review_status === 'REJECTED' ? 'text-danger' : '') }}">{{ ucfirst(strtolower($r->review_status)) }}</strong>
                                        @if($r->reviewed_at) oleh {{ $r->reviewer?->name }} · {{ $r->reviewed_at->format('d/m/Y H:i') }}@endif
                                        @if($r->rca)<div>RCA: {{ $r->rca }}{{ $r->sub_rca ? ' / ' . $r->sub_rca : '' }} · Action: {{ $r->action }}</div>@endif
                                        @if($r->review_notes)<div class="text-muted" style="white-space:pre-wrap;">{{ $r->review_notes }}</div>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== MODAL ===================== --}}

@if($mustDecide)
    {{-- Wajib: tidak bisa ditutup (tanpa tombol tutup, backdrop static, Esc mati).
         Server menolak aksi lain selama keputusan belum tercatat. --}}
    <div class="modal fade" id="mdlTtDecide" tabindex="-1" role="dialog" aria-labelledby="mdlTtDecideTitle" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="{{ route('trouble-ticket.decide', $t) }}" id="frmTtDecide">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="mdlTtDecideTitle"><i class="fas fa-route mr-1"></i> Rencana Penanganan</h5>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <p class="mb-2">Respons Anda untuk <strong>{{ $t->ticket_number }}</strong> sudah tercatat. Pilih cara penanganan sebelum melanjutkan — pilihan ini dikunci setelah disimpan.</p>
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" class="custom-control-input" id="decVisit" name="decision" value="VISIT" @checked(old('decision') === 'VISIT')>
                        <label class="custom-control-label" for="decVisit"><strong>Plan Visit</strong> — saya akan mengunjungi site. Timer perjalanan dimulai saat saya menekan "Berangkat ke Site".</label>
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
                    <button type="submit" class="btn btn-primary btn-sm" id="btnTtDecide" disabled><i class="fas fa-save mr-1"></i> Simpan Keputusan</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($bisaAjukan)
    <div class="modal fade" id="mdlTtClosure" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.closure.request', $t) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-flag-checkered mr-1"></i> Ajukan Closed</h5>
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
                    <small class="text-muted">Maks. 10 MB per berkas. Tiket akan menunggu approval leader team; Anda tidak bisa menutupnya sendiri.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-paper-plane mr-1"></i> Ajukan</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($canReview)
    <div class="modal fade" id="mdlTtApprove" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.closure.approve', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check mr-1"></i> Setujui Closed</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <div class="mb-2"><span class="text-muted">Pengajuan dari {{ $pendingReview?->requester?->name }}:</span>
                        <div><strong>RCA:</strong> {{ $pendingReview?->rca }}{{ $pendingReview?->sub_rca ? ' / ' . $pendingReview->sub_rca : '' }}</div>
                        <div><strong>Action:</strong> {{ $pendingReview?->action }}</div>
                        @if($pendingReview?->resolution_summary)<div style="white-space:pre-wrap;">{{ $pendingReview->resolution_summary }}</div>@endif
                    </div>
                    <label for="fldApproveNotes" class="font-weight-bold">Catatan (opsional)</label>
                    <textarea name="notes" id="fldApproveNotes" class="form-control" rows="2" maxlength="1000"></textarea>
                    <small class="text-muted">Waktu Closed dicatat dari jam server dan menutup timer resolusi.</small>
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
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.closure.reject', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times mr-1"></i> Tolak Pengajuan Closed</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <label for="fldRejectNotes" class="font-weight-bold">Alasan penolakan <span class="text-danger">*</span></label>
                    <textarea name="notes" id="fldRejectNotes" class="form-control" rows="3" maxlength="1000" required></textarea>
                    <small class="text-muted">Tiket kembali In Progress; waktu mulai troubleshooting tidak berubah.</small>
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
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.decision.correct', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-1"></i> Koreksi Keputusan Kunjungan</h5>
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

@if($bisaBatal)
    <div class="modal fade" id="mdlTtCancel" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content tt-submit-once" method="POST" action="{{ route('trouble-ticket.cancel', $t) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-ban mr-1"></i> Batalkan Tiket</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" style="font-size:13px;">
                    <label for="fldCancelReason" class="font-weight-bold">Alasan pembatalan <span class="text-danger">*</span></label>
                    <textarea name="reason" id="fldCancelReason" class="form-control" rows="2" maxlength="500" required></textarea>
                    <small class="text-muted">Tiket yang dibatalkan tidak bisa dibuka kembali.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Kembali</button>
                    <button type="submit" class="btn btn-danger btn-sm">Batalkan Tiket</button>
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

    /* ---------- Kirim pesan ---------- */
    var $form = $('#frmTtMessage');
    if ($form.length) {
        var token = newToken();
        function newToken() {
            if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        }
        $('#ttFiles').on('change', function () { checkFiles(this, $('#ttFilesInfo')); });
        $('#ttBody').on('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) $form.trigger('submit'); });

        $form.on('submit', function (e) {
            e.preventDefault();
            var $btn = $('#btnTtSend');
            if ($btn.prop('disabled')) return;
            var files = $('#ttFiles')[0].files;
            if (!$.trim($('#ttBody').val()) && !files.length) return;

            var fd = new FormData($form[0]);
            fd.append('client_token', token);
            fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
            $('#ttMsgError').hide();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({ url: $form.attr('action'), method: 'POST', data: fd, processData: false, contentType: false, headers: { Accept: 'application/json' } })
                .done(function (res) {
                    appendUnique(res.html);
                    scrollBottom();
                    $form[0].reset();
                    $('#ttFilesInfo').text('');
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
                .always(function () { $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Kirim'); });
        });
    }
});
</script>
@endpush
