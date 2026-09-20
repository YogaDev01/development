@extends('layouts.app')

@section('title', 'Detail Customer — ' . $customer->name)

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap" style="gap:10px;">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                    {{ $customer->name }}
                    <span class="badge badge-{{ $customer->status === 'ACTIVE' ? 'success' : 'secondary' }} ml-2"
                          style="font-size:11px;vertical-align:middle;">{{ $customer->status }}</span>
                </h1>
                <small class="text-muted">
                    {{ $customer->initial }} &middot; {{ $customer->customerType->name ?? 'Tanpa tipe' }}
                </small>
            </div>
            <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                <a href="{{ url('/customer') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali
                </a>
                <a href="{{ url('/customer/' . $customer->id . '/edit') }}" class="btn btn-warning btn-sm">
                    <i class="fas fa-pen mr-1"></i> Edit Customer
                </a>
            </div>
        </div>

        <x-stat-cards :cards="[
            ['Total PO', $summary['po_qty'] . ' PO · ' . $summary['po_number_qty'] . ' No. PO', 'primary', 'fas fa-file-contract'],
            ['Nilai Kontrak', 'Rp ' . number_format($summary['contract_value'], 0, ',', '.'), 'info', 'fas fa-file-signature'],
            ['Total Invoice', 'Rp ' . number_format($summary['invoice_value'], 0, ',', '.'), 'secondary', 'fas fa-file-invoice'],
            ['Sudah Dibayar', 'Rp ' . number_format($summary['paid_value'], 0, ',', '.'), 'success', 'fas fa-check-circle'],
            ['Outstanding', 'Rp ' . number_format($summary['outstanding'], 0, ',', '.'), $summary['outstanding'] > 0 ? 'warning' : 'secondary', 'fas fa-hourglass-half'],
        ]" />

        <div class="row mt-3">
            {{-- ── PROFIL & PIC ──────────────────────────────────────────── --}}
            <div class="col-lg-4">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-building mr-1"></i> Profil</h3></div>
                    <div class="card-body" style="font-size:12px;">
                        <dl class="row mb-0">
                            <dt class="col-5 text-muted">Nama</dt>
                            <dd class="col-7">{{ $customer->name }}</dd>

                            <dt class="col-5 text-muted">Initial</dt>
                            <dd class="col-7"><span class="badge badge-dark px-2 py-1">{{ $customer->initial }}</span></dd>

                            <dt class="col-5 text-muted">Tipe</dt>
                            <dd class="col-7">{{ $customer->customerType->name ?? '-' }}</dd>

                            <dt class="col-5 text-muted">NPWP</dt>
                            <dd class="col-7">{{ $customer->npwp ?: '-' }}</dd>

                            <dt class="col-5 text-muted">Alamat</dt>
                            <dd class="col-7">{{ $customer->address ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-id-badge mr-1"></i> PIC ({{ $customer->pics->count() }})</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:12px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col">Nama</th>
                                        <th scope="col">Jabatan</th>
                                        <th scope="col">Kontak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($customer->pics as $pic)
                                    <tr>
                                        <td>{{ $pic->pic }}</td>
                                        <td>{{ $pic->position ?: '-' }}</td>
                                        <td>
                                            {{ $pic->phone ?: '-' }}
                                            @if($pic->email)
                                                <div class="text-muted" style="font-size:10px;">{{ $pic->email }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">Belum ada PIC.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran ({{ $customer->attachments->count() }})</h3>
                    </div>
                    <div class="card-body">
                        @forelse($customer->attachments as $att)
                            <a href="/storage/{{ $att->file_path }}" target="_blank"
                               class="btn btn-outline-info btn-sm mb-1 d-block text-left text-truncate">
                                <i class="fas fa-file mr-1"></i>{{ $att->title ?? basename($att->file_path) }}
                            </a>
                        @empty
                            <x-empty title="Belum ada lampiran" icon="fas fa-paperclip" compact />
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ── DATATABLE BERTINGKAT: PO > (No. PO / Invoice / Budget) ──── --}}
            <div class="col-lg-8">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                        <h3 class="card-title"><i class="fas fa-sitemap mr-1"></i> Purchase Order ({{ $projects->count() }})</h3>
                        <div class="ml-auto d-flex align-items-center" style="gap:6px;">
                            <input type="search" id="custSearch" class="form-control form-control-sm"
                                   placeholder="Cari PO / No. PO / invoice" style="width:240px;font-size:12px;">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="custExpandAll" style="font-size:11px;">
                                <i class="fas fa-angle-double-down mr-1"></i>Buka Semua
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0" id="tblCustomerPo" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col">PO / Rincian</th>
                                        <th scope="col" class="text-nowrap">Status</th>
                                        <th scope="col" class="text-right text-nowrap">Nilai</th>
                                        <th scope="col" class="text-right text-nowrap">Diterima</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($projects as $pi => $project)
                                    @php
                                        $poKey = 'po' . $pi;

                                        // Uang yang sudah diterima atas PO ini — dihitung dari
                                        // relasi yang sudah di-eager-load, bukan query baru.
                                        $poReceived = (int) $project->invoices->sum(fn ($inv) =>
                                            (int) $inv->cashReceipts->sum('amount') + (int) $inv->cashReceipts->sum('withholding_tax'));

                                        $poSearch = strtolower($project->code . ' ' . $project->name);
                                    @endphp

                                    {{-- LAPIS 1 — PO --}}
                                    <tr class="cust-row cust-row-po" data-key="{{ $poKey }}"
                                        data-search="{{ $poSearch }}" style="cursor:pointer;background:var(--surface-2);">
                                        <td>
                                            <i class="fas fa-caret-right cust-caret mr-1"></i>
                                            <strong>{{ $project->code }}</strong> — {{ $project->name }}
                                            <div class="text-muted" style="font-size:10px;">
                                                PM {{ strtoupper($project->manager->username ?? '-') }} &middot;
                                                {{ $project->poNumbers->count() }} No. PO &middot;
                                                {{ $project->invoices->count() }} invoice
                                            </div>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-secondary px-2 py-1">{{ $project->status }}</span>
                                        </td>
                                        <td class="text-right text-nowrap"><strong>{{ number_format($project->value ?? 0, 0, ',', '.') }}</strong></td>
                                        <td class="text-right text-nowrap text-success">{{ number_format($poReceived, 0, ',', '.') }}</td>
                                    </tr>

                                    {{-- LAPIS 2a — No. PO --}}
                                    @foreach($project->poNumbers as $pn)
                                    <tr class="cust-row" data-parent="{{ $poKey }}" style="display:none;"
                                        data-search="{{ strtolower($poSearch . ' ' . $pn->po_number) }}">
                                        <td style="padding-left:32px;">
                                            <i class="fas fa-hashtag text-muted mr-1" style="font-size:10px;"></i>
                                            <span style="font-family:monospace;font-size:11px;">{{ $pn->po_number }}</span>
                                            @if($pn->tanggal_bast)
                                                <span class="text-muted ml-2" style="font-size:10px;">BAST {{ $pn->tanggal_bast->format('d M Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-light border px-2 py-1" style="font-size:9px;">{{ $pn->status }}</span>
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format($pn->nominal, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap text-muted">-</td>
                                    </tr>
                                    @endforeach

                                    {{-- LAPIS 2b — Invoice --}}
                                    @foreach($project->invoices as $inv)
                                    @php
                                        $invReceived = (int) $inv->cashReceipts->sum('amount') + (int) $inv->cashReceipts->sum('withholding_tax');
                                    @endphp
                                    <tr class="cust-row" data-parent="{{ $poKey }}" style="display:none;"
                                        data-search="{{ strtolower($poSearch . ' ' . $inv->code) }}">
                                        <td style="padding-left:32px;">
                                            <i class="fas fa-file-invoice text-muted mr-1" style="font-size:10px;"></i>
                                            <a href="{{ url('/invoice-cash-receipt/' . $inv->id) }}" target="_blank">{{ $inv->code }}</a>
                                            <span class="text-muted ml-2" style="font-size:10px;">
                                                {{ $inv->invoice_date?->format('d M Y') }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-info px-2 py-1" style="font-size:9px;">{{ $inv->status }}</span>
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format($inv->captured_total, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap text-success">{{ number_format($invReceived, 0, ',', '.') }}</td>
                                    </tr>
                                    @endforeach

                                    {{-- LAPIS 2c — Budget Plan --}}
                                    @foreach($project->budgetPlans as $bp)
                                    <tr class="cust-row" data-parent="{{ $poKey }}" style="display:none;"
                                        data-search="{{ strtolower($poSearch . ' ' . $bp->code) }}">
                                        <td style="padding-left:32px;">
                                            <i class="fas fa-wallet text-muted mr-1" style="font-size:10px;"></i>
                                            <a href="{{ url('/budget-plan?open=' . $bp->id) }}" target="_blank">{{ $bp->code }}</a>
                                            <span class="text-muted ml-2" style="font-size:10px;">{{ Str::limit($bp->description, 50) }}</span>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-secondary px-2 py-1" style="font-size:9px;">{{ $bp->status }}</span>
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format($bp->amount ?? 0, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap text-muted">-</td>
                                    </tr>
                                    @endforeach

                                    @empty
                                    <tr>
                                        <td colspan="4">
                                            <x-empty title="Customer ini belum punya PO" icon="fas fa-file-contract" compact />
                                        </td>
                                    </tr>
                                    @endforelse

                                    <tr id="custNoMatch" style="display:none;">
                                        <td colspan="4" class="text-center text-muted py-3">
                                            Tidak ada yang cocok dengan pencarian.
                                        </td>
                                    </tr>
                                </tbody>
                                @if($projects->isNotEmpty())
                                <tfoot>
                                    <tr style="background:var(--surface-2);font-weight:700;">
                                        <th colspan="2" class="text-right">Total</th>
                                        <th class="text-right">{{ number_format($summary['po_value'], 0, ',', '.') }}</th>
                                        <th class="text-right text-success">{{ number_format($summary['paid_value'], 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('styles')
<style>
.table td, .table th { vertical-align:middle !important; }
.cust-row-po:hover { filter:brightness(.97); }
.cust-caret { transition:transform .15s; }
.cust-caret.rotated { transform:rotate(90deg); }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    /* MN-01 — tabel bertingkat Customer > PO > rincian.
       Sengaja BUKAN DataTable: baris di sini punya hubungan induk-anak, dan
       paging DataTable akan memotong anak dari induknya. Yang dibutuhkan
       hanyalah buka/tutup dan pencarian, keduanya ringan di sisi klien karena
       seluruh isi halaman sudah dirender dari server. */

    function children(key) {
        return $('#tblCustomerPo tbody tr[data-parent="' + key + '"]');
    }

    $('#tblCustomerPo').on('click', '.cust-row-po', function () {
        var $row  = $(this);
        var $kids = children($row.data('key'));
        if (!$kids.length) return;

        var expanding = $kids.filter(':visible').length === 0;
        $kids.toggle(expanding);
        $row.find('.cust-caret').toggleClass('rotated', expanding);
    });

    $('#custExpandAll').on('click', function () {
        var $kids = $('#tblCustomerPo tbody tr[data-parent]');
        var expanding = $kids.filter(':visible').length === 0;

        $kids.toggle(expanding);
        $('#tblCustomerPo .cust-caret').toggleClass('rotated', expanding);
        $(this).html(expanding
            ? '<i class="fas fa-angle-double-up mr-1"></i>Tutup Semua'
            : '<i class="fas fa-angle-double-down mr-1"></i>Buka Semua');
        $('#custNoMatch').hide();
    });

    var searchTimer = null;
    $('#custSearch').on('input', function () {
        var q = $(this).val().toLowerCase().trim();

        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            var $rows = $('#tblCustomerPo tbody tr.cust-row');

            if (!q) {
                $rows.filter('[data-parent]').hide();
                $rows.filter('.cust-row-po').show();
                $('#tblCustomerPo .cust-caret').removeClass('rotated');
                $('#custNoMatch').hide();
                return;
            }

            $rows.hide();
            var hit = 0;

            $rows.each(function () {
                var $r = $(this);
                if (($r.data('search') || '').toString().indexOf(q) === -1) return;

                hit++;
                $r.show();

                // Baris anak yang cocok ditampilkan beserta induknya, supaya
                // hasilnya tetap terbaca sebagai hierarki.
                var parent = $r.data('parent');
                if (parent) {
                    $('#tblCustomerPo tbody tr[data-key="' + parent + '"]')
                        .show().find('.cust-caret').addClass('rotated');
                }
            });

            $('#custNoMatch').toggle(hit === 0);
        }, 150);
    });
});
</script>
@endpush
