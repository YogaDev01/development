@extends('layouts.app')

@section('title', 'Source Fund')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Source Fund</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Source Fund</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Source Fund', $summary['total'], 'primary', 'fas fa-piggy-bank'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">

            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-piggy-bank mr-1"></i> Daftar Source Fund</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterSf" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="sfRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                            <i class="fas fa-plus mr-1"></i> Tambah Source Fund
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="sfTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="5%" class="all">No</th>
                                <th scope="col" class="all">Nama</th>
                                <th scope="col" width="20%">No Account</th>
                                <th scope="col" width="20%">Bank</th>
                                <th scope="col" width="10%">Status</th>
                                <th scope="col" width="10%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sourceFunds as $sf)
                            <tr data-status="{{ $sf->status }}" data-bank="{{ $sf->bank->name ?? '' }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <strong>{{ $sf->name }}</strong>
                                    @if($sf->is_main_account)
                                        <span class="badge badge-warning px-2 py-1 ml-1" style="font-size:9px;">MAIN ACCOUNT</span>
                                    @endif
                                </td>
                                <td>{{ $sf->no_account ?? '-' }}</td>
                                <td>{{ $sf->bank->name ?? '-' }}</td>
                                <td>
                                    @if($sf->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $sf->id }}"
                                                title="Edit" aria-label="Edit {{ $sf->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $sf->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>
                                            <form action="{{ url('/source-fund/' . $sf->id) }}"
                                                  method="POST" class="delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>

                                        </div>

                                    </div>
                                </td>

                            </tr>
                            @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty title="Belum ada data Source Fund" icon="fas fa-folder-open" compact />
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

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-piggy-bank mr-2" style="color:var(--accent);"></i> Tambah Source Fund</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ url('/source-fund') }}" method="POST" id="formTambah">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group">
                        <label class="font-weight-600">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" style="text-transform:uppercase;" required>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">No Account</label>
                        <input type="text" name="no_account" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Bank</label>
                        <select name="bank_id" class="form-control">
                            <option value="">— Pilih Bank —</option>
                            @foreach($banks as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group form-check mb-0">
                        <input type="checkbox" name="is_main_account" value="1" class="form-check-input" id="chkMainAccount">
                        <label class="form-check-label" for="chkMainAccount">Jadikan Main Account</label>
                        <small class="text-muted d-block mt-1">
                            Hanya 1 akun boleh jadi Main Account — akun yang ditandai sebelumnya otomatis dinonaktifkan.
                        </small>
                    </div>
                    <input type="hidden" name="status" value="ACTIVE">
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-pen mr-2" style="color:var(--accent);"></i> Edit Source Fund</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="formEdit" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body" style="padding:24px 28px;">
                    <div id="editLoading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    </div>
                    <div id="editFields" style="display:none;">
                        <div class="form-group">
                            <label class="font-weight-600">Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control" style="text-transform:uppercase;" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-600">No Account</label>
                            <input type="text" name="no_account" id="editNoAccount" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="font-weight-600">Bank</label>
                            <select name="bank_id" id="editBankId" class="form-control">
                                <option value="">— Pilih Bank —</option>
                                @foreach($banks as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-600">Status <span class="text-danger">*</span></label>
                            <select name="status" id="editStatus" class="form-control" required>
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>
                        <div class="form-group form-check mb-0">
                            <input type="checkbox" name="is_main_account" value="1" class="form-check-input" id="editIsMainAccount">
                            <label class="form-check-label" for="editIsMainAccount">Jadikan Main Account</label>
                            <small class="text-muted d-block mt-1">
                                Hanya 1 akun boleh jadi Main Account — akun yang ditandai sebelumnya otomatis dinonaktifkan.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning" id="btnSimpanEdit" style="display:none;"><i class="fas fa-save mr-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }
@media (max-width: 767.98px) {
    .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn { width:100%; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterSf { width:100%; }
    #sfRowFilters { width:100%; }
    #sfRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #customSearch input { width:100%; }
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    // ── Modal Tambah/Edit/Hapus — dibind PALING AWAL, supaya tetap
    // responsif walaupun ada error di inisialisasi DataTable di bawah.
    $(document).on('click', '#btnTambah', function () {
        $('#formTambah')[0].reset();
        $('#modalTambah').modal('show');
    });

    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $('#editLoading').show();
        $('#editFields').hide();
        $('#btnSimpanEdit').hide();
        $('#modalEdit').modal('show');
        $('#formEdit').attr('action', '{{ url("/source-fund") }}/' + id);

        $.get('{{ url("/source-fund") }}/' + id + '/edit-data', function (res) {
            $('#editName').val(res.name);
            $('#editNoAccount').val(res.no_account);
            $('#editBankId').val(res.bank_id);
            $('#editStatus').val(res.status);
            $('#editIsMainAccount').prop('checked', !!res.is_main_account);
            $('#editLoading').hide();
            $('#editFields').show();
            $('#btnSimpanEdit').show();
        }).fail(function () {
            $('#editLoading').html('<p class="text-danger">Gagal memuat data.</p>');
        });
    });

    $(document).on('submit', '.delete-form', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus Source Fund?', icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    // ── DataTable (dibungkus try-catch — kalau gagal, tombol di atas tetap jalan) ──
    try {
        var table = $('#sfTable').DataTable({
            responsive: { details: { type: 'inline' } },
            autoWidth: false, pageLength: 10,
            dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
            language: {
                lengthMenu: 'Page Size _MENU_', zeroRecords: 'Data tidak ditemukan',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
            }
        });

        $('#sfTable_filter').hide();
        $('#customSearch').html('<input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">');
        $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

        TesysColumnFilter.initRowFilters('#sfTable', '#sfRowFilters', [
            { key: 'bank', label: 'Bank' },
            { key: 'status', label: 'Status' },
        ]);
        $('#btnClearFilterSf').on('click', function () { TesysColumnFilter.clearAll('#sfTable'); });
    } catch (err) {
        console.error('Gagal inisialisasi DataTable Source Fund:', err);
    }

    $('#refreshTable').on('click', function () { location.reload(); });

});
</script>
@endpush