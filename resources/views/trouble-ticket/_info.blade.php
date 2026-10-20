{{-- Isi modal "Info" dari daftar Trouble Ticket (TroubleTicketController::info).
     Hanya membaca — membuka info TIDAK dihitung sebagai respons teknisi.

     Isi datanya sengaja disamakan dengan tab "Tiket" di halaman detail
     (show.blade.php: Identitas / Gangguan / Penyelesaian) supaya pembaca tidak
     menemukan dua ringkasan yang berbeda untuk tiket yang sama. --}}
@php
    use App\Models\TroubleTicket;
    $t = $ticket;
    $tgl = fn ($dt) => $dt ? $dt->format('d M Y') : '—';
    // Target SLA kosong ditulis '—', bukan 'Tidak terhingga'.
    $targetLabel = fn ($m) => $m ? TroubleTicket::formatDuration($m * 60) : '-';
@endphp
<div class="tt-info-modal" data-title="{{ $t->ticket_number }}">
    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:6px;">
        <x-status :status="$t->status" context="trouble-ticket" />
        <span class="tt-chip tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span>
        <span class="tt-chip">Aging {{ $t->aging_days }} hari</span>
    </div>

    {{-- Dua menu (permintaan 19 Okt 2026): Identitas & Log Aktivitas. Isinya
         dimuat sekaligus — modal ini satu permintaan, jadi berpindah tab tidak
         menambah query. --}}
    <ul class="nav nav-tabs tt-tabs mb-2" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#ttInfoIdentitas" role="tab"><i class="fas fa-ticket-alt"></i><span>Identitas</span></a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ttInfoLog" role="tab"><i class="fas fa-history"></i><span>Log Aktivitas</span><span class="tt-tab-count">{{ $statusLog->count() }}</span></a></li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="ttInfoIdentitas" role="tabpanel">
    <div class="row">
        <div class="col-md-6">
            <div class="tt-sec-title mt-0">Identitas</div>
            <dl class="m-0">
                <div class="tt-kv"><dt>No. TT</dt><dd><strong>{{ $t->ticket_number }}</strong></dd></div>
                {{-- Nama & ID site dipisah, dengan istilah yang sama dengan
                     halaman detail (Site Name / Site ID). --}}
                <div class="tt-kv"><dt>Site Name</dt><dd>{{ $t->site?->sitename ?? '—' }}</dd></div>
                <div class="tt-kv"><dt>Site ID</dt><dd>{{ $t->site?->tid ?? '—' }}</dd></div>
                <div class="tt-kv"><dt>Date Open</dt><dd>{{ $tgl($t->reported_at) }}</dd></div>
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
                <div class="tt-kv"><dt>Keterangan</dt><dd style="white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($t->description, 400) }}</dd></div>
            </dl>

            {{-- Resolution menutup cerita Gangguan, jadi ia tinggal di kolom
                 KIRI tepat di bawahnya (20 Okt 2026) — bukan di seberang. --}}
            <div class="tt-sec-title">Resolution</div>
            <dl class="m-0">
                <div class="tt-kv"><dt>RCA</dt><dd>{{ $t->rca ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Sub-RCA</dt><dd>{{ $t->sub_rca ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Keterangan</dt><dd style="white-space:pre-wrap;">{{ $t->resolution_summary ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Close by</dt><dd>{{ $t->closer?->name ?: '—' }}</dd></div>
                <div class="tt-kv"><dt>Date closed</dt><dd>{{ $tgl($t->closed_at) }}</dd></div>
            </dl>
        </div>
        <div class="col-md-6">
            {{-- Estimasi biaya plan visit (keputusan 20 Okt 2026). Hanya muncul
                 bila plan-nya memang sudah punya baris biaya. --}}
            @if(($planCosts ?? collect())->isNotEmpty())
                <div class="tt-sec-title mt-3 mt-md-0">Biaya Plan</div>
                <dl class="m-0">
                    <div class="tt-kv"><dt>Budget Plan</dt><dd>{{ $t->budgetPlan?->code ?? '—' }}</dd></div>
                </dl>
                <table class="table table-sm tt-cost-table mt-1 mb-0">
                    <thead>
                        <tr><th>COA</th><th>Description</th><th class="text-right">Harga</th><th class="text-right">Qty</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach($planCosts as $c)
                            <tr>
                                <td>{{ $c->account?->name ?? $c->account_name ?? '—' }}</td>
                                <td>{{ $c->description }}</td>
                                <td class="text-right">Rp {{ number_format($c->amount, 0, ',', '.') }}</td>
                                <td class="text-right">{{ (int) $c->qty }}</td>
                                <td class="text-right">Rp {{ number_format($c->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        {{-- Totalnya menautkan ke Cost Request yang lahir dari plan
                             ini (20 Okt 2026): dibuka di tab baru lewat ?open=<id>,
                             parameter yang sudah dipakai halaman Cost Request untuk
                             membuka modal detail satu CR. Tautan hanya muncul kalau
                             CR-nya memang sudah terbentuk. --}}
                        <tr>
                            <th colspan="4" class="text-right">Total Estimasi Biaya</th>
                            <th class="text-right">
                                @if($t->budgetRequest)
                                    <a class="tt-cell-link" title="Detail Request"
                                       href="{{ url('/budget-request?open=' . $t->budgetRequest->id) }}"
                                       target="_blank" rel="noopener">Rp {{ number_format((int) $t->plan_cost_total, 0, ',', '.') }}</a>
                                @else
                                    Rp {{ number_format((int) $t->plan_cost_total, 0, ',', '.') }}
                                @endif
                            </th>
                        </tr>
                    </tfoot>
                </table>
            @endif

            {{-- SLA tidak ditampilkan kepada teknisi tiket ini (keputusan
                 19 Okt 2026), sama seperti tab SLA di halaman detail. --}}
            @unless($isTechnician ?? false)
            <div class="tt-sec-title {{ ($planCosts ?? collect())->isEmpty() ? 'mt-3 mt-md-0' : '' }}">SLA</div>
            <div class="tt-sum-sla mt-1">
                @foreach(['response' => ['Response', $t->sla_response_minutes], 'travel' => ['Travel', $t->sla_travel_minutes], 'resolution' => ['Resolution', $t->sla_resolution_minutes]] as $key => [$label, $target])
                    <div class="tt-sla-card" style="cursor:default;">
                        <span class="tt-sla-card-label">{{ $label }}</span>
                        @include('trouble-ticket._sla', ['m' => $t->slaMetric($key), 'compact' => true])
                        <div class="text-muted" style="font-size:10.5px;">target {{ $targetLabel($target) }}</div>
                    </div>
                @endforeach
            </div>
            @endunless
        </div>
    </div>
    </div>

    {{-- Isi dan urutannya sama dengan tab "Log" di halaman detail — membuka
         info tetap tidak dihitung sebagai respons teknisi, jadi tidak ada
         aksi di sini. --}}
    <div class="tab-pane fade" id="ttInfoLog" role="tabpanel">
        <div class="tt-info-log">
            @include('trouble-ticket._log', ['statusLog' => $statusLog])
        </div>
    </div>
    </div>
</div>
