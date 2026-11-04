@extends('layouts.app')

@section('title', 'PIC')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>PIC Master</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Master</li>
                <li class="breadcrumb-item active">PIC</li>
            </ol>
        </div>

        <x-tw.page-header title="Daftar PIC"
            description="Penanggung jawab internal maupun eksternal beserta kontak dan akun penghubungnya.">
            <x-slot:actions>
                <x-tw.button id="btnTambah" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah PIC</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <button type="button" class="ts-fab" data-trigger="#btnTambah" aria-label="Tambah PIC" title="Tambah PIC"><i class="fas fa-plus" aria-hidden="true"></i></button>

        {{-- Nama akun/karyawan contoh masih berlabel "DUMMY" di basis data. Kata
             itu dibuang untuk TAMPILAN saja — nilai yang dikirim tetap id-nya,
             dan username asli di tabel users tidak disentuh supaya loginnya
             tidak ikut berubah. --}}
        @php
            $tanpaDummy = function ($v) {
                $bersih = trim(preg_replace('/\s{2,}/', ' ', preg_replace('/\bdummy\b[\s._-]*/i', '', (string) $v)));
                return $bersih !== '' ? $bersih : (string) $v;
            };

        @endphp

        {{-- Tab utama. Internal paling kiri sekaligus tab bawaan: daftar itulah
             yang paling sering dikerjakan. Tab External = semua yang BUKAN internal
             (Customer, External, Site Keeper) — jenis baru apa pun ikut masuk ke
             sana dengan sendirinya. Database memuat semuanya.

             Seluruh baris dikirim sekali, lalu tab dipilah di browser. Berpindah
             tab tidak memuat ulang halaman: pemuatan ulang sempat memperlihatkan
             halaman setengah jadi tanpa gaya, dan itu terjadi setiap kali tab
             disentuh padahal datanya tidak berubah. --}}
        @php $picTabs = [
            'internal' => ['Internal', 'fas fa-users'],
            'external' => ['External', 'fas fa-handshake'],
            'db'       => ['Database', 'fas fa-database'],
        ]; @endphp
        <ul class="nav nav-tabs ts-pill-tabs pic-main-tabs mt-2 mb-3" role="tablist">
            @foreach($picTabs as $key => [$label, $icon])
                <li class="nav-item">
                    {{-- href tetap ditulis supaya tautannya bisa dibuka di tab baru
                         dan terbaca sebagai tautan; klik biasa ditangani JS. --}}
                    <a class="nav-link pic-tab {{ $tab === $key ? 'active' : '' }}"
                       data-pictab="{{ $key }}"
                       href="{{ url('/master-pic?tab=' . $key) }}">
                        <i class="{{ $icon }} mr-1"></i> {{ $label }}
                        <span class="badge badge-secondary ml-1">{{ number_format($tabCounts[$key], 0, ',', '.') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Beneficiary / Cost Request). --}}
        <x-tw.card padding="sm">

                <!-- FILTER BAR -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterPic" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="picRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>

                        {{-- M-02 — saringan kerja: sisakan hanya PIC internal yang
                             belum punya akun User, supaya pemetaannya bisa
                             dikerjakan sampai habis tanpa memilah sendiri dari
                             daftar penuh. Diproses di server, jadi angkanya tetap
                             konsisten dengan isi tabel. --}}
                        <span id="picUnlinkedWrap" @if($tab === 'external') style="display:none;" @endif>
                        @if(($filters['unlinked'] ?? false))
                            <a href="{{ url('/master-pic?tab=' . $tab) }}" class="btn btn-danger btn-sm">
                                <i class="fas fa-user-slash mr-1"></i> Belum Terhubung
                                <span class="badge badge-light ml-1">{{ $summary['unlinked'] }}</span>
                                <i class="fas fa-times ml-1" title="Hapus saringan"></i>
                            </a>
                        @elseif($summary['unlinked'] > 0)
                            <a href="{{ url('/master-pic?tab=internal&unlinked=1') }}" class="btn btn-outline-danger btn-sm"
                               title="Tampilkan hanya PIC internal yang belum terhubung ke akun User">
                                <i class="fas fa-user-slash mr-1"></i> Belum Terhubung
                                <span class="badge badge-danger ml-1">{{ $summary['unlinked'] }}</span>
                            </a>
                        @endif
                        </span>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <x-tw.button id="refreshTable" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="picTable" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="4%" class="all">No</th>
                                <th scope="col" width="11%" class="all">Type</th>
                                <th scope="col" width="20%" class="all">Nama PIC</th>
                                <th scope="col" width="15%">Jabatan</th>
                                <th scope="col" width="14%">Phone</th>
                                <th scope="col" width="14%">Email</th>
                                <th scope="col" width="8%">Bank</th>
                                <th scope="col" width="8%">Token</th>
                                <th scope="col" width="9%" class="text-right">Nominal</th>
                                <th scope="col" width="7%">Status</th>
                                <th scope="col" width="10%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pics as $pic)
                            <tr data-type="{{ $pic->type_label }}" data-typekey="{{ $pic->type }}" data-status="{{ $pic->status }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{-- Label & warnanya diambil dari MasterPic::TYPES, jadi
                                         menambah jenis baru tidak perlu menyentuh baris ini. --}}
                                    <span class="badge {{ \App\Models\MasterPic::TYPE_BADGES[$pic->type] ?? 'badge-light' }} px-2 py-1">{{ $pic->type_label }}</span>
                                </td>
                                <td>
                                    <strong>{{ $pic->pic }}</strong>
                                    @if($pic->beneficiaries_count > 0)
                                        <div class="text-muted" style="font-size:10px;">
                                            <i class="fas fa-link mr-1"></i>{{ $pic->beneficiaries_count }} beneficiary
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $pic->position ?? '-' }}</td>
                                <td>{{ $pic->phone ?? '-' }}</td>
                                <td>{{ $pic->email ?? '-' }}</td>
                                {{-- Bank. PIC tidak lagi menyimpan rekeningnya sendiri
                                     (kolom bank_id sudah dilepas, lihat migrasi
                                     2026_10_01_000020); yang ada rekening adalah
                                     beneficiary yang ia tangani. Jadi selama PIC ini
                                     punya beneficiary, pembayarannya lewat rekening
                                     yang sudah terdaftar di sistem — ditandai In-House. --}}
                                <td data-order="{{ $pic->beneficiaries_count > 0 ? 1 : 0 }}">
                                    @if($pic->beneficiaries_count > 0)
                                        <span class="badge badge-info px-2 py-1" style="font-size:10px;"
                                              title="{{ $pic->beneficiaries_count }} beneficiary terhubung ke PIC ini">In-House</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                {{-- Hanya dibaca di sini. Mengubahnya lewat modal Edit,
                                     sejalan dengan kolom lain di baris ini. --}}
                                <td>{{ $pic->token ?: '-' }}</td>
                                <td class="text-right" data-order="{{ $pic->nominal !== null ? (float) $pic->nominal : -1 }}">
                                    @if($pic->nominal !== null)
                                        {{-- Pemisah ribuan titik, desimal koma. Desimal hanya
                                             ditulis kalau memang ada. --}}
                                        {{ fmod((float) $pic->nominal, 1) == 0.0
                                            ? number_format((float) $pic->nominal, 0, ',', '.')
                                            : number_format((float) $pic->nominal, 2, ',', '.') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($pic->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $pic->id }}"
                                                title="Edit" aria-label="Edit {{ $pic->pic }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        {{-- Hapus ditampilkan langsung, bukan di balik menu
                                             "aksi lain": isinya memang cuma satu perintah, jadi
                                             menunya hanya menambah satu klik tanpa menyembunyikan
                                             apa pun. --}}
                                        <form action="{{ url('/master-pic/' . $pic->id) }}"
                                              method="POST" class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger"
                                                    title="Hapus" aria-label="Hapus {{ $pic->pic }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>
                            @empty
                            <tr>
                                <td colspan="11">
                                    <x-empty title="Belum ada data PIC" icon="fas fa-folder-open" compact />
                                </td>
                            </tr>
                            @endforelse
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
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-id-badge mr-2" style="color:var(--accent);"></i>
                    Tambah PIC
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="{{ url('/master-pic') }}" method="POST" id="formTambah">
                @csrf

                <div class="modal-body" style="padding:28px 32px;">
                    <div class="row">

                        <!-- TYPE -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Type <span class="text-danger">*</span>
                                </label>
                                <select name="type" id="tambahType" class="form-control" required>
                                    @foreach(\App\Models\MasterPic::TYPES as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- NAMA PIC -->
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Nama PIC <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="pic" id="tambahPic"
                                       class="form-control"
                                       placeholder="Masukkan nama lengkap"
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                            </div>
                        </div>

                        <!-- JABATAN -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Jabatan</label>
                                <input type="text" name="position" id="tambahPosition"
                                       class="form-control"
                                       placeholder="Masukkan jabatan"
                                       autocomplete="off">
                            </div>
                        </div>


                        <!-- PHONE -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Phone</label>
                                <input type="text" name="phone" id="tambahPhone"
                                       class="form-control"
                                       placeholder="Masukkan nomor telepon"
                                       autocomplete="off">
                            </div>
                        </div>

                        <!-- EMAIL -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Email</label>
                                <input type="email" name="email" id="tambahEmail"
                                       class="form-control"
                                       placeholder="Masukkan email"
                                       autocomplete="off">
                            </div>
                        </div>

                        <!-- TOKEN (hanya tipe internal) -->
                        <div class="col-md-6 pic-internal-only">
                            <div class="form-group">
                                <label class="font-weight-600">Token</label>
                                <input type="text" name="token" id="tambahToken"
                                       class="form-control" maxlength="100"
                                       placeholder="Masukkan token"
                                       autocomplete="off">
                            </div>
                        </div>

                        <!-- NOMINAL (hanya tipe internal) -->
                        <div class="col-md-6 pic-internal-only">
                            <div class="form-group">
                                <label class="font-weight-600">Nominal</label>
                                <input type="text" name="nominal" id="tambahNominal"
                                       class="form-control text-right money-input"
                                       inputmode="decimal" placeholder="0"
                                       autocomplete="off">
                            </div>
                        </div>

                        {{-- M-02 — hubungkan PIC internal ke orang yang sebenarnya.
                             Dibiarkan opsional: PIC customer memang orang luar. --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Akun User</label>
                                <select name="user_id" id="tambahUser" class="form-control">
                                    <option value="">— Tidak terhubung —</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}">{{ strtoupper($tanpaDummy($u->username)) }} — {{ $tanpaDummy($u->name) }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted field-hint">Isi untuk PIC internal supaya datanya menyatu dengan Team.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Data Karyawan</label>
                                <select name="employee_id" id="tambahEmployee" class="form-control">
                                    <option value="">— Tidak terhubung —</option>
                                    @foreach($employees as $e)
                                        <option value="{{ $e->id }}">{{ $tanpaDummy($e->name) }} ({{ $e->employee_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>

                    <!-- STATUS hidden — default ACTIVE -->
                    <input type="hidden" name="status" value="ACTIVE">

                </div>

                <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL EDIT PIC                                                    -->
<!-- ================================================================ -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-pen mr-2" style="color:var(--accent);"></i>
                    Edit PIC
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form id="formEdit" method="POST">
                @csrf
                @method('PUT')

                <div class="modal-body" style="padding:28px 32px;">

                    <!-- Loading -->
                    <div id="editLoading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Memuat data...</p>
                    </div>

                    <div id="editFields" style="display:none;">
                        <div class="row">

                            <!-- TYPE -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Type <span class="text-danger">*</span>
                                    </label>
                                    <select name="type" id="editType" class="form-control" required>
                                        @foreach(\App\Models\MasterPic::TYPES as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- NAMA PIC -->
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Nama PIC <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="pic" id="editPic"
                                           class="form-control"
                                           style="text-transform:uppercase;"
                                           autocomplete="off" required>
                                </div>
                            </div>

                            <!-- JABATAN -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Jabatan</label>
                                    <input type="text" name="position" id="editPosition"
                                           class="form-control" autocomplete="off">
                                </div>
                            </div>

                            <!-- PHONE -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Phone</label>
                                    <input type="text" name="phone" id="editPhone"
                                           class="form-control" autocomplete="off">
                                </div>
                            </div>

                            <!-- EMAIL -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Email</label>
                                    <input type="email" name="email" id="editEmail"
                                           class="form-control" autocomplete="off">
                                </div>
                            </div>

                            <!-- TOKEN (hanya tipe internal) -->
                            <div class="col-md-6 pic-internal-only">
                                <div class="form-group">
                                    <label class="font-weight-600">Token</label>
                                    <input type="text" name="token" id="editToken"
                                           class="form-control" maxlength="100"
                                           autocomplete="off">
                                </div>
                            </div>

                            <!-- NOMINAL (hanya tipe internal) -->
                            <div class="col-md-6 pic-internal-only">
                                <div class="form-group">
                                    <label class="font-weight-600">Nominal</label>
                                    <input type="text" name="nominal" id="editNominal"
                                           class="form-control text-right money-input"
                                           inputmode="decimal" autocomplete="off">
                                </div>
                            </div>

                            <!-- AKUN USER (M-02) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Akun User</label>
                                    <select name="user_id" id="editUser" class="form-control">
                                        <option value="">— Tidak terhubung —</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}">{{ strtoupper($tanpaDummy($u->username)) }} — {{ $tanpaDummy($u->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- DATA KARYAWAN (M-02) -->
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="font-weight-600">Data Karyawan</label>
                                    <select name="employee_id" id="editEmployee" class="form-control">
                                        <option value="">— Tidak terhubung —</option>
                                        @foreach($employees as $e)
                                            <option value="{{ $e->id }}">{{ $tanpaDummy($e->name) }} ({{ $e->employee_number }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- STATUS -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Status <span class="text-danger">*</span>
                                    </label>
                                    <select name="status" id="editStatus" class="form-control" required>
                                        <option value="ACTIVE">ACTIVE</option>
                                        <option value="INACTIVE">INACTIVE</option>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-warning" id="btnSimpanEdit" style="display:none;">
                        <i class="fas fa-save mr-1"></i> Update
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>

/* Jarak antar isian di modal PIC dibuat seragam: tinggi tiap kolom disamakan
   supaya keterangan kecil di bawah "Akun User" tidak menggeser baris berikutnya. */
#modalTambah .modal-body .form-group,
#modalEdit   .modal-body .form-group { margin-bottom:18px; }
#modalTambah .modal-body .row > [class^="col-"],
#modalEdit   .modal-body .row > [class^="col-"] { display:flex; flex-direction:column; }
#modalTambah .modal-body .form-group.mb-0,
#modalEdit   .modal-body .form-group.mb-0 { margin-bottom:0 !important; }
/* Keterangan kecil diletakkan di sela jarak baris, bukan menambah tinggi
   kolomnya, supaya baris "Data Karyawan" di bawahnya tidak ikut terdorong. */
#modalTambah .modal-body .form-group { position:relative; }
#modalTambah .modal-body .field-hint {
    position:absolute; top:100%; left:0; right:0; margin-top:2px;
    font-size:12px; line-height:1.3;
}

.action-header-buttons { display:flex; gap:8px; }
.card-header .btn      { border-radius:var(--radius-sm); font-weight:600; }
.font-weight-600       { font-weight:600; }
/* Kolom uang dibaca dengan membandingkan digitnya, jadi angkanya rata kanan. */
.money-input { font-variant-numeric: tabular-nums; }

/* TOOLBAR */
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch        { width:100%; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }

/* TOMSELECT */
.ts-wrapper  { min-width:260px; width:100%; }
.ts-control  { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.ts-control.focus { border-color:var(--border-strong) !important; box-shadow:0 0 0 0.15rem rgba(0,123,255,.15) !important; }

/* TAB UTAMA */
.pic-main-tabs { border-bottom:2px solid var(--border); flex-wrap:wrap; }
.pic-main-tabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:9px 16px; font-weight:600; font-size:13px; margin-bottom:-2px; }
.pic-main-tabs .nav-link:hover { color:var(--text); }
.pic-main-tabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; }

/* TABLE */
#picTable            { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column      { white-space:nowrap !important; min-width:80px !important; }

.action-buttons     { display:flex; align-items:center; justify-content:center; flex-wrap:nowrap; gap:4px; }
.action-buttons .btn { flex-shrink:0; }

table.dataTable.dtr-inline.collapsed > tbody > tr > td:first-child:before,
table.dataTable.dtr-inline.collapsed > tbody > tr > th:first-child:before {
    top:50%; transform:translateY(-50%);
}

/* TABLE BOTTOM */
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }

/* PAGE SIZE */
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select {
    appearance:none !important; -webkit-appearance:none !important;
    background-color:var(--surface) !important; color:var(--text-dim) !important;
    border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important;
    padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important;
    font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M1.5 5.5l6 6 6-6'/%3E%3C/svg%3E") !important;
    background-repeat:no-repeat !important; background-position:right 10px center !important; background-size:12px !important;
}

/* PAGINATION — amber */
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; transition:all .2s; }
.dataTables_paginate .paginate_button.current,
.dataTables_paginate .paginate_button.current:hover,
.dataTables_wrapper .pagination .page-item.active .page-link {
    background:var(--accent) !important; background-image:none !important;
    border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important;
}
.dataTables_wrapper .pagination .page-link { color:var(--accent) !important; }
.dataTables_wrapper .pagination .page-item:not(.active) .page-link:hover {
    background:var(--warning-bg) !important; border-color:var(--accent) !important; color:var(--accent-dark) !important;
}
.dataTables_wrapper .pagination .page-link:focus {
    box-shadow:0 0 0 3px rgba(200,39,46,0.30) !important; outline:none !important;
}
.dataTables_wrapper .pagination .page-item.disabled .page-link { color:var(--text-faint) !important; }

/* MOBILE */
@media (max-width: 768px) {
    .action-header-buttons { width:100%; flex-direction:column; gap:10px; }
    .action-header-buttons .btn { width:100%; }
    .content-header .row { flex-direction:column; }
    .breadcrumb { float:left !important; margin-top:10px; }
    .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn { width:100%; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; gap:10px; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterPic { width:100%; }
    #picRowFilters { width:100%; }
    #picRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; align-items:flex-start; }
    .toolbar-input { flex:1; width:100%; min-width:0; }
    #customSearch input { width:100% !important; }
    .ts-wrapper,.ts-control { width:100% !important; min-width:0 !important; }
    .ts-control > input { min-width:0 !important; width:100% !important; }
    .dataTables_wrapper .row:last-child { display:flex; flex-direction:column; align-items:flex-start !important; gap:12px; }
    .dataTables_wrapper .row:last-child .col-md-6 { width:100%; max-width:100%; display:flex; justify-content:flex-start !important; }
    .dataTables_paginate { width:100%; overflow-x:auto; }
    .dataTables_length { margin-left:0; }
    .modal-body { padding:20px !important; }
}

</style>
@endpush

@push('scripts')
<script>

$(function () {

    // Tab yang sedang terbuka. Dipakai juga oleh modal Tambah (jenis bawaan),
    // jadi dideklarasikan sebelum handler mana pun dipasang.
    var activeTab = '{{ $tab }}';

    /*
    |--------------------------------------------------------------------------
    | MODAL TAMBAH & EDIT — dibind PALING AWAL, supaya tetap responsif
    | walaupun ada error di inisialisasi DataTable/TomSelect di bawah.
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | DROPDOWN BER-PENCARIAN
    |
    | Daftar Akun User dan Karyawan sudah puluhan baris — menggulirnya sampai
    | ketemu lebih lama daripada mengetik dua huruf. <select> aslinya tetap ada
    | (disembunyikan), jadi nilai yang dikirim form tidak berubah.
    |--------------------------------------------------------------------------
    */
    [
        ['#tambahUser',     '— Tidak terhubung —',   'Cari akun user...'],
        ['#tambahEmployee', '— Tidak terhubung —',   'Cari karyawan...'],
        ['#editUser',       '— Tidak terhubung —',   'Cari akun user...'],
        ['#editEmployee',   '— Tidak terhubung —',   'Cari karyawan...'],
    ].forEach(function (cfg) {
        if ($(cfg[0]).length) {
            TesysSearchableSelect.init(cfg[0], { placeholder: cfg[1], searchPlaceholder: cfg[2] });
        }
    });

    $(document).on('click', '#btnTambah', function () {
        $('#formTambah')[0].reset();
        // Jenisnya disetel mengikuti tab yang sedang dibuka — PIC yang ditambah
        // dari tab Internal hampir selalu internal. Masih bisa diganti.
        if (activeTab === 'internal') $('#tambahType').val('internal');
        sinkronFieldInternal('#formTambah');
        // reset() hanya mengubah <select> yang tersembunyi — tombolnya masih
        // memajang pilihan sebelumnya sampai diberitahu.
        $('#tambahUser, #tambahEmployee').trigger('tss:sync');
        $('#modalTambah').modal('show');
        $('#modalTambah').one('shown.bs.modal', function () {
            $('#tambahPic').focus();
        });
    });

    $(document).on('click', '.btn-edit', function () {
        let id = $(this).data('id');

        $('#editLoading').show();
        $('#editFields').hide();
        $('#btnSimpanEdit').hide();
        $('#modalEdit').modal('show');

        $('#formEdit').attr('action', '{{ url("/master-pic") }}/' + id);

        $.get('{{ url("/master-pic") }}/' + id + '/edit-data', function (res) {
            $('#editType').val(res.type);
            $('#editPic').val(res.pic);
            $('#editPosition').val(res.position ?? '');
            $('#editPhone').val(res.phone ?? '');
            $('#editEmail').val(res.email ?? '');
            $('#editToken').val(res.token ?? '');
            // Nilai dari server selalu berdesimal dua ("100000000.00"). Desimal
            // nol dibuang supaya yang terlihat sama dengan yang tertulis di tabel.
            $('#editNominal').val(res.nominal !== null && res.nominal !== undefined
                ? formatMoney(String(res.nominal).replace(/\.00$/, '').replace('.', ','))
                : '');
            $('#editUser').val(res.user_id ?? '');
            $('#editEmployee').val(res.employee_id ?? '');
            $('#editStatus').val(res.status);
            $('#editUser, #editEmployee').trigger('tss:sync');
            sinkronFieldInternal('#formEdit');

            $('#editLoading').hide();
            $('#editFields').show();
            $('#btnSimpanEdit').show();

            $('#modalEdit').one('shown.bs.modal', function () {
                $('#editPic').focus();
            });
        }).fail(function () {
            $('#editLoading').html(
                '<p class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data. Silakan coba lagi.</p>'
            );
        });
    });

    /*
    |--------------------------------------------------------------------------
    | NOMINAL — pemisah ribuan
    |
    | Kotaknya bertipe text, bukan number: input number tidak bisa menampilkan
    | titik pemisah, dan tampilannya ikut berubah-ubah mengikuti locale browser.
    | Yang dikirim ke server tetap angka polos — lihat handler submit di bawah.
    |--------------------------------------------------------------------------
    */

    // Token & Nominal hanya berlaku untuk PIC internal. Untuk tipe lain field
    // disembunyikan dan dikosongkan, supaya tidak ikut terkirim.
    function sinkronFieldInternal(form) {
        let $f = $(form);
        let internal = $f.find('select[name="type"]').val() === 'internal';
        let $box = $f.find('.pic-internal-only');
        $box.toggle(internal);
        if (!internal) $box.find('input').val('');
    }
    $(document).on('change', '#tambahType, #editType', function () {
        sinkronFieldInternal($(this).closest('form'));
    });

    function formatMoney(raw) {
        let v = String(raw ?? '').replace(/[^\d,.-]/g, '');
        if (v === '') return '';

        // Titik dianggap pemisah ribuan, koma pemisah desimal.
        v = v.replace(/\./g, '');
        let parts  = v.split(',');
        let intRaw = parts[0].replace(/\D/g, '');
        let dec    = parts.length > 1 ? parts[1].replace(/\D/g, '').slice(0, 2) : null;

        if (intRaw === '') intRaw = '0';
        let int = intRaw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return dec !== null ? int + ',' + dec : int;
    }

    // Angka polos untuk dikirim ke server: "100.000.000,50" -> "100000000.50"
    function unformatMoney(raw) {
        let v = String(raw ?? '').trim();
        if (v === '') return '';
        return v.replace(/\./g, '').replace(',', '.');
    }

    $(document).on('input', '.money-input', function () {
        let el  = this;
        let end = el.value.length - el.selectionStart;   // jarak kursor dari kanan
        el.value = formatMoney(el.value);
        // Kursor dikembalikan diukur dari kanan, supaya titik yang baru
        // disisipkan tidak melompatkan kursor ke ujung.
        let pos = Math.max(0, el.value.length - end);
        try { el.setSelectionRange(pos, pos); } catch (e) {}
    });

    // Kedua form dikirim biasa (bukan AJAX), jadi nilainya dipoloskan tepat
    // sebelum berangkat — kalau tidak, "100.000.000" gagal validasi numeric.
    $(document).on('submit', '#formTambah, #formEdit', function () {
        $(this).find('.money-input').each(function () {
            this.value = unformatMoney(this.value);
        });
    });

    $(document).on('submit', '.delete-form', function (e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Hapus PIC?',
            text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#9a3030',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) form.submit(); });
    });

    /*
    |--------------------------------------------------------------------------
    | DATATABLE (dibungkus try-catch — kalau gagal, modal Tambah/Edit
    | di atas tetap jalan normal, tabel jatuh ke tampilan HTML polos)
    |--------------------------------------------------------------------------
    */

    try {

    let picTable = $('#picTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
        order: [],
        columnDefs: [
            // Indeks kolom: 0 No, 1 Type, 2 Nama PIC, 3 Jabatan, 4 Phone,
            // 5 Email, 6 Bank, 7 Token, 8 Nominal, 9 Status, 10 Action. Kolom No
            // tidak bisa diurutkan: nomornya dihitung ulang tiap tabel digambar.
            { orderable: false,        targets: [0] },
            { responsivePriority: 1,   targets: [0, 2, -1] },
            { responsivePriority: 3,   targets: [6, 7, 8] },
            { responsivePriority: 100, targets: [1, 3, 4, 5, 9] }
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
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    // Kolom No dihitung ulang tiap digambar (urutan tampil, bukan urutan muat).
    picTable.on('draw.dt', function () {
        var mulai = picTable.page.info().start;
        picTable.column(0, { page: 'current' }).nodes().each(function (cell, i) {
            cell.textContent = mulai + i + 1;
        });
    });

    $('#picTable_filter').hide();

    $('#customSearch').html(
        '<input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">'
    );
    $('#globalSearch').on('keyup', function () { picTable.search(this.value).draw(); });

    /* Daftar pilihan dinyatakan, bukan disimpulkan dari baris yang ada: jenis
       yang belum dipakai satu PIC pun tetap harus terlihat di filter — justru
       di situ orang ingin memastikan memang belum ada. search:false karena
       pilihannya sedikit. */
    TesysColumnFilter.initRowFilters('#picTable', '#picRowFilters', [
        // Tombolnya tetap dipasang untuk ketiga tab lalu disembunyikan oleh
        // applyTab() di tab Internal — memasang ulang tiap ganti tab berarti
        // membongkar state filter yang sudah terpasang di DataTable.
        { key: 'type', label: 'Type', options: @json(array_values(\App\Models\MasterPic::TYPES)), search: false },
        { key: 'status', label: 'Status', options: ['ACTIVE', 'INACTIVE'], search: false },
    ]);

    $('#btnClearFilterPic').on('click', function () {
        TesysColumnFilter.clearAll('#picTable');
    });

    /*
    |--------------------------------------------------------------------------
    | TAB — dipilah di browser, tanpa memuat ulang halaman
    |
    | Seluruh baris sudah ada di tabel; tab hanya menentukan baris mana yang
    | lolos saringan dan kolom mana yang diperlihatkan. Angka kartu untuk
    | ketiga tab ikut dikirim dari server, jadi tidak ada permintaan baru.
    |--------------------------------------------------------------------------
    */

    $.fn.dataTable.ext.search.push(function (settings, searchData, index) {
        if (settings.nTable.id !== 'picTable') return true;
        if (activeTab === 'db') return true;

        var row = settings.aoData[index].nTr;
        if (!row) return true;

        var jenis = row.getAttribute('data-typekey') || '';
        return activeTab === 'internal' ? jenis === 'internal' : jenis !== 'internal';
    });

    function applyTab(tab, pushUrl) {
        if (['internal', 'external', 'db'].indexOf(tab) === -1) tab = 'internal';
        activeTab = tab;

        $('.pic-tab').removeClass('active')
            .filter('[data-pictab="' + tab + '"]').addClass('active');

        // Kolom disembunyikan, bukan dihapus dari markup: indeks kolom lain
        // (dipakai columnDefs & data-order) jadi tidak ikut bergeser.
        // Type mubazir di tab Internal; di Database rincian kontak dilepas
        // supaya barisnya tetap terbaca.
        picTable.column(1).visible(tab !== 'internal', false);
        [4, 5].forEach(function (i) { picTable.column(i).visible(tab !== 'db', false); });
        // Token & Nominal hanya berlaku untuk PIC internal.
        [7, 8].forEach(function (i) { picTable.column(i).visible(tab === 'internal', false); });
        picTable.columns.adjust();

        // Filter Type tidak menyaring apa pun di tab Internal. Sisa pilihan dari
        // tab sebelumnya ikut dibersihkan, supaya tabel tidak tampak kosong
        // karena saringan yang tombolnya sedang tidak terlihat.
        var $tombolType = $('#picRowFilters .tcf-row-btn').eq(0);
        if (tab === 'internal') {
            if ($tombolType.hasClass('tcf-row-active')) TesysColumnFilter.clearAll('#picTable');
            $tombolType.hide();
        } else {
            $tombolType.show();
        }

        $('#picUnlinkedWrap').toggle(tab !== 'external');

        picTable.page('first').draw();

        // Alamat ikut diperbarui tanpa memuat ulang, jadi menyegarkan halaman
        // atau menyalin tautannya tetap membuka tab yang sama.
        if (pushUrl && window.history && history.replaceState) {
            var u = new URL(window.location.href);
            u.searchParams.set('tab', tab);
            history.replaceState(null, '', u.toString());
        }
    }

    $(document).on('click', '.pic-tab', function (e) {
        // Ctrl/cmd/tengah dibiarkan membuka tautan seperti biasa.
        if (e.which === 2 || e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        applyTab($(this).data('pictab'), true);
    });

    applyTab(activeTab, false);

    $('#refreshTable').on('click', function () {
        $('#globalSearch').val('');
        picTable.search('').page('first').draw();
        location.reload();
    });

    } catch (err) {
        console.error('Gagal inisialisasi DataTable PIC:', err);
    }

});

</script>
@endpush
