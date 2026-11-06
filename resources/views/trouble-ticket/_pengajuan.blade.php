{{-- Tab "Pengajuan" — pengajuan biaya (pra-Cost Request) tiket ini.

     Terlepas dari Plan Visit: satu tiket boleh punya banyak pengajuan, di tahap
     mana pun kecuali Closed. Cost Request (berstatus Draft) baru lahir saat
     supervisor meng-approve. TIDAK ada informasi budget apa pun di sini (sisa
     pagu, NOB, kuota Cash Advance): teknisi hanya mengajukan dana.

     Variabel dari show.blade: $t, $pengajuan, $flagPengajuan, $canRequest,
     $planCosts (data lama), $settleList, $settleCr. --}}
@php
    $rp = fn ($v) => 'Rp ' . number_format((int) $v, 0, ',', '.');
    $dapatSettle = $settleList->pluck('id')->all();
@endphp

@if($financeBlockers)
    <div class="tt-fin-box">
        <div class="font-weight-bold mb-1"><i class="fas fa-coins mr-1" aria-hidden="true"></i> Keuangan belum selesai</div>
        <ul class="mb-0 pl-3">
            @foreach($financeBlockers as $sisa)<li>{{ $sisa }}</li>@endforeach
        </ul>
    </div>
@elseif($financeState === \App\Models\TroubleTicket::FINANCE_SETTLED)
    <div class="tt-stage-note mb-2"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i> Urusan keuangan tiket ini sudah selesai.</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="tt-sub m-0">Pengajuan dana</div>
    @if($canRequest)
        <button type="button" class="btn btn-primary btn-xs" id="btnTtReqNew">
            <i class="fas fa-plus mr-1"></i> Ajukan Dana
        </button>
    @endif
</div>

