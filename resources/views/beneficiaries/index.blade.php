@extends('layouts.app')

@section('title', 'Beneficiary')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Beneficiary Master</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Beneficiary</li>
            </ol>
        </div>

        {{-- Angkanya dirender server untuk keadaan awal, lalu dihitung ulang di
             peramban tiap tabel digambar supaya selalu sama dengan baris yang
             benar-benar tampil (lihat hitungUlangKartu). Urutan kartu di bawah
             ini mengikat: JS menunjuknya per posisi. --}}
        <div id="benKpi">
            <x-stat-cards :cards="[
                ['Total Beneficiary', $summary['total'], 'primary', 'fas fa-user-friends'],
                ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
                ['Internal', $summary['internal'], 'info', 'fas fa-building'],
                ['External', $summary['external'], 'warning', 'fas fa-truck'],
                ['Billing', $summary['billing'], 'secondary', 'fas fa-file-invoice'],
            ]" />
        </div>

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-user-friends mr-1"></i> List Beneficiary</h3>
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
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterBen" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        {{-- Kategori, Bank, Status — semuanya saringan ceklis di sisi
                             peramban, jadi memilihnya langsung terasa tanpa memuat
                             ulang halaman. <select> server-side yang dulu berdiri
                             sendiri di sebelah kanan sudah dihapus: dua kendali untuk
                             satu hal bisa saling bertentangan.

                             Kartu ringkasan di atas ikut dihitung ulang setiap tabel
                             digambar (lihat hitungUlangKartu di bawah) — tanpa itu,
                             tabel menampilkan 1 baris sementara kartunya tetap
                             menulis 778. --}}
                        <div id="benRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        {{-- Bank Master dijaga 'role:superadmin,admin,guest' di routes/web.php,
                             bukan 'menu:beneficiaries'. Tanpa penjaga ini Manager — yang kini
                             boleh membuka halaman ini — melihat tombol yang pasti 403. --}}
                        @if($canManage)
                        <a href="{{ url('/bank') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-university mr-1"></i> Bank Master
                        </a>
                        @endif
                        {{-- Menambah terbuka untuk semua role yang lolos 'menu:beneficiaries'
                             (termasuk Manager) — tombol ini sengaja TIDAK digerbang. --}}
                        <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                            <i class="fas fa-plus mr-1"></i> Tambah Beneficiary
                        </button>
                    </div>
                </div>

                {{-- HASIL PENCARIAN PER PIC
                     Muncul menggantikan tabel saat kata pencarian cocok dengan
                     nama PIC. Mencari "RANI FEBRIANDINI" dulu memuntahkan 18
                     baris beneficiary yang kolom PIC-nya sama semua — yang
                     dicari orangnya, bukan 18 penerima dana itu satu per satu.
                     Anggotanya dibuka lewat tombol View. --}}
                <div id="picResultWrap" style="display:none;">
                    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:8px;">
                        <h3 class="m-0" style="font-size:14px;font-weight:700;color:var(--text);">
                            <i class="fas fa-user-tie mr-1"></i> Hasil PIC
                        </h3>
                        <span class="badge badge-secondary" id="picResultCount">0</span>
                    </div>
                    {{-- Tombol "kembali ke semua beneficiary" sengaja tidak ada di
                         sini: tombol Clear Filter (ikon penghapus) di toolbar
                         sudah melakukan persis itu — mengosongkan pencarian dan
                         menutup panel ini — jadi menambah tombol kedua di sini
                         hanya mengulang yang sudah ada. --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:4%;">No</th>
                                    <th scope="col">PIC</th>
                                    <th scope="col" style="width:12%;">Tipe</th>
                                    <th scope="col" style="width:20%;">Kontak</th>
                                    <th scope="col" class="text-center" style="width:12%;">Beneficiary</th>
                                    <th scope="col" class="text-center action-column" style="width:12%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="picResultBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive" id="beneficiaryTableWrap">
                    <table id="beneficiaryTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="4%"  class="all">No</th>
                                <th scope="col" width="11%" class="all">Nickname</th>
                                <th scope="col" width="19%">Complete Name</th>
                                <th scope="col" width="10%">Kategori</th>
                                <th scope="col" width="12%">PIC</th>
                                <th scope="col" width="10%">Bank</th>
                                <th scope="col" width="13%">Account Number</th>
                                <th scope="col" width="11%">Kontak</th>
                                <th scope="col" width="8%">Status</th>
                                <th scope="col" width="10%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($beneficiaries as $beneficiary)
                            <tr data-bank="{{ $beneficiary->bank->name ?? '' }}"
                                data-status="{{ $beneficiary->status }}"
                                data-category="{{ $beneficiary->category }}">
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $beneficiary->nickname }}</strong></td>
                                <td>{{ $beneficiary->complete_name }}</td>
                                <td>
                                    @php
                                        $catBadge = ['INTERNAL' => 'badge-primary', 'EXTERNAL' => 'badge-warning', 'BILLING' => 'badge-secondary'][$beneficiary->category] ?? 'badge-secondary';
                                    @endphp
                                    <span class="badge {{ $catBadge }} px-2 py-1">{{ $beneficiary->category }}</span>
                                </td>
                                <td>
                                    @if($beneficiary->pic)
                                        {{ $beneficiary->pic->pic }}
                                        @if($beneficiary->pic->position)
                                            <div class="text-muted" style="font-size:11px;">{{ $beneficiary->pic->position }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-info px-2 py-1">
                                        {{ $beneficiary->bank->name ?? '-' }}
                                    </span>
                                </td>
                                <td>{{ $beneficiary->account_number ?? '-' }}</td>
                                <td>{{ $beneficiary->contact ?? '-' }}</td>
                                <td>
                                    @if($beneficiary->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>
                                {{-- Kolomnya tetap dirender walau kosong: columnDefs DataTable
                                     di bawah menunjuk kolom terakhir lewat targets:[-1], jadi
                                     jumlah kolom <td> harus sama untuk setiap role. --}}
                                <td class="text-center action-column">
                                    @if($canManage)
                                    <div class="action-buttons ts-actions">

                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $beneficiary->id }}"
                                                title="Edit" aria-label="Edit {{ $beneficiary->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        {{-- Hapus ditampilkan langsung, bukan disembunyikan di
                                             balik menu "aksi lain": isinya memang cuma satu
                                             perintah, jadi menunya hanya menambah satu klik
                                             tanpa menyembunyikan apa pun. --}}
                                        <form action="{{ url('/beneficiaries/' . $beneficiary->id) }}"
                                              method="POST" class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger"
                                                    title="Hapus" aria-label="Hapus {{ $beneficiary->nickname }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                            </button>
                                        </form>

                                    </div>
                                    @else
                                    <span class="text-muted" title="Hanya Superadmin yang bisa mengubah atau menghapus">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10">
                                    <x-empty title="Belum ada data beneficiary" icon="fas fa-folder-open" compact />
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
<!-- MODAL TAMBAH BENEFICIARY                                          -->
<!-- ================================================================ -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-user-friends mr-2" style="color:var(--accent);"></i>
                    Tambah Beneficiary
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="{{ url('/beneficiaries') }}" method="POST" id="formTambah">
                @csrf

                <div class="modal-body" style="padding:28px 32px;">
                    <div class="row">

                        <!-- NICKNAME -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Nickname <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nickname" id="tambahNickname"
                                       class="form-control"
                                       placeholder="Masukkan nickname"
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                            </div>
                        </div>

                        <!-- COMPLETE NAME -->
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Complete Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="complete_name" id="tambahCompleteName"
                                       class="form-control"
                                       placeholder="Masukkan nama lengkap"
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                            </div>
                        </div>

                        <!-- BANK -->
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

                        <!-- ACCOUNT NUMBER -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Account Number</label>
                                <input type="text" name="account_number" id="tambahAccountNumber"
                                       class="form-control"
                                       placeholder="Masukkan nomor rekening"
                                       autocomplete="off">
                            </div>
                        </div>

                        <!-- KATEGORI (MN-02) -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Kategori <span class="text-danger">*</span>
                                </label>
                                <select name="category" id="tambahCategory" class="form-control" required>
                                    @foreach(\App\Models\Beneficiary::CATEGORIES as $cat)
                                        <option value="{{ $cat }}" @selected($cat === 'EXTERNAL')>{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- PIC (MN-02) -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">PIC</label>
                                <select name="pic_id" id="tambahPic" class="form-control">
                                    <option value="">— Pilih PIC —</option>
                                    @foreach($pics as $pic)
                                        <option value="{{ $pic->id }}">{{ $pic->pic }}@if($pic->position) — {{ $pic->position }}@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- CONTACT — nomor telepon / kontak yang bisa dihubungi.
                             Labelnya dulu tertulis "Email" padahal kolomnya
                             `contact`; tabel ini memang tidak punya kolom email. -->
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Kontak</label>
                                <input type="text" name="contact" id="tambahContact"
                                       class="form-control"
                                       placeholder="Nomor telepon / kontak"
                                       autocomplete="off">
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
<!-- MODAL EDIT BENEFICIARY                                            -->
<!-- ================================================================ -->
{{-- Tidak dirender sama sekali untuk role yang tidak berhak mengubah. Menyembunyikan
     tombolnya saja tidak cukup: formulir ini memuat nomor rekening & kontak, dan
     markup-nya tetap terbaca di source halaman. --}}
@if($canManage)
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-pen mr-2" style="color:var(--accent);"></i>
                    Edit Beneficiary
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

                            <!-- NICKNAME -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Nickname <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="nickname" id="editNickname"
                                           class="form-control"
                                           style="text-transform:uppercase;"
                                           autocomplete="off" required>
                                </div>
                            </div>

                            <!-- COMPLETE NAME -->
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Complete Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="complete_name" id="editCompleteName"
                                           class="form-control"
                                           style="text-transform:uppercase;"
                                           autocomplete="off" required>
                                </div>
                            </div>

                            <!-- BANK -->
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

                            <!-- ACCOUNT NUMBER -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Account Number</label>
                                    <input type="text" name="account_number" id="editAccountNumber"
                                           class="form-control" autocomplete="off">
                                </div>
                            </div>

                            <!-- KATEGORI (MN-02) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Kategori <span class="text-danger">*</span>
                                    </label>
                                    <select name="category" id="editCategory" class="form-control" required>
                                        @foreach(\App\Models\Beneficiary::CATEGORIES as $cat)
                                            <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- PIC (MN-02) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">PIC</label>
                                    <select name="pic_id" id="editPic" class="form-control">
                                        <option value="">— Pilih PIC —</option>
                                        @foreach($pics as $pic)
                                            <option value="{{ $pic->id }}">{{ $pic->pic }}@if($pic->position) — {{ $pic->position }}@endif</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- CONTACT -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Kontak</label>
                                    <input type="text" name="contact" id="editContact"
                                           class="form-control" autocomplete="off">
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
@endif

{{-- DETAIL PIC
     Isi satu PIC beserta seluruh beneficiary yang menunjuknya. Edit & hapus
     dilakukan dari sini supaya pengguna tidak perlu keluar dari hasil
     pencarian hanya untuk memperbaiki satu baris. --}}
{{-- Bentuknya mengikuti modal Detail Cost Request: panel ringkas GELAP penuh
     di kiri (judul, kode, dan seluruh keterangan PIC), ruang kerja terang di
     kanan. Kelas dm-body/dm-side/dm-head sudah global di tesys-ui.css dan
     tesys-ui-luxe.css; hanya tata letak dua kolomnya yang ditulis per halaman
     (lihat @push('styles')), sama seperti di budget-request & daily-transaction. --}}
<div class="modal fade" id="modalPicDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">

            {{-- Tanpa pita header: judulnya pindah ke kepala panel gelap, supaya
                 modal terbaca sebagai satu lembar. --}}
            <div class="modal-body dm-body" style="padding:0;background:var(--surface);">
                <div class="pic-modal-flex">

                    <div class="pic-sidebar dm-side">
                        <div class="dm-head">
                            <button type="button" class="close dm-close" data-dismiss="modal"
                                    aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                            <div id="picDetailCode" class="dm-code"></div>
                            <h5 class="dm-title" id="picDetailTitle">Detail PIC</h5>

                            <div class="dm-badges">
                                <span id="picDetailTypeBadge"></span>
                            </div>
                        </div>

                        <hr style="border-color:rgba(255,255,255,0.08);">

                        <div class="mb-2">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Jabatan</span>
                            <div id="picDetailPosition" class="text-white mt-1" style="font-size:12px;"></div>
                        </div>

                        {{-- Rekening milik PIC-nya sendiri. Nominalnya bukan angka
                             uang, tapi ini keterangan yang paling dicari di panel
                             ini — jadi diberi nada terang seperti baris nominal. --}}
                        <div class="mb-2">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">No. Rekening</span>
                            <div id="picDetailAccount" style="color:var(--accent);font-size:15px;font-weight:700;margin-top:4px;"></div>
                            <div id="picDetailBank" style="color:var(--text-faint);font-size:11px;"></div>
                        </div>

                        <div class="mb-2">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Telepon</span>
                            <div id="picDetailPhone" class="text-white mt-1" style="font-size:12px;"></div>
                        </div>

                        <div class="mb-2">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Email</span>
                            <div id="picDetailEmail" class="text-white mt-1" style="font-size:12px;overflow-wrap:anywhere;"></div>
                        </div>

                        <div class="mb-2">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Beneficiary</span>
                            <div id="picDetailTotal" class="text-white mt-1" style="font-size:12px;"></div>
                        </div>

                        <div class="pic-sidebar-spacer"></div>
                    </div>

                    <div class="pic-main-panel">
                        <div class="d-flex align-items-center flex-wrap mb-3" style="gap:8px;">
                            <h6 class="m-0" style="font-size:13px;font-weight:700;color:var(--text);">
                                <i class="fas fa-user-friends mr-1"></i> Beneficiary
                            </h6>
                            <span class="badge badge-secondary" id="picDetailCount">0</span>

                            @if($canManage)
                            {{-- Mengaitkan beneficiary yang SUDAH ADA ke PIC ini —
                                 BUKAN mendaftarkan penerima dana baru (itu tombol
                                 "Tambah Beneficiary" di toolbar utama). Ini mengubah
                                 kepemilikan PIC pada baris yang sudah terdaftar, jadi
                                 digerbang $canManage sama seperti Edit/Hapus/Pindah
                                 PIC lain di modal ini. --}}
                            <button type="button" id="btnPicTambahBeneficiary" class="btn btn-success btn-sm ml-auto">
                                <i class="fas fa-plus mr-1"></i> Tambahkan Beneficiary
                            </button>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered mb-0" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:4%;">No</th>
                                        <th scope="col">Nickname</th>
                                        <th scope="col">Complete Name</th>
                                        <th scope="col" style="width:12%;">Bank</th>
                                        <th scope="col" style="width:16%;">Account Number</th>
                                        <th scope="col" class="text-center" style="width:9%;">Status</th>
                                        @if($canManage)
                                        <th scope="col" class="text-center action-column" style="width:14%;">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody id="picDetailBody"></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

@if($canManage)
{{-- PINDAH PIC — satu beneficiary dipindahkan ke PIC lain tanpa membuka
     seluruh formulir Edit, yang setiap kolomnya ikut terkirim dan bisa
     berubah tanpa disengaja. --}}
<div class="modal fade" id="modalMovePic" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formMovePic" method="POST">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt mr-1"></i> Pindah PIC</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <p class="mb-3" style="font-size:12.5px;color:var(--text-dim);">
                        <strong id="movePicNama"></strong> akan dipindahkan ke PIC yang dipilih.
                        Data beneficiary-nya tidak berubah selain penanggung jawabnya.
                    </p>

                    <div class="form-group mb-0">
                        <label class="font-weight-600">PIC Tujuan</label>
                        <select name="pic_id" id="movePicTujuan" class="form-control">
                            {{-- Kosong berarti melepas baris ini dari PIC-nya, tanpa
                                 ikut melepas beneficiary lain di PIC yang sama. --}}
                            <option value="">— Tanpa PIC —</option>
                            @foreach($pics as $pic)
                                <option value="{{ $pic->id }}">{{ $pic->pic }}@if($pic->position) — {{ $pic->position }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-exchange-alt mr-1"></i> Pindahkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- TAMBAHKAN BENEFICIARY (yang sudah ada) KE PIC — kebalikan dari Pindah
     PIC: dari sisi PIC, kaitkan beberapa beneficiary yang SUDAH TERDAFTAR
     sekaligus. TIDAK membuat baris baru; itu tetap tugas tombol "Tambah
     Beneficiary" di toolbar utama. --}}
<div class="modal fade" id="modalPicAttach" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="formPicAttach" method="POST">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus mr-1"></i> Tambahkan Beneficiary ke PIC — <span id="picAttachName"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <p class="mb-2" style="font-size:12.5px;color:var(--text-dim);">
                        Pilih beneficiary yang sudah terdaftar untuk dikaitkan ke PIC ini.
                        Untuk mendaftarkan penerima dana baru, gunakan tombol
                        <strong>Tambah Beneficiary</strong> di halaman utama.
                    </p>

                    <input type="search" id="picAttachSearch" class="form-control form-control-sm mb-2"
                           placeholder="Cari nickname atau nama lengkap...">

                    <div id="picAttachList" style="max-height:320px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius-sm);padding:4px 8px;"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnPicAttachSubmit" class="btn btn-success" disabled>
                        <i class="fas fa-user-plus mr-1"></i> Tambahkan (<span id="picAttachCount">0</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('styles')
<style>

.action-header-buttons { display:flex; gap:8px; }
.card-header .btn      { border-radius:var(--radius-sm); font-weight:600; }
.font-weight-600       { font-weight:600; }

/* MODAL DETAIL PIC — dua kolom, panel gelap di kiri.
   Kelas dm-* (panel, kepala, badge) sudah global; yang tidak global hanya
   kerangka dua kolomnya, dan memang ditulis ulang per halaman — pola yang
   sama dipakai budget-request dan daily-transaction. */
/* Tinggi PASTI untuk modalnya, bukan sekadar batas atas.
   |
   | Panel gelap di kiri mewarisi tingginya lewat rantai height:100%, dan
   | rantai itu putus begitu salah satu induknya hanya punya max-height — atau
   | tinggi otomatis seperti sebelumnya. Akibatnya panelnya berhenti setinggi
   | isinya sendiri sementara daftar beneficiary di kanan terus memanjang,
   | menyisakan kertas putih di bawah panel.
   |
   | Angkanya disamakan dengan modal detail lain (.modal-dialog-xxl di
   | tesys-ui-luxe.css) supaya semua modal detail setinggi sama. */
#modalPicDetail .modal-dialog  { height: min(860px, calc(100vh - 64px)); margin: 1.75rem auto; }
#modalPicDetail .modal-content { height: 100%; max-height: 100%; }

/* .dm-side dibatasi max-height:88vh di tesys-ui.css. Batas itu dipasang untuk
   panel yang berdiri di modal setinggi isi; di sini induknya sudah bertinggi
   pasti, sehingga 88vh justru MEMOTONG panelnya lebih pendek dari modal dan
   menyisakan kertas putih di bawahnya. Dikembalikan ke tinggi induk — panel
   tetap menggulung sendiri kalau isinya lebih panjang. */
#modalPicDetail .dm-side { max-height: 100%; }

/* .dm-body bawaan (tesys-ui.css) sudah overflow-y:auto — dipakai modal
   SATU-KOLOM yang seluruh isinya boleh ikut tergulir. Di modal DUA-KOLOM ini
   yang boleh menggulir hanya sisi kanan (.pic-main-panel); kolom kiri harus
   diam di tempat. Membiarkan keduanya overflow:auto menghasilkan DUA
   scrollbar bertumpuk — satu di tepi modal, satu lagi di tepi tabel. */
#modalPicDetail .modal-body.dm-body { overflow: hidden; }

.pic-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.pic-sidebar {
    width:280px; flex:0 0 280px; background:var(--charcoal); padding:28px 20px;
    display:flex; flex-direction:column;
}
.pic-sidebar-spacer { flex:1 1 auto; }
.pic-main-panel     { flex:1 1 auto; padding:24px; min-width:0; }

/* Aturan global di tesys-ui-luxe.css menyebut .cr-/.bp-/.prj- satu per satu,
   jadi kerangka baru ini tidak ikut tersapu. Dua hal yang perlu ditiru:
   barisnya setinggi penuh (tanpa itu panel gelapnya berhenti di tengah dan
   menyisakan kertas putih), dan sisi kanannya yang menggulung sendiri supaya
   kepala PIC di kiri tetap terlihat saat daftarnya panjang. */
body.tesys-ui .modal-body.dm-body .pic-modal-flex { height:100%; min-height:100%; }
body.tesys-ui .modal-body.dm-body .pic-main-panel { overflow-y:auto; max-height:100%; }
@media (max-width:767.98px) {
    .pic-modal-flex { flex-direction:column; }
    .pic-sidebar    { width:100%; flex:0 0 auto; }
}

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
#beneficiaryTable   { width:100% !important; }
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
    #btnClearFilterBen { width:100%; }
    #benRowFilters { width:100%; }
    #benRowFilters .tcf-row-btn { width:100%; }
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


/*
|--------------------------------------------------------------------------
| SEARCHABLE SELECT (Bank & PIC di modal Tambah/Edit)
|--------------------------------------------------------------------------
| Select native tidak bisa dicari; daftar bank & PIC sudah ratusan baris.
| Select-nya tetap ada (tersembunyi) supaya nama field & submit form tidak
| berubah — lapisan ini hanya UI di atasnya.
*/
.ss-wrap { position:relative; }
.ss-wrap > select.ss-native { display:none !important; }
.ss-display {
    display:flex; align-items:center; justify-content:space-between;
    cursor:pointer; user-select:none; white-space:nowrap; overflow:hidden;
}
.ss-display .ss-label { overflow:hidden; text-overflow:ellipsis; }
.ss-display.ss-placeholder .ss-label { color:#9aa0a6; }
.ss-display .ss-caret { margin-left:8px; color:#6c757d; flex:0 0 auto; }
.ss-wrap.open .ss-display { border-color:#c0392b; box-shadow:0 0 0 .15rem rgba(192,57,43,.15); }
.ss-menu {
    display:none; position:fixed; z-index:2000;
    background:#fff; border:1px solid #e0e0e0; border-radius:6px;
    box-shadow:0 6px 18px rgba(0,0,0,.12); padding:8px;
}
.ss-menu.open { display:block; }
.ss-menu .ss-search { height:34px; font-size:13px; }
.ss-options { max-height:230px; overflow-y:auto; margin-top:6px; }
.ss-menu.ss-up { box-shadow:0 -6px 18px rgba(0,0,0,.12); }
.ss-opt { padding:7px 10px; font-size:13px; border-radius:4px; cursor:pointer; }
.ss-opt:hover, .ss-opt.hl { background:#f4f5f7; }
.ss-opt.active { background:#fdecea; color:#c0392b; font-weight:600; }
.ss-none { display:none; padding:10px; font-size:13px; color:#9aa0a6; text-align:center; }

</style>
@endpush

@push('scripts')
<script>

$(function () {

    /*
    |--------------------------------------------------------------------------
    | DATATABLE
    |--------------------------------------------------------------------------
    */

    let table = $('#beneficiaryTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, -1] },
            { responsivePriority: 2,   targets: [3] },
            { responsivePriority: 100, targets: [2, 4, 5, 6, 7, 8] }
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

    $('#beneficiaryTable_filter').hide();

    $('#customSearch').html(`
        <input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">
    `);
    /*
    |--------------------------------------------------------------------------
    | PENCARIAN — beneficiary, atau diciutkan per PIC
    |--------------------------------------------------------------------------
    | Kalau kata pencariannya cocok dengan nama PIC, yang ditampilkan adalah
    | PIC-nya (satu baris per orang), bukan puluhan beneficiary yang kolom
    | PIC-nya sama semua. Anggotanya dibuka lewat tombol View.
    |
    | Kembali ke daftar penuh memakai tombol Clear Filter yang sudah ada di
    | toolbar (lihat #btnClearFilterBen di bawah) — bukan tombol terpisah di
    | dalam panel ini.
    */
    var PIC_GROUPS = @json($picGroups);

    /* Data PIC berasal dari input pengguna (nama, jabatan, email), jadi tidak
       pernah disisipkan ke HTML tanpa dijinakkan lebih dulu. */
    function esc(nilai) {
        return $('<div>').text(nilai == null ? '' : String(nilai)).html();
    }

    function picCocok(term) {
        var t = $.trim(term).toLowerCase();
        // Satu huruf cocok dengan hampir semua nama — tabel akan berkedip
        // berganti mode di tiap ketukan pertama tanpa ada gunanya.
        if (t.length < 2) return [];

        return PIC_GROUPS.filter(function (g) {
            return String(g.pic || '').toLowerCase().indexOf(t) !== -1;
        });
    }

    function aksiPicHtml(g) {
        var html = '<div class="action-buttons ts-actions">'
            + '<button type="button" class="ts-action-primary is-neutral btn-pic-view" data-pic="' + g.id + '"'
            + ' title="Lihat beneficiary PIC ini" aria-label="Lihat beneficiary PIC ' + esc(g.pic) + '">'
            + '<i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">View</span></button>';

        @if($canManage)
        // Menghapus PIC-nya di Master PIC. Beneficiary-nya tidak ikut
        // terhapus, hanya kehilangan PIC — lihat
        // BeneficiaryController::destroyPic().
        html += '<form action="{{ url("/beneficiaries/pic") }}/' + g.id + '" method="POST"'
            + ' class="d-inline pic-destroy-form" data-pic-name="' + esc(g.pic) + '" data-total="' + g.total + '">'
            + '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
            + '<input type="hidden" name="_method" value="DELETE">'
            + '<button type="submit" class="ts-action-primary is-danger" title="Hapus seluruh data PIC ini"'
            + ' aria-label="Hapus PIC ' + esc(g.pic) + '">'
            + '<i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span></button>'
            + '</form>';
        @endif

        return html + '</div>';
    }

    function renderPicResult(groups) {
        var html = '';

        groups.forEach(function (g, i) {
            var tipeBadge = g.type === 'internal' ? 'badge-primary' : 'badge-warning';
            var kontak    = g.phone || g.email || '-';

            html += '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><strong>' + esc(g.pic) + '</strong>'
                    + (g.position ? '<div class="text-muted" style="font-size:11px;">' + esc(g.position) + '</div>' : '')
                + '</td>'
                + '<td><span class="badge ' + tipeBadge + ' px-2 py-1">' + esc(String(g.type).toUpperCase()) + '</span></td>'
                + '<td>' + esc(kontak) + '</td>'
                + '<td class="text-center"><span class="badge badge-info px-2 py-1">' + g.total + '</span></td>'
                + '<td class="text-center action-column">' + aksiPicHtml(g) + '</td>'
                + '</tr>';
        });

        $('#picResultBody').html(html);
        $('#picResultCount').text(groups.length);
    }

    function terapkanPencarian(term) {
        table.search(term).draw();

        var cocok = picCocok(term);

        if (cocok.length) {
            renderPicResult(cocok);
            $('#picResultWrap').show();
            $('#beneficiaryTableWrap').hide();
        } else {
            $('#picResultWrap').hide();
            $('#beneficiaryTableWrap').show();
        }
    }

    $('#globalSearch').on('keyup', function () {
        terapkanPencarian(this.value);
    });

    /* ── Detail satu PIC ── */

    function cariGroup(id) {
        return PIC_GROUPS.filter(function (g) { return String(g.id) === String(id); })[0];
    }

    /* Dipisah jadi fungsi bernama karena dipanggil dari dua tempat: tombol
       View di baris "Hasil PIC", dan modal Tambahkan Beneficiary yang
       membuka detail PIC yang sama lagi setelah ditutup tanpa submit. */
    function bukaDetailPic(picId) {
        var g = cariGroup(picId);
        if (!g) return;

        $('#picDetailCode').text('PIC-' + String(g.id).padStart(4, '0'));
        $('#picDetailTitle').text(g.pic);
        $('#picDetailTypeBadge').html(
            '<span class="badge ' + (g.type === 'internal' ? 'badge-primary' : 'badge-warning') + '">'
            + esc(String(g.type).toUpperCase()) + '</span>'
        );

        $('#picDetailPosition').text(g.position || '-');
        // Rekening PIC-nya sendiri — yang sebelumnya tidak ada di panel ini.
        $('#picDetailAccount').text(g.account_number || '-');
        $('#picDetailBank').text(g.bank || (g.account_number ? '' : 'Belum diisi di Master PIC'));
        $('#picDetailPhone').text(g.phone || '-');
        $('#picDetailEmail').text(g.email || '-');
        $('#picDetailTotal').text(g.total + ' beneficiary');

        var badgeKategori = { INTERNAL: 'badge-primary', EXTERNAL: 'badge-warning', BILLING: 'badge-secondary' };
        var rows = '';

        g.items.forEach(function (b, i) {
            rows += '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><strong>' + esc(b.nickname) + '</strong>'
                    + ' <span class="badge ' + (badgeKategori[b.category] || 'badge-secondary') + '" style="font-size:9px;">'
                    + esc(b.category) + '</span></td>'
                + '<td>' + esc(b.complete_name) + '</td>'
                + '<td>' + esc(b.bank) + '</td>'
                + '<td>' + esc(b.account_number) + '</td>'
                + '<td class="text-center"><span class="badge ' + (b.status === 'ACTIVE' ? 'badge-success' : 'badge-danger') + '">'
                    + esc(b.status) + '</span></td>';

            @if($canManage)
            rows += '<td class="text-center action-column"><div class="action-buttons ts-actions">'
                + '<button type="button" class="ts-action-primary is-neutral btn-pic-edit" data-id="' + b.id + '"'
                + ' title="Edit" aria-label="Edit ' + esc(b.nickname) + '">'
                + '<i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span></button>'
                + '<button type="button" class="ts-action-primary is-neutral btn-move-pic" data-id="' + b.id + '"'
                + ' data-nama="' + esc(b.nickname) + '" data-pic="' + g.id + '"'
                + ' title="Pindah ke PIC lain" aria-label="Pindah ' + esc(b.nickname) + ' ke PIC lain">'
                + '<i class="fas fa-exchange-alt" aria-hidden="true"></i><span class="ts-al">Pindah</span></button>'
                + '<form action="{{ url("/beneficiaries") }}/' + b.id + '" method="POST" class="d-inline delete-form">'
                + '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
                + '<input type="hidden" name="_method" value="DELETE">'
                + '<button type="submit" class="ts-action-primary is-danger" title="Hapus"'
                + ' aria-label="Hapus ' + esc(b.nickname) + '">'
                + '<i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span></button>'
                + '</form>'
                + '</div></td>';
            @endif

            rows += '</tr>';
        });

        $('#picDetailBody').html(rows);
        $('#picDetailCount').text(g.total);
        // Diingat di elemen modalnya sendiri, dipakai tombol Tambahkan
        // Beneficiary di bawah untuk tahu PIC mana yang sedang dibuka.
        $('#modalPicDetail').data('pic-id', g.id).modal('show');
    }

    $(document).on('click', '.btn-pic-view', function () {
        bukaDetailPic($(this).data('pic'));
    });

    @if($canManage)
    /*
    |--------------------------------------------------------------------------
    | TAMBAHKAN BENEFICIARY (yang sudah ada) KE PIC
    |--------------------------------------------------------------------------
    | Kebalikan dari Pindah PIC: di sana satu beneficiary dipindah dari sisi
    | barisnya sendiri; di sini beberapa beneficiary yang SUDAH TERDAFTAR
    | ditarik masuk sekaligus dari sisi PIC-nya. TIDAK membuat baris baru —
    | untuk mendaftarkan penerima dana baru, tombolnya ada di toolbar utama
    | (#btnTambah / modal Tambah Beneficiary), bukan di sini.
    */
    var BENEFICIARY_PICKER = @json($beneficiaryPicker);

    function renderPicAttachList() {
        var picId = $('#formPicAttach').data('pic-id');
        var term  = $.trim($('#picAttachSearch').val()).toLowerCase();

        var items = BENEFICIARY_PICKER.filter(function (b) {
            // Yang sudah menjadi anggota PIC ini tidak perlu ditawarkan lagi.
            if (String(b.pic_id) === String(picId)) return false;
            if (!term) return true;
            return (b.nickname || '').toLowerCase().indexOf(term) !== -1
                || (b.complete_name || '').toLowerCase().indexOf(term) !== -1;
        });

        var html = '';
        // Dibatasi 200 baris per render — daftar penuhnya bisa mencapai ribuan
        // baris, dan mengetik satu-dua huruf sudah cukup mempersempitnya.
        items.slice(0, 200).forEach(function (b) {
            html += '<label class="d-flex align-items-center picAttachRow" style="gap:8px;padding:6px 4px;border-bottom:1px solid var(--border);cursor:pointer;">'
                + '<input type="checkbox" class="picAttachCheck" value="' + b.id + '">'
                + '<span class="flex-grow-1"><strong>' + esc(b.nickname) + '</strong> '
                    + '<span class="text-muted" style="font-size:11px;">' + esc(b.complete_name) + '</span></span>'
                + (b.pic_name
                    ? '<span class="badge badge-light border" style="font-size:10px;" title="Akan dipindah dari PIC ini">Saat ini: ' + esc(b.pic_name) + '</span>'
                    : '')
                + '</label>';
        });

        if (!items.length) {
            html = '<div class="text-muted text-center py-4">'
                + (term ? 'Tidak ada yang cocok.' : 'Semua beneficiary sudah menjadi anggota PIC ini.')
                + '</div>';
        } else if (items.length > 200) {
            html += '<div class="text-muted text-center py-2" style="font-size:11px;">'
                + 'Menampilkan 200 dari ' + items.length + ' hasil — persempit dengan mengetik.</div>';
        }

        $('#picAttachList').html(html);
        updatePicAttachCount();
    }

    function updatePicAttachCount() {
        var n = $('.picAttachCheck:checked').length;
        $('#picAttachCount').text(n);
        $('#btnPicAttachSubmit').prop('disabled', n === 0);
    }

    /* Modal detailnya ditutup lebih dulu — pola yang sama dengan tombol Edit
       & Pindah PIC: dua modal Bootstrap bertumpuk menyisakan backdrop yang
       tidak bisa diklik. */
    $('#btnPicTambahBeneficiary').on('click', function () {
        var g = cariGroup($('#modalPicDetail').data('pic-id'));
        if (!g) return;

        $('#modalPicDetail').one('hidden.bs.modal', function () {
            $('#picAttachName').text(g.pic);
            $('#formPicAttach')
                .data('pic-id', g.id)
                .attr('action', '{{ url("/beneficiaries/pic") }}/' + g.id + '/attach')
                .find('input[name="beneficiary_ids[]"]').remove();
            $('#picAttachSearch').val('');
            renderPicAttachList();
            $('#modalPicAttach').modal('show');
            $('#modalPicAttach').one('shown.bs.modal', function () {
                $('#picAttachSearch').focus();
            });
        }).modal('hide');
    });

    $('#picAttachSearch').on('input', renderPicAttachList);
    $(document).on('change', '.picAttachCheck', updatePicAttachCount);

    // Batal cukup menutup modal — sama seperti Edit & Pindah PIC, yang juga
    // tidak membuka ulang detail PIC setelah dibatalkan.
    $('#formPicAttach').on('submit', function (e) {
        var ids = $('.picAttachCheck:checked').map(function () { return this.value; }).get();
        if (!ids.length) { e.preventDefault(); return; }

        var $f = $(this);
        $f.find('input[name="beneficiary_ids[]"]').remove();
        ids.forEach(function (id) {
            $f.append('<input type="hidden" name="beneficiary_ids[]" value="' + id + '">');
        });
        // Dibiarkan lanjut sebagai submit sungguhan — full page POST lalu
        // redirect kembali ke /beneficiaries dengan pesan sukses.
    });
    @endif

    @if($canManage)
    $(document).on('submit', '.pic-destroy-form', function (e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'Hapus PIC ' + $(form).data('pic-name') + '?',
            html: 'Seluruh data PIC ini dihapus dari Master PIC dan <b>tidak dapat dikembalikan</b>.'
                + '<br><br><b>' + $(form).data('total') + '</b> beneficiary tidak ikut terhapus, '
                + 'tapi kolom PIC-nya menjadi kosong.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#9a3030',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) form.submit(); });
    });

    /* Pindah PIC — modal kecil berisi satu pilihan, dibuka dari dalam modal
       Detail PIC. Modalnya ditutup lebih dulu dengan alasan yang sama seperti
       tombol Edit: dua modal Bootstrap bertumpuk menyisakan backdrop mati. */
    $(document).on('click', '.btn-move-pic', function () {
        var id   = $(this).data('id');
        var nama = $(this).data('nama');
        var asal = $(this).data('pic');

        $('#modalPicDetail').one('hidden.bs.modal', function () {
            $('#formMovePic').attr('action', '{{ url("/beneficiaries") }}/' + id + '/move-pic');
            $('#movePicNama').text(nama);
            // Dimulai dari PIC saat ini supaya terlihat sedang pindah dari mana.
            $('#movePicTujuan').val(String(asal)).trigger('change');
            $('#modalMovePic').modal('show');
        }).modal('hide');
    });
    @endif

    /* Kategori & Status memakai daftar pilihan TETAP, bukan disimpulkan dari
       baris yang sedang tampil. Himpunan nilainya memang sudah pasti, dan
       menyimpulkannya dari data membuat BILLING tidak pernah muncul justru
       ketika belum ada satu pun barisnya — saat itulah seseorang ingin
       memastikan memang belum ada.

       Bank tetap disimpulkan dari data: daftar banknya memang tumbuh, dan
       kotak cari di situ tetap berguna. Kategori & Status sengaja
       search:false — pilihannya cuma 3 dan 2, kotak cari di situ tidak
       menyaring apa-apa, hanya menambah satu klik sebelum pilihannya
       terlihat. */
    TesysColumnFilter.initRowFilters('#beneficiaryTable', '#benRowFilters', [
        { key: 'category', label: 'Kategori', options: @json(\App\Models\Beneficiary::CATEGORIES), search: false },
        { key: 'bank',     label: 'Bank' },
        { key: 'status',   label: 'Status',   options: ['ACTIVE', 'INACTIVE'], search: false },
    ]);

    /*
    |--------------------------------------------------------------------------
    | KARTU RINGKASAN MENGIKUTI TABEL
    |--------------------------------------------------------------------------
    | Penyaringan terjadi di sisi peramban supaya terasa seketika, tapi kartu
    | di atas dirender server dari SELURUH data. Tanpa perhitungan ulang ini,
    | menyaring INTERNAL menyisakan 1 baris di tabel sementara kartunya tetap
    | menulis 778 — dua angka untuk himpunan yang sama.
    |
    | Ikut berlaku untuk kotak pencarian dan saringan Bank, yang dulu memang
    | tidak pernah menyentuh kartu sama sekali.
    */
    var $nilaiKartu = $('#benKpi .ts-kpi-value');

    function hitungUlangKartu() {
        var total = 0, aktif = 0, internal = 0, eksternal = 0, billing = 0;

        table.rows({ search: 'applied' }).nodes().each(function (tr) {
            total++;
            if (tr.getAttribute('data-status') === 'ACTIVE') aktif++;

            switch (tr.getAttribute('data-category')) {
                case 'INTERNAL': internal++; break;
                case 'EXTERNAL': eksternal++; break;
                case 'BILLING':  billing++;  break;
            }
        });

        // Urutannya mengikuti kartu KPI di atas: Total, Active, Internal,
        // External, Billing. Komponen kartunya dipakai 20+ halaman dan bentuk
        // prop-nya sengaja tidak diubah, jadi kartunya ditunjuk per posisi.
        //
        // (Jangan menulis nama komponennya dalam bentuk tag di komentar ini:
        //  Blade tidak peduli itu berada di dalam komentar JavaScript, ia
        //  tetap merendernya sebagai komponen di tengah berkas skrip.)
        [total, aktif, internal, eksternal, billing].forEach(function (n, i) {
            $nilaiKartu.eq(i).text(n);
        });
    }

    table.on('draw', hitungUlangKartu);
    hitungUlangKartu();

    $('#btnClearFilterBen').on('click', function () {
        TesysColumnFilter.clearAll('#beneficiaryTable');
        $('#globalSearch').val('');
        /* Mengosongkan kotaknya saja tidak cukup: kata kunci yang sudah
           terlanjur masuk ke DataTables tetap menyaring baris, sehingga
           tombolnya terlihat "tidak bekerja" — nama hilang tapi tabel masih
           memperlihatkan hasil pencarian tadi. Lewat terapkanPencarian('')
           pencariannya dikosongkan, tabel digambar ulang, dan panel hasil PIC
           ikut ditutup kalau sedang tampil. */
        terapkanPencarian('');
        // Filter server-side ikut dibersihkan — kalau tidak, tombol ini terasa
        // "tidak bekerja" saat kategori/status sedang aktif di URL.
        if (window.location.search) window.location = '{{ url("/beneficiaries") }}';
    });

    $('#refreshTable').on('click', function () {
        $('#globalSearch').val('');
        table.search('').page('first').draw();
        location.reload();
    });

    /* Didelegasikan ke document: form hapus juga dibuat belakangan di dalam
       modal Detail PIC, dan pengikatan langsung hanya mengenai baris yang
       sudah ada saat halaman dimuat — di modal itu konfirmasinya akan
       terlewat dan data langsung terhapus sekali klik. */
    $(document).on('submit', '.delete-form', function (e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Hapus Beneficiary?',
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
    | MODAL TAMBAH
    |--------------------------------------------------------------------------
    */

    $('#btnTambah').on('click', function () {
        $('#formTambah')[0].reset();
        $('#tambahCategory').val('EXTERNAL');
        $('#modalTambah').modal('show');
        $('#modalTambah').one('shown.bs.modal', function () {
            $('#tambahNickname').focus();
        });
    });

    /*
    |--------------------------------------------------------------------------
    | SEARCHABLE SELECT — Bank & PIC
    |--------------------------------------------------------------------------
    | Membungkus <select> native jadi dropdown ber-kolom-cari. Select aslinya
    | tetap dipakai sebagai sumber nilai (name & submit tidak berubah), jadi
    | kode lain cukup .val(...).trigger('change') untuk mengisinya.
    */
    function initSearchableSelect(selector, placeholderCari) {
        var $sel = $(selector);
        if (!$sel.length || $sel.data('ssInit')) return;
        $sel.data('ssInit', true);

        var kosongTeks = $sel.find('option[value=""]').first().text().trim() || '— Pilih —';

        var $wrap = $('<div class="ss-wrap"></div>');
        $sel.before($wrap);
        $wrap.append($sel.addClass('ss-native'));

        var $disp = $('<div class="ss-display form-control" tabindex="0">' +
                          '<span class="ss-label"></span>' +
                          '<i class="fas fa-caret-down ss-caret"></i>' +
                      '</div>');
        var $menu = $('<div class="ss-menu">' +
                          '<input type="text" class="ss-search form-control" autocomplete="off">' +
                          '<div class="ss-options"></div>' +
                          '<div class="ss-none">Tidak ada hasil</div>' +
                      '</div>');
        $menu.find('.ss-search').attr('placeholder', placeholderCari);
        $wrap.append($disp);
        /* Panelnya dikeluarkan dari modal-body — kolom itu punya overflow
           sendiri sehingga dropdown di dalamnya ikut terpotong. Ditempel ke
           elemen .modal (bukan <body>) supaya focus-trap Bootstrap tidak
           merebut fokus dari kolom carinya. */
        var $host = $sel.closest('.modal');
        (($host.length ? $host : $('body'))).append($menu);
        $menu.data('ssWrap', $wrap);

        var $opts = $menu.find('.ss-options');
        $sel.find('option').each(function () {
            if (this.value === '') return;
            $opts.append(
                $('<div class="ss-opt"></div>')
                    .attr('data-value', this.value)
                    .text($(this).text().trim())
            );
        });

        function syncLabel() {
            var v = $sel.val();
            var t = $sel.find('option:selected').text().trim();
            $disp.toggleClass('ss-placeholder', !v);
            $disp.find('.ss-label').text(v ? t : kosongTeks);
            $opts.find('.ss-opt').removeClass('active');
            if (v) $opts.find('.ss-opt').filter(function () {
                return $(this).attr('data-value') === String(v);
            }).addClass('active');
        }

        function filter(q) {
            q = (q || '').toLowerCase().trim();
            var ada = 0;
            $opts.find('.ss-opt').each(function () {
                var cocok = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
                $(this).toggle(cocok).removeClass('hl');
                if (cocok) ada++;
            });
            $menu.find('.ss-none').toggle(ada === 0);
        }

        /* Posisi panel dihitung manual (position:fixed): menempel di bawah
           kolomnya, lalu dibalik ke atas kalau ruang bawah tidak cukup. */
        function posisikan() {
            var r = $disp[0].getBoundingClientRect();
            var maxH = Math.min(300, Math.max(180, window.innerHeight - 32));
            $menu.css({ left: r.left + 'px', width: r.width + 'px', top: '', bottom: '' });
            $opts.css('max-height', (maxH - 70) + 'px');

            var tinggi = $menu.outerHeight();
            var ruangBawah = window.innerHeight - r.bottom - 8;
            if (ruangBawah < tinggi && r.top - 8 > ruangBawah) {
                $menu.addClass('ss-up').css('top', Math.max(8, r.top - tinggi - 4) + 'px');
            } else {
                $menu.removeClass('ss-up').css('top', (r.bottom + 4) + 'px');
            }
        }

        function buka() {
            tutupSemua();
            $wrap.addClass('open');
            $menu.addClass('open');
            filter('');
            posisikan();
            $menu.find('.ss-search').val('').focus();
            var $act = $opts.find('.ss-opt.active');
            if ($act.length) $opts.scrollTop($act.position().top + $opts.scrollTop() - 60);
        }
        function tutup() { $wrap.removeClass('open'); $menu.removeClass('open'); }

        $(window).on('resize scroll', function () { if ($menu.hasClass('open')) posisikan(); });
        $(document).on('scroll', '.modal', function () { if ($menu.hasClass('open')) posisikan(); });
        $menu.on('ssReposisi', posisikan);

        $disp.on('click', function () { $wrap.hasClass('open') ? tutup() : buka(); });
        $disp.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') { e.preventDefault(); buka(); }
        });

        $menu.on('click', function (e) { e.stopPropagation(); });
        $menu.find('.ss-search').on('input', function () { filter(this.value); });
        $menu.find('.ss-search').on('keydown', function (e) {
            var $vis = $opts.find('.ss-opt:visible');
            var i = $vis.index($vis.filter('.hl'));
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!$vis.length) return;
                i = e.key === 'ArrowDown' ? (i + 1) % $vis.length : (i <= 0 ? $vis.length - 1 : i - 1);
                $vis.removeClass('hl').eq(i).addClass('hl');
                var el = $vis.get(i);
                if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                var $pilih = $vis.filter('.hl').first();
                if (!$pilih.length) $pilih = $vis.first();
                if ($pilih.length) $pilih.trigger('click');
            } else if (e.key === 'Escape') {
                e.preventDefault(); tutup(); $disp.focus();
            }
        });

        $opts.on('click', '.ss-opt', function () {
            $sel.val($(this).attr('data-value')).trigger('change');
            tutup();
            $disp.focus();
        });

        $sel.on('change', syncLabel);
        syncLabel();
    }

    /* Satu dropdown terbuka dalam satu waktu; klik di luar juga menutup. */
    function tutupSemua() { $('.ss-wrap.open').removeClass('open'); $('.ss-menu.open').removeClass('open'); }
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.ss-wrap, .ss-menu').length) tutupSemua();
    });
    $(document).on('hidden.bs.modal', '.modal', tutupSemua);

    initSearchableSelect('#tambahBank', 'Cari nama bank...');
    initSearchableSelect('#tambahPic',  'Cari nama PIC...');
    initSearchableSelect('#editBank',   'Cari nama bank...');
    initSearchableSelect('#editPic',    'Cari nama PIC...');
    initSearchableSelect('#movePicTujuan', 'Cari nama PIC...');

    /*
    |--------------------------------------------------------------------------
    | MODAL EDIT — load data via AJAX
    |--------------------------------------------------------------------------
    | Ikut digerbang $canManage. Tanpa ini penangannya tetap terpasang untuk
    | role yang tombolnya sudah hilang — tidak berbahaya (tidak ada elemen yang
    | cocok), tapi alamat endpoint edit-data ikut tercetak di source halaman.
    */
    @if($canManage)
    /* Dipisah jadi fungsi bernama karena dipanggil dari dua tempat: tombol
       Edit di tabel, dan tombol Edit di dalam modal Detail PIC. Yang kedua
       harus menutup modalnya dulu — dua modal Bootstrap yang bertumpuk
       menyisakan backdrop yang tidak bisa diklik. */
    function bukaModalEdit(id) {
        $('#editLoading').show();
        $('#editFields').hide();
        $('#btnSimpanEdit').hide();
        $('#modalEdit').modal('show');

        $('#formEdit').attr('action', '{{ url("/beneficiaries") }}/' + id);

        $.get('{{ url("/beneficiaries") }}/' + id + '/edit-data', function (res) {
            $('#editNickname').val(res.nickname);
            $('#editCompleteName').val(res.complete_name);
            $('#editCategory').val(res.category ?? 'EXTERNAL');
            $('#editPic').val(res.pic_id ?? '').trigger('change');
            $('#editBank').val(res.bank_id ?? '').trigger('change');
            $('#editAccountNumber').val(res.account_number ?? '');
            $('#editContact').val(res.contact ?? '');
            $('#editStatus').val(res.status);

            $('#editLoading').hide();
            $('#editFields').show();
            $('#btnSimpanEdit').show();

            $('#modalEdit').one('shown.bs.modal', function () {
                $('#editNickname').focus();
            });
        }).fail(function () {
            $('#editLoading').html(
                '<p class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data. Silakan coba lagi.</p>'
            );
        });
    }

    $(document).on('click', '.btn-edit', function () {
        bukaModalEdit($(this).data('id'));
    });


    /* Edit dari dalam modal Detail PIC: modalnya ditutup lebih dulu, dan modal
       Edit baru dibuka setelah penutupannya benar-benar selesai. */
    $(document).on('click', '.btn-pic-edit', function () {
        var id = $(this).data('id');

        $('#modalPicDetail')
            .one('hidden.bs.modal', function () { bukaModalEdit(id); })
            .modal('hide');
    });
    @endif

});

</script>
@endpush