@extends('layouts.app')

@section('title', 'Treasury')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Treasury</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Treasury</li>
            </ol>
        </div>

        @php
            $bolehBuat = Auth::user()->hasRole(['superadmin','finance','finance_manager','treasury']);
            // Kolom centang bulk approve/reject hanya untuk peran yang berwenang approve batch.
            $bolehBulk = Auth::user()->hasRole(['superadmin','approver']);
        @endphp
        <x-tw.page-header title="Batch Pembayaran"
            description="Batch Cash Out dan Top Up dana beserta persetujuan dan pembayarannya.">
            @if($bolehBuat)
            <x-slot:actions>
                <x-tw.button href="{{ url('/daily-transaction/create') }}" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Buat Batch Baru</x-tw.button>
            </x-slot:actions>
            @endif
        </x-tw.page-header>

        @if($bolehBuat)
        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <a href="{{ url('/daily-transaction/create') }}" class="ts-fab" aria-label="Buat Batch Baru" title="Buat Batch Baru">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
        @endif

        {{-- Tab tipe batch (filter di sisi klien) + rentang tanggal (filter server-side). --}}
        @php
            $tabTipe = [
                'co' => ['CASH_OUT', 'Cash Out', 'fas fa-money-bill-wave', $summaryByType['CASH_OUT']['total'] ?? 0],
                'tu' => ['TOP_UP',   'Top Up',   'fas fa-exchange-alt',    $summaryByType['TOP_UP']['total'] ?? 0],
                'db' => ['',         'Database', 'fas fa-database',        $summary['total'] ?? 0],
            ];
            $tabAktif = in_array(request('tab'), ['co', 'tu', 'db'], true) ? request('tab') : 'co';
        @endphp
        <div class="tw:flex tw:flex-wrap tw:items-end tw:justify-between tw:gap-2">
            <ul class="nav nav-tabs ts-pill-tabs" id="dtTabs" role="tablist">
                @foreach($tabTipe as $kunci => [$tipe, $label, $ikon, $jml])
                <li class="nav-item">
                    <a class="nav-link {{ $tabAktif === $kunci ? 'active' : '' }}" href="#" role="tab" data-dttab="{{ $kunci }}" data-type="{{ $tipe }}">
                        <i class="{{ $ikon }}" aria-hidden="true"></i> {{ $label }}
                        <span class="badge">{{ number_format($jml, 0, ',', '.') }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
            <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:pb-1">
                {{-- Satu kotak rentang (TesysDateRange): klik membuka dua
                     kalender From/To. Nilainya tersimpan di input hidden. --}}
                <div class="d-flex align-items-center batch-date-range" style="gap:6px;">
                    <input type="text" id="batchDateRange" class="form-control form-control-sm tdr-input" readonly aria-label="Rentang tanggal">
                    <input type="hidden" id="batchDateFrom" value="{{ substr($dateFrom, 0, 10) }}">
                    <input type="hidden" id="batchDateTo" value="{{ substr($dateTo, 0, 10) }}">
                </div>
                <x-tw.button id="btnRefresh" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
            </div>
        </div>

        {{-- Stepper per tipe (tab Database tanpa stepper, sama dengan Cost Request). Rejected
             ditaruh paling awal. Klik tahap memfilter tabel di bawahnya. --}}
        @foreach(['co' => 'CASH_OUT', 'tu' => 'TOP_UP'] as $kunci => $tipe)
        @php
            $hitung = $summaryByType[$tipe] ?? [];
            $batchSteps = [
                ['Rejected',  $hitung['REJECTED'] ?? 0,         'danger',  'REJECTED'],
                ['Draft',     $hitung['DRAFT'] ?? 0,            'secondary', 'DRAFT'],
                ['Submitted', $hitung['PENDING_APPROVER'] ?? 0, 'warning', 'PENDING_APPROVER'],
                ['Done',      $hitung['DONE'] ?? 0,             'primary', 'DONE'],
                ['Paid',      $hitung['PAID'] ?? 0,             'success', 'PAID'],
            ];
        @endphp
        <div class="dt-step" data-dttab="{{ $kunci }}" @if($tabAktif !== $kunci) hidden @endif>
            <x-workflow-stepper :steps="$batchSteps" :compact="true" table="tblBatch" field="status" :groups="[['Fase 1: Persetujuan', ['REJECTED','DRAFT','PENDING_APPROVER']], ['Fase 2: Pembayaran', ['DONE','PAID']]]" />
        </div>
        @endforeach

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">

            {{-- Daftar dibatasi 1.000 baris terbaru (Concerns\CapsLargeLists);
                 angka pada kartu ringkasan tetap dihitung dari seluruh data. --}}
            @if(!empty($truncated))
            <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> batch terbaru dari total
                <strong>{{ number_format($total, 0, ',', '.') }}</strong>.
                Persempit rentang tanggal untuk melihat sisanya.
            </div>
            @endif

            <!-- FILTER -->
            <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input" id="batchSearchBox"></div>
                    </div>
                    <button aria-label="Clear Filter" type="button" id="btnClearFilterBatch" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>
                    <div id="batchRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                </div>
                <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                    @if($bolehBulk)
                    {{-- Bar bulk: muncul bila ada batch Submitted yang dicentang. --}}
                    <div class="dt-bulk-bar d-none tw:flex-wrap tw:items-center tw:gap-2 tw:rounded-md tw:border tw:border-accent-soft tw:bg-accent-tint tw:py-1 tw:pl-3 tw:pr-1">
                        <span class="dt-bulk-info tw:font-label tw:tabular-nums"></span>
                        <x-tw.button size="sm" class="dt-bulk-approve"><i class="fas fa-check" aria-hidden="true"></i> Approve</x-tw.button>
                        <x-tw.button variant="danger" size="sm" class="dt-bulk-reject"><i class="fas fa-times" aria-hidden="true"></i> Reject</x-tw.button>
                        <x-tw.button variant="ghost" size="sm" class="dt-bulk-clear" title="Batalkan pilihan" aria-label="Batalkan pilihan"><i class="fas fa-times" aria-hidden="true"></i></x-tw.button>
                    </div>
                    @endif
                    <x-tw.button href="{{ url('/daily-transaction/export') }}" id="btnExportBatch" variant="secondary" size="sm">
                        <i class="fas fa-file-excel" aria-hidden="true"></i> Export Excel
                    </x-tw.button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tblBatch" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light">
                        <tr>
                            @if($bolehBulk)
                            <th scope="col" class="text-center dt-col-check" style="width:1%;"><input type="checkbox" class="dt-check-all" aria-label="Pilih semua batch Submitted"></th>
                            @endif
                            <th scope="col" style="width:1%;" class="text-nowrap">No</th>
                            <th scope="col" class="text-nowrap" data-col="kode">Kode Batch</th>
                            <th scope="col" class="text-nowrap" data-col="tanggal">Tanggal</th>
                            <th scope="col" data-col="desc">Deskripsi</th>
                            <th scope="col" class="text-nowrap col-type" data-col="type">Type</th>
                            <th scope="col" class="text-nowrap" data-col="kategori">Kategori</th>
                            <th scope="col" class="text-nowrap text-right" data-col="item">Item</th>
                            <th scope="col" class="text-nowrap" data-col="dibuat">Dibuat Oleh</th>
                            <th scope="col" class="text-nowrap text-right" data-col="total">Total</th>
                            <th scope="col" class="text-nowrap" data-col="status">Status</th>
                            <th scope="col" class="text-center action-column" style="width:1%;"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $trx)
                        @php
                            // Relasi sudah di-eager load di controller; ()->count() memicu satu query per baris.
                            $crCount = $trx->includedItems->count();
                            // Gerbang peran disalin apa adanya; yang berubah hanya susunannya.
                            $bolehPutus = $trx->status === 'PENDING_APPROVER' && Auth::user()->hasRole(['superadmin','approver']);
                            $bolehDraft = $trx->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','finance','finance_manager','treasury']);
                            $kategori = $trx->isTopUp()
                                ? ($trx->sourceFund->name ?? '-') . ' → ' . ($trx->toSourceFund->name ?? '-')
                                : ($trx->sourceFund->name ?? '-');
                        @endphp
                        <tr data-id="{{ $trx->id }}" data-code="{{ $trx->code }}" data-typekey="{{ $trx->type }}" data-status="{{ $trx->status }}" data-category="{{ $trx->sourceFund->name ?? '' }}" data-type="{{ $trx->type_label }}">
                            @if($bolehBulk)
                            <td class="text-center dt-col-check">
                                @php $eligBulk = $trx->status === 'PENDING_APPROVER'; @endphp
                                <input type="checkbox" class="dt-check-row" value="{{ $trx->id }}" data-elig="{{ $eligBulk ? '1' : '0' }}"
                                       aria-label="Pilih batch {{ $trx->code }}"
                                       @unless($eligBulk) disabled title="Hanya batch Submitted yang bisa di-approve/reject massal" @endunless>
                            </td>
                            @endif
                            <td class="text-nowrap">{{ $loop->iteration }}</td>
                            <td class="text-nowrap">
                                {{-- Klik kode = salin; detail dibuka lewat klik baris. --}}
                                <button type="button" class="ts-copy-code" data-code="{{ $trx->code }}" title="Klik untuk menyalin kode">{{ $trx->code }}</button>
                            </td>
                            <td class="text-nowrap" data-order="{{ $trx->date->format('Y-m-d') }}">{{ $trx->date->format('d/m/Y') }}</td>
                            <td class="ts-col-desc" title="{{ $trx->description }}">{{ \Illuminate\Support\Str::limit($trx->description ?? '-', 60) }}</td>
                            <td class="text-nowrap col-type">{{ $trx->type_label }}</td>
                            <td class="text-nowrap ts-col-desc" title="{{ $kategori }}">{{ $kategori }}</td>
                            <td class="text-nowrap text-right">{{ $trx->isTopUp() ? '-' : $crCount }}</td>
                            <td class="text-nowrap ts-col-desc">{{ $trx->createdBy->name ?? '-' }}</td>
                            <td class="text-right text-nowrap col-raw-total ts-col-money" data-raw="{{ $trx->grand_total }}">{{ $trx->grand_total_formatted }}</td>
                            <td class="text-nowrap">
                                <x-status :status="$trx->status" context="daily-transaction" />
                            </td>
                            <td class="text-center text-nowrap action-column">
                                {{-- 0 aksi = sel kosong (detail lewat klik baris); >1 aksi = menu titik tiga. --}}
                                <div class="ts-actions">
                                    @if($bolehPutus)
                                    <button type="button" class="ts-action-more"
                                            aria-haspopup="true" aria-expanded="false"
                                            title="Aksi" aria-label="Aksi untuk batch {{ $trx->code }}">
                                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                    </button>
                                    <div class="ts-action-menu dropdown-menu" hidden>
                                        <button type="button" class="dropdown-item btn-approve-batch"
                                                data-id="{{ $trx->id }}" data-code="{{ $trx->code }}">
                                            <i class="fas fa-check"></i>Approve
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        <button type="button" class="dropdown-item is-danger btn-reject-batch"
                                                data-id="{{ $trx->id }}" data-code="{{ $trx->code }}">
                                            <i class="fas fa-times"></i>Reject
                                        </button>
                                    </div>
                                    @endif
                                    @if($bolehDraft)
                                    <button type="button" class="ts-action-more"
                                            aria-haspopup="true" aria-expanded="false"
                                            title="Aksi" aria-label="Aksi untuk draft {{ $trx->code }}">
                                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                    </button>
                                    <div class="ts-action-menu dropdown-menu" hidden>
                                        <button type="button" class="dropdown-item btn-submit-batch"
                                                data-id="{{ $trx->id }}" data-code="{{ $trx->code }}">
                                            <i class="fas fa-paper-plane"></i>Submit
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        <button type="button" class="dropdown-item is-danger btn-delete-batch"
                                                data-id="{{ $trx->id }}" data-code="{{ $trx->code }}">
                                            <i class="fas fa-trash"></i>Hapus Draft
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        {{-- Sengaja tidak ada baris di sini. Tabel ini dikelola DataTables, dan
                             baris kosong buatan sendiri akan dianggap baris data lalu selnya
                             dikosongkan. Keadaan kosongnya diisi language.emptyTable, sedangkan
                             language.zeroRecords untuk hasil pencarian yang tidak cocok. --}}
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--surface-2);">
                            <th colspan="{{ $bolehBulk ? 9 : 8 }}" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                            <th class="text-right text-nowrap" id="batchFooterTotal" style="font-size:12px;"></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-tw.card>

      </div>
    </div>
