@extends('layouts.app')

@section('title', 'PIC')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">PIC Master</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">PIC</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total PIC', $summary['total'], 'primary', 'fas fa-id-badge'],
            ['PIC Customer', $summary['customer'], 'info', 'fas fa-building'],
            ['PIC Internal', $summary['internal'], 'warning', 'fas fa-users'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
            ['Internal Tanpa Akun', $summary['unlinked'], $summary['unlinked'] > 0 ? 'danger' : 'secondary', 'fas fa-user-slash'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-id-badge mr-1"></i> Daftar PIC</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- CARD BODY -->
            <div class="card-body">

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
                        @if(($filters['unlinked'] ?? false))
                            <a href="{{ url('/master-pic') }}" class="btn btn-danger btn-sm">
                                <i class="fas fa-user-slash mr-1"></i> Belum Terhubung
                                <span class="badge badge-light ml-1">{{ $summary['unlinked'] }}</span>
                                <i class="fas fa-times ml-1" title="Hapus saringan"></i>
                            </a>
                        @elseif($summary['unlinked'] > 0)
                            <a href="{{ url('/master-pic?unlinked=1') }}" class="btn btn-outline-danger btn-sm"
                               title="Tampilkan hanya PIC internal yang belum terhubung ke akun User">
                                <i class="fas fa-user-slash mr-1"></i> Belum Terhubung
                                <span class="badge badge-danger ml-1">{{ $summary['unlinked'] }}</span>
                            </a>
                        @endif
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                            <i class="fas fa-plus mr-1"></i> Tambah PIC
                        </button>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="picTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="4%"  class="all">No</th>
                                <th scope="col" width="10%" class="all">Type</th>
                                <th scope="col" width="16%" class="all">Nama PIC</th>
                                <th scope="col" width="12%">Jabatan</th>
                                <th scope="col" width="12%">Terhubung Ke</th>
                                <th scope="col" width="12%">Phone</th>
                                <th scope="col" width="12%">Email</th>
                                <th scope="col" width="9%">Token</th>
                                <th scope="col" width="9%" class="text-right">Nominal</th>
                                <th scope="col" width="7%">Status</th>
                                <th scope="col" width="10%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pics as $pic)
                            <tr data-type="{{ $pic->type == 'customer' ? 'Customer' : 'Internal' }}" data-status="{{ $pic->status }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    @if($pic->type == 'customer')
                                        <span class="badge badge-primary px-2 py-1">Customer</span>
                                    @else
                                        <span class="badge badge-secondary px-2 py-1">Internal</span>
                                    @endif
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
                                <td>
                                    {{-- M-02 — kaitan ke orang sebenarnya. PIC internal
                                         tanpa kaitan ditandai, karena itulah data yang
                                         masih perlu dirapikan. --}}
                                    @if($pic->user)
                                        <span class="badge badge-info px-2 py-1" style="font-size:10px;">
                                            <i class="fas fa-user mr-1"></i>{{ strtoupper($pic->user->username) }}
                                        </span>
                                    @elseif($pic->type === 'internal')
                                        <span class="badge badge-warning px-2 py-1" style="font-size:10px;" title="PIC internal belum terhubung ke akun User">
                                            Belum terhubung
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                    @if($pic->employee)
                                        <div class="text-muted" style="font-size:10px;margin-top:2px;">
                                            NIP {{ $pic->employee->employee_number }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $pic->phone ?? '-' }}</td>
                                <td>{{ $pic->email ?? '-' }}</td>
                                {{-- Hanya dibaca di sini. Mengubahnya lewat modal Edit,
                                     sejalan dengan kolom lain di baris ini. --}}
                                <td>{{ $pic->token ?: '-' }}</td>
                                <td class="text-right" data-order="{{ $pic->nominal !== null ? (float) $pic->nominal : -1 }}">
                                    @if($pic->nominal !== null)
                                        {{-- Pemisah ribuan titik, desimal koma. Desimal hanya
                                             ditulis kalau memang ada — ",00" di setiap baris
                                             menambah lebar kolom tanpa menambah keterangan. --}}
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

            </div>
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
                                    <option value="internal">Internal</option>
                                    <option value="customer">Customer</option>
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

                        {{-- BANK & REKENING — milik PIC-nya sendiri, bukan rekening
                             beneficiary yang ia tangani. --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Bank</label>
                                <select name="bank_id" id="tambahBank" class="form-control">
                                    <option value="">— Pilih Bank —</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">No. Rekening</label>
                                <input type="text" name="account_number" id="tambahAccountNumber"
                                       class="form-control"
                                       placeholder="Masukkan nomor rekening"
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

                        <!-- TOKEN -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Token</label>
                                <input type="text" name="token" id="tambahToken"
                                       class="form-control" maxlength="100"
                                       placeholder="Masukkan token"
                                       autocomplete="off">
                            </div>
                        </div>

                        <!-- NOMINAL -->
                        <div class="col-md-6">
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
                                        <option value="{{ $u->id }}">{{ strtoupper($u->username) }} — {{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Isi untuk PIC internal supaya datanya menyatu dengan Team.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Data Karyawan</label>
                                <select name="employee_id" id="tambahEmployee" class="form-control">
                                    <option value="">— Tidak terhubung —</option>
                                    @foreach($employees as $e)
                                        <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_number }})</option>
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
                                        <option value="internal">Internal</option>
                                        <option value="customer">Customer</option>
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

                            {{-- BANK & REKENING milik PIC-nya sendiri. --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Bank</label>
                                    <select name="bank_id" id="editBank" class="form-control">
                                        <option value="">— Pilih Bank —</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">No. Rekening</label>
                                    <input type="text" name="account_number" id="editAccountNumber"
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

                            <!-- TOKEN -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Token</label>
                                    <input type="text" name="token" id="editToken"
                                           class="form-control" maxlength="100"
                                           autocomplete="off">
                                </div>
                            </div>

                            <!-- NOMINAL -->
                            <div class="col-md-6">
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
                                            <option value="{{ $u->id }}">{{ strtoupper($u->username) }} — {{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- DATA KARYAWAN (M-02) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Data Karyawan</label>
                                    <select name="employee_id" id="editEmployee" class="form-control">
                                        <option value="">— Tidak terhubung —</option>
                                        @foreach($employees as $e)
                                            <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_number }})</option>
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
<style>

.action-header-buttons { display:flex; gap:8px; }
.card-header .btn      { border-radius:var(--radius-sm); font-weight:600; }
.font-weight-600       { font-weight:600; }

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

/* TABLE */
#picTable            { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column      { white-space:nowrap !important; min-width:80px !important; }

/* Kolom uang dibaca dengan membandingkan digitnya, jadi angkanya rata kanan. */
.money-input { font-variant-numeric: tabular-nums; }
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

    /*
    |--------------------------------------------------------------------------
    | MODAL TAMBAH & EDIT — dibind PALING AWAL, supaya tetap responsif
    | walaupun ada error di inisialisasi DataTable/TomSelect di bawah.
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '#btnTambah', function () {
        $('#formTambah')[0].reset();
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
            $('#editBank').val(res.bank_id ?? '');
            $('#editAccountNumber').val(res.account_number ?? '');
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
    | NOMINAL — pemisah ribuan
    |
    | Kotaknya bertipe text, bukan number: input number tidak bisa menampilkan
    | titik pemisah, dan tampilannya ikut berubah-ubah mengikuti locale browser
    | ("100000000,00" pada tangkapan layar sebelumnya). Yang dikirim ke server
    | tetap angka polos — lihat handler submit di bawah.
    |--------------------------------------------------------------------------
    */

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
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, -1] },
            { responsivePriority: 2,   targets: [4] },
            { responsivePriority: 3,   targets: [7, 8] },
            { responsivePriority: 100, targets: [2, 3, 5, 6, 9] }
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

    $('#picTable_filter').hide();

    $('#customSearch').html(
        '<input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">'
    );
    $('#globalSearch').on('keyup', function () { picTable.search(this.value).draw(); });

    TesysColumnFilter.initRowFilters('#picTable', '#picRowFilters', [
        { key: 'type', label: 'Type' },
        { key: 'status', label: 'Status' },
    ]);

    $('#btnClearFilterPic').on('click', function () {
        TesysColumnFilter.clearAll('#picTable');
    });

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