@forelse($pengajuan->reverse() as $r)
    @php
        $fl = $flagPengajuan[$r->id];
        $baris = $r->items->where('revision', $r->revision);
        $umum = $r->attachments->whereNull('item_id');
        $cr = $r->budgetRequest;
    @endphp
    <div class="tt-req" id="ttReq{{ $r->id }}">
        <div class="tt-req-head">
            <strong class="tt-req-type">{{ $r->display_number }} · {{ $r->requestType?->name ?? '—' }}</strong>
            <span class="badge badge-{{ $r->status_color }}">{{ $r->status_label }}</span>
            <span class="tt-req-rev">rev {{ $r->revision }}</span>
        </div>
        <div class="tt-req-desc">{{ $r->description }}</div>
        <div class="tt-req-meta">
            {{ $rp($r->total) }} · {{ $r->requester?->name ?? '—' }} · {{ $r->updated_at?->format('d M Y H:i') }}
        </div>

        @if($cr)
            <div class="tt-req-cr">
                <i class="fas fa-file-invoice-dollar mr-1"></i> Cost Request <strong>{{ $cr->code }}</strong>
                @if($r->costRequestGone())
                    <span class="badge badge-dark">Dibatalkan</span>
                @else
                    <x-status :status="$cr->status" context="budget-request" size="sm" />
                @endif
            </div>
            {{-- Nilai CR dibaca live: Manager/Finance bisa mengubah atau men-takeout item. --}}
            <div class="tt-req-meta">
                CR {{ $rp($cr->amount) }}@if((int) $cr->amount !== (int) $r->total) (diajukan {{ $rp($r->total) }})@endif
                @if($cr->hasSettlementSubmitted())
                    · Settle {{ $rp($cr->total_realization) }}
                    @php $selisih = (int) $cr->total_realization - (int) $cr->amount; @endphp
                    @if($selisih > 0) · Lebih {{ $rp($selisih) }} @elseif($selisih < 0) · Sisa {{ $rp(-$selisih) }} @endif
                @endif
            </div>
            @if($r->settlement_review_status === 'PENDING')
                <div class="tt-stage-note">Settlement menunggu review atasan.</div>
            @elseif($r->settlement_review_status === 'REJECTED' && $r->settlement_review_notes)
                <div class="tt-stage-note text-danger">Settlement dikembalikan: {{ $r->settlement_review_notes }}</div>
            @endif
            @foreach($kwitansi->get($cr->id, collect()) as $kw)
                <a class="d-block tt-req-att" href="{{ route('trouble-ticket.receipt', [$t, $kw->id]) }}" target="_blank" rel="noopener">
                    <i class="fas fa-receipt mr-1"></i>{{ $kw->receipt_number }} · {{ $kw->recipient_name }} · {{ $rp($kw->amount) }}@if($kw->status !== 'VALID') (versi lama)@endif
                </a>
            @endforeach
        @endif

        @if($r->status === 'REJECTED' && $r->review_notes)
            <div class="tt-stage-note text-danger">Ditolak {{ $r->reviewer?->name }}: {{ $r->review_notes }}</div>
        @elseif($r->status === 'CANCELLED' && $r->cancel_reason)
            <div class="tt-stage-note">Dibatalkan: {{ $r->cancel_reason }}</div>
        @endif

        <details class="tt-acc">
            <summary>Rincian ({{ $baris->count() }} baris)</summary>
            <div class="table-responsive">
                <table class="table table-sm tt-cost-table mb-1">
                    <thead>
                        <tr><th>COA / Sub-item</th><th>Deskripsi</th><th class="text-right">Harga</th><th class="text-right">Qty</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach($baris as $b)
                            <tr>
                                <td>{{ $b->account_name ?? '-' }}@if($b->sub_item_name) — {{ $b->sub_item_name }}@endif</td>
                                <td>
                                    {{ $b->description }}
                                    @foreach($b->attachments as $a)
                                        <a class="d-block tt-req-att" href="{{ route('trouble-ticket.requests.attachment', [$t, $a->id]) }}"><i class="fas fa-paperclip mr-1"></i>{{ $a->original_name }}</a>
                                    @endforeach
                                </td>
                                <td class="text-right">{{ number_format((int) $b->amount, 0, ',', '.') }}</td>
                                <td class="text-right">{{ (int) $b->qty }}</td>
                                <td class="text-right">{{ number_format((int) $b->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="4" class="text-right">Total</th><th class="text-right">{{ number_format((int) $r->total, 0, ',', '.') }}</th></tr></tfoot>
                </table>
            </div>
            @foreach($umum as $a)
                <a class="d-block tt-req-att" href="{{ route('trouble-ticket.requests.attachment', [$t, $a->id]) }}"><i class="fas fa-paperclip mr-1"></i>{{ $a->original_name }}</a>
            @endforeach
            <div class="tt-stage-note">
                {{ $r->budgetPlan?->code }}@if($r->cluster) · [{{ $r->cluster->code }}] {{ $r->cluster->name }}@endif
                @if($r->beneficiary) · {{ $r->beneficiary->nickname ?? $r->beneficiary->complete_name }}@endif
            </div>
        </details>

        <div class="tt-req-acts">
            @if($fl['review'])
                <form method="POST" action="{{ route('trouble-ticket.requests.approve', [$t, $r->id]) }}" class="d-inline tt-submit-once tt-confirm"
                      data-title="Setujui pengajuan?" data-text="Cost Request berstatus Draft akan dibuat atas nama Anda.">@csrf
                    <button type="submit" class="btn btn-success btn-xs"><i class="fas fa-check mr-1"></i> Approve</button>
                </form>
                @if($fl['direct'])
                    <form method="POST" action="{{ route('trouble-ticket.requests.approve', [$t, $r->id]) }}" class="d-inline tt-submit-once tt-confirm"
                          data-title="Setujui dan ajukan ke Head?" data-text="Cost Request langsung berstatus Submitted dan mengikat budget. Aturan lampiran dan limit Cash Advance tetap berlaku.">@csrf
                        <input type="hidden" name="direct" value="1">
                        <button type="submit" class="btn btn-primary btn-xs"><i class="fas fa-share mr-1"></i> Approve &amp; Submit</button>
                    </form>
                @endif
                <button type="button" class="btn btn-outline-danger btn-xs btn-tt-req-decide" data-kind="reject" data-id="{{ $r->id }}"><i class="fas fa-times mr-1"></i> Reject</button>
                <button type="button" class="btn btn-outline-primary btn-xs btn-tt-req-edit" data-mode="review" data-id="{{ $r->id }}"><i class="fas fa-pen mr-1"></i> Ubah</button>
            @endif
            @if($fl['edit'])
                <button type="button" class="btn btn-outline-primary btn-xs btn-tt-req-edit" data-mode="requester" data-id="{{ $r->id }}">
                    <i class="fas fa-pen mr-1"></i> {{ $r->status === 'REJECTED' ? 'Revisi' : 'Ubah' }}
                </button>
            @endif
            @if($fl['submit'])
                <form method="POST" action="{{ route('trouble-ticket.requests.submit', [$t, $r->id]) }}" class="d-inline tt-submit-once tt-confirm"
                      data-title="Ajukan ke supervisor?" data-text="Pengajuan akan menunggu review supervisor team Anda.">@csrf
                    <button type="submit" class="btn btn-primary btn-xs"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
                </form>
            @endif
            @if($fl['cancel'])
                <button type="button" class="btn btn-outline-secondary btn-xs btn-tt-req-decide" data-kind="cancel" data-id="{{ $r->id }}"><i class="fas fa-ban mr-1"></i> Batal</button>
            @endif
            @if($cr && in_array($cr->id, $dapatSettle, true))
                @if($settleCr && $settleCr->id === $cr->id)
                    <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#mdlTtSettle"><i class="fas fa-file-invoice-dollar mr-1"></i> Settlement</button>
                @else
                    <a class="btn btn-primary btn-xs" href="{{ route('trouble-ticket.show', [$t, 'settle' => $cr->id]) }}#settle"><i class="fas fa-file-invoice-dollar mr-1"></i> Settlement</a>
                @endif
            @endif
            @if($fl['settle_send'])
                <form method="POST" action="{{ route('trouble-ticket.settlement.submit', [$t, $cr->id]) }}" class="d-inline tt-submit-once tt-confirm"
                      data-title="Kirim settlement ke atasan?" data-text="Settlement terkunci selama direview.">@csrf
                    <button type="submit" class="btn btn-success btn-xs"><i class="fas fa-paper-plane mr-1"></i> Kirim Settlement</button>
                </form>
            @endif
            @if($fl['settle_review'])
                <form method="POST" action="{{ route('trouble-ticket.settlement.review', [$t, $cr->id]) }}" class="d-inline tt-submit-once tt-confirm"
                      data-title="Teruskan settlement ke Finance?" data-text="Cost Request menjadi Settle dan masuk tahap Head.">@csrf
                    <input type="hidden" name="decision" value="approve">
                    <button type="submit" class="btn btn-success btn-xs"><i class="fas fa-check mr-1"></i> Teruskan ke Finance</button>
                </form>
                <button type="button" class="btn btn-outline-danger btn-xs btn-tt-req-decide" data-kind="settle-return" data-url="{{ route('trouble-ticket.settlement.review', [$t, $cr->id]) }}"><i class="fas fa-undo mr-1"></i> Kembalikan</button>
            @endif
            @if($fl['resubmit'])
                <form method="POST" action="{{ route('trouble-ticket.requests.resubmit', [$t, $r->id]) }}" class="d-inline tt-submit-once tt-confirm"
                      data-title="Ajukan ulang?" data-text="Pengajuan baru berstatus Draft dibuat dari isi pengajuan ini.">@csrf
                    <button type="submit" class="btn btn-outline-primary btn-xs"><i class="fas fa-redo mr-1"></i> Ajukan Ulang</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <x-empty title="Belum ada pengajuan biaya" message="Ajukan biaya (Cash Advance atau jenis lain) kapan saja selama tiket belum Closed. Supervisor team Anda yang mereview." icon="fas fa-file-invoice-dollar" compact />
@endforelse

{{-- Biaya Plan (data lama): sebelum Pengajuan Biaya, biaya diisi saat Create Plan.
     Tetap terbaca (read-only); CR lamanya dan settlement-nya tetap berfungsi. --}}
@if($planCosts->isNotEmpty() || $t->budgetRequest)
    <details class="tt-acc mt-3">
        <summary>Biaya Plan (lama)</summary>
        @if($planCosts->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm tt-cost-table mb-1">
                    <thead><tr><th>COA</th><th>Deskripsi</th><th class="text-right">Harga</th><th class="text-right">Qty</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach($planCosts as $c)
                            <tr>
                                <td>{{ $c->account_name ?? $c->account?->name ?? '-' }}</td>
                                <td>{{ $c->description }}</td>
                                <td class="text-right">{{ number_format((int) $c->amount, 0, ',', '.') }}</td>
                                <td class="text-right">{{ (int) $c->qty }}</td>
                                <td class="text-right">{{ number_format((int) $c->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="4" class="text-right">Total</th><th class="text-right">{{ number_format((int) $t->plan_cost_total, 0, ',', '.') }}</th></tr></tfoot>
                </table>
            </div>
        @endif
        @if($t->budgetRequest)
            <div class="tt-req-cr">
                <i class="fas fa-file-invoice-dollar mr-1"></i> Cost Request <strong>{{ $t->budgetRequest->code }}</strong>
                <x-status :status="$t->budgetRequest->status" context="budget-request" size="sm" />
                @if(in_array($t->budgetRequest->id, $dapatSettle, true))
                    @if($settleCr && $settleCr->id === $t->budgetRequest->id)
                        <button type="button" class="btn btn-primary btn-xs ml-1" data-toggle="modal" data-target="#mdlTtSettle"><i class="fas fa-file-invoice-dollar mr-1"></i> Settlement</button>
                    @else
                        <a class="btn btn-primary btn-xs ml-1" href="{{ route('trouble-ticket.show', [$t, 'settle' => $t->budgetRequest->id]) }}#settle"><i class="fas fa-file-invoice-dollar mr-1"></i> Settlement</a>
                    @endif
                @endif
            </div>
        @endif
        <div class="tt-stage-note">Data dari alur lama (biaya diisi saat Create Plan); tidak dapat diubah dari sini.</div>
    </details>
@endif

@push('styles')
<style>
.tt-fin-box { border:1px solid var(--border); border-left:3px solid var(--warning); border-radius:var(--radius-md); padding:8px 12px; margin-bottom:10px; background:var(--warning-bg); font-size:12px; color:var(--text); }
.tt-req { border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 12px; margin-bottom:10px; background:var(--surface); }
.tt-req-head { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.tt-req-type { font-size:13px; color:var(--text); }
.tt-req-rev { font-size:11px; color:var(--text-faint); margin-left:auto; }
.tt-req-desc { font-size:13px; color:var(--text); margin-top:4px; overflow-wrap:anywhere; }
.tt-req-meta { font-size:11.5px; color:var(--text-muted); margin-top:2px; }
.tt-req-cr { font-size:12px; color:var(--text-dim); margin-top:6px; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.tt-req-att { font-size:11.5px; overflow-wrap:anywhere; }
.tt-req-acts { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.tt-req-acts .btn { min-height:30px; }
@media (max-width: 575.98px) { .tt-req-acts .btn { min-height:40px; } }
</style>
@endpush

@push('scripts')
<script>
    // Tautan dari Todo List (?tab=pengajuan) membuka tab Pengajuan.
    $(function () {
        if (new URLSearchParams(location.search).get('tab') === 'pengajuan') {
            $('.tt-tabs a[href="#ttTabPengajuan"]').tab('show');
        }
    });
</script>
@endpush
