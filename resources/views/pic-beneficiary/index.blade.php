@extends('layouts.app')

@section('title', 'PIC & Beneficiary')

@section('content')

{{-- Halaman gabungan PIC & Beneficiary (2026-11-09). Menggantikan
     beneficiaries/index & master-pic/index. Alurnya PIC -> Beneficiary:
     satu DataTable berisi seluruh PIC (semua tipe, tanpa tab), klik baris
     untuk membuka beneficiary-nya. Beneficiary tanpa PIC tampil sebagai
     baris sendiri berlabel "Tanpa PIC". Lihat PicBeneficiaryController. --}}
@php
    // Nama akun/karyawan contoh masih berlabel "DUMMY" di basis data. Dibuang
    // untuk TAMPILAN saja — nilai yang dikirim tetap id-nya.
    $tanpaDummy = function ($v) {
        $bersih = trim(preg_replace('/\s{2,}/', ' ', preg_replace('/\bdummy\b[\s._-]*/i', '', (string) $v)));
        return $bersih !== '' ? $bersih : (string) $v;
    };
    $catBadges = ['INTERNAL' => 'badge-primary', 'EXTERNAL' => 'badge-warning', 'BILLING' => 'badge-secondary'];
    $fmtNominal = fn ($n) => fmod((float) $n, 1) == 0.0
        ? number_format((float) $n, 0, ',', '.')
        : number_format((float) $n, 2, ',', '.');
@endphp

