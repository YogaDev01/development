@extends('layouts.app')

@section('title', 'Cost Request')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Cost Request</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Cost Request</li>
            </ol>
        </div>

        <!-- SUMMARY WORKFLOW (clickable, filter tabel Cost Request) -->
        @php
            $crSteps = [
                ['Draft',     $summary['draft'],     'secondary', 'DRAFT'],
                ['Submitted', $summary['submitted'], 'warning',   'SUBMITTED'],
                ['Checked',   $summary['checked'],   'info',      'CHECKED'],
                ['Verified',  $summary['verified'],  'primary',   'VERIFIED'],
                ['Approved',  $summary['approved'],  'success',   'APPROVED'],
                ['Process',   $summary['queued'],    'warning',   'QUEUED'],
                ['Paid',      $summary['paid'],      'success',  'PAID'],
                ['Settle',    $summary['realized'],  'primary',  'REALIZED'],
                ['Settle Checked',  $summary['settle_checked'],  'info',    'SETTLE_CHECKED'],
                ['Settle Verified', $summary['settle_verified'], 'primary', 'SETTLE_VERIFIED'],
                ['Closed',    $summary['closed'],    'dark',     'CLOSED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$crSteps" :compact="true" table="tblCR" field="status" />
        <!-- <div class="text-muted text-center" style="font-size:11.5px;margin-top:-4px;margin-bottom:12px;">
            <i class="fas fa-info-circle mr-1"></i>Pengajuan akan diproses pada hari Senin, Rabu &amp; Jumat
        </div> -->

        {{-- Worklist approval per Budget Plan (dulu section "Review Cost Request") sudah
             dipindah ke halaman Todo List Request. --}}
        <div class="card card-primary card-outline shadow-sm mt-3">

            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1"></i> Daftar Request</h3>
                <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
                    <div class="d-flex align-items-center cr-date-range" style="gap:4px;">
                        <input type="text" id="crDateFrom" class="form-control form-control-sm" value="{{ $dateFrom }}" readonly>
                        <span class="text-muted" style="font-size:12px;">s/d</span>
                        <input type="text" id="crDateTo" class="form-control form-control-sm" value="{{ $dateTo }}" readonly>
                    </div>
                    <button id="btnRefreshCR" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="card-body">

                @if(!empty($truncated))
                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> Cost Request terbaru dari total
                    <strong>{{ number_format($total, 0, ',', '.') }}</strong> pada rentang tanggal ini.
                    Persempit rentang tanggal, atau gunakan <strong>Export Excel</strong> untuk data lengkap.
                </div>
                @endif

                <!-- FILTER -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="crSearchBox"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterCR" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="crRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <div class="dropdown" id="crColVisDropdown">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown">
                                <i class="fas fa-columns mr-1"></i> Kolom
                            </button>
                            <div class="dropdown-menu dropdown-menu-right p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                                @php
                                    $crHideableCols = [
                                        'tanggal'   => 'Tanggal',
                                        'paid_at'   => 'Tanggal Paid',
                                        'pengaju'   => 'Pengaju',
                                        'penerima'  => 'Penerima',
                                        'realisasi' => 'Settlement',
                                        'refund'    => 'Refund',
                                    ];
                                @endphp
                                @foreach($crHideableCols as $key => $label)
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input cr-col-vis-toggle" id="crColVis_{{ $key }}" data-column="{{ $key }}">
                                    <label class="custom-control-label" for="crColVis_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <a href="{{ url('/budget-request/export') }}" id="btnExportCR" class="btn btn-info btn-sm">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </a>
                        @if(Auth::user()->hasRole(['superadmin','manager','supervisor']))
                        <a href="{{ url('/budget-request/create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Tambah Request
                        </a>
                        @endif
                    </div>
                </div>

                <!-- TABLE -->
                <div class="cr-table-wrap" id="crTableWrap">
                    <table id="tblCR" class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="4%"  class="all">No</th>
                                <th scope="col" class="all text-nowrap cr-col-code" style="width:1%;">Code</th>
                                <th scope="col" class="all cr-col-desc">Description</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Tanggal</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Tanggal Paid</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Requestor</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Request</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Settlement</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Refund</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center all action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($budgetRequests as $cr)
                            @php
                                // NOB global: total pengajuan CR melebihi sisa Budget Plan -> merah + badge.
                                // Dibaca dari kolom is_nob (dulu: awalan teks '[NOB]' pada deskripsi).
                                $isNob = (bool) $cr->is_nob;
                                // NOB sub-item: hanya salah satu baris rincian yang melebihi sisa sub-item budgetnya -> kuning.
                                $isNobItem = !$isNob && $cr->details->contains(fn ($d) => $d->is_nob);
                                $realisasi = $cr->total_realization;
                                /* Refund baru nyata SETELAH settle. Sebelumnya kolom ini
                                   memakai selisih mentah (amount - realisasi), jadi CR yang
                                   belum disettle tampil "refund" sebesar nilai pengajuannya
                                   sendiri — angka yang tidak pernah benar. refundAmount()
                                   sudah menjaga aturan itu di satu tempat. */
                                $refund    = $cr->refundAmount();
                            @endphp
                            <tr data-id="{{ $cr->id }}"
                                data-status="{{ $cr->status }}"
                                data-type="{{ $cr->type?->name ?? '' }}"
                                data-pminternal="{{ strtoupper($cr->pm_internal_name) }}"
                                data-po="{{ $cr->budgetPlan?->project ? '[' . $cr->budgetPlan->project->code . '] ' . $cr->budgetPlan->project->name : '' }}"
                                data-beneficiary="{{ $cr->beneficiary?->nickname ?? '' }}"
                                data-nob="{{ ($isNob || $isNobItem) ? '1' : '0' }}"
                                data-date="{{ $cr->created_at->format('Y-m-d') }}"
                                class="{{ $isNob ? 'row-nob' : ($isNobItem ? 'row-nob-item' : '') }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap cr-col-code">
                                    {{-- Kode membuka drawer tinjau; sel nominal dan status tetap
                                         membuka modal pada tab masing-masing seperti sebelumnya. --}}
                                    <button type="button" class="cr-number-btn btn-review-cr" data-id="{{ $cr->id }}">
                                        {{ $cr->code }}
                                    </button>
                                </td>

                                <td class="cr-col-desc">
                                    <div style="font-size:13px;font-weight:600;">
                                        @if($isNob)
                                            <span class="badge badge-danger px-1 py-1 mr-1" style="font-size:9px;">NOB</span>
                                        @endif
                                        {{ Str::limit($cr->description, 200) }}
                                    </div>
                                    <div style="font-size:10px;color:var(--text-faint);margin-top:2px;">
                                        {{ $cr->po_code ?? '-' }} - {{ $cr->type?->name ?? '-' }}, {{ strtoupper($cr->pm_internal_name) }}
                                    </div>
                                </td>

                                <td class="text-nowrap" data-order="{{ $cr->created_at->format('Y-m-d') }}">
                                    {{ $cr->created_at->format('d/m/Y') }}
                                </td>

                                <td class="text-nowrap" data-order="{{ $cr->paid_at?->format('Y-m-d') ?? '' }}">
                                    {{ $cr->paid_at?->format('d/m/Y') ?? '-' }}
                                </td>

                                <td class="text-nowrap">
                                    {{ $cr->applicant?->username ? strtoupper($cr->applicant->username) : '-' }}
                                </td>

                                <td class="text-nowrap">
                                    {{ $cr->beneficiary?->nickname ?? '-' }}
                                </td>

                                <td class="text-right text-nowrap col-raw-amount" data-raw="{{ $cr->amount }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr" data-id="{{ $cr->id }}" data-tab="#crPaneDetails" title="Detail Request">{{ $cr->amount_formatted }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-realisasi" data-raw="{{ $realisasi }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr" data-id="{{ $cr->id }}" data-tab="#crPaneRealization" title="Detail Settlement">{{ number_format($realisasi, 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-var {{ $refund > 0 ? 'text-var-pos' : '' }}" data-raw="{{ $refund }}">
                                    {{ number_format($refund, 0, ',', '.') }}
                                </td>

                                <td class="text-nowrap">
                                    {{-- Label, keterangan, dan warna berasal dari App\Support\WorkflowStatus.
                                         Kelas pemicu (cr-status-link btn-detail-cr) dipertahankan supaya
                                         klik pada badge tetap membuka tab Log seperti sebelumnya. --}}
                                    <x-status :status="$cr->status"
                                              context="budget-request"
                                              class="cr-status-link btn-detail-cr"
                                              data-id="{{ $cr->id }}"
                                              data-tab="#crPaneLogs"
                                              role="button"
                                              tabindex="0" />
                                </td>

                                <td class="text-center action-column">
                                    @php
                                        /*
                                          Gerbang peran di bawah ini SALINAN PERSIS dari kondisi
                                          sebelumnya — tidak ada peran yang ditambah maupun
                                          dikurangi. Yang berubah hanya cara menampilkannya:
                                          satu aksi utama bernama + menu untuk sisanya, sehingga
                                          aksi mundur (Reject, Hapus, Cancel) tidak lagi sejajar
                                          dan sewarna dengan aksi maju.
                                        */
                                        $u = Auth::user();
                                        $st = $cr->status;

                                        $bolehSubmit    = $st === 'DRAFT' && $u->hasRole(['superadmin','manager']);
                                        $bolehUbahDraft = $st === 'DRAFT' && $u->hasRole(['superadmin','manager','supervisor']);
                                        $bolehHead      = $st === 'SUBMITTED' && $u->hasRole(['superadmin','head']);
                                        $bolehFinance   = $st === 'CHECKED' && $u->hasRole(['superadmin','finance','finance_manager']);
                                        $bolehCeo       = $st === 'VERIFIED' && $u->hasRole(['superadmin','approver']);
                                        $bolehRevisi    = $st === 'REJECTED' && $u->hasRole(['superadmin','manager','supervisor']);
                                        $bolehBatal     = $st === 'REJECTED' && $u->hasRole(['superadmin','manager']);
                                        $bolehSettle    = $st === 'PAID' && $u->hasRole(['superadmin','manager','supervisor']);
                                        $bolehHeadSet   = $st === 'REALIZED' && $u->hasRole(['superadmin','head']);
                                        $bolehFinSet    = $st === 'SETTLE_CHECKED' && $u->hasRole(['superadmin','finance','finance_manager','cost_control']);
                                        $bolehTutup     = $st === 'SETTLE_VERIFIED' && $u->hasRole(['superadmin','finance_manager']);

                                        $adaAksi = $bolehSubmit || $bolehUbahDraft || $bolehHead || $bolehFinance
                                            || $bolehCeo || $bolehRevisi || $bolehSettle || $bolehHeadSet
                                            || $bolehFinSet || $bolehTutup;
                                    @endphp

                                    <div class="action-buttons ts-actions">

                                        {{-- ── AKSI UTAMA — satu per baris, selalu bernama ── --}}

                                        {{-- DRAFT: manager mengajukan; supervisor hanya menyunting --}}
                                        @if($bolehSubmit)
                                        <form action="{{ url('/budget-request/'.$cr->id.'/submit') }}"
                                              method="POST" class="frmSubmitCR d-inline">
                                            @csrf
                                            <button type="submit" class="ts-action-primary" title="Submit ke Head">
                                                <i class="fas fa-paper-plane"></i><span class="ts-al">Submit</span>
                                            </button>
                                        </form>
                                        @elseif($bolehUbahDraft)
                                        <a href="{{ url('/budget-request/'.$cr->id.'/edit') }}"
                                           class="ts-action-primary is-neutral" title="Edit">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>
                                        @endif

                                        @if($bolehHead)
                                        <button type="button" class="ts-action-primary btn-head-approve"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                        </button>
                                        @endif

                                        @if($bolehFinance)
                                        <button type="button" class="ts-action-primary btn-finance-verify"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Verify">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Verify</span>
                                        </button>
                                        @endif

                                        @if($bolehCeo)
                                        <button type="button" class="ts-action-primary btn-ceo-approve"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                        </button>
                                        @endif

                                        @if($bolehRevisi)
                                        <button type="button" class="ts-action-primary is-neutral btn-manager-revise"
                                                data-id="{{ $cr->id }}" title="Revise → Draft">
                                            <i class="fas fa-undo"></i><span class="ts-al">Revise</span>
                                        </button>
                                        @endif

                                        @if($bolehSettle)
                                        <a href="{{ route('budget-request.realize-form', $cr->id) }}"
                                           class="ts-action-primary" title="Settle">
                                            <i class="fas fa-receipt" aria-hidden="true"></i><span class="ts-al">Settle</span>
                                        </a>
                                        @endif

                                        @if($bolehHeadSet)
                                        <button type="button" class="ts-action-primary btn-head-check-settlement"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Check → Settle Checked">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Check</span>
                                        </button>
                                        @endif

                                        @if($bolehFinSet)
                                        <button type="button" class="ts-action-primary btn-finance-verify-settlement"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Verify → Settle Verified">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Verify</span>
                                        </button>
                                        @endif

                                        @if($bolehTutup)
                                        <button type="button" class="ts-action-primary btn-ceo-close-settlement"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Close">
                                            <i class="fas fa-lock"></i><span class="ts-al">Close</span>
                                        </button>
                                        @endif

                                        {{-- ── AKSI LAIN — termasuk semua aksi mundur/merusak ── --}}
                                        @php
                                            $adaMenu = $bolehUbahDraft || $bolehHead || $bolehFinance || $bolehCeo
                                                || $bolehBatal || $bolehHeadSet || $bolehFinSet;
                                        @endphp

                                        @if($adaMenu)
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $cr->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            {{-- Kode CR membuka drawer ringkas; "Tinjau" membuka
                                                 modal rincian lengkap. Keduanya sengaja berbeda:
                                                 satu untuk melirik, satu untuk memeriksa. --}}
                                            <button type="button" class="dropdown-item btn-detail-cr"
                                                    data-id="{{ $cr->id }}" data-tab="#crPaneInfo">
                                                <i class="fas fa-eye"></i>Tinjau
                                            </button>

                                            @if($bolehSubmit && $bolehUbahDraft)
                                            <a href="{{ url('/budget-request/'.$cr->id.'/edit') }}" class="dropdown-item">
                                                <i class="fas fa-pen"></i>Edit
                                            </a>
                                            @endif

                                            @if($bolehHead)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-head-reject"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehFinance)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-finance-reject"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehCeo)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-ceo-reject"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehHeadSet)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-head-reject-settlement"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}">
                                                <i class="fas fa-times"></i>Tolak settlement
                                            </button>
                                            @endif

                                            @if($bolehFinSet)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-finance-reject-settlement"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}">
                                                <i class="fas fa-times"></i>Tolak settlement
                                            </button>
                                            @endif

                                            @if($bolehBatal)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-manager-cancel"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-ban"></i>Cancel
                                            </button>
                                            @endif

                                            @if($bolehUbahDraft)
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ url('/budget-request/'.$cr->id) }}"
                                                  method="POST" class="frmDeleteCR">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>
                                            @endif

                                        </div>
                                        @endif

                                        @unless($adaAksi)
                                        <button type="button" class="ts-action-more btn-detail-cr"
                                                data-id="{{ $cr->id }}" data-tab="#crPaneDetails"
                                                title="Lihat detail" aria-label="Lihat detail {{ $cr->code }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                        @endunless

                                    </div>
                                </td>
                            </tr>
                            @empty
                            {{-- Sengaja tidak ada baris di sini. Tabel ini dikelola DataTables dan
                                 baris kosong buatan sendiri akan dihitung sebagai baris data lalu
                                 selnya dikosongkan. Isinya diambil dari template di bawah lewat
                                 language.emptyTable. --}}
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--surface-2);">
                                <th colspan="7" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right text-nowrap" id="crFooterAmount" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right text-nowrap" id="crFooterRealisasi" style="font-size:12px;color:var(--success);"></th>
                                <th class="text-right text-nowrap" id="crFooterVar" style="font-size:12px;"></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Isi keadaan kosong tabel. Ditaruh di template, bukan di dalam tbody,
                         karena DataTables yang merender barisnya; ditulis di Blade supaya
                         tombol "Tambah Request" tetap tunduk pada gerbang peran yang sama
                         dengan tombol di toolbar. --}}
                    <template id="crEmptyTemplate">
                        <x-empty title="Belum ada Cost Request"
                                 message="Belum ada pengajuan pada rentang tanggal dan filter yang sedang dipakai. Coba perlebar rentang tanggalnya, atau buat pengajuan baru.">
                            @if(Auth::user()->hasRole(['superadmin','manager','supervisor']))
                                <x-slot:action>
                                    <a href="{{ url('/budget-request/create') }}" class="ts-action-primary">
                                        <i class="fas fa-plus"></i><span class="ts-al">Tambah Request</span>
                                    </a>
                                </x-slot:action>
                            @endif
                        </x-empty>
                    </template>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- ================================================================ --}}
{{-- DRAWER TINJAU CR                                                   --}}
{{-- Permukaan tinjau cepat: status + konteks, ringkasan, timeline,     --}}
{{-- lampiran, dan aksi. Memakai endpoint detail-json yang sudah ada.   --}}
{{-- Tabel rincian yang lebar tetap di modal (tombol "Rincian lengkap").--}}
{{-- ================================================================ --}}
<x-drawer id="crDrawer" label="Detail Cost Request" width="580px">
    <div id="crDrawerContent" hidden></div>

    <div id="crDrawerLoading">
        <x-skeleton type="lines" :rows="2" />
        <div style="height:14px"></div>
        <x-skeleton type="card" />
        <div style="height:14px"></div>
        <x-skeleton type="lines" :rows="4" />
    </div>

    <x-slot:footer>
        <div id="crDrawerActions" class="ts-actions" style="flex:1;justify-content:flex-start;"></div>
        <button type="button" class="ts-action-primary is-neutral" id="crDrawerFull">
            <i class="fas fa-list-alt"></i><span class="ts-al">Rincian lengkap</span>
        </button>
    </x-slot:footer>