</div>

{{-- DRAWER DETAIL BATCH — pola drawer Cost Request: klik baris membuka detail batch
     lengkap (Info / Item / Log) beserta seluruh aksi halaman detail (Approve/Tolak,
     Submit/Hapus Draft, Tandai PAID, Export Data/Batch, Takeout/Keluarkan item).
     Info & Log dari daily-transaction.show-json; tab Item dirender server lewat
     daily-transaction.items-html (partial yang sama dengan halaman detail). --}}
<x-drawer id="dtDrawer" label="Detail Batch" width="50vw">
    <div id="dtDrawerContent" hidden>
        <ul class="nav nav-tabs mb-3" id="dtDetailTabs" style="border-bottom:2px solid var(--border);">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#dtPaneInfo"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#dtPaneItems"><i class="fas fa-list-ul mr-1"></i> Item <span id="dtItemCount" class="badge badge-secondary ml-1"></span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#dtPaneLog"><i class="fas fa-history mr-1"></i> Log</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="dtPaneInfo"></div>
            <div class="tab-pane fade" id="dtPaneItems">
                <div id="dtItemHost"></div>
                <div class="mt-3">
                    <h6 class="ts-dw-section-title">
                        <i class="fas fa-paperclip mr-1"></i> Lampiran <span id="dtAttCount" class="badge badge-secondary ml-1"></span>
                    </h6>
                    <div id="dtAttBody" class="ts-dw-files"></div>
                </div>
            </div>
            <div class="tab-pane fade" id="dtPaneLog">
                <div id="dtLogBody" class="cr-chat"></div>
                {{-- Kotak catatan gaya chat (sama dengan tab Log Cost Request); disembunyikan JS untuk batch PAID/REJECTED. --}}
                <form id="dtLogForm" class="cr-log-form" hidden>
                    <textarea id="dtLogInput" class="form-control" rows="2" maxlength="1000"
                              placeholder="Tulis catatan atau update…" aria-label="Catatan log"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary" id="dtLogSend"><i class="fas fa-paper-plane mr-1"></i> Kirim</button>
                </form>
                <div id="dtLogClosed" class="cr-log-closed" hidden><i class="fas fa-lock mr-1"></i> Batch sudah selesai — catatan baru tidak dapat ditambahkan.</div>
            </div>
        </div>
    </div>

    <div id="dtDrawerError" hidden></div>

    <div id="dtDrawerLoading">
        <x-skeleton type="lines" :rows="2" />
        <div style="height:14px"></div>
        <x-skeleton type="card" />
        <div style="height:14px"></div>
        <x-skeleton type="lines" :rows="4" />
    </div>

    <x-slot:footer>
        <div id="dtDrawerActions" class="ts-actions" style="flex:1;justify-content:flex-start;flex-wrap:wrap;"></div>
    </x-slot:footer>