<div class="content">
    <div class="container-fluid">

        <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar (dipindah tesys-shell.js hoistPageTitle). --}}
        <div class="tw:sr-only">
            <h1>PIC & Beneficiary</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Master</li>
                <li class="breadcrumb-item active">PIC & Beneficiary</li>
            </ol>
        </div>

        <x-tw.page-header title="PIC & Beneficiary"
            description="Penanggung jawab (PIC) beserta beneficiary — penerima dana dan rekeningnya. Klik baris PIC untuk melihat beneficiary-nya.">
            <x-slot:actions>
                {{-- Bank Master dijaga 'role:superadmin,admin,guest', bukan menu
                     halaman ini — tanpa penjaga ini Manager melihat tombol yang 403. --}}
                @if($canManage)
                <x-tw.button href="{{ url('/bank') }}" variant="secondary"><i class="fas fa-university" aria-hidden="true"></i> Bank Master</x-tw.button>
                @endif
                {{-- Menambah PIC & beneficiary terbuka untuk semua pemegang menu. --}}
                <x-tw.button id="btnTambahPic" variant="secondary"><i class="fas fa-user-plus" aria-hidden="true"></i> Tambah PIC</x-tw.button>
                <x-tw.button id="btnTambahBen" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Beneficiary</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        <button type="button" class="ts-fab" data-trigger="#btnTambahBen" aria-label="Tambah Beneficiary" title="Tambah Beneficiary"><i class="fas fa-plus" aria-hidden="true"></i></button>

        <x-tw.card padding="sm">

            <!-- FILTER BAR -->
            <div class="custom-toolbar mb-2">
                <div class="custom-toolbar-left">
                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input">
                            <input type="search" id="globalSearch" class="form-control form-control-sm"
                                   placeholder="PIC, beneficiary, no. rekening..." aria-label="Cari PIC atau beneficiary">
                        </div>
                    </div>
                    <button aria-label="Clear Filter" type="button" id="btnClearFilter" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>
                    <div id="pbRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>

                    {{-- Saringan kerja cepat. --}}
                    @if($counts['unlinked'] > 0)
                    <button type="button" id="btnUnlinked" class="btn btn-outline-danger btn-sm" aria-pressed="false"
                            title="Hanya PIC internal yang belum terhubung ke akun User">
                        <i class="fas fa-user-slash mr-1"></i> Belum Terhubung
                        <span class="badge badge-danger ml-1">{{ $counts['unlinked'] }}</span>
                    </button>
                    @endif
                    @if($counts['orphan'] > 0)
                    <button type="button" id="btnOrphan" class="btn btn-outline-secondary btn-sm"
                            title="Hanya beneficiary yang belum punya PIC">
                        <i class="fas fa-unlink mr-1"></i> Tanpa PIC
                        <span class="badge badge-secondary ml-1">{{ $counts['orphan'] }}</span>
                    </button>
                    @endif
                </div>
                <div class="custom-toolbar-right">
                    <x-tw.button id="btnCollapseAll" variant="secondary" size="sm"><i class="fas fa-compress-alt" aria-hidden="true"></i> Tutup Semua</x-tw.button>
                    <x-tw.button id="refreshTable" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
                </div>
            </div>

            <p class="pb-meta mb-2">
                {{ number_format($counts['pic'], 0, ',', '.') }} PIC
                &middot; {{ number_format($counts['ben'], 0, ',', '.') }} beneficiary
                @if($counts['orphan'] > 0)
                    &middot; {{ number_format($counts['orphan'], 0, ',', '.') }} tanpa PIC
                @endif
            </p>

            <!-- TABLE -->
            <div class="table-responsive">
                <table id="pbTable" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" class="pb-ctl-col"><span class="sr-only">Detail</span></th>
                            <th scope="col" style="width:4%;">No</th>
                            <th scope="col" style="width:24%;">Nama</th>
                            <th scope="col" style="width:11%;">Jenis</th>
                            <th scope="col" class="d-none d-lg-table-cell" style="width:15%;">Kontak</th>
                            <th scope="col" class="d-none d-lg-table-cell" style="width:12%;">Akun User</th>
                            <th scope="col" style="width:14%;">Beneficiary / Rekening</th>
                            <th scope="col" class="d-none d-lg-table-cell text-right" style="width:9%;">Token / Nominal</th>
                            <th scope="col" style="width:7%;">Status</th>
                            <th scope="col" class="text-center action-column" style="width:9%;">Action</th>
                            <th scope="col">Cari</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- ── Baris PIC ── --}}
                        @foreach($pics as $pic)
                            @php
                                $bens     = $benByPic[$pic->id] ?? collect();
                                $unlinked = $pic->type === 'internal' && ! $pic->user_id;
                                $cari     = collect([$pic->pic, $pic->position, $pic->phone, $pic->email, $pic->token, $pic->user?->username, $pic->user?->name])
                                    ->merge($bens->flatMap(fn ($b) => [$b['nickname'], $b['complete_name'], $b['account_number']]))
                                    ->filter()->implode(' ');
                            @endphp
                            <tr class="pb-row-pic" data-kind="pic"
                                data-pic-id="{{ $pic->id }}"
                                data-pic-name="{{ $pic->pic }}"
                                data-pic-type="{{ $pic->type }}"
                                data-pic-active="{{ $pic->status === 'ACTIVE' ? 1 : 0 }}"
                                data-jenis="{{ $pic->type_label }}"
                                data-status="{{ $pic->status }}"
                                data-unlinked="{{ $unlinked ? 1 : 0 }}">
                                <td class="pb-ctl">
                                    <button type="button" class="pb-toggle" aria-expanded="false"
                                            aria-label="Tampilkan beneficiary {{ $pic->pic }}" title="Tampilkan beneficiary">
                                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                    </button>
                                </td>
                                <td></td>
                                <td>
                                    <strong>{{ $pic->pic }}</strong>
                                    @if($pic->position)
                                        <div class="pb-sub">{{ $pic->position }}</div>
                                    @endif
                                </td>
                                <td><span class="badge {{ \App\Models\MasterPic::TYPE_BADGES[$pic->type] ?? 'badge-light' }} px-2 py-1">{{ $pic->type_label }}</span></td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pic->phone ?: '-' }}
                                    @if($pic->email)
                                        <div class="pb-sub pb-wrap">{{ $pic->email }}</div>
                                    @endif
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    @if($pic->user)
                                        {{ strtoupper($tanpaDummy($pic->user->username)) }}
                                    @elseif($unlinked)
                                        <span class="text-danger pb-sub"><i class="fas fa-user-slash mr-1" aria-hidden="true"></i>Belum terhubung</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td data-order="{{ $pic->beneficiaries_count }}">
                                    @if($pic->beneficiaries_count > 0)
                                        <button type="button" class="pb-count pb-toggle-count">
                                            <i class="fas fa-hand-holding-usd mr-1" aria-hidden="true"></i>{{ $pic->beneficiaries_count }} beneficiary
                                        </button>
                                    @else
                                        <span class="text-muted pb-sub">Belum ada</span>
                                    @endif
                                </td>
                                <td class="d-none d-lg-table-cell text-right" data-order="{{ $pic->nominal !== null ? (float) $pic->nominal : -1 }}">
                                    {{ $pic->token ?: '-' }}
                                    @if($pic->nominal !== null)
                                        <div class="pb-sub">{{ $fmtNominal($pic->nominal) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $pic->status === 'ACTIVE' ? 'badge-success' : 'badge-danger' }} px-2 py-1">{{ $pic->status }}</span>
                                </td>
                                <td class="text-center action-column">
                                    @if($canManagePic)
                                    <div class="action-buttons ts-actions">
                                        <button type="button" class="ts-action-primary is-neutral btn-edit-pic" data-id="{{ $pic->id }}"
                                                title="Edit PIC" aria-label="Edit PIC {{ $pic->pic }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>
                                        <form action="{{ url('/master-pic/' . $pic->id) }}" method="POST" class="d-inline pic-destroy-form"
                                              data-pic-name="{{ $pic->pic }}" data-total="{{ $pic->beneficiaries_count }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger" title="Hapus PIC" aria-label="Hapus PIC {{ $pic->pic }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                            </button>
                                        </form>
                                    </div>
                                    @else
                                    <span class="text-muted" title="Hanya Superadmin yang bisa mengubah atau menghapus PIC">&mdash;</span>
                                    @endif
                                </td>
                                <td>{{ $cari }}</td>
                            </tr>
                        @endforeach

                        {{-- ── Beneficiary tanpa PIC ── --}}
                        @foreach($orphans as $b)
                            <tr class="pb-row-orphan" data-kind="ben"
                                data-ben-id="{{ $b->id }}"
                                data-jenis="Tanpa PIC"
                                data-status="{{ $b->status }}"
                                data-unlinked="0">
                                <td class="pb-ctl"><span class="pb-ctl-dot" aria-hidden="true"></span></td>
                                <td></td>
                                <td>
                                    <strong>{{ $b->nickname }}</strong>
                                    <div class="pb-sub">{{ $b->complete_name }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-light border px-2 py-1">Tanpa PIC</span>
                                    <span class="badge {{ $catBadges[$b->category] ?? 'badge-secondary' }} pb-mini">{{ $b->category }}</span>
                                </td>
                                <td class="d-none d-lg-table-cell">{{ $b->contact ?: '-' }}</td>
                                <td class="d-none d-lg-table-cell"><span class="text-muted">-</span></td>
                                <td data-order="-1">
                                    @if($b->bank)<span class="badge badge-info px-2 py-1">{{ $b->bank->name }}</span>@endif
                                    <div class="pb-sub pb-num">{{ $b->account_number ?: '-' }}</div>
                                </td>
                                <td class="d-none d-lg-table-cell text-right" data-order="-2"><span class="text-muted">-</span></td>
                                <td>
                                    <span class="badge {{ $b->status === 'ACTIVE' ? 'badge-success' : 'badge-danger' }} px-2 py-1">{{ $b->status }}</span>
                                </td>
                                <td class="text-center action-column">
                                    @if($canManage)
                                    <div class="action-buttons ts-actions">
                                        <button type="button" class="ts-action-primary is-neutral btn-edit-ben" data-id="{{ $b->id }}"
                                                title="Edit" aria-label="Edit {{ $b->nickname }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>
                                        <button type="button" class="ts-action-primary is-neutral btn-move-pic" data-id="{{ $b->id }}"
                                                data-nama="{{ $b->nickname }}" data-pic=""
                                                title="Kaitkan ke PIC" aria-label="Kaitkan {{ $b->nickname }} ke PIC">
                                            <i class="fas fa-link" aria-hidden="true"></i><span class="ts-al">Kaitkan</span>
                                        </button>
                                        <form action="{{ url('/beneficiaries/' . $b->id) }}" method="POST" class="d-inline ben-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger" title="Hapus" aria-label="Hapus {{ $b->nickname }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                            </button>
                                        </form>
                                    </div>
                                    @else
                                    <span class="text-muted" title="Hanya Superadmin yang bisa mengubah atau menghapus">&mdash;</span>
                                    @endif
                                </td>
                                <td>{{ collect([$b->nickname, $b->complete_name, $b->account_number, $b->bank?->name, $b->contact])->filter()->implode(' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </x-tw.card>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL TAMBAH PIC                                                  -->
<!-- ================================================================ -->
<div class="modal fade" id="modalTambahPic" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content pb-modal">
            <div class="modal-header pb-modal-head">
                <h5 class="modal-title text-white"><i class="fas fa-id-badge mr-2 pb-accent"></i> Tambah PIC</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ url('/master-pic') }}" method="POST" id="formTambahPic">
                @csrf
                <div class="modal-body pb-modal-body">
                    @include('pic-beneficiary._pic_fields', ['p' => 'tambahPic', 'withStatus' => false])
                    <input type="hidden" name="status" value="ACTIVE">
                </div>
                <div class="modal-footer pb-modal-foot">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit PIC: tidak dirender untuk role yang tidak berhak (formulir memuat
     token & nominal, dan markup-nya tetap terbaca di source halaman). --}}
