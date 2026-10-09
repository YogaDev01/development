@extends('layouts.app')

@section('title', 'Trouble Ticket')

@php
    use App\Models\TroubleTicket;

    /* Tautan judul kolom: klik sekali = urut kolom itu (desc), klik lagi = balik arah. */
    $sortLink = function (string $key, string $label) use ($sort, $dir) {
        $arah = ($sort === $key && $dir === 'desc') ? 'asc' : 'desc';
        $ikon = $sort === $key ? ($dir === 'desc' ? ' fa-sort-down' : ' fa-sort-up') : ' fa-sort text-muted';
        $url  = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $arah, 'page' => null]);

        return '<a href="' . e($url) . '">' . e($label) . ' <i class="fas' . $ikon . '" style="font-size:10px;"></i></a>';
    };
    $adaFilter = collect($filters)->except(['sort', 'dir'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap" style="gap:8px;">
            <div>
                <h1 class="tt-title">Trouble Ticket</h1>
                <div class="tt-sub">Gangguan teknis di site, respons teknisi, kunjungan, dan SLA penyelesaian.</div>
            </div>
            <div class="d-flex" style="gap:6px;">
                @if($canSettings)
                    <a href="{{ route('trouble-ticket.settings') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sliders-h mr-1"></i> Pengaturan</a>
                @endif
                <a href="{{ route('trouble-ticket.create') }}" class="btn btn-success btn-sm"><i class="fas fa-plus mr-1"></i> Buat Tiket</a>
            </div>
        </div>

        <x-stat-cards :cards="[
            ['Terbuka', $stats['terbuka'], 'info', 'fas fa-ticket-alt'],
            ['Lewat SLA', $stats['overdue'], 'danger', 'fas fa-exclamation-triangle', 'hanya tiket ber-target SLA'],
            ['Menunggu Approval', $stats['pending_closure'], 'warning', 'fas fa-user-check'],
            ['Closed Bulan Ini', $stats['closed_bulan_ini'], 'success', 'fas fa-check-circle'],
        ]" />

        <div class="card shadow-sm mb-3">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('trouble-ticket.index') }}" class="tt-filter" id="frmTtFilter">
                    <div class="form-row align-items-end">
                        <div class="col-lg-3 col-md-6 mb-2">
                            <label for="fQ">Cari</label>
                            <input type="search" name="q" id="fQ" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="No. TT, ref, site, customer, device, alarm">
                        </div>
                        <div class="col-lg-2 col-md-3 col-6 mb-2">
                            <label for="fStatus">Status</label>
                            <select name="status" id="fStatus" class="form-control form-control-sm">
                                <option value="">Semua</option>
                                @foreach(TroubleTicket::STATUSES as $s)
                                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ \App\Support\WorkflowStatus::label($s, 'trouble-ticket') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-3 col-6 mb-2">
                            <label for="fSev">Severity</label>
                            <select name="severity" id="fSev" class="form-control form-control-sm">
                                <option value="">Semua</option>
                                @foreach($options['severities'] as $sv)
                                    <option value="{{ $sv->code }}" @selected(($filters['severity'] ?? '') === $sv->code)>{{ $sv->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-2">
                            <label for="fProblem">Problem</label>
                            <select name="problem_id" id="fProblem" class="form-control form-control-sm" data-searchable>
                                <option value="">Semua</option>
                                @foreach($options['problems'] as $p)
                                    <option value="{{ $p->id }}" @selected((string) ($filters['problem_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-2">
                            <label for="fCustomer">Customer</label>
                            <select name="customer_id" id="fCustomer" class="form-control form-control-sm" data-searchable>
                                <option value="">Semua</option>
                                @foreach($options['customers'] as $c)
                                    <option value="{{ $c->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-2">
                            <label for="fSite">Site</label>
                            <select name="site_id" id="fSite" class="form-control form-control-sm" data-searchable>
                                <option value="">Semua</option>
                                @foreach($options['sites'] as $s)
                                    <option value="{{ $s->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $s->id)>{{ $s->tid }} — {{ $s->sitename }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-2">
                            <label for="fTech">Teknisi</label>
                            <select name="assigned_to" id="fTech" class="form-control form-control-sm" data-searchable>
                                <option value="">Semua</option>
                                @foreach($options['technicians'] as $u)
                                    <option value="{{ $u->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6 mb-2">
                            <label for="fFrom">Dibuat dari</label>
                            <input type="date" name="date_from" id="fFrom" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-lg-2 col-md-3 col-6 mb-2">
                            <label for="fTo">s.d.</label>
                            <input type="date" name="date_to" id="fTo" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-lg-2 col-md-3 mb-2">
                            <div class="custom-control custom-checkbox mb-1">
                                <input type="checkbox" class="custom-control-input" id="fOverdue" name="overdue" value="1" @checked(! empty($filters['overdue']))>
                                <label class="custom-control-label" for="fOverdue" style="text-transform:none;font-size:12.5px;color:var(--text);">Hanya lewat SLA</label>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3 mb-2 d-flex" style="gap:6px;">
                            <input type="hidden" name="sort" value="{{ $sort }}">
                            <input type="hidden" name="dir" value="{{ $dir }}">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Terapkan</button>
                            @if($adaFilter)
                                <a href="{{ route('trouble-ticket.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                @if($tickets->isEmpty())
                    <div class="p-3">
                        <x-empty :title="$adaFilter ? 'Tidak ada tiket yang cocok' : 'Belum ada Trouble Ticket'"
                                 :message="$adaFilter ? 'Ubah atau reset filter untuk melihat tiket lain.' : 'Tiket yang Anda buat, ditugaskan kepada Anda, atau milik site team Anda akan tampil di sini.'"
                                 icon="fas fa-ticket-alt" />
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 tt-table">
                            <thead>
                                <tr>
                                    <th>{!! $sortLink('ticket_number', 'No. TT') !!}</th>
                                    <th>{!! $sortLink('severity', 'Severity') !!}</th>
                                    <th>Customer</th>
                                    <th>Site</th>
                                    <th>Problem</th>
                                    <th>Issue</th>
                                    <th>Device / Alarm</th>
                                    <th>Teknisi</th>
                                    <th>{!! $sortLink('status', 'Status') !!}</th>
                                    <th>Rencana</th>
                                    <th title="Tiket dibuat → respons pertama teknisi (acknowledged)">Response</th>
                                    <th title="Berangkat → tiba di site">Travel</th>
                                    <th title="Start Progress → Closed disetujui">Resolution</th>
                                    <th>{!! $sortLink('reported_at', 'Dilaporkan') !!}</th>
                                    <th>{!! $sortLink('updated_at', 'Diperbarui') !!}</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tickets as $t)
                                    @php $url = route('trouble-ticket.show', $t); @endphp
                                    <tr data-href="{{ $url }}">
                                        <td class="tt-no">
                                            {{ $t->ticket_number }}
                                            @if($t->external_ref)<div class="text-muted" style="font-size:11px;font-weight:400;" title="Ref. eksternal">{{ $t->external_ref }}</div>@endif
                                        </td>
                                        <td><span class="tt-sev tt-sev-{{ $t->severity_level }}">{{ $t->severity_label }}</span></td>
                                        <td><div class="tt-trunc" style="max-width:150px;" title="{{ $t->customer?->name }}">{{ $t->customer?->name ?? '-' }}</div></td>
                                        <td style="white-space:nowrap;">
                                            <strong>{{ $t->site?->tid }}</strong>
                                            <div class="tt-trunc text-muted" style="max-width:160px;font-size:11.5px;" title="{{ $t->site?->sitename }}">{{ $t->site?->sitename }}</div>
                                        </td>
                                        <td>{{ $t->problem?->name ?? '-' }}</td>
                                        <td><div class="tt-trunc" title="{{ $t->issue }}">{{ $t->issue }}</div></td>
                                        <td style="white-space:nowrap;">
                                            {{ $t->device ?: '-' }}
                                            @if($t->flag_alarm)<div class="text-muted" style="font-size:11px;">{{ $t->flag_alarm }}</div>@endif
                                        </td>
                                        <td style="white-space:nowrap;">{{ $t->technician?->name ?? '-' }}</td>
                                        <td><x-status :status="$t->status" context="trouble-ticket" /></td>
                                        <td style="white-space:nowrap;">
                                            {{ $t->visit_decision_label ?? '—' }}
                                            @if($t->visit_site)<div class="text-muted" style="font-size:11px;">{{ $t->visit_site->format('d/m/y') }}</div>@endif
                                        </td>
                                        <td>@include('trouble-ticket._sla', ['m' => $t->slaMetric('response'), 'compact' => true])</td>
                                        <td>@include('trouble-ticket._sla', ['m' => $t->slaMetric('travel'), 'compact' => true])</td>
                                        <td>@include('trouble-ticket._sla', ['m' => $t->slaMetric('resolution'), 'compact' => true])</td>
                                        <td style="white-space:nowrap;">{{ ($t->reported_at ?? $t->created_at)->format('d/m/y H:i') }}</td>
                                        <td style="white-space:nowrap;">{{ $t->updated_at->format('d/m/y H:i') }}</td>
                                        <td class="text-right">
                                            <a href="{{ $url }}" class="btn btn-outline-secondary btn-xs" title="Buka tiket"><i class="fas fa-arrow-right"></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @if($tickets->hasPages())
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="font-size:12px;gap:8px;">
                    <span class="text-muted">{{ $tickets->firstItem() }}–{{ $tickets->lastItem() }} dari {{ $tickets->total() }} tiket</span>
                    {{ $tickets->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    TesysSearchableSelect.init('#frmTtFilter select[data-searchable]', { searchPlaceholder: 'Cari...' });

    // Baris bisa diklik; tautan/tombol di dalam baris tetap bekerja normal.
    $('.tt-table').on('click', 'tbody tr[data-href]', function (e) {
        if ($(e.target).closest('a,button,input,label').length) return;
        window.location = this.dataset.href;
    });

    TtTimers.start(@json(now()->toIso8601String()));
});
</script>
@endpush