</x-drawer>

@include('daily-transaction.partials.action-modals')

<!-- MODAL APPROVE BATCH -->
<div class="modal fade" id="mdlApproveBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> Approve Batch</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmApproveBatch" method="POST">
                <input type="hidden" name="from_index" value="1">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="approveBatchText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Catatan approval..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Ya, Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT BATCH -->
<div class="modal fade" id="mdlRejectBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:var(--accent);"></i> Tolak Seluruh Batch</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectBatch" method="POST">
                <input type="hidden" name="from_index" value="1">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="rejectBatchText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Tuliskan alasan penolakan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Tolak Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Data CR amounts untuk JS --}}
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
.font-weight-600 { font-weight:600; }
.table td,.table th { vertical-align:middle !important; }
.cr-check-item:hover { background:var(--surface-2); }
.cr-check-item.selected { background:var(--info-bg); }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:var(--radius-sm); }
#tblBatch { width:100% !important; }

.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
#batchSearchBox input { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper          { min-width:260px; }
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.ts-control .item {
    max-width: 240px;
    display: flex;
    align-items: center;
    overflow: visible;
}
.ts-control .item > div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}
.ts-control .item .remove { flex-shrink: 0; }

.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
/* Tab Log drawer gaya chat — disalin dari tab Log Cost Request (budget-request/index). */
.cr-chat { display:flex; flex-direction:column; gap:14px; padding:4px 2px; }
.cr-chat-row { display:flex; gap:10px; max-width:78%; }
.cr-chat-row.mine { align-self:flex-end; flex-direction:row-reverse; text-align:right; }
.cr-chat-av { width:30px; height:30px; border-radius:50%; background:var(--info-bg); color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; overflow:hidden; }
.cr-chat-row.mine .cr-chat-av { background:var(--charcoal); color:#fff; }
.cr-chat-av img { width:100%; height:100%; object-fit:cover; }
.cr-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.cr-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
.cr-chat-note { margin-top:6px; font-size:11.5px; line-height:1.45; white-space:pre-line; color:var(--text); }
#dtPaneLog.active { display:flex; flex-direction:column; }
#dtPaneLog #dtLogBody { margin-top:auto; }
#dtPaneLog .cr-log-form, #dtPaneLog .cr-log-closed { position:sticky; bottom:-24px; margin-bottom:-24px; padding-bottom:24px; background:var(--surface); }
.cr-log-form { display:flex; gap:8px; align-items:flex-end; margin-top:16px; padding-top:12px; border-top:1px solid var(--border); }
.cr-log-form textarea { font-size:12px; resize:none; flex:1 1 auto; }
.cr-log-closed { margin-top:16px; padding-top:12px; border-top:1px solid var(--border); font-size:11.5px; color:var(--text-faint); }
/* Gulir mendatar hanya pada tabel, bukan footer (info + paginasi). */
.table-responsive:has(> .dataTables_wrapper) { overflow-x:visible; }
.ts-dt-scroll { overflow-x:auto; width:100%; }
/* Kolom Type disembunyikan di tab Cash Out / Top Up (isinya sama untuk semua baris). */
#tblBatch.hide-col-type .col-type { display:none; }
/* Kolom Aksi beku di kanan saat tabel digulir mendatar. Mode card mobile dikecualikan
   lewat media query; kelas .ts-card-table TIDAK bisa dipakai sebagai pengecualian
   karena tesys-table.js memasangnya di semua lebar layar (CSS-nya yang membatasi <= 640px). */
@media (min-width: 641px) {
    #tblBatch > thead > tr > th.action-column,
    #tblBatch > tbody > tr > td:last-child:not([colspan]) {
        position: sticky; right: 0; z-index: 1;
        background-color: var(--surface);
    }
    #tblBatch > thead > tr > th.action-column { z-index: 3; background-color: var(--surface-2) !important; }
    #tblBatch > tbody > tr > td:last-child:not([colspan]) { box-shadow: -1px 0 0 var(--border); }
    #tblBatch > tbody > tr:hover > td:last-child:not([colspan]) { background-color: var(--surface-2); }
}
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
@media (max-width: 576px) {
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #batchSearchBox input { width:100%; }
    #btnClearFilterBatch { width:100%; }
    #batchRowFilters { width:100%; }
    #batchRowFilters .tcf-row-btn { width:100%; }
    .ts-wrapper { width:100% !important; min-width:0 !important; }
}
/* --- Filter dropdown: bedakan header grup vs item --- */
.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text) !important;
    background: var(--surface-2) !important;
    padding: 8px 12px !important;
    border-top: 1px solid var(--border);
    cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header {
    border-top: none;
}
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option {
    padding-left: 26px !important;
}

/* Drawer membawa aksi lengkap: modal (Mark PAID, View Budget, Takeout, Approve) dan
   konfirmasi Swal tampil DI ATAS drawer (z-index 1070), bukan di belakangnya. */
body.ts-drawer-lock .modal { z-index: 1085; }
body.ts-drawer-lock .modal-backdrop { z-index: 1080; }
body.ts-drawer-lock .swal2-container { z-index: 1095; }
#dtItemHost .table { font-size: 12px; }

/* Kaki drawer: .ts-actions aslinya kisi slot 36px untuk kolom aksi tabel (label
   disembunyikan). Di sini tombolnya berlabel, jadi kisi diganti flex yang
   membungkus supaya tombol tidak saling menumpuk. */