@if($canManagePic)
<div class="modal fade" id="modalEditPic" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content pb-modal">
            <div class="modal-header pb-modal-head">
                <h5 class="modal-title text-white"><i class="fas fa-pen mr-2 pb-accent"></i> Edit PIC</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="formEditPic" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body pb-modal-body">
                    <div class="pb-loading text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Memuat data...</p>
                    </div>
                    <div class="pb-fields" style="display:none;">
                        @include('pic-beneficiary._pic_fields', ['p' => 'editPic', 'withStatus' => true])
                    </div>
                </div>
                <div class="modal-footer pb-modal-foot">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Batal</button>
                    <button type="submit" class="btn btn-warning pb-submit" style="display:none;"><i class="fas fa-save mr-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- ================================================================ -->
<!-- MODAL TAMBAH BENEFICIARY                                          -->
<!-- ================================================================ -->
<div class="modal fade" id="modalTambahBen" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content pb-modal">
            <div class="modal-header pb-modal-head">
                <h5 class="modal-title text-white"><i class="fas fa-user-friends mr-2 pb-accent"></i> Tambah Beneficiary</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ url('/beneficiaries') }}" method="POST" id="formTambahBen">
                @csrf
                <div class="modal-body pb-modal-body">
                    @include('pic-beneficiary._ben_fields', ['p' => 'tambahBen', 'withStatus' => false])
                    <input type="hidden" name="status" value="ACTIVE">
                </div>
                <div class="modal-footer pb-modal-foot">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($canManage)
<!-- MODAL EDIT BENEFICIARY -->
<div class="modal fade" id="modalEditBen" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content pb-modal">
            <div class="modal-header pb-modal-head">
                <h5 class="modal-title text-white"><i class="fas fa-pen mr-2 pb-accent"></i> Edit Beneficiary</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="formEditBen" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body pb-modal-body">
                    <div class="pb-loading text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Memuat data...</p>
                    </div>
                    <div class="pb-fields" style="display:none;">
                        @include('pic-beneficiary._ben_fields', ['p' => 'editBen', 'withStatus' => true])
                    </div>
                </div>
                <div class="modal-footer pb-modal-foot">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Batal</button>
                    <button type="submit" class="btn btn-warning pb-submit" style="display:none;"><i class="fas fa-save mr-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- PINDAH / KAITKAN PIC — hanya mengubah pic_id satu beneficiary. --}}
<div class="modal fade" id="modalMovePic" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formMovePic" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt mr-1"></i> <span id="movePicJudul">Pindah PIC</span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3 pb-note">
                        <strong id="movePicNama"></strong> akan dikaitkan ke PIC yang dipilih.
                        Data beneficiary-nya tidak berubah selain penanggung jawabnya.
                    </p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600" for="movePicTujuan">PIC Tujuan</label>
                        <select name="pic_id" id="movePicTujuan" class="form-control">
                            <option value="">— Tanpa PIC —</option>
                            @foreach($picOptions as $po)
                                <option value="{{ $po->id }}">{{ $po->pic }}@if($po->position) — {{ $po->position }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- KAITKAN BENEFICIARY YANG SUDAH ADA KE PIC — tidak membuat baris baru. --}}