</x-drawer>

{{-- ================================================================ --}}
{{-- MODAL DETAIL CR                                                    --}}
{{-- ================================================================ --}}
<div class="modal fade" id="mdlDetailCR" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-xxl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            {{-- Tidak ada pita header di sini. Judul dokumen, kodenya, dan
                 tombol tutup pindah ke kepala panel ringkas gelap di bawah,
                 supaya modal terbaca sebagai SATU lembar: kolom gelap penuh
                 di kiri, ruang kerja terang di kanan. --}}
            <div class="modal-body dm-body" style="padding:0;background:var(--surface);">
                {{-- Kerangka mengikuti bentuk isi modal (kolom kiri ringkasan,
                     kanan tabel) supaya tidak ada lompatan tata letak saat data tiba. --}}
                <div id="crDetailLoading" style="padding:18px;display:flex;gap:16px;flex-wrap:wrap;min-height:420px;">
                    <div style="flex:0 0 200px;max-width:100%;background:var(--surface);border-radius:var(--radius-lg);padding:16px;">
                        <x-skeleton type="card" />
                        <x-skeleton type="lines" :rows="6" />
                    </div>
                    <div style="flex:1;min-width:240px;background:var(--surface);border-radius:var(--radius-lg);padding:16px;">
                        <x-skeleton type="lines" :rows="2" />
                        <x-skeleton type="table" :rows="9" :cols="4" />
                    </div>
                </div>
                <div id="crDetailContent" style="display:none;">
                    <div class="cr-modal-flex">
                        <div class="cr-sidebar dm-side">

                            {{-- Kepala panel. Lingkaran ikon 60px yang dulu ada di
                                 sini dilepas: ia mengambil sepertiga tinggi panel
                                 untuk mengulang informasi yang sudah dinyatakan
                                 oleh modul tempat modal ini dibuka. --}}
                            <div class="dm-head">
                                <button type="button" class="close dm-close" data-dismiss="modal"
                                        aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                                <div id="crCode" class="dm-code"></div>
                                <h5 class="dm-title" id="crModalTitle">Cost Request Detail</h5>
                                <small id="crModalSubtitle" class="dm-sub-hidden"></small>

                                <div class="dm-badges">
                                    <span id="crStatusBadge"></span>
                                    <span id="crNobBadge"></span>
                                </div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Type</span><div id="crType" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Request</span><div id="crAmount" style="color:var(--accent);font-size:18px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Settlement</span><div id="crRealizationAmount" style="color:var(--success);font-size:14px;font-weight:700;margin-top:4px;"></div></div>
                            {{-- Label & warnanya diisi JS: "Refund" kalau settlement lebih
                                 kecil dari pengajuan, "Second Payment" kalau justru lebih besar
                                 (lihat pengisian #crRefundLabel di bawah). --}}
                            <div class="mb-2"><span id="crRefundLabel" style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Refund</span><div id="crRefundAmount" class="dm-calc-zero" style="font-size:14px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Beneficiary</span><div id="crBeneficiary" class="text-white mt-1" style="font-size:12px;"></div><div id="crBank" style="color:var(--text-faint);font-size:11px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Applicant</span><div id="crApplicant" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="cr-sidebar-spacer"></div>
                        </div>
                        <div class="cr-main-panel">
                            <ul class="nav nav-tabs mb-3" id="crDetailTabs" style="border-bottom:2px solid var(--border);flex-wrap:wrap;">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#crPaneInfo" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneDetails" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-list-ul mr-1"></i> Request <span id="crDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneRealization" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-receipt mr-1"></i> Settlement <span id="crRealizationCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneLogs" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-history mr-1"></i> Log</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="crPaneInfo">
                                    {{-- Satu informasi satu baris (.ts-deflist), sama dengan
                                         modal Purchase Order & Budgeting. --}}
                                    <div class="ts-deflist mb-3">
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Reference</span>
                                            <span id="infoCode" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Description</span>
                                            <span id="infoDesc" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Type</span>
                                            <span id="infoType" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Total</span>
                                            <span id="infoAmount" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Email Notification</span>
                                            <span id="infoEmail" class="ts-deflist-value" style="word-break:break-all;"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Budget Plan</span>
                                            <span id="infoBudgetPlan" class="ts-deflist-value"></span>
                                        </div>
                                    </div>
                                    {{-- Baris yang hanya muncul untuk sebagian CR
                                         dipisah supaya id="crScopeRow" tetap bisa
                                         disembunyikan seluruhnya seperti sebelumnya. --}}
                                    <div class="ts-deflist mb-3" id="crScopeRow">
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Scope of Work</span>
                                            <span id="infoProject" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Project</span>
                                            <span id="infoCluster" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row" id="crPaymentNotesCard" style="display:none;">
                                            <span class="ts-deflist-label">Payment Notes</span>
                                            <span id="infoPaymentNotes" class="ts-deflist-value"></span>
                                        </div>
                                        {{-- Notes ikut sebaris dengan informasi lain, tidak lagi
                                             di balik tombol buka-tutup: ia satu keterangan biasa,
                                             dan menyembunyikannya membuat catatan penting terlewat. --}}
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Notes</span>
                                            <span id="infoNotes" class="ts-deflist-value" style="white-space:pre-line;"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneDetails">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:4%;">No</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Description</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Notes</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:13%;">Price</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:6%;">Qty</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Total</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:1%;white-space:nowrap;" id="crDetailActionTh" class="d-none">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="crDetailBody"></tbody>
                                            <tfoot><tr style="background:var(--surface-2);"><th colspan="5" class="text-right">Total (item aktif)</th><th class="text-right" id="crDetailTotal" style="color:var(--info);"></th><th class="crDetailActionCell d-none"></th></tr></tfoot>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">
                                            <i class="fas fa-paperclip mr-1"></i> Attachment <span id="crAttCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                        </h6>
                                        <div id="crAttBody" class="cr-att-grid"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneRealization">
                                    <div id="crNoRealization" class="text-center text-muted py-4" style="display:none;">
                                        <i class="fas fa-receipt d-block mb-2"></i> No settlement data yet.
                                    </div>
                                    <div id="crRealizationWrapper">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0" style="font-size:12px;">
                                                <thead style="background:var(--surface-2);">
                                                    <tr>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;width:4%;">No</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;">CR Detail Item</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:16%;">Total CR</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:16%;">Actual Used</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;width:16%;">Notes</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;width:16%;">Lampiran Item</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="crRealizationBody"></tbody>
                                                <tfoot><tr style="background:var(--surface-2);"><th colspan="3" class="text-right">Total Settlement</th><th class="text-right" id="crRealizationTotal" style="color:var(--success);"></th><th></th><th></th></tr></tfoot>
                                            </table>
                                        </div>
                                        <div class="mt-3">
                                            <h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">
                                                <i class="fas fa-paperclip mr-1"></i> Settlement Attachment (Global)
                                            </h6>
                                            <div id="crRealizationAtts" class="cr-att-grid"></div>
                                        </div>
                                        <div id="crSecondPaymentBox" class="mt-3" style="display:none;"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneLogs">
                                    <div id="crLogBody" class="cr-timeline cr-chat"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL HEAD APPROVE --}}
<div class="modal fade" id="mdlHeadApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> Head Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmHeadApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="headApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL NOTES (Head Revise/Reject, Finance Revise/Reject, Reject Realization) --}}
<div class="modal fade" id="mdlNotes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white" id="mdlNotesTitle"></h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmNotes" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <div id="mdlNotesAlert" class="alert alert-warning mb-3" style="font-size:12px;display:none;"></div>
                <div class="form-group mb-0">
                    <label class="font-weight-600" id="mdlNotesLabel">Catatan <span class="text-danger">*</span></label>
                    <textarea name="notes" class="form-control" rows="3" required placeholder="Tuliskan catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger" id="mdlNotesSubmitBtn">Konfirmasi</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL FINANCE VERIFY --}}