body.tesys-ui #dtDrawerActions.ts-actions { display: flex; flex-wrap: wrap; gap: 8px; }
body.tesys-ui #dtDrawerActions.ts-actions .ts-action-primary {
    width: auto; min-width: 0; height: 34px; padding: 0 12px; gap: 6px; overflow: visible;
}
body.tesys-ui #dtDrawerActions.ts-actions .ts-action-primary .ts-al {
    position: static; width: auto; height: auto; margin: 0; overflow: visible; clip-path: none;
}
#dtItemHost .dt-items-loading { padding: 8px 0; }

/* Tombol kaki drawer menampilkan caption (aturan global .ts-actions
   menyembunyikan .ts-al untuk kolom aksi tabel). */
#dtDrawerActions .ts-action-primary { width: auto; padding: 0 12px; gap: 6px; }
body.tesys-ui #dtDrawerActions .ts-action-primary .ts-al {
    position: static; width: auto; height: auto; margin: 0;
    overflow: visible; clip-path: none;
}
</style>
@endpush

@push('scripts')
<script src="@aset('js/tesys-col-resize.js')"></script>
<script src="@aset('js/tesys-treasury-batch.js')"></script>
<script>
$(function () {

    /* ── FILTER TANGGAL ────────────────────────────────────────────────────
    | Komponen bersama TesysDateRange (public/js/tesys-date-range.js), sama
    | seperti Cost Request: satu kotak teks yang membuka dua kalender From/To.
    | Rentang baru langsung memuat ulang daftar batch (filternya server-side).
    | Kalau flatpickr gagal termuat, filter turun ke dua input teks manual. */
    function reloadWithDateRangeBatch(from, to, range) {
        var url = new URL(window.location.href);
        url.searchParams.set('date_from', from);
        url.searchParams.set('date_to', to);
        if (range) { url.searchParams.set('range', range); }
        else       { url.searchParams.delete('range'); }
        window.location.href = url.toString();
    }

    var batchPeriode = (typeof TesysDateRange === 'object')
        ? TesysDateRange.init({
            input: '#batchDateRange', from: '#batchDateFrom', to: '#batchDateTo',
            quick: true,
            onApply: function (from, to, range) {
                /* Pemanggilan pertama hanya menuliskan nilai awal. */
                if (from === '{{ substr($dateFrom, 0, 10) }}' && to === '{{ substr($dateTo, 0, 10) }}') return;
                reloadWithDateRangeBatch(from, to, range);
            }
        })
        : null;

    if (!batchPeriode) {
        console.warn('TesysDateRange tidak tersedia - filter tanggal beralih ke mode input manual.');
        $('#batchDateRange').remove();
        $('#batchDateFrom, #batchDateTo').attr('type', 'text')
            .addClass('form-control form-control-sm')
            .attr('placeholder', 'YYYY-MM-DD')
            .on('change', function () {
                reloadWithDateRangeBatch($('#batchDateFrom').val(), $('#batchDateTo').val());
            });
    }

    /*
      Warna tombol konfirmasi dan keadaan sibuk — pola yang sama dengan Cost
      Request. Aksi batch adalah POST biasa yang memuat ulang halaman, jadi
      tanda sibuk memang berguna di sini.
    */
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');

    function sibukkan(form) {
        if (form.target && form.target !== '_self') return;
        var tombol = form.querySelector('button[type="submit"]');
        if (tombol && window.TesysUI && TesysUI.busy) TesysUI.busy(tombol, true);
    }

    $(document).on('submit', '#frmApproveBatch, #frmRejectBatch', function () { sibukkan(this); });

    // ── Approve/Reject dibind duluan (defensif, konsisten dgn page lain) ──
    $(document).on('click', '.btn-approve-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#approveBatchText').text('Approve batch ' + code + '? Batch siap diproses untuk pembayaran.');
        $('#frmApproveBatch').attr('action', '/daily-transaction/' + id + '/approve');
        $('#frmApproveBatch textarea[name="notes"]').val('');
        $('#mdlApproveBatch').modal('show');
    });

    $(document).on('click', '.btn-reject-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#rejectBatchText').text('Tolak batch ' + code + '? Semua CR dikembalikan ke APPROVED.');
        $('#frmRejectBatch').attr('action', '/daily-transaction/' + id + '/reject-batch');
        $('#frmRejectBatch textarea[name="notes"]').val('');
        $('#mdlRejectBatch').modal('show');
    });

    /* ── TAB TIPE BATCH (Cash Out / Top Up / Database) ─────────────────────
       Satu tabel; tab hanya menyaring baris lewat data-typekey (Database =
       semua). Stepper per tab ikut berganti, filter stepper dibersihkan, dan
       tab aktif disimpan di ?tab= supaya bertahan setelah muat ulang. */
    var DT_TAB_TIPE = { co: 'CASH_OUT', tu: 'TOP_UP', db: '' };
    var dtTabAktif = $('#dtTabs .nav-link.active').data('dttab') || 'co';

    $.fn.dataTable.ext.search.push(function (settings, data, idx) {
        if (settings.nTable.id !== 'tblBatch') return true;
        var tipe = DT_TAB_TIPE[dtTabAktif];
        if (!tipe) return true;
        var node = settings.aoData[idx] && settings.aoData[idx].nTr;
        return !node || node.getAttribute('data-typekey') === tipe;
    });

    /* Kolom Type hanya berguna di tab Database (semua tipe campur). Disembunyikan
       lewat CSS, jadi colspan label footer disesuaikan: semua kolom kasatmata
       kecuali Total, Status, dan Aksi. */
    function aturKolomType() {
        var sembunyi = dtTabAktif !== 'db';
        var $tbl = $('#tblBatch').toggleClass('hide-col-type', sembunyi);
        var kolom = $tbl.find('thead th').length - (sembunyi ? 1 : 0);
        $tbl.find('tfoot tr').first().children().first().attr('colspan', kolom - 3);
    }

    function pilihTabBatch(kunci) {
        dtTabAktif = kunci;
        $('#dtTabs .nav-link').removeClass('active').filter('[data-dttab="' + kunci + '"]').addClass('active');
        $('.dt-step').attr('hidden', true).filter('[data-dttab="' + kunci + '"]').removeAttr('hidden');
        if (window.TesysWorkflowStepper) TesysWorkflowStepper.clear('tblBatch');
        aturKolomType();
        if ($.fn.dataTable.isDataTable('#tblBatch')) $('#tblBatch').DataTable().draw();
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', kunci);
            window.history.replaceState(null, '', url.toString());
        } catch (e) {}
    }

    $('#dtTabs').on('click', '.nav-link', function (e) {
        e.preventDefault();
        var kunci = $(this).data('dttab');
        if (kunci && kunci !== dtTabAktif) pilihTabBatch(kunci);
    });

    /* ── DRAWER DETAIL BATCH ──────────────────────────────────────────────
       Klik di mana saja pada baris membuka drawer (pola Cost Request). Kontrol
       di dalam baris (kode yang disalin, menu aksi, tombol kontrol responsif
       DataTables) tetap bekerja sendiri. */
    function escDt(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function escAttrDt(s) { return escDt(s).replace(/"/g, '&quot;'); }
    function isiDt(v) { return (v === null || v === undefined || v === '') ? '-' : v; }

    /* ── TAB LOG GAYA CHAT (pola tab Log Cost Request) ── */
    var dtUserId = {{ (int) Auth::id() }};

    function renderLogRowDt(log) {
        var mine = log.user_id && log.user_id === dtUserId;
        var isNote = log.kind === 'note';
        var actor = log.actor || 'System';
        var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
        var isi = isNote
            ? '<div class="cr-chat-note" style="margin-top:0;">' + escDt(log.notes) + '</div>'
            : '<span class="badge badge-' + escAttrDt(log.action_color || 'secondary') + ' px-2 py-1" style="font-size:9px;">' + escDt(log.action_label) + '</span>'
              + (log.description ? ' <span style="font-size:11px;">' + escDt(log.description) + '</span>' : '')
              + (log.notes ? '<div class="cr-chat-note">' + escDt(log.notes) + '</div>' : '');
        return '<div class="cr-chat-row ' + (mine ? 'mine' : '') + '">'
            + '<div class="cr-chat-av">' + (log.avatar ? '<img src="' + escAttrDt(log.avatar) + '" alt="">' : escDt(initials)) + '</div>'
            + '<div><div class="cr-chat-meta"><strong>' + escDt(actor) + '</strong> · ' + escDt(log.date) + '</div>'
            + '<div class="cr-chat-bubble">' + isi + '</div></div></div>';
    }

    /* Tab Log setinggi area gulir drawer supaya chat menempel di bawah walau isinya sedikit. */
    function aturTinggiLogDt(gulirKeBawah) {
        var body = document.getElementById('dtDrawer-body');
        var $tabs = $('#dtDetailTabs');
        if (!body || !$tabs.length) return;
        var cs = getComputedStyle(body);
        var tinggi = body.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom)
                   - $tabs.outerHeight() - parseFloat($tabs.css('margin-bottom')) - parseFloat($tabs.css('margin-top'));
        $('#dtPaneLog').css('min-height', Math.max(tinggi, 0));
        if (gulirKeBawah) body.scrollTop = body.scrollHeight;
    }
    $(document).on('shown.bs.tab', '#dtDetailTabs a[data-toggle="tab"]', function () {
        if ($(this).attr('href') === '#dtPaneLog') aturTinggiLogDt(true);
    });
    $(window).on('resize', function () { if ($('#dtPaneLog').hasClass('active')) aturTinggiLogDt(false); });

    $(document).on('submit', '#dtLogForm', function (e) {
        e.preventDefault();
        var $f = $(this), $in = $('#dtLogInput'), $btn = $('#dtLogSend');
        var teks = $.trim($in.val());
        if (!teks) { $in.focus(); return; }
        $btn.prop('disabled', true);
        $.post('{{ url('/daily-transaction') }}/' + $f.data('id') + '/log-note', { _token: $('meta[name="csrf-token"]').attr('content'), notes: teks })
            .done(function (res) {
                if (!res || !res.log) return;
                $('#dtLogBody .dt-log-empty').remove();
                $('#dtLogBody').append(renderLogRowDt(res.log));
                $in.val('');
                var body = document.getElementById('dtDrawer-body');
                if (body) body.scrollTop = body.scrollHeight;
            })
            .fail(function (xhr) {
                var pesan = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && xhr.responseJSON.errors.notes && xhr.responseJSON.errors.notes[0])))
                    || 'Catatan belum dapat dikirim. Coba lagi.';
                Swal.fire({ icon: 'error', title: 'Gagal mengirim', text: pesan });
            })
            .always(function () { $btn.prop('disabled', false); });
    });

    /* Form aksi (Mark PAID, Takeout, draft) membawa from_index=1 — lihat tesys-treasury-batch.js. */
    window.TESYS_DT_FROM_INDEX = true;

    var dtDetail = null;      // JSON batch yang sedang terbuka di drawer
    var dtItemsUntuk = null;  // id batch yang tab Item-nya sudah dimuat

    /* ?open={id} di URL selama drawer terbuka: setelah aksi (POST → back()) halaman
       kembali ke daftar yang sama dan drawer batch itu dibuka ulang. */
    function setOpenParam(id) {
        try {
            var url = new URL(window.location.href);
            if (id) url.searchParams.set('open', id); else url.searchParams.delete('open');
            window.history.replaceState(null, '', url.toString());
        } catch (e) {}
    }

    /* Tab Item: tabel "Daftar CR dalam Batch" dari server (partial yang sama dengan
       halaman detail), dimuat saat tab dibuka pertama kali. Modal View Budget-nya
       dipindah ke <body> supaya tidak terjepit di dalam panel drawer. */
    function muatItemsDt(id) {
        if (dtItemsUntuk === id) return;
        dtItemsUntuk = id;
        $('#dtItemHost').html('<div class="dt-items-loading text-muted" style="font-size:12px;"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat daftar item…</div>');
        $.get('{{ url('/daily-transaction') }}/' + encodeURIComponent(id) + '/items-html')
            .done(function (html) {
                if (dtItemsUntuk !== id) return;
                $('body > .dt-coa-modals').remove();
                var $html = $('<div>').html(html);
                $html.find('.dt-coa-modals').appendTo('body');
                $('#dtItemHost').empty().append($html.contents());
            })
            .fail(function () {
                dtItemsUntuk = null;
                $('#dtItemHost').html('<p class="text-danger mb-0" style="font-size:12px;"><i class="fas fa-exclamation-triangle mr-1"></i>Daftar item belum dapat dimuat. Buka ulang tab Item.</p>');
            });
    }
    $(document).on('shown.bs.tab', '#dtDetailTabs a[href="#dtPaneItems"]', function () {
        if (dtDetail && !dtDetail.is_top_up) muatItemsDt(dtDetail.id);
    });

    /* Aksi kaki drawer — gerbang peran/status dari server (d.can), sama dengan halaman detail. */
    function renderAksiDt(d) {
        var can = d.can || {}, h = '', attr = ' data-id="' + escAttrDt(d.id) + '" data-code="' + escAttrDt(d.code) + '"';
        var jml = d.is_top_up ? 'Top Up' : (d.included_count || 0) + ' CR';
        var tombol = function (kelas, ikon, label, ekstra) {
            return '<button type="button" class="ts-action-primary ' + kelas + '"' + (ekstra || attr) + '><i class="fas ' + ikon + '"></i><span class="ts-al">' + escDt(label) + '</span></button>';
        };
        if (can.approve) {
            h += tombol('btn-approve-batch', 'fa-check', 'Approve (' + jml + ')');
            h += tombol('is-danger btn-reject-batch', 'fa-times', 'Tolak Seluruh Batch');
        }
        if (can.draft) {
            h += tombol('btn-submit-batch', 'fa-paper-plane', 'Submit ke CEO (' + (d.is_top_up ? 'Top Up' : (d.included_count || 0) + ' item') + ')');
            h += tombol('is-danger btn-delete-batch', 'fa-trash', 'Hapus Draft');
        }
        if (can.mark_paid) {
            var info = d.is_top_up ? 'Top Up ini akan ditandai PAID (transfer selesai).' : (d.included_count || 0) + ' CR dalam batch ini akan berstatus PAID.';
            h += tombol('btn-mark-paid', 'fa-money-bill-wave', 'Tandai PAID (' + jml + ')',
                ' data-action="{{ url('/daily-transaction') }}/' + escAttrDt(d.id) + '/mark-paid" data-info="' + escAttrDt(info) + '"');
        }
        if (can.export_data) {
            h += '<a href="{{ url('/daily-transaction') }}/' + escAttrDt(d.id) + '/export-data" class="ts-action-primary is-neutral"><i class="fas fa-file-excel"></i><span class="ts-al">Export Data</span></a>';
        }
        if (!d.is_top_up) {
            h += can.export_batch
                ? '<a href="{{ url('/daily-transaction') }}/' + escAttrDt(d.id) + '/export" class="ts-action-primary is-neutral"><i class="fas fa-file-excel"></i><span class="ts-al">Export Batch</span></a>'
                : '<button type="button" class="ts-action-primary is-neutral" disabled title="Export tidak tersedia lagi setelah batch DONE/PAID"><i class="fas fa-file-excel"></i><span class="ts-al">Export Batch</span></button>';
        }
        $('#dtDrawerActions').html(h);
    }

    /* ── PANGGUNG EVIDENCE (carousel di kiri drawer) — komponen bersama tesys-evidence-stage.js ── */
    var stageEvidence = TesysEvidenceStage.attach('dtDrawer');

    /* Drawer ditutup → ?open= dibuang supaya muat ulang biasa tidak membukanya lagi. */
    $(document).on('click', '#dtDrawer [data-drawer-close]', function () { setOpenParam(null); });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && !$('.modal.show, .swal2-container, .tes-ev-overlay.show').length) setOpenParam(null);
    });

    function bukaDrawerBatch(id, pemicu) {
        var $baris = pemicu ? $(pemicu).closest('tr') : $('#tblBatch tbody tr[data-id="' + id + '"]').first();

        $('#dtDrawerContent').attr('hidden', true);
        $('#dtDrawerError').attr('hidden', true).empty();
        $('#dtDrawerLoading').show();
        $('#dtDrawer-eyebrow').text('');
        $('#dtDrawer-title').text('Memuat…');
        $('#dtDrawerActions').empty();
        $('#dtDetailTabs a[href="#dtPaneInfo"]').tab('show');

        TesysDrawer.open('dtDrawer', pemicu);
        dtItemsUntuk = null;
        $('#dtItemHost').empty();
        stageEvidence.clear();
        setOpenParam(id);

        function gagal(pesan) {
            $('#dtDrawerLoading').hide();
            $('#dtDrawerError').html(
                '<div class="ts-empty">'
                + '<span class="ts-empty-ic"><i class="fas fa-exclamation-triangle"></i></span>'
                + '<p class="ts-empty-title">Detail belum dapat dimuat</p>'
                + '<p class="ts-empty-msg">' + escDt(pesan) + '</p></div>'
            ).removeAttr('hidden');
            $('#dtDrawer-title').text($baris.data('code') || 'Detail Batch');
        }

        $.get('{{ url('/daily-transaction') }}/' + encodeURIComponent(id) + '/detail-json')
            .done(function (d) {
                // Sesi habis -> AJAX menerima HTML login berstatus 200, bukan JSON.
                if (!d || typeof d !== 'object' || !d.code) {
                    gagal('Sesi Anda mungkin sudah berakhir. Muat ulang halaman, lalu masuk kembali.');
                    return;
                }

                var tipe = d.type_label || $baris.data('type') || '';
                $('#dtDrawer-eyebrow').html(
                    '<button type="button" class="ts-copy-code" data-code="' + escAttrDt(d.code) + '" title="Klik untuk menyalin kode">'
                    + escDt(d.code) + '</button>' + escDt(tipe ? ' · ' + tipe : ''));
                $('#dtDrawer-title').text(d.description || d.code);

                var sumber = isiDt(d.source_fund)
                    + (d.source_fund_bank && d.source_fund_bank !== '-' ? ' · ' + d.source_fund_bank : '')
                    + (d.source_fund_no_account && d.source_fund_no_account !== '-' ? ' · ' + d.source_fund_no_account : '');
                var siapa = function (nama, waktu) { return (nama && nama !== '-') ? nama + (waktu ? ' · ' + waktu : '') : '-'; };

                var info = '<dl class="ts-dw-kv">'
                    + '<dt>Status</dt><dd><span class="badge badge-' + escAttrDt(d.status_color || 'secondary') + '">' + escDt(d.status_label || d.status) + '</span></dd>'
                    + '<dt>Tanggal</dt><dd>' + escDt(isiDt(d.date)) + '</dd>'
                    + '<dt>Type</dt><dd>' + escDt(isiDt(tipe)) + '</dd>'
                    + (d.is_top_up
                        ? '<dt>Dari (Main Account)</dt><dd>' + escDt(sumber) + '</dd>'
                          + '<dt>Ke (Rekening Operasional)</dt><dd>' + escDt(isiDt(d.to_source_fund)
                              + (d.to_source_fund_bank && d.to_source_fund_bank !== '-' ? ' · ' + d.to_source_fund_bank : '')
                              + (d.to_source_fund_no_account && d.to_source_fund_no_account !== '-' ? ' · ' + d.to_source_fund_no_account : '')) + '</dd>'
                        : '<dt>Sumber Dana</dt><dd>' + escDt(sumber) + '</dd>')
                    + '<dt>Total Item</dt><dd class="is-amount">' + escDt(isiDt(d.total_amount_formatted)) + '</dd>'
                    + '<dt>Biaya</dt><dd class="is-amount">' + escDt(isiDt(d.charge_formatted)) + '</dd>'
                    + '<dt>Grand Total</dt><dd class="is-amount">' + escDt(isiDt(d.grand_total_formatted)) + '</dd>'
                    + '<dt>Item</dt><dd>' + escDt((d.included_count || 0) + ' aktif' + (d.rejected_count ? ', ' + d.rejected_count + ' ditolak' : '')) + '</dd>'
                    + '<dt>Dibuat</dt><dd>' + escDt(siapa(d.created_by, d.created_at)) + '</dd>'
                    + '<dt>Approved</dt><dd>' + escDt(siapa(d.approved_by, d.approved_at)) + '</dd>'
                    + '<dt>Paid</dt><dd>' + escDt(siapa(d.paid_by, d.paid_at)) + '</dd>'
                    + '<dt>Catatan</dt><dd>' + escDt(isiDt(d.notes)) + '</dd>'
                    + '<dt>Catatan Bayar</dt><dd>' + escDt(isiDt(d.payment_notes)) + '</dd>'
                    + '</dl>';
                $('#dtPaneInfo').html(info);

                $('#dtItemCount').text(d.is_top_up ? '-' : (d.included_count || 0));
                dtDetail = d;
                if (d.is_top_up) {
                    // Top Up tidak berisi CR — tab Item menampilkan rincian transfer (sama dengan halaman detail).
                    dtItemsUntuk = d.id;
                    $('#dtItemHost').html('<dl class="ts-dw-kv">'
                        + '<dt>Dari</dt><dd>' + escDt(sumber) + '</dd>'
                        + '<dt>Ke</dt><dd>' + escDt(isiDt(d.to_source_fund) + (d.to_source_fund_no_account && d.to_source_fund_no_account !== '-' ? ' · ' + d.to_source_fund_no_account : '')) + '</dd>'
                        + '<dt>Jumlah Transfer</dt><dd class="is-amount">' + escDt(isiDt(d.captured_amount_formatted)) + '</dd></dl>');
                } else if ($('#dtPaneItems').hasClass('active')) {
                    muatItemsDt(d.id);
                }

                renderAksiDt(d);
                stageEvidence.render(d.evidence || []);

                var atts = d.attachments || [];
                $('#dtAttCount').text(atts.length);
                $('#dtAttBody').html(atts.length
                    ? atts.map(function (a) {
                        return '<a class="ts-dw-file" href="/storage/' + escAttrDt(a.path) + '" data-evidence="dt-att" data-evidence-name="' + escAttrDt(a.original_name) + '" target="_blank" rel="noopener">'
                            + '<i class="fas ' + (a.is_image ? 'fa-file-image' : 'fa-file') + '"></i><span>' + escDt(a.original_name) + '</span><small>' + escDt(isiDt(a.size_formatted)) + '</small></a>';
                    }).join('')
                    : '<p class="text-muted mb-0" style="font-size:12px;">Belum ada lampiran.</p>');

                // Tab Log — gaya chat seperti Cost Request: aktivitas batch + catatan bebas,
                // terlama di atas; milik user yang login rata kanan.
                var logs = d.logs || [];
                $('#dtLogBody').html(logs.length
                    ? logs.map(renderLogRowDt).join('')
                    : '<p class="text-muted mb-0 dt-log-empty" style="font-size:12px;">Belum ada riwayat untuk batch ini.</p>');
                var bisaCatat = d.can_post_log_note !== false;
                $('#dtLogForm').prop('hidden', !bisaCatat).data('id', d.id);
                $('#dtLogClosed').prop('hidden', bisaCatat);
                $('#dtLogInput').val('');
                if ($('#dtPaneLog').hasClass('active')) aturTinggiLogDt(true);

                $('#dtDrawerLoading').hide();
                $('#dtDrawerContent').removeAttr('hidden');
            })
            .fail(function (xhr) {
                gagal(xhr && xhr.status === 404 ? 'Batch tidak ditemukan.' : 'Terjadi kesalahan saat memuat data. Coba lagi.');
            });
    }

    $(document).on('click', '#tblBatch tbody tr[data-id]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, .ts-actions, .ts-action-menu, td.dtr-control, .dt-col-check').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        bukaDrawerBatch($(this).data('id'), this);
    });


    /* Submit / Hapus Draft / Keluarkan item, Takeout, Tandai PAID, dan modal View
       Budget ditangani public/js/tesys-treasury-batch.js (sama dengan halaman detail). */
    function warnaToken(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }

    /* Kembali dari aksi drawer (atau tautan ?open={id}): buka ulang drawer batch tsb. */
    (function () {
        var buka = new URLSearchParams(window.location.search).get('open');
        if (buka && /^\d+$/.test(buka)) bukaDrawerBatch(parseInt(buka, 10), null);
    })();

    /* ── BULK APPROVE / REJECT BATCH (pola Cost Request) ────────────────────
       Hanya batch Submitted (PENDING_APPROVER) yang bisa dicentang — hak
       centang dihitung server (data-elig). Maks 50 per proses; pilihan yang
       tidak lagi lolos filter/tab dibuang supaya yang dikirim = yang terlihat.
       Server menerima expected[id] dan melewati batch yang statusnya berubah. */
    var DT_BULK_MAX = 50;
    var dtSel = new Set();

    function dtBulkTabel() { return $.fn.dataTable.isDataTable('#tblBatch') ? $('#tblBatch').DataTable() : null; }
    function dtBulkInfo(tr) {
        var $tr = $(tr);
        return {
            id: String($tr.attr('data-id')), status: $tr.attr('data-status'), code: $tr.attr('data-code'),
            total: parseInt($tr.find('.col-raw-total').attr('data-raw'), 10) || 0
        };
    }
    function dtBulkWarn() {
        if (window.tsToast) window.tsToast('warning', 'Maksimal ' + DT_BULK_MAX + ' batch per proses. Pilihan dibatalkan; persempit dengan filter lalu pilih lagi.');
    }
    function dtBulkRefresh() {
        var t = dtBulkTabel();
        if (!t) return;
        var tampil = new Set(), jml = 0, total = 0, kandidat = 0, dipilih = 0;
        t.rows({ search: 'applied' }).every(function () {
            var node = this.node(), inf = dtBulkInfo(node);
            tampil.add(inf.id);
            if ($(node).find('.dt-check-row').attr('data-elig') === '1') {
                kandidat++;
                if (dtSel.has(inf.id)) dipilih++;
            }
        });
        dtSel.forEach(function (id) { if (!tampil.has(id)) dtSel.delete(id); });

        $(t.rows().nodes()).each(function () {
            var inf = dtBulkInfo(this);
            $(this).find('.dt-check-row').prop('checked', dtSel.has(inf.id));
            if (dtSel.has(inf.id)) { jml++; total += inf.total; }
        });

        var all = document.querySelector('#tblBatch .dt-check-all');
        if (all) {
            all.checked = kandidat > 0 && dipilih === kandidat;
            all.indeterminate = dipilih > 0 && dipilih < kandidat;
        }
        $('.dt-bulk-bar').toggleClass('d-none', jml === 0).toggleClass('d-flex', jml > 0);
        $('.dt-bulk-info').text(jml + ' dipilih · total Rp ' + Math.round(total).toLocaleString('id-ID'));
    }

    $(document).on('change', '.dt-check-row', function () {
        var id = String(this.value);
        if (this.checked) {
            if (dtSel.size >= DT_BULK_MAX) { this.checked = false; dtBulkWarn(); return; }
            dtSel.add(id);
        } else {
            dtSel.delete(id);
        }
        dtBulkRefresh();
    });

    $(document).on('change', '.dt-check-all', function () {
        var t = dtBulkTabel();
        if (!t) return;
        if (!this.checked) { dtSel.clear(); dtBulkRefresh(); return; }
        var gabungan = new Set(dtSel);
        t.rows({ search: 'applied' }).every(function () {
            var $n = $(this.node());
            if ($n.find('.dt-check-row').attr('data-elig') === '1') gabungan.add(String($n.attr('data-id')));
        });
        if (gabungan.size > DT_BULK_MAX) { dtSel.clear(); dtBulkWarn(); dtBulkRefresh(); return; }
        gabungan.forEach(function (id) { dtSel.add(id); });
        dtBulkRefresh();
    });

    $(document).on('click', '.dt-bulk-clear', function () { dtSel.clear(); dtBulkRefresh(); });

    var dtBulkKirim = false;
    $(document).on('click', '.dt-bulk-approve, .dt-bulk-reject', function () {
        var t = dtBulkTabel();
        if (!t || dtBulkKirim) return;
        var tolak = $(this).hasClass('dt-bulk-reject'), $btn = $(this);
        var rows = [], total = 0;
        $(t.rows().nodes()).each(function () {
            var inf = dtBulkInfo(this);
            if (dtSel.has(inf.id)) { rows.push(inf); total += inf.total; }
        });
        if (!rows.length) return;

        var html = '<div style="text-align:left;font-size:13px;line-height:1.7;">'
            + '<div><strong>' + rows.length + ' batch</strong>: Submitted &rarr; ' + (tolak ? 'Rejected' : 'Done') + '</div>'
            + '<div>Total: <strong>Rp ' + Math.round(total).toLocaleString('id-ID') + '</strong></div>'
            + (tolak ? '<div class="mt-1" style="font-size:12px;">Semua Cost Request di batch yang ditolak kembali ke APPROVED.</div>' : '')
            + '</div>';

        var opsi = tolak
            ? { title: 'Tolak ' + rows.length + ' batch?', icon: 'warning',
                html: html + '<div class="mt-2" style="text-align:left;font-size:12px;">Alasan penolakan wajib diisi:</div>',
                input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan penolakan' },
                inputValidator: function (v) { if (!v || !v.trim()) return 'Alasan penolakan wajib diisi.'; },
                confirmButtonColor: warnaToken('--danger', '#9a3030'), confirmButtonText: 'Ya, Tolak' }
            : { title: 'Approve ' + rows.length + ' batch?', icon: 'question', html: html,
                confirmButtonColor: warnaToken('--accent', '#92232b'), confirmButtonText: 'Ya, Approve' };
        opsi.showCancelButton = true;
        opsi.cancelButtonColor = warnaToken('--text-muted', '#6a6e72');
        opsi.cancelButtonText = 'Batal';

        Swal.fire(opsi).then(function (r) {
            if (!r.isConfirmed || dtBulkKirim) return;
            dtBulkKirim = true;
            var $f = $('<form method="POST"></form>').attr('action', tolak
                ? '{{ route('daily-transaction.bulk-reject') }}' : '{{ route('daily-transaction.bulk-approve') }}');
            $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            rows.forEach(function (x) {
                $f.append($('<input type="hidden" name="ids[]">').val(x.id));
                $f.append($('<input type="hidden">').attr('name', 'expected[' + x.id + ']').val(x.status));
            });
            if (tolak) $f.append($('<input type="hidden" name="notes">').val(r.value));
            $('.dt-bulk-bar button').prop('disabled', true);
            if (window.TesysUI && TesysUI.busy) TesysUI.busy($btn[0], true);
            $('body').append($f);
            $f[0].submit();
        });
    });

    // Kembali lewat tombol Back (bfcache): kunci kirim dilepas lagi.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        dtBulkKirim = false;
        $('.dt-bulk-bar button').prop('disabled', false).each(function () {
            if (window.TesysUI && TesysUI.busy) TesysUI.busy(this, false);
        });
    });

    $('#btnRefresh').on('click', function () { location.reload(); });

    (function updateExportBatchLink() {
        var url = '{{ url("/daily-transaction/export") }}?date_from=' + encodeURIComponent('{{ $dateFrom }}') + '&date_to=' + encodeURIComponent('{{ $dateTo }}');
        $('#btnExportBatch').attr('href', url);
    })();

    $('#btnClearFilterBatch').on('click', function () {
        TesysColumnFilter.clearAll('#tblBatch');
        TesysWorkflowStepper.clear('tblBatch');
    });

    try {

    var DT_ADA_CEK = {{ $bolehBulk ? 'true' : 'false' }};

    var table = $('#tblBatch').DataTable({
        autoWidth: false, pageLength: 10,
        order: [], // pertahankan urutan asli dari server (input terbaru di atas)
        // Kolom centang bulk (bila dirender server) menggeser indeks kolom No satu langkah.
        columnDefs: [
            { targets: [DT_ADA_CEK ? 1 : 0], visible: false, orderable: false },
            // Kolom aksi: header kosong, tanpa sortir & tanpa ikut pencarian (pola Cost Request).
            { targets: [-1], orderable: false, searchable: false }
        ].concat(DT_ADA_CEK ? [{ targets: [0], orderable: false, searchable: false }] : []),
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            search: '', lengthMenu: 'Page Size _MENU_',
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>'
                + '<p class="ts-empty-title">Belum ada batch pembayaran</p>'
                + '<p class="ts-empty-msg">Belum ada batch pada rentang tanggal yang dipilih.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada batch yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_-_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    // Scrollbar mendatar hanya untuk tabel: DataTables membungkus tabel bersama
    // footer (info + paginasi) dalam satu wrapper, jadi .table-responsive di
    // luar ikut menggulir footer. Tabel dibungkus lagi di dalam wrapper itu.
    $('#tblBatch').wrap('<div class="ts-dt-scroll"></div>');

    // Kolom bisa diseret (lebar disimpan per browser) — helper bersama tesys-col-resize.js.
    if (window.TesysColResize) TesysColResize.init(document.getElementById('tblBatch'), 'dtColW_batch');

    TesysColumnFilter.initRowFilters('#tblBatch', '#batchRowFilters', [
        { key: 'type', label: 'Type' },
        { key: 'category', label: 'Kategori' },
    ]);

    $('#tblBatch_filter').hide();
    $('#batchSearchBox').html('<input type="search" id="batchSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#batchSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    /*
    | FOOTER KALKULASI — total dari SEMUA baris yang lolos filter/search saat ini.
    */
    function moneyFmtBatch(v) {
        return Math.round(v).toLocaleString('id-ID');
    }

    function recalcFooterBatch() {
        var total = 0;
        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            total += parseFloat(node.querySelector('.col-raw-total')?.dataset.raw) || 0;
        });
        $('#batchFooterTotal').text(moneyFmtBatch(total));
    }

    /* Export Excel mengikuti filter yang SEDANG aktif di tabel — lihat
       public/js/tesys-export-filtered.js. Tanpa filter tambahan, tautannya
       tetap GET dengan rentang tanggal yang sudah dipasang di atas. */
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnExportBatch', table, '{{ url("/daily-transaction/export") }}');
    }

    aturKolomType();
    table.on('draw', recalcFooterBatch);
    recalcFooterBatch();
    table.on('draw', dtBulkRefresh);
    dtBulkRefresh();

    } catch (err) {
        console.error('Gagal inisialisasi DataTable Treasury:', err);
    }

});
</script>
@endpush