<div class="modal fade" id="modalPicAttach" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="formPicAttach" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-link mr-1"></i> Kaitkan Beneficiary ke PIC — <span id="picAttachName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 pb-note">
                        Pilih beneficiary yang sudah terdaftar untuk dikaitkan ke PIC ini.
                        Untuk mendaftarkan penerima dana baru, gunakan tombol <strong>+ Beneficiary</strong>.
                    </p>
                    <input type="search" id="picAttachSearch" class="form-control form-control-sm mb-2"
                           placeholder="Cari nickname atau nama lengkap..." aria-label="Cari beneficiary">
                    <div id="picAttachList" class="pb-attach-list"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnPicAttachSubmit" class="btn btn-success" disabled>
                        <i class="fas fa-link mr-1"></i> Kaitkan (<span id="picAttachCount">0</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
.font-weight-600 { font-weight:600; }
.pb-meta  { font-size:12px; color:var(--text-faint); }
.pb-sub   { font-size:11px; color:var(--text-faint); line-height:1.35; }
.pb-wrap  { overflow-wrap:anywhere; }
.pb-num   { font-variant-numeric:tabular-nums; }
.pb-mini  { font-size:9px; }
.pb-note  { font-size:12.5px; color:var(--text-dim); }
.pb-accent { color:var(--accent); }

/* MODAL */
.pb-modal      { border-radius:var(--radius-lg); overflow:hidden; }
.pb-modal-head { background:var(--charcoal); border-bottom:3px solid var(--accent); }
.pb-modal-body { padding:28px 32px; }
.pb-modal-foot { background:var(--surface-2); border-top:1px solid var(--border); }
.pb-modal-body .form-group { margin-bottom:18px; }
.money-input   { font-variant-numeric:tabular-nums; }

/* TOOLBAR */
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.custom-toolbar-right{ display:flex; gap:8px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
#globalSearch        { width:240px; border-radius:var(--radius-sm); }

/* TABLE */
#pbTable { width:100% !important; }
#pbTable td, #pbTable th { vertical-align:middle !important; }
.action-column  { white-space:nowrap !important; min-width:80px !important; }
.action-buttons { display:flex; align-items:center; justify-content:center; flex-wrap:nowrap; gap:4px; }

.pb-ctl-col, .pb-ctl { width:34px; text-align:center; padding-left:4px !important; padding-right:4px !important; }
.pb-toggle {
    width:24px; height:24px; border:1px solid var(--border); border-radius:var(--radius-sm);
    background:var(--surface); color:var(--text-dim); display:inline-flex; align-items:center; justify-content:center;
    font-size:10px; cursor:pointer; padding:0;
}
.pb-toggle:hover, .pb-toggle:focus-visible { border-color:var(--accent); color:var(--accent); outline:none; }
.pb-toggle i { transition:transform .15s; }
tr.pb-open .pb-toggle i { transform:rotate(90deg); }
tr.pb-open > td { background:var(--surface-2); }
.pb-ctl-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--border-strong); }
.pb-row-pic { cursor:pointer; }
.pb-row-orphan > td:nth-child(3) strong { color:var(--text-dim); }

.pb-count {
    border:none; background:none; padding:0; font-size:12px; font-weight:600; color:var(--accent);
    cursor:pointer;
}
.pb-count:hover, .pb-count:focus-visible { text-decoration:underline; outline:none; }

/* CHILD ROW — beneficiary milik satu PIC */
#pbTable > tbody > tr.pb-child-row > td { padding:0 !important; background:var(--surface-2); }
.pb-child { padding:10px 14px 14px 46px; border-left:3px solid var(--accent); }
.pb-child-head { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
.pb-child-title { font-size:12px; font-weight:700; color:var(--text); margin:0; }
.pb-child-actions { margin-left:auto; display:flex; gap:6px; flex-wrap:wrap; }
.pb-child table { background:var(--surface); font-size:12.5px; margin-bottom:0; }
.pb-child table th { font-size:11px; font-weight:600; color:var(--text-dim); background:var(--surface-2); }
.pb-child tr.pb-match > td { background:var(--warning-bg); }
.pb-child-empty { font-size:12px; color:var(--text-faint); padding:10px 0; }

/* KAITKAN */
.pb-attach-list { max-height:320px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-sm); padding:4px 8px; }
.pb-attach-row  { display:flex; align-items:center; gap:8px; padding:6px 4px; border-bottom:1px solid var(--border); cursor:pointer; margin:0; }

/* TABLE BOTTOM */
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_wrapper .pagination .page-item.active .page-link {
    background:var(--accent) !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important;
}
.dataTables_wrapper .pagination .page-link { color:var(--accent) !important; }

