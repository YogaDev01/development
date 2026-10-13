{{-- Isi modal "Info" dari daftar Trouble Ticket (TroubleTicketController::info).
     Hanya membaca — membuka info TIDAK dihitung sebagai respons teknisi.

     Isi datanya sengaja disamakan dengan tab "Tiket" di halaman detail
     (show.blade.php: Identitas / Gangguan / Penyelesaian) supaya pembaca tidak
     menemukan dua ringkasan yang berbeda untuk tiket yang sama. --}}
@php
    use App\Models\TroubleTicket;
    $t = $ticket;
    $fmt = fn ($dt) => $dt ? $dt->format('d/m/Y H:i') : '—';
    $tgl = fn ($dt) => $dt ? $dt->format('d/m/Y') : '—';
    $targetLabel = fn ($m) => $m ? TroubleTicket::formatDuration($m * 60) : 'Tidak terhingga';
@endphp
<div class="tt-info-modal" data-title="{{ $t->ticket_number }}">
    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:6px;">
        <x-status :status="$t->status" context="trouble-ticket" />
        <span class="tt-chip tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
        <span class="tt-chip">Aging {{ $t->aging_days }} hari</span>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="tt-sec-title mt-0">Identitas</div>
            <dl class="m-0">
                <div class="tt-kv"><dt>No. TT</dt><dd><strong>{{ $t->ticket_number }}</strong></dd></div>
                <div class="tt-kv"><dt>Site</dt><dd>{{ $t->site?->sitename ?? '—' }}</dd></div>
                <div class="tt-kv"><dt>Date Open</dt><dd>{{ $fmt($t->reported_at) }}</dd></div>
                <div class="tt-kv"><dt>Dibuat oleh</dt><dd>{{ $t->creator?->name ?: '—' }}</dd></div>
                @if($t->visit_decision === TroubleTicket::VISIT)
                    <div class="tt-kv"><dt>Plan visit</dt><dd>{{ $tgl($t->visit_site) }}</dd></div>
                @endif
            </dl>

            <div class="tt-sec-title">Gangguan</div>
            <dl class="m-0">
                <div class="tt-kv"><dt>Aging</dt><dd>{{ $t->aging_days }} hari</dd></div>
                <div class="tt-kv"><dt>Severity</dt><dd><span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span></dd></div>
                {{-- Setelah tiket ditutup, RCA & Sub-RCA dibaca di Penyelesaian saja. --}}
                @unless($t->closed_at)
                    <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca_label ?: '—' }}</dd></div>
                    <div class="tt-kv"><dt>Sub-RCA</dt><dd>{{ $t->sub_rca_label ?: '—' }}</dd></div>
                @endunless
                <div class="tt-kv"><dt>Device</dt><dd>{{ $t->device_label ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Keterangan</dt><dd style="white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($t->description, 400) }}</dd></div>
            </dl>
        </div>
        <div class="col-md-6">
            <div class="tt-sec-title mt-3 mt-md-0">Penyelesaian</div>
            <dl class="m-0">
                <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Sub-RCA</dt><dd>{{ $t->sub_rca ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Keterangan</dt><dd style="white-space:pre-wrap;">{{ $t->resolution_summary ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Close by</dt><dd>{{ $t->closer?->name ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Date closed</dt><dd>{{ $tgl($t->closed_at) }}</dd></div>
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
