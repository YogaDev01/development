{{-- Isi modal "Info" dari daftar Trouble Ticket (TroubleTicketController::info).
     Hanya membaca — membuka info TIDAK dihitung sebagai respons teknisi. --}}
@php
    use App\Models\TroubleTicket;
    $t = $ticket;
    $fmt = fn ($dt) => $dt ? $dt->format('d/m/Y H:i') : '—';
    $targetLabel = fn ($m) => $m ? TroubleTicket::formatDuration($m * 60) : 'Belum diatur';
@endphp
<div class="tt-info-modal" data-title="{{ $t->ticket_number }}">
    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:6px;">
        <x-status :status="$t->status" context="trouble-ticket" />
        <span class="tt-chip tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
        <span class="tt-chip">Aging {{ $t->aging_days }} hari</span>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="tt-sec-title mt-0">Gangguan</div>
            <dl class="m-0">
                <div class="tt-kv"><dt>Site ID</dt><dd><strong>{{ $t->site?->tid ?? '—' }}</strong></dd></div>
                <div class="tt-kv"><dt>Nama Site</dt><dd>{{ $t->site?->sitename ?? '—' }}</dd></div>
                <div class="tt-kv"><dt>Customer</dt><dd>{{ $t->site?->customer?->name ?? '—' }}</dd></div>
                <div class="tt-kv"><dt>Problem</dt><dd>{{ $t->problem?->name ?? '—' }}</dd></div>
                <div class="tt-kv"><dt>Initial Problem</dt><dd>{{ $t->issue_label ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>RCA / Sub-RCA</dt><dd>{{ $t->rca_label ?: '—' }}{{ $t->sub_rca_label ? ' · ' . $t->sub_rca_label : '' }}</dd></div>
                <div class="tt-kv"><dt>Device / Alarm</dt><dd>{{ $t->device_label ?: '—' }}{{ $t->flag_alarm ? ' · ' . $t->flag_alarm : '' }}</dd></div>
                <div class="tt-kv"><dt>Deskripsi</dt><dd style="white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($t->description, 400) }}</dd></div>
                <div class="tt-kv"><dt>Date Open</dt><dd>{{ $fmt($t->reported_at) }}</dd></div>
                <div class="tt-kv"><dt>Dibuat</dt><dd>{{ $t->creator?->name }} · {{ $fmt($t->created_at) }}</dd></div>
            </dl>
        </div>
        <div class="col-md-6">
            <div class="tt-sec-title mt-3 mt-md-0">Penanganan</div>
            <dl class="m-0">
                <div class="tt-kv"><dt>Teknisi</dt><dd>{{ $t->technician?->name ?? '—' }}{{ $t->technician?->phone ? ' · ' . $t->technician->phone : '' }}</dd></div>
                <div class="tt-kv"><dt>Leader team</dt><dd>{{ $supervisors->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Acknowledged</dt><dd>{{ $fmt($t->acknowledged_at) }}</dd></div>
                <div class="tt-kv"><dt>Plan</dt><dd>{{ $t->visit_decision_label ?? 'Belum diajukan' }}{{ $t->visit_site ? ' · ' . $t->visit_site->format('d/m/Y') : '' }}</dd></div>
                <div class="tt-kv"><dt>Target online</dt><dd>{{ $t->target_online?->format('d/m/Y') ?? '—' }}</dd></div>
                <div class="tt-kv"><dt>Closed</dt><dd>{{ $fmt($t->closed_at) }}{{ $t->closer ? ' · ' . $t->closer->name : '' }}</dd></div>
                <div class="tt-kv"><dt>RCA / Action</dt><dd>{{ $t->rca ?: '—' }}{{ $t->action ? ' · ' . $t->action : '' }}</dd></div>
            </dl>
            <div class="tt-sec-title">SLA</div>
            <div class="tt-sum-sla mt-1">
                @foreach(['response' => ['Response', $t->sla_response_minutes], 'travel' => ['Travel', $t->sla_travel_minutes], 'resolution' => ['Resolution', $t->sla_resolution_minutes]] as $key => [$label, $target])
                    <div class="tt-sla-card" style="cursor:default;">
                        <span class="tt-sla-card-label">{{ $label }}</span>
                        @include('trouble-ticket._sla', ['m' => $t->slaMetric($key), 'compact' => true])
                        <div class="text-muted" style="font-size:10.5px;">target {{ $targetLabel($target) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