<div class="modal fade" id="mdlFinanceVerify" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Finance Verify</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmFinanceVerify" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="financeVerifyText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Verify</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL CEO APPROVE --}}
<div class="modal fade" id="mdlCeoApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> CEO Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmCeoApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="ceoApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL SUBMIT REALISASI --}}
{{-- MODAL VERIFY REALIZATION --}}
<div class="modal fade" id="mdlVerifyRealization" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Settlement</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmVerifyRealization" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="verifyRealText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" id="btnVerifyRealSubmit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- accounts JSON untuk realisasi --}}

<!-- IMAGE VIEWER (carousel + zoom) -->
<div id="imgViewerOverlay">
    <button aria-label="Close" type="button" id="imgViewerClose" title="Close"><i class="fas fa-times"></i></button>
    <div id="imgViewerName"></div>
    <div id="imgViewerStage">
        <button aria-label="Previous" type="button" class="imgViewerNav" id="imgViewerPrev" title="Previous"><i class="fas fa-chevron-left"></i></button>
        <img id="imgViewerImg" src="" alt="">
        <button aria-label="Next" type="button" class="imgViewerNav" id="imgViewerNext" title="Next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div id="imgViewerCounter"></div>
</div>

@endsection

@push('styles')
<style>
.row-nob td      { color:var(--danger) !important; }
.cr-date-range input.form-control { width:100px !important; padding:2px 6px !important; font-size:12px !important; }
.row-nob-item td { color:var(--warning) !important; }
.text-var-pos { color:var(--success); }
.text-var-neg { color:var(--danger); }

/* ── Gaya index Purchase Order: baris bersih + link kode/status biru-tua ── */
#tblCR tbody tr,
#tblCR tbody tr:hover { background-color:var(--surface) !important; }
#tblCR tbody td { background-color:var(--surface); }
.cr-number-btn, .cr-cell-link {
    display:inline-block; background:transparent; border:none; padding:2px 8px; border-radius:var(--radius-sm);
    color:var(--text); font-weight:700; line-height:1.4;
    transition:background-color .12s ease, color .12s ease;
}
.cr-number-btn { font-size:12px; }
.cr-cell-link { font-size:13px; }
.cr-number-btn:hover, .cr-number-btn:focus,
.cr-cell-link:hover, .cr-cell-link:focus { background:var(--charcoal); color:#fff; outline:none; }
.cr-status-link { cursor:pointer; }
.cr-status-link:hover { filter:brightness(.92); }

/* ── TABEL: scroll horizontal, semua kolom fit-content kecuali Description,
   kolom Code & Action dibekukan (pola sama seperti invoice-cash-receipt &
   project/Purchase Order). ── */
.cr-hscroll { overflow-x:auto; }
.cr-hscroll #tblCR { width:auto !important; min-width:100%; margin-bottom:0; }
.cr-hscroll #tblCR th,
.cr-hscroll #tblCR td { white-space:nowrap; }

.cr-hscroll #tblCR th.cr-col-code,
.cr-hscroll #tblCR td.cr-col-code {
    position:sticky; left:0; z-index:3; background:var(--surface);
    box-shadow:2px 0 6px -2px rgba(0,0,0,.18);
}
.cr-hscroll #tblCR th.action-column,
.cr-hscroll #tblCR td.action-column {
    position:sticky; right:0; z-index:3; background:var(--surface);
    box-shadow:-2px 0 6px -2px rgba(0,0,0,.18);
}
.cr-hscroll #tblCR thead th.cr-col-code,
.cr-hscroll #tblCR thead th.action-column { background:var(--surface-2); z-index:4; }
.cr-hscroll #tblCR tfoot th.cr-col-code,
.cr-hscroll #tblCR tfoot th.action-column { background:var(--surface-2); }

/* Modal detail CR — ukuran & style disamakan dengan modal show Budget Plan. */
.modal-dialog-xxl {
    max-width: calc(100vw - 32px);
    width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    margin: 16px auto;
}
.modal-dialog-xxl .modal-content {
    height: calc(100vh - 32px);
    max-height: calc(100vh - 32px);
    display: flex;
    flex-direction: column;
}
.modal-dialog-xxl .modal-header,
.modal-dialog-xxl .modal-footer { flex: 0 0 auto; }
.modal-dialog-xxl .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}
.modal-dialog-xxl #crDetailContent { height: 100%; }

.cr-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.cr-sidebar {
    width:280px; flex:0 0 280px; background:var(--charcoal); padding:28px 20px;
    display:flex; flex-direction:column;
}
.cr-sidebar-spacer { flex:1 1 auto; }
.cr-main-panel { flex:1 1 auto; padding:24px; min-width:0; }
@media (max-width:767.98px) {
    .cr-modal-flex { flex-direction:column; }
    .cr-sidebar { width:100%; flex:0 0 auto; }
}

/* Log tab — gaya chat sama persis dengan tab Log Purchase Order
   (project/_index-assets.blade.php: .prj-chat-*) — log dari user yang sedang
   login rata kanan ("mine"), lainnya rata kiri, dengan avatar inisial. */
.cr-timeline { padding:14px 4px; }
.cr-chat { display:flex; flex-direction:column; gap:14px; padding:4px 2px; }
.cr-chat-row { display:flex; gap:10px; max-width:78%; }
.cr-chat-row.mine { align-self:flex-end; flex-direction:row-reverse; text-align:right; }
.cr-chat-av { width:30px; height:30px; border-radius:50%; background:var(--info-bg); color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.cr-chat-row.mine .cr-chat-av { background:var(--charcoal); color:#fff; }
.cr-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.cr-chat-row.mine .cr-chat-bubble { border-color:var(--border); }
.cr-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
.cr-chat-note { margin-top:6px; font-size:11.5px; line-height:1.45; white-space:pre-line; color:var(--text); }

/* Budget tab — reuse pola pivot grup/NOB dari Budget Plan */
.bp-caret.rotated { transform:rotate(90deg); }
.cr-req-badge { font-size:9px; }

/* TAB INFO (Cost Request) — clean grid layout, sama seperti Budget Plan */
.cr-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.cr-info-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px 18px; }
.cr-info-grid-ref { display:grid; grid-template-columns:320px 1fr; gap:14px 18px; }
.cr-info-grid-scope { display:grid; grid-template-columns:3fr 1fr; gap:14px 18px; }
.cr-info-grid-scope.has-payment { grid-template-columns:12fr 5fr 3fr; }
.cr-info-item { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:12px 16px; }
.cr-info-label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text-faint); letter-spacing:.03em; margin-bottom:5px; }
.cr-info-value { font-size:13px; color:var(--text); white-space:nowrap; }
.cr-info-grid-scope .cr-info-value { white-space:normal; }
.cr-info-highlight { background:var(--warning-bg); border-color:var(--border-strong); }
.cr-info-highlight .cr-info-value { font-weight:700; color:var(--warning); }
@media (max-width:576px) {
    .cr-info-grid, .cr-info-grid-3, .cr-info-grid-ref, .cr-info-grid-scope, .cr-info-grid-scope.has-payment { grid-template-columns:1fr; }
    .cr-info-value { white-space:normal; }
}

