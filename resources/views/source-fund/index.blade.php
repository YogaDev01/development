@extends('layouts.app')

@section('title', 'Source Fund')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">Source Fund</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">Source Fund</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner"><h3>{{ $summary['total'] }}</h3><p>Total Source Fund</p></div>
                    <div class="icon"><i class="fas fa-piggy-bank"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner"><h3>{{ $summary['active'] }}</h3><p>Active</p></div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>

        <div class="card card-primary card-outline shadow-sm">

            <div class="card-header d-flex align-items-center">
                <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <div class="ml-auto">
                    <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                        <i class="fas fa-plus mr-1"></i> Tambah Source Fund
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="sfTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="all">No</th>
                                <th class="all">Nama</th>
                                <th width="20%">No Account</th>
                                <th width="20%">Bank</th>
                                <th width="10%">Status</th>
                                <th width="10%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sourceFunds as $sf)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $sf->name }}</strong></td>
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
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-warning btn-sm btn-edit"
                                                data-id="{{ $sf->id }}" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form action="{{ url('/source-fund/' . $sf->id) }}" method="POST"
                                              style="display:inline-block;" class="delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data Source Fund
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-piggy-bank mr-2" style="color:#F5A623;"></i> Tambah Source Fund</h5>
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
                    <input type="hidden" name="status" value="ACTIVE">
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-pen mr-2" style="color:#F5A623;"></i> Edit Source Fund</h5>
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
                        <div class="form-group mb-0">
                            <label class="font-weight-600">Status <span class="text-danger">*</span></label>
                            <select name="status" id="editStatus" class="form-control" required>
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning" id="btnSimpanEdit" style="display:none;"><i class="fas fa-save mr-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

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
            confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    // ── DataTable (dibungkus try-catch — kalau gagal, tombol di atas tetap jalan) ──
    try {
        $('#sfTable').DataTable({
            responsive: { details: { type: 'inline' } },
            autoWidth: false, pageLength: 10,
            dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
            language: {
                lengthMenu: 'Page Size _MENU_', zeroRecords: 'Data tidak ditemukan',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
            }
        });
    } catch (err) {
        console.error('Gagal inisialisasi DataTable Source Fund:', err);
    }

    $('#refreshTable').on('click', function () { location.reload(); });

});
</script>
@endpush