/* MOBILE */
@media (max-width: 768px) {
    .custom-toolbar, .custom-toolbar-left { flex-direction:column; align-items:stretch; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn, #btnClearFilter, #btnUnlinked, #btnOrphan { width:100%; }
    .toolbar-row, .toolbar-input { width:100%; }
    #globalSearch { width:100% !important; }
    #pbRowFilters .tcf-row-btn { width:100%; }
    .pb-child { padding-left:14px; }
    .pb-child-actions { margin-left:0; width:100%; }
    .pb-modal-body { padding:20px; }
    .dataTables_wrapper .row:last-child { display:flex; flex-direction:column; align-items:flex-start !important; gap:12px; }
    .dataTables_paginate { width:100%; overflow-x:auto; }
    .dataTables_length { margin-left:0; }
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    /* Kode aksi kelola (edit/hapus/pindah/kaitkan) hanya dicetak untuk role
       yang berhak (direktif if Blade), supaya alamat endpoint-nya tidak terbaca di
       source halaman role lain. Penjaga sebenarnya tetap di server. */
    var CAN_MANAGE     = @json($canManage);
    var CSRF           = @json(csrf_token());
    var URL_BEN        = @json(url('/beneficiaries'));
    var URL_PIC        = @json(url('/master-pic'));

    // Beneficiary per PIC: { pic_id: [ {id, nickname, ...}, ... ] }.
    var BEN_BY_PIC = @json($benByPic) || {};

    var CAT_BADGE = { INTERNAL: 'badge-primary', EXTERNAL: 'badge-warning', BILLING: 'badge-secondary' };

    /* Data berasal dari input pengguna, jadi selalu dijinakkan sebelum
       disisipkan ke HTML. */
    function esc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }

    // Tombol melayang (HP) memicu tombol di header yang disembunyikan.
    $('.ts-fab[data-trigger]').on('click', function () { $($(this).data('trigger')).trigger('click'); });

    /*
    |--------------------------------------------------------------------------
    | DROPDOWN BER-PENCARIAN (Bank, PIC, Akun User, Karyawan)
    |--------------------------------------------------------------------------
    */
    [
        ['#tambahBenBank', '— Pilih Bank —', 'Cari nama bank...', false],
        ['#tambahBenPic',  '— Pilih PIC —',  'Cari nama PIC...',  true],
        ['#editBenBank',   '— Pilih Bank —', 'Cari nama bank...', false],
        ['#editBenPic',    '— Pilih PIC —',  'Cari nama PIC...',  true],
        ['#movePicTujuan', '— Tanpa PIC —',  'Cari nama PIC...',  true],
        ['#tambahPicUser',     '— Tidak terhubung —', 'Cari akun user...', true],
        ['#tambahPicEmployee', '— Tidak terhubung —', 'Cari karyawan...',  true],
        ['#editPicUser',       '— Tidak terhubung —', 'Cari akun user...', true],
        ['#editPicEmployee',   '— Tidak terhubung —', 'Cari karyawan...',  true],
    ].forEach(function (c) {
        if ($(c[0]).length) {
            TesysSearchableSelect.init(c[0], { placeholder: c[1], searchPlaceholder: c[2], allowEmpty: c[3] });
        }
    });

    function setSelect(sel, val) { $(sel).val(val == null ? '' : String(val)).trigger('change').trigger('tss:sync'); }

    /*
    |--------------------------------------------------------------------------
    | MODAL PIC — Tambah & Edit
    |--------------------------------------------------------------------------
    */

    // Token & Nominal hanya berlaku untuk PIC internal.
    function sinkronFieldInternal(form) {
        var $f = $(form);
        var internal = $f.find('select[name="type"]').val() === 'internal';
        var $box = $f.find('.pic-internal-only');
        $box.toggle(internal);
        if (!internal) $box.find('input').val('');
    }
    $(document).on('change', '#tambahPicType, #editPicType', function () {
        sinkronFieldInternal($(this).closest('form'));
    });

    $('#btnTambahPic').on('click', function () {
        $('#formTambahPic')[0].reset();
        $('#tambahPicType').val('internal');
        sinkronFieldInternal('#formTambahPic');
        $('#tambahPicUser, #tambahPicEmployee').trigger('tss:sync');
        $('#modalTambahPic').modal('show').one('shown.bs.modal', function () { $('#tambahPicPic').focus(); });
    });

    function formatMoney(raw) {
        var v = String(raw == null ? '' : raw).replace(/[^\d,.-]/g, '');
        if (v === '') return '';
        v = v.replace(/\./g, '');
        var parts = v.split(',');
        var intRaw = parts[0].replace(/\D/g, '') || '0';
        var dec = parts.length > 1 ? parts[1].replace(/\D/g, '').slice(0, 2) : null;
        var int = intRaw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return dec !== null ? int + ',' + dec : int;
    }
    $(document).on('input', '.money-input', function () {
        var el = this, end = el.value.length - el.selectionStart;
        el.value = formatMoney(el.value);
        var pos = Math.max(0, el.value.length - end);
        try { el.setSelectionRange(pos, pos); } catch (e) {}
    });
    // Dikirim sebagai angka polos: "100.000.000,50" -> "100000000.50".
    $(document).on('submit', '#formTambahPic, #formEditPic', function () {
        $(this).find('.money-input').each(function () {
            var v = $.trim(this.value);
            this.value = v === '' ? '' : v.replace(/\./g, '').replace(',', '.');
        });
    });

    function bukaModalEdit($modal, $form, action, dataUrl, isi) {
        $modal.find('.pb-loading').show().html('<i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="text-muted mt-2 mb-0">Memuat data...</p>');
        $modal.find('.pb-fields, .pb-submit').hide();
        $form.attr('action', action);
        $modal.modal('show');
        $.get(dataUrl, function (res) {
            isi(res);
            $modal.find('.pb-loading').hide();
            $modal.find('.pb-fields, .pb-submit').show();
        }).fail(function () {
            $modal.find('.pb-loading').html('<p class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data. Silakan coba lagi.</p>');
        });
    }

    @if($canManagePic)
        $(document).on('click', '.btn-edit-pic', function (e) {
            e.stopPropagation();
            var id = $(this).data('id');
            bukaModalEdit($('#modalEditPic'), $('#formEditPic'), URL_PIC + '/' + id, URL_PIC + '/' + id + '/edit-data', function (res) {
                $('#editPicType').val(res.type);
                $('#editPicPic').val(res.pic);
                $('#editPicPosition').val(res.position || '');
                $('#editPicPhone').val(res.phone || '');
                $('#editPicEmail').val(res.email || '');
                $('#editPicToken').val(res.token || '');
                $('#editPicNominal').val(res.nominal !== null && res.nominal !== undefined
                    ? formatMoney(String(res.nominal).replace(/\.00$/, '').replace('.', ',')) : '');
                setSelect('#editPicUser', res.user_id);
                setSelect('#editPicEmployee', res.employee_id);
                $('#editPicStatus').val(res.status);
                sinkronFieldInternal('#formEditPic');
            });
        });

        $(document).on('submit', '.pic-destroy-form', function (e) {
            e.preventDefault();
            var form = this, total = $(form).data('total');
            Swal.fire({
                title: 'Hapus PIC ' + $(form).data('pic-name') + '?',
                html: 'Data PIC dihapus dan <b>tidak dapat dikembalikan</b>.'
                    + (total > 0 ? '<br><br><b>' + total + '</b> beneficiary tidak ikut terhapus, tetapi menjadi <b>Tanpa PIC</b>.' : '')
                    + '<br><br><small>Ditolak bila PIC masih dipakai Customer, Project, Homebase, atau Site.</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#9a3030',
                cancelButtonColor: '#55595e',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
        });
    @endif

    /*
    |--------------------------------------------------------------------------
    | MODAL BENEFICIARY — Tambah & Edit
    |--------------------------------------------------------------------------
    */

    function sinkronWajibPic() {
        $('#tambahBenPicReq').toggle($('#tambahBenCategory').val() === 'INTERNAL');
    }
    $('#tambahBenCategory').on('change', sinkronWajibPic);

    /* opsi.picId / opsi.category: dipakai tombol "+ Beneficiary" di detail
       PIC supaya PIC-nya langsung terisi. */
    function bukaTambahBen(opsi) {
        opsi = opsi || {};
        $('#formTambahBen')[0].reset();
        $('#tambahBenCategory').val(opsi.category || 'EXTERNAL');
        setSelect('#tambahBenPic', opsi.picId || '');
        setSelect('#tambahBenBank', '');
        sinkronWajibPic();
        $('#modalTambahBen').modal('show').one('shown.bs.modal', function () { $('#tambahBenNickname').focus(); });
    }
    $('#btnTambahBen').on('click', function () { bukaTambahBen(); });

    $('#formTambahBen').on('submit', function (e) {
        if ($('#tambahBenCategory').val() === 'INTERNAL' && !$('#tambahBenPic').val()) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'PIC wajib dipilih', text: 'Beneficiary kategori INTERNAL harus memiliki PIC.' });
        }
    });

    @if($canManage)
        $(document).on('click', '.btn-edit-ben', function (e) {
            e.stopPropagation();
            var id = $(this).data('id');
            bukaModalEdit($('#modalEditBen'), $('#formEditBen'), URL_BEN + '/' + id, URL_BEN + '/' + id + '/edit-data', function (res) {
                $('#editBenNickname').val(res.nickname);
                $('#editBenCompleteName').val(res.complete_name);
                $('#editBenCategory').val(res.category || 'EXTERNAL');
                setSelect('#editBenPic', res.pic_id);
                setSelect('#editBenBank', res.bank_id);
                $('#editBenAccountNumber').val(res.account_number || '');
                $('#editBenContact').val(res.contact || '');
                $('#editBenStatus').val(res.status);
            });
        });

        $(document).on('submit', '.ben-delete-form', function (e) {
            e.preventDefault();
            var form = this;
            Swal.fire({
                title: 'Hapus Beneficiary?',
                text: 'Data yang dihapus tidak dapat dikembalikan. Ditolak bila sudah dipakai Cost Request.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#9a3030',
                cancelButtonColor: '#55595e',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
        });

        /* Pindah PIC (dari detail PIC) / Kaitkan ke PIC (baris Tanpa PIC). */
        $(document).on('click', '.btn-move-pic', function (e) {
            e.stopPropagation();
            var asal = $(this).data('pic');
            $('#formMovePic').attr('action', URL_BEN + '/' + $(this).data('id') + '/move-pic');
            $('#movePicNama').text($(this).data('nama'));
            $('#movePicJudul').text(asal ? 'Pindah PIC' : 'Kaitkan ke PIC');
            setSelect('#movePicTujuan', asal || '');
            $('#modalMovePic').modal('show');
        });

        /* Kaitkan beneficiary yang SUDAH ADA ke PIC (bukan membuat baru). */
        var PICKER = null;

        function renderAttach() {
            if (!PICKER) { $('#picAttachList').html('<div class="text-muted text-center py-4">Memuat...</div>'); return; }
            var picId = String($('#formPicAttach').data('pic-id'));
            var term  = $.trim($('#picAttachSearch').val()).toLowerCase();
            var items = PICKER.filter(function (b) {
                if (String(b.pic_id) === picId) return false;
                if (!term) return true;
                return (b.nickname || '').toLowerCase().indexOf(term) !== -1
                    || (b.complete_name || '').toLowerCase().indexOf(term) !== -1;
            });
            var html = '';
            // Dibatasi 200 baris per render — mengetik satu-dua huruf sudah cukup mempersempit.
            items.slice(0, 200).forEach(function (b) {
                html += '<label class="pb-attach-row">'
                    + '<input type="checkbox" class="picAttachCheck" value="' + b.id + '">'
                    + '<span class="flex-grow-1"><strong>' + esc(b.nickname) + '</strong> '
                    + '<span class="text-muted pb-sub">' + esc(b.complete_name) + '</span></span>'
                    + (b.pic_name ? '<span class="badge badge-light border pb-mini" title="Akan dipindah dari PIC ini">Saat ini: ' + esc(b.pic_name) + '</span>'
                                  : '<span class="badge badge-light border pb-mini">Tanpa PIC</span>')
                    + '</label>';
            });
            if (!items.length) {
                html = '<div class="text-muted text-center py-4">' + (term ? 'Tidak ada yang cocok.' : 'Semua beneficiary sudah menjadi anggota PIC ini.') + '</div>';
            } else if (items.length > 200) {
                html += '<div class="text-muted text-center py-2 pb-sub">Menampilkan 200 dari ' + items.length + ' hasil — persempit dengan mengetik.</div>';
            }
            $('#picAttachList').html(html);
            hitungAttach();
        }
        function hitungAttach() {
            var n = $('.picAttachCheck:checked').length;
            $('#picAttachCount').text(n);
            $('#btnPicAttachSubmit').prop('disabled', n === 0);
        }

        $(document).on('click', '.btn-pic-attach', function () {
            var picId = $(this).data('pic'), nama = $(this).data('nama');
            $('#picAttachName').text(nama);
            $('#formPicAttach').data('pic-id', picId).attr('action', URL_BEN + '/pic/' + picId + '/attach');
            $('#picAttachSearch').val('');
            renderAttach();
            if (!PICKER) {
                $.getJSON(URL_BEN + '/picker')
                    .done(function (d) { PICKER = d; renderAttach(); })
                    .fail(function () { $('#picAttachList').html('<div class="text-danger text-center py-4">Gagal memuat daftar beneficiary.</div>'); });
            }
            $('#modalPicAttach').modal('show').one('shown.bs.modal', function () { $('#picAttachSearch').focus(); });
        });
        $('#picAttachSearch').on('input', renderAttach);
        $(document).on('change', '.picAttachCheck', hitungAttach);
        $('#formPicAttach').on('submit', function (e) {
            var ids = $('.picAttachCheck:checked').map(function () { return this.value; }).get();
            if (!ids.length) { e.preventDefault(); return; }
            var $f = $(this);
            $f.find('input[name="beneficiary_ids[]"]').remove();
            ids.forEach(function (id) { $f.append('<input type="hidden" name="beneficiary_ids[]" value="' + id + '">'); });
        });
    @endif

    /*
    |--------------------------------------------------------------------------
    | DATATABLE — satu tabel untuk seluruh PIC + beneficiary tanpa PIC
    |--------------------------------------------------------------------------
    | Responsive DataTables sengaja TIDAK dipakai: ia memakai child row yang
    | sama dengan detail beneficiary. Kolom sekunder disembunyikan di layar
    | kecil lewat kelas d-none d-lg-table-cell.
    */
    try {

    var table = $('#pbTable').DataTable({
        autoWidth: false, pageLength: 25, lengthChange: true,
        lengthMenu: [10, 25, 50, 100],
        searching: true, ordering: true,
        order: [],   // urutan server: PIC (A-Z), lalu beneficiary tanpa PIC
        columnDefs: [
            { orderable: false, targets: [0, 1, 9] },
            { visible: false, targets: [10] }   // kolom kata kunci pencarian
        ],
        dom:
            'rt' +
            '<"row mt-3 align-items-center"' +
                '<"col-md-6 col-12 mb-2 mb-md-0"i>' +
                '<"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>' +
            '>',
        language: {
            search: '', lengthMenu: 'Page Size _MENU_',
            zeroRecords: 'Data tidak ditemukan',
            emptyTable: 'Belum ada data PIC maupun beneficiary',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_)',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    // Nomor urut tampil (bukan urutan muat).
    table.on('draw.dt', function () {
        var mulai = table.page.info().start;
        table.column(1, { page: 'current' }).nodes().each(function (cell, i) { cell.textContent = mulai + i + 1; });
    });

    /* ── Detail beneficiary (child row) ── */

    function istilahCari() { return $.trim($('#globalSearch').val()).toLowerCase(); }

    function cocok(b, term) {
        if (!term) return false;
        return [b.nickname, b.complete_name, b.account_number].some(function (v) {
            return (v || '').toLowerCase().indexOf(term) !== -1;
        });
    }

    function detailHtml(tr) {
        var picId   = tr.getAttribute('data-pic-id');
        var picName = tr.getAttribute('data-pic-name');
        var picType = tr.getAttribute('data-pic-type');
        var aktif   = tr.getAttribute('data-pic-active') === '1';
        var items   = BEN_BY_PIC[picId] || [];
        var term    = istilahCari();

        var aksi = '';
        // PIC nonaktif tidak muncul di pilihan PIC form beneficiary.
        if (aktif) {
            aksi += '<button type="button" class="btn btn-success btn-sm btn-add-ben-pic" data-pic="' + picId + '"'
                + ' data-category="' + (picType === 'internal' ? 'INTERNAL' : 'EXTERNAL') + '">'
                + '<i class="fas fa-plus mr-1"></i> Beneficiary</button>';
        }
        @if($canManage)
            aksi += '<button type="button" class="btn btn-outline-secondary btn-sm btn-pic-attach" data-pic="' + picId + '" data-nama="' + esc(picName) + '">'
                + '<i class="fas fa-link mr-1"></i> Kaitkan yang sudah ada</button>';
        @endif

        var html = '<div class="pb-child">'
            + '<div class="pb-child-head">'
            + '<h3 class="pb-child-title"><i class="fas fa-hand-holding-usd mr-1"></i> Beneficiary ' + esc(picName)
            + ' <span class="badge badge-secondary">' + items.length + '</span></h3>'
            + '<div class="pb-child-actions">' + aksi + '</div>'
            + '</div>';

        if (!items.length) {
            return html + '<div class="pb-child-empty">Belum ada beneficiary untuk PIC ini.</div></div>';
        }

        html += '<div class="table-responsive"><table class="table table-bordered table-sm">'
            + '<thead><tr><th style="width:4%;">No</th><th>Nickname</th><th>Complete Name</th><th style="width:9%;">Kategori</th>'
            + '<th style="width:10%;">Bank</th><th style="width:15%;">No. Rekening</th><th class="d-none d-lg-table-cell" style="width:12%;">Kontak</th>'
            + '<th style="width:8%;">Status</th>' + (CAN_MANAGE ? '<th class="text-center action-column" style="width:13%;">Action</th>' : '')
            + '</tr></thead><tbody>';

        items.forEach(function (b, i) {
            html += '<tr' + (cocok(b, term) ? ' class="pb-match"' : '') + '>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><strong>' + esc(b.nickname) + '</strong></td>'
                + '<td>' + esc(b.complete_name) + '</td>'
                + '<td><span class="badge ' + (CAT_BADGE[b.category] || 'badge-secondary') + '">' + esc(b.category) + '</span></td>'
                + '<td>' + (b.bank ? '<span class="badge badge-info">' + esc(b.bank) + '</span>' : '-') + '</td>'
                + '<td class="pb-num">' + esc(b.account_number || '-') + '</td>'
                + '<td class="d-none d-lg-table-cell">' + esc(b.contact || '-') + '</td>'
                + '<td><span class="badge ' + (b.status === 'ACTIVE' ? 'badge-success' : 'badge-danger') + '">' + esc(b.status) + '</span></td>';
            @if($canManage)
                html += '<td class="text-center action-column"><div class="action-buttons ts-actions">'
                    + '<button type="button" class="ts-action-primary is-neutral btn-edit-ben" data-id="' + b.id + '" title="Edit" aria-label="Edit ' + esc(b.nickname) + '">'
                    + '<i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span></button>'
                    + '<button type="button" class="ts-action-primary is-neutral btn-move-pic" data-id="' + b.id + '" data-nama="' + esc(b.nickname) + '" data-pic="' + picId + '"'
                    + ' title="Pindah ke PIC lain" aria-label="Pindah ' + esc(b.nickname) + ' ke PIC lain">'
                    + '<i class="fas fa-exchange-alt" aria-hidden="true"></i><span class="ts-al">Pindah</span></button>'
                    + '<form action="' + URL_BEN + '/' + b.id + '" method="POST" class="d-inline ben-delete-form">'
                    + '<input type="hidden" name="_token" value="' + esc(CSRF) + '"><input type="hidden" name="_method" value="DELETE">'
                    + '<button type="submit" class="ts-action-primary is-danger" title="Hapus" aria-label="Hapus ' + esc(b.nickname) + '">'
                    + '<i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span></button></form>'
                    + '</div></td>';
            @endif
            html += '</tr>';
        });

        return html + '</tbody></table></div></div>';
    }

    function bukaDetail(tr, buka) {
        var row = table.row(tr);
        if (!row.length || tr.getAttribute('data-kind') !== 'pic') return;
        if (buka === undefined) buka = !row.child.isShown();
        if (buka) {
            row.child(detailHtml(tr), 'pb-child-row').show();
        } else {
            row.child.hide();
        }
        $(tr).toggleClass('pb-open', buka).find('.pb-toggle').attr('aria-expanded', buka ? 'true' : 'false');
    }

    // Klik di mana saja pada baris PIC membuka detail, kecuali di tombol/aksi.
    $('#pbTable tbody').on('click', 'tr.pb-row-pic', function (e) {
        if ($(e.target).closest('.action-column, a, form').length && !$(e.target).closest('.pb-toggle').length) return;
        bukaDetail(this);
    });

    $(document).on('click', '.btn-add-ben-pic', function () {
        bukaTambahBen({ picId: $(this).data('pic'), category: $(this).data('category') });
    });

    $('#btnCollapseAll').on('click', function () {
        table.rows('.pb-open').every(function () { bukaDetail(this.node(), false); });
    });

    /* ── Pencarian ──
       Kata kunci mencakup nama/kontak PIC DAN nickname/nama/no. rekening
       beneficiary-nya (kolom tersembunyi). Bila baris PIC lolos karena
       beneficiary-nya, detailnya dibuka otomatis dan baris yang cocok
       disorot — yang dicari sering nomor rekening, bukan nama PIC. */
    var cariTimer = null;
    $('#globalSearch').on('input', function () {
        clearTimeout(cariTimer);
        var v = this.value;
        cariTimer = setTimeout(function () { table.search(v).draw(); }, 200);
    });

    table.on('draw.dt', function () {
        var term = istilahCari();
        table.rows({ page: 'current' }).every(function () {
            var tr = this.node();
            if (tr.getAttribute('data-kind') !== 'pic') return;
            var items = BEN_BY_PIC[tr.getAttribute('data-pic-id')] || [];
            var adaCocok = term.length >= 2 && items.some(function (b) { return cocok(b, term); });
            if (adaCocok) {
                bukaDetail(tr, true);
            } else if (this.child.isShown()) {
                // Segarkan sorotan pada detail yang sedang terbuka.
                this.child(detailHtml(tr), 'pb-child-row').show();
            }
        });
    });

    /* ── Saringan ── */
    TesysColumnFilter.initRowFilters('#pbTable', '#pbRowFilters', [
        { key: 'jenis',  label: 'Jenis',  options: @json(array_merge(array_values(\App\Models\MasterPic::TYPES), ['Tanpa PIC'])), search: false },
        { key: 'status', label: 'Status', options: ['ACTIVE', 'INACTIVE'], search: false },
    ]);

    var hanyaUnlinked = false;
    $.fn.dataTable.ext.search.push(function (settings, data, index) {
        if (settings.nTable.id !== 'pbTable' || !hanyaUnlinked) return true;
        var tr = settings.aoData[index].nTr;
        return tr && tr.getAttribute('data-unlinked') === '1';
    });

    function setUnlinked(on) {
        hanyaUnlinked = on;
        $('#btnUnlinked').toggleClass('btn-danger', on).toggleClass('btn-outline-danger', !on).attr('aria-pressed', on ? 'true' : 'false');
        table.page('first').draw();
    }
    $('#btnUnlinked').on('click', function () { setUnlinked(!hanyaUnlinked); });

    $('#btnOrphan').on('click', function () {
        TesysColumnFilter.clearAll('#pbTable');
        TesysColumnFilter.setRowFilter('#pbTable', 'jenis', 'Tanpa PIC');
    });

    $('#btnClearFilter').on('click', function () {
        TesysColumnFilter.clearAll('#pbTable');
        $('#globalSearch').val('');
        hanyaUnlinked = false;
        $('#btnUnlinked').removeClass('btn-danger').addClass('btn-outline-danger').attr('aria-pressed', 'false');
        table.rows('.pb-open').every(function () { bukaDetail(this.node(), false); });
        table.search('').page('first').draw();
    });

    $('#refreshTable').on('click', function () { location.reload(); });

    } catch (err) {
        console.error('Gagal inisialisasi DataTable PIC & Beneficiary:', err);
    }
});
</script>
@endpush