/* TAB REQUEST & REALIZATION — thumbnail grid lampiran */
.cr-att-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; margin-top:8px; }
.cr-att-card { border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; background:var(--surface); }
.cr-att-thumb { width:100%; height:120px; object-fit:cover; display:block; cursor:pointer; background:var(--surface-2); }
.cr-att-icon { width:100%; height:120px; display:flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--text-faint); font-size:30px; }
.cr-att-name { font-size:11px; padding:6px 8px; color:var(--text); word-break:break-word; border-top:1px solid var(--border); }

.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
#crSearchBox input   { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper          { min-width:260px; }
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
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
#tblCR               { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; width:1% !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:3px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
#crDetailTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 12px; }
#crDetailTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; font-weight:700; }
@media (max-width:576px) {
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterCR { width:100%; }
    #crRowFilters { width:100%; }
    #crRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #crSearchBox input { width:100%; }
    .ts-wrapper { width:100% !important; min-width:0 !important; }
    .card-header { flex-direction:column; align-items:stretch !important; gap:10px; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .card-header .ml-auto .btn { flex:1 1 auto; }
    #crRowFilters .tcf-row-btn { flex:1 1 auto; }
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

/* ── IMAGE VIEWER (carousel + zoom, ala WhatsApp) ── */
#imgViewerOverlay {
    position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:2000;
    display:none; align-items:center; justify-content:center; flex-direction:column;
}
#imgViewerOverlay.show { display:flex; }
#imgViewerStage { position:relative; width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
#imgViewerImg {
    max-width:90vw; max-height:82vh; object-fit:contain; cursor:zoom-in;
    transition:transform .15s ease; transform-origin:center center; user-select:none; -webkit-user-drag:none;
}
#imgViewerImg.zoomed { cursor:grab; }
#imgViewerImg.dragging { cursor:grabbing; transition:none; }
#imgViewerClose {
    position:absolute; top:16px; right:20px; z-index:10; background:rgba(255,255,255,.12); border:none; color:#fff;
    width:38px; height:38px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
#imgViewerClose:hover { background:rgba(255,255,255,.25); }
.imgViewerNav {
    position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.12); border:none; color:#fff;
    width:44px; height:44px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
.imgViewerNav:hover { background:rgba(255,255,255,.25); }
#imgViewerPrev { left:16px; }
#imgViewerNext { right:16px; }
#imgViewerCounter { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); color:#fff; font-size:12px; background:rgba(255,255,255,.12); padding:4px 12px; border-radius:var(--radius-lg); }
#imgViewerName { position:absolute; top:20px; left:20px; z-index:10; color:#fff; font-size:12px; opacity:.85; max-width:60vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
@media (max-width:576px) {
    .imgViewerNav { width:36px; height:36px; font-size:16px; }
    #imgViewerPrev { left:6px; } #imgViewerNext { right:6px; }
}
</style>
@endpush

@push('scripts')
<script>
var currentUserId = parseInt('{{ (int) Auth::id() }}', 10) || 0;

$(function () {

    // Keterangan status berasal dari App\Support\WorkflowStatus (satu sumber).
    var statusMeta = @json(collect(\App\Models\BudgetRequest::STATUSES)->mapWithKeys(fn ($s) => [
        $s => \App\Support\WorkflowStatus::meta($s, 'budget-request'),
    ]));


    /* ── FILTER TANGGAL (flatpickr) — diinisialisasi PALING AWAL & dibungkus
       try/catch supaya error di bagian script manapun (DataTable, dsb.) tidak
       pernah bisa membuat date-picker ini gagal terpasang. Kalau library
       flatpickr sendiri gagal termuat (CDN diblokir jaringan, atau file plugin
       lokal belum ter-upload ke server), lepas readonly supaya user tetap bisa
       mengetik tanggal manual (format YYYY-MM-DD) sebagai fallback. */
    try {
        if (typeof flatpickr === 'function') {
            flatpickr('#crDateFrom', {
                dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
                defaultDate: '{{ $dateFrom }}',
                onChange: function (selectedDates, dateStr) { reloadWithDateRange(dateStr, $('#crDateTo').val()); }
            });
            flatpickr('#crDateTo', {
                dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
                defaultDate: '{{ $dateTo }}',
                onChange: function (selectedDates, dateStr) { reloadWithDateRange($('#crDateFrom').val(), dateStr); }
            });
        } else {
            throw new Error('flatpickr is not a function');
        }
    } catch (err) {
        console.warn('Flatpickr gagal diinisialisasi — filter tanggal beralih ke mode input manual.', err);
        $('#crDateFrom, #crDateTo').removeAttr('readonly').attr('placeholder', 'YYYY-MM-DD');
        $('#crDateFrom, #crDateTo').on('change', function () {
            reloadWithDateRange($('#crDateFrom').val(), $('#crDateTo').val());
        });
    }

    // (accounts holder lama sudah tidak diperlukan — realisasi sekarang pakai CR Detail Item)

    /* ── DATATABLE ──
    | Dibungkus try/catch: kalau init gagal (mis. edge-case DataTable/responsive
    | saat tabel benar-benar kosong), error-nya tidak boleh merambat dan
    | menggagalkan binding tombol aksi (approve/reject/dll) yang terdaftar
    | setelah blok ini di file yang sama. */
    // KOLOM TOGGLE — Tanggal/Pengaju/Penerima/Realisasi/Refund bisa disembunyikan
    // lewat dropdown "Kolom", preferensi disimpan di localStorage (pola sama
    // seperti invoice-cash-receipt/index.blade.php).
    var crColVisMap = { tanggal: 3, paid_at: 4, pengaju: 5, penerima: 6, realisasi: 8, refund: 9 };
    var crColVisDefault = { tanggal: true, paid_at: true, pengaju: true, penerima: true, realisasi: false, refund: true };
    function getCrColVis(key) {
        var stored = localStorage.getItem('crColVis_' + key);
        return stored === null ? !!crColVisDefault[key] : stored === '1';
    }
    function setCrColVis(key, visible) {
        localStorage.setItem('crColVis_' + key, visible ? '1' : '0');
    }

    try {
        // TIDAK pakai extension "responsive" DataTables — dia rebentrok dengan
        // toggle visibility manual (Responsive kalkulasi ulang kolom mana yang
        // muat lalu menimpa balik .visible() yang baru di-set user). Solusinya
        // pola sama seperti invoice-cash-receipt & project (Purchase Order):
        // tabel selalu scroll horizontal, kolom Code & Action dibekukan (lihat
        // CSS .cr-hscroll + .wrap() di bawah).
        var table = $('#tblCR').DataTable({
            autoWidth:false, pageLength:10, lengthChange:true, searching:true,
            order: [], // pertahankan urutan asli dari server (input terbaru di atas)
            columnDefs:[
                { targets:[0], visible:false, orderable:false },
                { targets:[3], visible: getCrColVis('tanggal') },
                { targets:[4], visible: getCrColVis('paid_at') },
                { targets:[5], visible: getCrColVis('pengaju') },
                { targets:[6], visible: getCrColVis('penerima') },
                { targets:[8], visible: getCrColVis('realisasi') },
                { targets:[9], visible: getCrColVis('refund') },
            ],
            dom:'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
            /*
              zeroRecords muncul saat filter/pencarian tidak menemukan apa pun —
              kasus yang berbeda dari "belum ada data sama sekali" (itu ditangani
              komponen empty di Blade). Keduanya kini sama-sama menjelaskan sebab
              dan memberi jalan keluar, bukan sekadar "Data tidak ditemukan".

              Catatan: jangan menulis tag komponen atau direktif Blade di dalam
              komentar mana pun pada berkas ini — Blade tetap mengompilasinya.
            */
            language:{ lengthMenu:'Page Size _MENU_',
                emptyTable: (document.getElementById('crEmptyTemplate') || {}).innerHTML || 'Belum ada data',
                zeroRecords:'<div class="ts-empty ts-empty-compact">'
                    + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                    + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                    + '<p class="ts-empty-msg">Tidak ada Cost Request yang cocok dengan pencarian atau filter yang sedang aktif.</p>'
                    + '<div class="ts-empty-action"><button type="button" class="ts-action-primary is-neutral" id="crEmptyReset">'
                    + '<i class="fas fa-undo"></i><span class="ts-al">Reset filter</span></button></div></div>',
                info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
        });

        $('#tblCR').wrap('<div class="cr-hscroll table-responsive"></div>');

        $('.cr-col-vis-toggle').each(function () {
            var key = $(this).data('column');
            if (crColVisMap[key] == null) return;
            $(this).prop('checked', table.column(crColVisMap[key]).visible());
        });
        $('.cr-col-vis-toggle').on('change', function () {
            var key = $(this).data('column');
            if (crColVisMap[key] == null) return;
            var visible = $(this).is(':checked');
            table.column(crColVisMap[key]).visible(visible);
            setCrColVis(key, visible);
            try { table.columns.adjust(); } catch (e) {}
        });
        $('#crColVisDropdown .dropdown-menu').on('click', function (e) { e.stopPropagation(); });
        table.on('column-visibility.dt', function () { try { table.columns.adjust(); } catch (e) {} });

        $('#tblCR_filter').hide();
        $('#crSearchBox').html('<input type="search" id="crSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
        // Debounce — hindari full-scan tabel pada tiap ketukan (berat kalau baris banyak).
        var crSearchTimer = null;
        $('#crSearchInput').on('keyup search', function () {
            var val = this.value;
            clearTimeout(crSearchTimer);
            crSearchTimer = setTimeout(function () { table.search(val).draw(); }, 300);
        });

        /*
        | SINKRON FILTER STEPPER STATUS <-> URL (?status=...)
        | Supaya filter status tidak hilang saat halaman reload setelah action
        | (approve/reject/verify/dll — form-form itu POST lalu redirect back()
        | ke URL saat ini, jadi query string ?status= harus tetap ada).
        */
        $(document).on('click', '.wf-tl-step', function () {
            var $step = $(this);
            if (!$step.closest('[data-wf-table="tblCR"]').length) return;
            var url = new URL(window.location.href);
            if ($step.hasClass('wf-active')) {
                url.searchParams.set('status', $step.data('status'));
            } else {
                url.searchParams.delete('status');
            }
            window.history.replaceState({}, '', url.toString());
        });

        (function applyStatusFilterFromUrl() {
            var status = new URLSearchParams(window.location.search).get('status');
            if (!status) return;
            var $step = $('[data-wf-table="tblCR"] .wf-tl-step[data-status="' + status + '"]');
            if ($step.length) $step.trigger('click');
        })();


        /*
        | FOOTER KALKULASI — total Amount, Realisasi & Var dari SEMUA baris yang lolos
        | filter/search saat ini (bukan cuma baris di halaman aktif). Dihitung ulang tiap draw.
        */
        var moneyFmtCR = function (v) {
            var sign = v < 0 ? '-' : '';
            return sign + Math.abs(Math.round(v)).toLocaleString('id-ID');
        };

        var recalcFooterCR = function () {
            var totalAmount = 0, totalRealisasi = 0, totalVar = 0;
            table.rows({ search: 'applied' }).every(function () {
                var node = this.node();
                totalAmount    += parseFloat(node.querySelector('.col-raw-amount')?.dataset.raw)    || 0;
                totalRealisasi += parseFloat(node.querySelector('.col-raw-realisasi')?.dataset.raw) || 0;
                totalVar       += parseFloat(node.querySelector('.col-raw-var')?.dataset.raw)       || 0;
            });
            $('#crFooterAmount').text(moneyFmtCR(totalAmount));
            $('#crFooterRealisasi').text(moneyFmtCR(totalRealisasi));
            $('#crFooterVar').html(
                '<span class="' + (totalVar >= 0 ? 'text-var-pos' : 'text-var-neg') + '">' + moneyFmtCR(totalVar) + '</span>'
            );
        };

        table.on('draw', recalcFooterCR);
        recalcFooterCR();

        TesysColumnFilter.initRowFilters('#tblCR', '#crRowFilters', [
            { key: 'po',          label: 'PO' },
            { key: 'type',        label: 'Tipe' },
            { key: 'beneficiary', label: 'Beneficiary' },
            { key: 'pminternal',  label: 'Manager' },
            { key: 'status',      label: 'Status' },
        ]);
    } catch (err) {
        console.error('Gagal inisialisasi DataTable Cost Request:', err);
    }

    $('#btnRefreshCR').on('click', function () { location.reload(); });

    $('#btnClearFilterCR').on('click', function () {
        TesysColumnFilter.clearAll('#tblCR');
        TesysWorkflowStepper.clear('tblCR');
        var url = new URL(window.location.href);
        url.searchParams.delete('status');
        window.history.replaceState({}, '', url.toString());
    });

    /* Tombol "Reset filter" pada keadaan kosong memakai ulang perilaku tombol
       Clear Filter di toolbar — tidak ada logika reset kedua. Delegasi dipakai
       karena baris kosong dibuat ulang oleh DataTables setiap kali menggambar. */
    $(document).on('click', '#crEmptyReset', function () {
        // Mengosongkan input saja tidak cukup — DataTables masih memegang kata
        // kunci lama. 'keyup' dipicu agar melewati jalur pencarian yang sama
        // dengan yang dipakai pengguna, bukan jalur kedua.
        $('#crSearchInput').val('').trigger('keyup');
        $('#btnClearFilterCR').trigger('click');
    });

    /* ── FILTER TANGGAL — server-side (reload halaman dengan ?date_from=&date_to=) ──
    | Data memang benar-benar ditarik ulang dari server sesuai rentang ini,
    | bukan sekadar disaring di browser, supaya status/summary di atas selalu
    | konsisten dengan data yang tampil (dan tidak menarik ribuan baris sekaligus).
    | Pakai "date_from"/"date_to" (bukan "from"/"to") karena "from" sudah dipakai
    | link sidebar (?from=finance) untuk penanda menu aktif — beda keperluan. */
    function reloadWithDateRange(from, to) {
        var url = new URL(window.location.href);
        url.searchParams.delete('from');
        url.searchParams.set('date_from', from);
        url.searchParams.set('date_to', to);
        window.location.href = url.toString();
    }

    (function updateExportLink() {
        // Fallback (JS gagal / DataTable belum siap): GET biasa, seluruh rentang tanggal.
        var url = '{{ url("/budget-request/export") }}?date_from=' + encodeURIComponent('{{ $dateFrom }}') + '&date_to=' + encodeURIComponent('{{ $dateTo }}');
        $('#btnExportCR').attr('href', url);
    })();

    // Export mengikuti filter yang SEDANG AKTIF di tabel (search, dropdown
    // PO/Tipe/Beneficiary/Manager/Status, klik stepper status). Kalau
    // TIDAK ada filter tambahan aktif, export tetap ambil seluruh data pada
    // rentang tanggal (bukan cuma baris yang ter-render/ter-cap di tabel) —
    // supaya "Export Excel untuk data lengkap" pada banner truncated masih benar.
    $('#btnExportCR').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);

        try {
            var total    = table.rows().count();
            var filtered = table.rows({ search: 'applied' }).count();

            if (filtered === total) {
                // Tidak ada filter tambahan -> export penuh sesuai rentang tanggal.
                window.location.href = $btn.attr('href');
                return;
            }

            var ids = [];
            table.rows({ search: 'applied' }).every(function () {
                var id = $(this.node()).data('id');
                if (id) ids.push(id);
            });

            var $f = $('<form method="POST" action="' + '{{ url("/budget-request/export") }}' + '"></form>');
            $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            ids.forEach(function (id) {
                $f.append($('<input type="hidden" name="ids[]">').val(id));
            });
            $('body').append($f);
            $f[0].submit();
            $f.remove();
        } catch (err) {
            // DataTable gagal init — fallback: GET biasa, semua data rentang tanggal.
            window.location.href = $btn.attr('href');
        }
    });

    /* ── WARNA TOMBOL KONFIRMASI ──
       Diambil dari token desain, bukan biru/merah bawaan Bootstrap, supaya
       dialog konfirmasi memakai warna yang sama dengan tombol di halaman. */
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');

    /* ── KEADAAN SIBUK ──
       Setiap aksi CR adalah POST biasa yang memuat ulang halaman. Tanpa
       penanda ini tidak ada umpan balik sama sekali antara klik dan halaman
       berganti, sehingga tombol mudah tertekan dua kali. */
    function sibukkan(form) {
        var tombol = form.querySelector('button[type="submit"]');
        if (tombol && window.TesysUI && TesysUI.busy) TesysUI.busy(tombol, true);
    }

    $(document).on('submit',
        '#frmHeadApprove, #frmNotes, #frmFinanceVerify, #frmCeoApprove, #frmVerifyRealization',
        function () { sibukkan(this); });

    /* ── SUBMIT CONFIRM ── */
    $(document).on('submit', '.frmSubmitCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit CR?', text:'CR akan dikirim ke Head untuk direview.', icon:'question', showCancelButton:true, confirmButtonColor:WARNA_UTAMA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    $(document).on('submit', '.frmDeleteCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus CR?', icon:'warning', showCancelButton:true, confirmButtonColor:WARNA_BAHAYA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* ── HEAD APPROVE ── */
    $(document).on('click', '.btn-head-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#headApproveText').text('Approve CR ' + code + '? Status akan menjadi CHECKED dan diteruskan ke Finance.');
        $('#frmHeadApprove').attr('action', '/budget-request/' + id + '/head-approve');
        $('#frmHeadApprove textarea').val('');
        $('#mdlHeadApprove').modal('show');
    });

    /* ── CEO APPROVE ── */
    $(document).on('click', '.btn-ceo-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#ceoApproveText').text('Approve CR ' + code + '? Status akan menjadi APPROVED dan siap masuk batch pembayaran.');
        $('#frmCeoApprove').attr('action', '/budget-request/' + id + '/ceo-approve');
        $('#frmCeoApprove textarea').val('');
        $('#mdlCeoApprove').modal('show');
    });

    /* ── GENERIC NOTES MODAL ── */
    function openNotesModal(id, action, title, label, btnClass, btnText, alertMsg) {
        $('#mdlNotesTitle').html('<i class="fas fa-exclamation-triangle mr-2" style="color:var(--accent);"></i>' + title);
        $('#mdlNotesLabel').html(label + ' <span class="text-danger">*</span>');
        if (alertMsg) { $('#mdlNotesAlert').html(alertMsg).show(); } else { $('#mdlNotesAlert').hide(); }
        $('#frmNotes').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmNotes textarea').val('');
        $('#mdlNotesSubmitBtn').text(btnText).removeClass('btn-danger btn-warning').addClass(btnClass);
        $('#mdlNotes').modal('show');
    }

    $(document).on('click', '.btn-head-reject', function () {
        openNotesModal($(this).data('id'), 'head-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-finance-reject', function () {
        openNotesModal($(this).data('id'), 'finance-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-ceo-reject', function () {
        openNotesModal($(this).data('id'), 'ceo-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-manager-revise', function () {
        openNotesModal($(this).data('id'), 'manager-revise', 'Revise CR → Draft', 'Catatan Revisi', 'btn-warning', 'Revise',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>DRAFT</strong> untuk diperbaiki.');
    });
    $(document).on('click', '.btn-manager-cancel', function () {
        openNotesModal($(this).data('id'), 'manager-cancel', 'Cancel CR', 'Alasan Pembatalan', 'btn-dark', 'Cancel',
            '<i class="fas fa-exclamation-triangle mr-1"></i> CR akan menjadi <strong>CANCELLED</strong> secara permanen dan tidak bisa diproses lagi.');
    });
    /* ── TAKEOUT / RESTORE SUB-ITEM ── */
    function submitPost(url) {
        var $f = $('<form method="POST" action="' + url + '"></form>').append(
            '<input type="hidden" name="_token" value="' + $('meta[name="csrf-token"]').attr('content') + '">'
        );
        $('body').append($f); $f.submit();
    }

    $(document).on('click', '.btn-takeout-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail'), desc = $(this).data('desc') || 'item ini';
        openNotesModal(crId, 'detail/' + detailId + '/takeout', 'Takeout Item', 'Alasan Takeout', 'btn-danger', 'Takeout',
            '<i class="fas fa-info-circle mr-1"></i> Item "<strong>' + esc(desc) + '</strong>" tidak akan dibawa proses. Nilai CR otomatis berkurang. Jika semua item di-takeout, CR menjadi <strong>REJECTED</strong>.');
    });

    $(document).on('click', '.btn-restore-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail');
        Swal.fire({
            title: 'Kembalikan item?', text: 'Item akan ikut diproses lagi dan nilai CR bertambah.',
            icon: 'question', showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Kembalikan', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) submitPost('/budget-request/' + crId + '/detail/' + detailId + '/restore');
        });
    });

    /* ── FINANCE VERIFY ── */
    $(document).on('click', '.btn-finance-verify', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#financeVerifyText').text('Verifikasi CR ' + code + '? Status akan menjadi VERIFIED dan menunggu approval CEO.');
        $('#frmFinanceVerify').attr('action', '/budget-request/' + id + '/finance-verify');
        $('#frmFinanceVerify textarea').val('');
        $('#mdlFinanceVerify').modal('show');
    });

    /* ── SETTLEMENT — HEAD CHECK / FINANCE VERIFY / CEO CLOSE (modal approve
       yang sama, action & teks disesuaikan per tahap) ── */
    function openSettlementApproveModal(id, code, action, text, btnText) {
        $('#verifyRealText').text(text);
        $('#frmVerifyRealization').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmVerifyRealization textarea').val('');
        $('#btnVerifyRealSubmit').html('<i class="fas fa-check-double mr-1"></i> ' + btnText);
        $('#mdlVerifyRealization').modal('show');
    }

    $(document).on('click', '.btn-head-check-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'head-check-settlement',
            'Check Settlement CR ' + code + '? Status akan berubah menjadi SETTLE CHECKED, menunggu verifikasi Finance.',
            'Check → Settle Checked');
    });
    $(document).on('click', '.btn-finance-verify-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'finance-verify-settlement',
            'Verifikasi Settlement CR ' + code + '? Status akan berubah menjadi SETTLE VERIFIED, menunggu Close CEO.',
            'Verify → Settle Verified');
    });
    $(document).on('click', '.btn-ceo-close-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'ceo-close-settlement',
            'Close Settlement CR ' + code + '? Status akan berubah menjadi CLOSED.',
            'Close');
    });

    $(document).on('click', '.btn-head-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'head-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });
    $(document).on('click', '.btn-finance-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'finance-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });

    /* ── MODAL DETAIL ── */
    // Set badge angka pada tab: tampil bila > 0, sembunyi bila 0
    function setTabCount(sel, n) {
        var el = $(sel);
        if (n && n > 0) { el.text(n).show(); }
        else { el.text('').hide(); }
    }

    /* Badge status di modal memakai peta yang sama dengan tabel dan drawer
       (statusMeta, berasal dari App\Support\WorkflowStatus). Peta warna
       kedua yang dulu ada di sini sudah dihapus. */
    function statusBadgeHtml(kode, labelCadangan) {
        var m = statusMeta[kode] || { label: labelCadangan || kode, tone: 'neutral', description: null };
        return '<span class="ts-status ts-status-' + m.tone + ' ts-status-sm"'
             + (m.description ? ' title="' + esc(m.label + ' — ' + m.description) + '"' : '')
             + '>' + esc(m.label) + '</span>';
    }

    /* ── IMAGE VIEWER — carousel + zoom (ala WhatsApp), untuk tab Request & Realization ── */
    (function () {
        var overlay  = document.getElementById('imgViewerOverlay');
        var stageImg = document.getElementById('imgViewerImg');
        var counter  = document.getElementById('imgViewerCounter');
        var images   = [];
        var idx      = 0;
        var zoomed   = false;
        var isDragging = false, dragStartX = 0, dragStartY = 0, imgX = 0, imgY = 0;
        var justSwiped = false;   // menandai klik yang sebenarnya gerakan geser

        function renderViewer() {
            var item = images[idx];
            if (!item) return;
            resetZoom();
            stageImg.src = item.full;
            $('#imgViewerName').text(item.name || '');
            counter.textContent = (idx + 1) + ' / ' + images.length;
            /* Panah hanya ada kalau memang ada tujuannya: tidak tampil untuk
               lampiran tunggal, tidak ada panah kiri di lampiran pertama dan
               tidak ada panah kanan di lampiran terakhir. */
            var banyak = images.length > 1;
            $('#imgViewerPrev').css('display', (banyak && idx > 0) ? 'flex' : 'none');
            $('#imgViewerNext').css('display', (banyak && idx < images.length - 1) ? 'flex' : 'none');
        }

        function resetZoom() {
            zoomed = false;
            imgX = 0; imgY = 0;
            stageImg.classList.remove('zoomed', 'dragging');
            stageImg.style.transform = 'translate(0px, 0px) scale(1)';
            stageImg.style.cursor = 'zoom-in';
        }

        function applyTransform() {
            stageImg.style.transform = 'translate(' + imgX + 'px, ' + imgY + 'px) scale(' + (zoomed ? 2.4 : 1) + ')';
        }

        function openViewer(groupImages, startIndex) {
            images = (groupImages || []).filter(function (it) { return it && it.full; });
            if (!images.length) return; // jangan buka overlay kosong
            idx = Math.min(Math.max(startIndex || 0, 0), images.length - 1);
            renderViewer();
            overlay.classList.add('show');
            $('body').css('overflow', 'hidden');
        }

        function closeViewer() {
            overlay.classList.remove('show');
            $('body').css('overflow', '');
        }

        function showNext() { if (idx < images.length - 1) { idx++; renderViewer(); } }
        function showPrev() { if (idx > 0) { idx--; renderViewer(); } }

        // Trigger: klik thumbnail gambar pada grid Attachment (Request maupun Realization)
        $(document).on('click', '.cr-att-thumb', function () {
            // Grup carousel = .cr-att-grid terdekat. Kalau thumbnail dirender
            // tanpa wrapper (mis. langsung di sel tabel Settlement), pakai
            // parent terdekat supaya daftar gambar tidak pernah kosong.
            var $group = $(this).closest('.cr-att-grid');
            if (!$group.length) $group = $(this).closest('td').length ? $(this).closest('td') : $(this).parent();
            var $thumbs = $group.find('.cr-att-thumb');
            if (!$thumbs.length) $thumbs = $(this);
            var groupImages = $thumbs.map(function () {
                return { full: $(this).data('full'), name: $(this).data('name') };
            }).get();
            var startIndex = $thumbs.index(this);
            openViewer(groupImages, startIndex < 0 ? 0 : startIndex);
        });

        // Dipakai tombol "View" pada kolom Aksi tab Request (lampiran per item).
        window.__crOpenImgViewer = openViewer;

        // Tombol View lampiran per item — buka carousel untuk gambar; file non-gambar
        // (pdf/doc) dibuka di tab baru sebagai fallback.
        $(document).on('click', '.btn-view-detail-att', function () {
            var id   = $(this).data('detail');
            var atts = (window.__crDetailAtt && window.__crDetailAtt[id]) || [];
            var imgs = atts.filter(function (a) { return a.is_image; })
                           .map(function (a) { return { full: '/storage/' + a.path, name: a.original_name }; });
            if (imgs.length) {
                openViewer(imgs, 0);
            } else if (atts.length) {
                window.open('/storage/' + atts[0].path, '_blank', 'noopener');
            }
        });

        $('#imgViewerClose').on('click', closeViewer);
        $('#imgViewerNext').on('click', showNext);
        $('#imgViewerPrev').on('click', showPrev);
        $(overlay).on('click', function (e) {
            // Klik di area kosong menutup viewer — kecuali kalau klik itu
            // ujung dari gerakan geser antar lampiran.
            if (justSwiped) { justSwiped = false; return; }
            if (e.target === overlay) closeViewer();
        });

        $(document).on('keydown', function (e) {
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'Escape') closeViewer();
            else if (e.key === 'ArrowRight') showNext();
            else if (e.key === 'ArrowLeft') showPrev();
        });

        stageImg.addEventListener('dblclick', function () {
            zoomed = !zoomed;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        });
        stageImg.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoomed = e.deltaY < 0 ? true : false;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        }, { passive: false });

        stageImg.addEventListener('mousedown', function (e) {
            if (!zoomed) return;
            isDragging = true;
            dragStartX = e.clientX - imgX;
            dragStartY = e.clientY - imgY;
            stageImg.classList.add('dragging');
        });
        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            imgX = e.clientX - dragStartX;
            imgY = e.clientY - dragStartY;
            applyTransform();
        });
        document.addEventListener('mouseup', function () {
            isDragging = false;
            stageImg.classList.remove('dragging');
        });

        /* ── GESER KIRI/KANAN untuk pindah lampiran ─────────────────────────
           Saat gambar TIDAK di-zoom, tarikan mendatar (mouse maupun sentuh)
           berpindah ke lampiran sebelum/sesudahnya — pola yang sama dengan
           galeri ponsel. Saat di-zoom, tarikan tetap menggeser gambar
           (pan), jadi keduanya tidak saling rebutan. */
        var SWIPE_MIN = 50;          // px, biar klik/getar kecil tidak terbaca geser
        var swipeX = null, swipeY = null;

        function swipeStart(x, y) { if (!zoomed) { swipeX = x; swipeY = y; } }

        function swipeEnd(x, y) {
            if (swipeX === null || images.length < 2) { swipeX = swipeY = null; return; }
            var dx = x - swipeX, dy = y - swipeY;
            swipeX = swipeY = null;
            if (Math.abs(dx) < SWIPE_MIN || Math.abs(dx) <= Math.abs(dy)) return;
            if (dx < 0 && idx >= images.length - 1) return;   // sudah di lampiran terakhir
            if (dx > 0 && idx <= 0) return;                   // sudah di lampiran pertama
            justSwiped = true;
            if (dx < 0) showNext(); else showPrev();
        }

        stageImg.addEventListener('mousedown', function (e) { swipeStart(e.clientX, e.clientY); });
        document.addEventListener('mouseup', function (e) {
            if (!overlay.classList.contains('show')) { swipeX = swipeY = null; return; }
            swipeEnd(e.clientX, e.clientY);
        });

        stageImg.addEventListener('touchstart', function (e) {
            var t = e.changedTouches[0];
            swipeStart(t.clientX, t.clientY);
        }, { passive: true });
        stageImg.addEventListener('touchend', function (e) {
            var t = e.changedTouches[0];
            swipeEnd(t.clientX, t.clientY);
        }, { passive: true });

        /* Geser juga boleh lewat area kosong di kiri/kanan gambar. */
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === stageImg) return;
            swipeStart(e.clientX, e.clientY);
        });
    })();

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function escAttr(s) { return esc(s).replace(/"/g, '&quot;'); }

    /* Keadaan kosong ringkas untuk ruang sempit di dalam modal — bentuknya
       sama dengan komponen keadaan kosong yang dipakai tabel dan drawer. */
    function kosongRingkas(pesan) {
        return '<div class="ts-empty ts-empty-compact"><p class="ts-empty-msg">' + esc(pesan) + '</p></div>';
    }

    /* Markup kerangka disimpan sekali karena saat pemuatan gagal isinya
       diganti pesan galat; tanpa ini pembukaan berikutnya kehilangan kerangka. */
    var kerangkaDetail = null;
    function pulihkanKerangka() {
        var el = $('#crDetailLoading');
        if (kerangkaDetail === null) kerangkaDetail = el.html();
        else el.html(kerangkaDetail);
    }

    function openCRDetail(id, tab) {
        pulihkanKerangka();
        $('#crDetailLoading').show(); $('#crDetailContent').hide();
        $('#mdlDetailCR').modal('show');

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            $('#crModalTitle').text(d.description);
            $('#crModalSubtitle').text(d.code);
            $('#crCode').text(d.code);
            $('#crStatusBadge').html(statusBadgeHtml(d.status, d.status_label));
            $('#crNobBadge').html(d.is_nob ? '<span class="badge badge-danger px-2 py-1 mt-1">NOB</span>' : '');
            $('#crType').text(d.type);
            $('#crAmount').text(d.amount_formatted);
            $('#crRealizationAmount').text(d.total_realization_formatted || '0');
            /* Settlement > pengajuan bukan refund — itu kekurangan bayar yang
               diselesaikan lewat Second Payment (istilah yang sudah dipakai
               modul ini; lihat tab Settlement & Todo List). Menyebutnya
               "Refund 0" menutupi bahwa masih ada kewajiban yang belum dibayar. */
            (function () {
                var lebih = parseInt(d.settlement_overage, 10) || 0;
                $('#crRefundLabel').text(lebih > 0 ? 'Second Payment' : 'Refund');
                /* Warna mengikuti arah selisihnya, bukan nama barisnya:
                   kurang bayar (Second Payment) merah terang, dana kembali
                   (Refund) hijau, dan nol ikut nada panel supaya tidak
                   terbaca sebagai peringatan. */
                var refund = parseInt(d.refund, 10) || 0;
                $('#crRefundAmount')
                    .text(lebih > 0 ? (d.settlement_overage_formatted || '0') : (d.refund_formatted || '0'))
                    .css('color', '')
                    .removeClass('dm-calc-neg dm-calc-pos dm-calc-zero')
                    .addClass(lebih > 0 ? 'dm-calc-neg' : (refund > 0 ? 'dm-calc-pos' : 'dm-calc-zero'));
            })();
            $('#crBeneficiary').text(d.beneficiary);
            $('#crBank').text(d.bank + ' — ' + d.account_number);
            $('#crApplicant').text(d.applicant);

            // Info tab
            $('#infoCode').text((d.code_reference || '-') + ' - ' + d.code);
            $('#infoDesc').html(
                (d.is_nob ? '<span class="badge badge-danger mr-1" style="font-size:9px;">NOB</span>' : '') +
                d.description +
                ' ' + statusBadgeHtml(d.status, d.status_label)
            );
            $('#infoType').html('<span class="badge badge-info px-2 py-1">' + d.type + '</span>');
            $('#infoProject').text(d.project);
            $('#infoCluster').text(d.cluster);
            $('#infoBudgetPlan').text(d.budget_plan);
            $('#infoAmount').html('<strong style="color:var(--info);">' + d.amount_formatted + '</strong>');
            $('#infoEmail').text(d.email || '-');

            if (d.payment_notes) {
                $('#infoPaymentNotes').text(d.payment_notes);
                $('#crPaymentNotesCard').show();
                $('#crScopeRow').addClass('has-payment');
            } else {
                $('#crPaymentNotesCard').hide();
                $('#crScopeRow').removeClass('has-payment');
            }
            $('#infoNotes').text(d.notes || '-');

            // Detail tab
            setTabCount('#crDetailCount', d.details.length);
            var canModerate = !!d.can_moderate_items;
            window.__crDetailAtt = {};
            var anyAtt = d.details.some(function (x) { return x.attachments && x.attachments.length; });
            var showActionCol = canModerate || anyAtt;
            $('#crDetailActionTh, .crDetailActionCell').toggleClass('d-none', !showActionCol);
            var dHtml = '', grand = 0;
            d.details.forEach(function(det, i) {
                var isNob    = !!det.is_nob; // kolom, bukan tebakan dari teks catatan
                var isTakeout = !!det.is_takeout;
                if (!isTakeout) grand += det.total;

                var rowStyle = isTakeout
                    ? ' style="color:var(--text-faint);background:var(--surface-2);"'
                    : (isNob ? ' style="color:var(--danger);"' : '');
                var strike = isTakeout ? 'text-decoration:line-through;' : '';

                var takeoutBadge = isTakeout
                    ? ' <span class="badge badge-warning" style="font-size:9px;" title="' + escAttr(det.takeout_notes || '') + '">Takeout &middot; ' + esc(det.takeout_stage_label || '-') + '</span>'
                    : '';

                var atts = det.attachments || [];
                window.__crDetailAtt[det.id] = atts;
                var attBadge = atts.length
                    ? ' <span class="badge badge-light border text-muted" style="font-size:9px;"><i class="fas fa-paperclip mr-1"></i>' + atts.length + '</span>'
                    : '';

                var btnStyle = 'white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;';
                var actionInner = '';
                if (atts.length) {
                    actionInner += '<button type="button" class="btn btn-info btn-view-detail-att mr-1" style="' + btnStyle + '" data-detail="' + det.id + '" title="Lihat lampiran"><i class="fas fa-eye"></i> View</button>';
                }
                if (canModerate) {
                    actionInner += isTakeout
                        ? '<button type="button" class="btn btn-outline-secondary btn-restore-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + det.id + '" title="Kembalikan item"><i class="fas fa-undo"></i> Restore</button>'
                        : '<button type="button" class="btn btn-outline-danger btn-takeout-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + det.id + '" data-desc="' + escAttr(det.description || '') + '" title="Takeout item"><i class="fas fa-cut"></i> Takeout</button>';
                }
                var actionCell = showActionCol ? '<td class="text-center text-nowrap">' + actionInner + '</td>' : '';

                dHtml += '<tr' + rowStyle + '>' +
                    '<td class="text-center" style="' + strike + '">' + (i+1) + '</td>' +
                    '<td style="' + strike + '">' + esc(det.description || '-') + takeoutBadge + attBadge +
                        (isTakeout && det.takeout_notes ? '<br><small class="text-muted" style="text-decoration:none;">Alasan: ' + esc(det.takeout_notes) + '</small>' : '') +
                    '</td>' +
                    '<td style="' + strike + '">' + esc(det.notes || '-') + '</td>' +
                    '<td class="text-right" style="' + strike + '">' + det.amount_formatted + '</td>' +
                    '<td class="text-center" style="' + strike + '">' + det.qty + '</td>' +
                    '<td class="text-right" style="' + strike + '"><strong>' + det.total_formatted + '</strong></td>' +
                    actionCell +
                '</tr>';
            });
            $('#crDetailBody').html(dHtml || '<tr><td colspan="7" class="text-center text-muted py-3">No item detail yet.</td></tr>');
            $('#crDetailTotal').text(grand.toLocaleString('id-ID'));

            // Helper: render grid thumbnail untuk attachments (dipakai tab Request & Realization)
            function renderAttGrid(atts) {
                if (!atts || !atts.length) return kosongRingkas('Belum ada lampiran.');
                var html = '';
                atts.forEach(function (a) {
                    var url = '/storage/' + a.path;
                    if (a.is_image) {
                        html += '<div class="cr-att-card">' +
                            '<img loading="lazy" src="' + url + '" class="cr-att-thumb" data-full="' + url + '" data-name="' + a.original_name + '">' +
                            '<div class="cr-att-name">' + a.original_name + '</div>' +
                        '</div>';
                    } else {
                        var icon = a.is_pdf ? 'fa-file-pdf' : 'fa-file-alt';
                        html += '<div class="cr-att-card">' +
                            '<a href="' + url + '" target="_blank" rel="noopener" class="cr-att-icon"><i class="fas ' + icon + '"></i></a>' +
                            '<div class="cr-att-name">' + a.original_name + '</div>' +
                        '</div>';
                    }
                });
                return html;
            }

            // Realization tab
            setTabCount('#crRealizationCount', d.realizations.length);
            if (d.realizations.length) {
                $('#crNoRealization').hide();
                $('#crRealizationWrapper').show();
                var rHtml = '', rGrand = 0;
                d.realizations.forEach(function(r, i) {
                    rGrand += r.total;
                    var itemAtts = r.attachments || [];
                    rHtml += '<tr><td class="text-center">' + (i+1) + '</td><td>' + r.cr_item + '</td><td class="text-right">' + r.total_cr_formatted + '</td><td class="text-right"><strong>' + r.actual_formatted + '</strong></td><td>' + (r.notes || '-') + '</td><td>' + (itemAtts.length ? '<div class="cr-att-grid">' + renderAttGrid(itemAtts) + '</div>' : '<span class="text-muted" style="font-size:11px;">-</span>') + '</td></tr>';
                });
                $('#crRealizationBody').html(rHtml);
                $('#crRealizationTotal').text(rGrand.toLocaleString('id-ID'));
                $('#crRealizationAtts').html(renderAttGrid(d.realization_attachments));

                // Second Payment — cuma tampil kalau Settlement pernah melebihi
                // nilai pengajuan awal (ada baris di second_payments).
                var sp = d.second_payments || [];
                if (sp.length) {
                    var spHtml = '<h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">' +
                        '<i class="fas fa-hand-holding-usd mr-1"></i> Second Payment</h6>' +
                        '<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;">' +
                        '<tbody>' +
                        '<tr><td>Original Request</td><td class="text-right"><strong>' + esc(d.amount_formatted) + '</strong></td></tr>' +
                        '<tr><td>Settlement</td><td class="text-right"><strong>' + esc(d.total_realization_formatted) + '</strong></td></tr>' +
                        sp.map(function (p) {
                            // Pembayarannya lewat batch Treasury — tanpa kode
                            // batch, "Pending" saja tidak memberi tahu apa pun
                            // tentang di mana uangnya sekarang.
                            var badge;
                            if (p.status === 'PAID') {
                                badge = '<span class="badge badge-success">Paid' + (p.paid_at ? ' — ' + esc(p.paid_at) : '') + '</span>'
                                      + (p.batch_code ? ' <span class="badge badge-light border">' + esc(p.batch_code) + '</span>' : '');
                            } else if (p.batch_code) {
                                badge = '<span class="badge badge-info">Menunggu pembayaran batch ' + esc(p.batch_code) + '</span>';
                            } else {
                                badge = '<span class="badge badge-warning">Belum masuk batch Treasury</span>';
                            }
                            return '<tr><td>Additional Payment</td><td class="text-right"><strong>' + esc(p.amount_formatted) + '</strong> ' + badge + '</td></tr>';
                        }).join('') +
                        '</tbody></table>';
                    $('#crSecondPaymentBox').html(spHtml).show();
                } else {
                    $('#crSecondPaymentBox').hide();
                }
            } else {
                $('#crNoRealization').show();
                $('#crRealizationWrapper').hide();
                $('#crSecondPaymentBox').hide();
            }

            // Attachment (di bawah tabel Request)
            var atts = d.attachments || [];
            setTabCount('#crAttCount', atts.length);
            $('#crAttBody').html(renderAttGrid(atts));

            // Logs tab — gaya chat, sama persis dengan tab Log Purchase Order:
            // log dari user yang sedang login rata kanan ("mine"), lainnya rata
            // kiri, avatar inisial dari nama actor. Tanpa nama role.
            var logHtml = '';
            (d.logs || []).slice().reverse().forEach(function (log) {
                var mine = log.user_id && currentUserId && log.user_id === currentUserId;
                var actor = log.actor || 'System';
                var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
                logHtml += '<div class="cr-chat-row ' + (mine ? 'mine' : '') + '">' +
                    '<div class="cr-chat-av">' + initials + '</div>' +
                    '<div>' +
                        '<div class="cr-chat-meta">' + (mine ? '' : '<strong>' + actor + '</strong> · ') + log.date + '</div>' +
                        '<div class="cr-chat-bubble">' +
                            '<span class="badge badge-' + log.action_color + ' px-2 py-1" style="font-size:9px;">' + log.action_label + '</span>' +
                            (log.status_from && log.status_to ? ' <span style="font-size:10px;opacity:.75;">' + (log.status_from_label || log.status_from) + ' → ' + (log.status_to_label || log.status_to) + '</span>' : '') +
                            (log.notes ? '<div class="cr-chat-note">' + log.notes + '</div>' : '') +
                        '</div>' +
                    '</div>' +
                '</div>';
            });
            $('#crLogBody').html(logHtml || kosongRingkas('Belum ada riwayat persetujuan.'));

            // Buka tab tertentu bila diminta pemanggil (mis. klik nilai Request ->
            // tab Request, klik badge Status -> tab Log) — default tab pertama (Info).
            var $targetTab = tab ? $('#crDetailTabs a[href="' + tab + '"]') : $();
            ($targetTab.length ? $targetTab : $('#crDetailTabs a:first')).tab('show');
            $('#crDetailLoading').hide(); $('#crDetailContent').show();

        }).fail(function () {
            $('#crDetailLoading').html(
                '<div class="ts-empty"><span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>' +
                '<p class="ts-empty-title">Detail gagal dimuat</p>' +
                '<p class="ts-empty-msg">Periksa koneksi Anda, lalu buka kembali Cost Request ini.</p></div>'
            );
            if (window.toast) toast.error('Gagal memuat', 'Detail Cost Request tidak bisa ditampilkan.');
        });
    }

    $(document).on('click', '.btn-detail-cr', function () {
        openCRDetail($(this).data('id'), $(this).data('tab'));
    });

    /* ================================================================
       DRAWER TINJAU CR

       Memakai endpoint /budget-request/{id}/detail-json yang sudah ada —
       tidak ada query, route, maupun aturan bisnis baru. Drawer hanya
       menyusun ulang data yang sama menjadi permukaan tinjau: status
       beserta konteksnya, ringkasan, timeline, dan lampiran.

       Tombol aksinya DISALIN dari baris tabel yang bersangkutan, sehingga
       gerbang peran dan status persis sama dengan yang sudah dirender
       server — tidak ada logika izin kedua di sisi klien.
       ================================================================ */

    var crDrawerId = null;

    /* esc() sudah didefinisikan di atas (dipakai modal detail); tidak dibuat
       ulang di sini supaya tidak ada dua fungsi dengan nama yang sama. */

    function drawerKV(pasangan) {
        var html = '<dl class="ts-dw-kv">';
        pasangan.forEach(function (p) {
            if (p[1] === null || p[1] === undefined || p[1] === '') return;
            html += '<dt>' + esc(p[0]) + '</dt><dd' + (p[2] ? ' class="is-amount"' : '') + '>' + esc(p[1]) + '</dd>';
        });
        return html + '</dl>';
    }

    function openCRDrawer(id, pemicu) {
        crDrawerId = id;

        $('#crDrawerContent').attr('hidden', true).empty();
        $('#crDrawerLoading').show();
        $('#crDrawer-eyebrow').text('');
        $('#crDrawer-title').text('Memuat…');
        $('#crDrawerActions').empty();

        TesysDrawer.open('crDrawer', pemicu);

        // Aksi baris disalin apa adanya, termasuk form dan token CSRF-nya.
        var baris = pemicu ? $(pemicu).closest('tr') : $('#tblCR tbody tr[data-id="' + id + '"]');
        var aksi = baris.find('.ts-actions').children('.ts-action-primary, form').clone(true, true);
        if (aksi.length) $('#crDrawerActions').append(aksi);

        function tampilkanGagal(pesan) {
            $('#crDrawerLoading').hide();
            $('#crDrawerContent').html(
                '<div class="ts-empty">'
                + '<span class="ts-empty-ic"><i class="fas fa-exclamation-triangle"></i></span>'
                + '<p class="ts-empty-title">Detail belum dapat dimuat</p>'
                + '<p class="ts-empty-msg">' + esc(pesan) + '</p>'
                + '<div class="ts-empty-action"><button type="button" class="ts-action-primary is-neutral" id="crDrawerRetry">'
                + '<i class="fas fa-redo"></i><span class="ts-al">Coba lagi</span></button></div></div>'
            ).removeAttr('hidden');
        }

        $.get('/budget-request/' + id + '/detail-json')
            .done(function (d) {
                /*
                  Saat sesi habis, Laravel mengalihkan permintaan AJAX ini ke
                  halaman login — jQuery mengikuti pengalihan itu dan menerima
                  HTML berstatus 200, bukan galat. Tanpa penjagaan ini drawer
                  akan merender isi kosong seolah datanya memang tidak ada.
                */
                if (!d || typeof d !== 'object' || !d.code) {
                    tampilkanGagal('Sesi Anda mungkin sudah berakhir. Muat ulang halaman, lalu masuk kembali.');
                    return;
                }

                var meta = statusMeta[d.status] || { label: d.status_label || d.status, description: null, tone: 'neutral' };

                $('#crDrawer-eyebrow').text(d.code + (d.type ? ' · ' + d.type : ''));
                $('#crDrawer-title').text(d.description);

                var html = '';

                /* ---- Status: sedang di mana, menunggu apa, sudah sejauh mana.
                   Batang alurnya digambar TesysWorkflow — renderer yang sama
                   dengan komponen x-workflow-flow di halaman lain. (Nama komponen
                   sengaja ditulis tanpa tanda kurung sudut: di dalam komentar
                   JavaScript pun Blade tetap mengompilasinya jadi komponen.) */
                html += '<div class="ts-dw-status">'
                     +    '<span class="ts-status ts-status-' + meta.tone + ' ts-status-md">' + esc(meta.label) + '</span>'
                     +    (meta.description
                            ? '<span class="ts-dw-status-text"><span>' + esc(meta.description) + '</span></span>'
                            : '')
                     +    (window.TesysWorkflow ? TesysWorkflow.render('budget-request', d.status) : '')
                     +  '</div>';

                /* ---- Nilai: nominal pengajuan berdiri sendiri karena itu
                   angka yang paling dicari saat meninjau. */
                var sisi = '';
                if (d.total_realization_formatted) {
                    sisi += '<span class="ts-dw-amount-side"><span>Settlement</span>'
                          + '<strong>' + esc(d.total_realization_formatted) + '</strong></span>';
                }
                /* Sama seperti sidebar modal: kelebihan settlement dinamai
                   Second Payment, bukan "Refund 0". */
                var lebihBayar = parseInt(d.settlement_overage, 10) || 0;
                if (lebihBayar > 0) {
                    sisi += '<span class="ts-dw-amount-side"><span>Second Payment</span>'
                          + '<strong>' + esc(d.settlement_overage_formatted) + '</strong></span>';
                } else if (d.refund_formatted) {
                    sisi += '<span class="ts-dw-amount-side"><span>Refund</span>'
                          + '<strong>' + esc(d.refund_formatted) + '</strong></span>';
                }

                html += '<div class="ts-dw-section"><div class="ts-dw-amount">'
                     +    '<span class="ts-dw-amount-main">'
                     +      '<span class="ts-dw-amount-label">Nilai pengajuan</span>'
                     +      '<span class="ts-dw-amount-value">' + esc(d.amount_formatted || '-') + '</span>'
                     +    '</span>'
                     +    sisi
                     +  '</div></div>';

                /* ---- Informasi pengajuan: siapa & atas dasar apa. */
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Informasi pengajuan</p>'
                     + drawerKV([
                         ['Pengaju', d.applicant],
                         ['Penerima', d.beneficiary],
                         ['Rekening', d.bank ? d.bank + ' — ' + d.account_number : null],
                         ['Purchase Order', d.project],
                         ['Budget Plan', d.budget_plan],
                       ])
                     + '</div>';

                /* ---- Tanggal: dipisah dari informasi supaya keduanya
                   bisa dipindai sendiri-sendiri. */
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Tanggal</p>'
                     + drawerKV([
                         ['Dibuat', d.created_at],
                         ['Diajukan', d.submitted_at],
                         ['Disetujui', d.approved_at],
                         ['Dibayar', d.paid_at],
                       ])
                     + '</div>';

                /* Timeline approval — memakai renderer bersama TesysTimeline,
                   bukan HTML sendiri, supaya modul lain (PO, Invoice) tinggal
                   memanggil fungsi yang sama. Payload datang terbaru-dulu dan
                   pembalikan urutannya ditangani di dalam renderer. */
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Riwayat persetujuan</p>'
                     + TesysTimeline.render(d.logs, { pesanKosong: 'Belum ada riwayat persetujuan.' })
                     + '</div>';

                /* Lampiran */
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Lampiran'
                     + (d.attachments_count ? ' (' + d.attachments_count + ')' : '') + '</p>';
                if (d.attachments && d.attachments.length) {
                    html += '<div class="ts-dw-files">';
                    d.attachments.forEach(function (a) {
                        var ikon = a.is_image ? 'fa-image' : (a.is_pdf ? 'fa-file-pdf' : 'fa-paperclip');
                        html += '<a class="ts-dw-file" href="/storage/' + esc(a.path) + '" target="_blank" rel="noopener">'
                             +    '<i class="fas ' + ikon + '"></i>'
                             +    '<span>' + esc(a.original_name) + '</span>'
                             +    (a.size_formatted ? '<small>' + esc(a.size_formatted) + '</small>' : '')
                             +  '</a>';
                    });
                    html += '</div>';
                } else {
                    html += '<div class="ts-empty ts-empty-compact">'
                         +    '<p class="ts-empty-msg">Tidak ada lampiran.</p></div>';
                }
                html += '</div>';

                $('#crDrawerContent').html(html).removeAttr('hidden');
                $('#crDrawerLoading').hide();
            })
            .fail(function () {
                tampilkanGagal('Sambungan ke server terputus atau data sedang tidak tersedia.');
            });
    }

    $(document).on('click', '.btn-review-cr', function () {
        openCRDrawer($(this).data('id'), this);
    });

    $(document).on('click', '#crDrawerRetry', function () {
        if (crDrawerId) openCRDrawer(crDrawerId);
    });

    // "Rincian lengkap" membuka modal lama beserta seluruh tabelnya.
    $(document).on('click', '#crDrawerFull', function () {
        if (!crDrawerId) return;
        TesysDrawer.close('crDrawer');
        openCRDetail(crDrawerId, '#crPaneDetails');
    });

    $('#mdlDetailCR').on('hidden.bs.modal', function () {
        $('#crDetailContent').hide(); $('#crDetailLoading').show();
    });

    // Auto-buka modal detail bila diakses dengan ?open=<id> (mis. dari To Do).
    // Parameternya langsung dibuang dari URL setelah dipakai: kalau dibiarkan,
    // setiap reload (atau redirect back() dari aksi lain) akan membuka modal
    // detail itu lagi seolah-olah bug.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var openId = params.get('open');
        if (!openId) return;

        params.delete('open');
        var sisa = params.toString();
        window.history.replaceState({}, '',
            window.location.pathname + (sisa ? '?' + sisa : '') + window.location.hash);

        openCRDetail(openId);
    })();

});
</script>
@endpush
