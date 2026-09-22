@extends('layouts.app')

@section('title', 'Department')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Department</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Department</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Department', $summary['total'], 'primary', 'fas fa-building'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-building mr-1"></i> Daftar Department</h3>
                <div class="ml-auto">
                    <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                        <i class="fas fa-plus mr-1"></i> Tambah Department
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Kode</th>
                                <th scope="col">Nama</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Team</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departments as $d)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><span class="badge badge-dark px-2 py-1">{{ $d->code }}</span></td>
                                <td style="font-weight:600;">{{ $d->name }}</td>
                                <td class="text-center"><span class="badge badge-secondary px-2 py-1">{{ $d->teams_count }}</span></td>
                                <td><span class="badge badge-{{ $d->status_color }} px-2 py-1">{{ $d->status }}</span></td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $d->id }}"
                                                data-name="{{ $d->name }}"
                                                data-status="{{ $d->status }}"
                                                title="Edit" aria-label="Edit {{ $d->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $d->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>
                                            <button type="button" class="dropdown-item is-danger btn-delete"
                                                    data-id="{{ $d->id }}" data-name="{{ $d->name }}">
                                                <i class="fas fa-trash"></i>Hapus
                                            </button>

                                        </div>

                                    </div>
                                </td>

                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada department.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-building mr-2" style="color:var(--accent);"></i> Tambah Department</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ url('/department') }}" method="POST" id="formTambah">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Nama Department <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" style="text-transform:uppercase;" autocomplete="off" required>
                        <small class="text-muted mt-1 d-block">Kode dibuat otomatis (DEPT-001).</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-edit mr-2" style="color:var(--accent);"></i> Edit Department</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="formEdit">
                @csrf
                @method('PUT')
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group">
                        <label class="font-weight-600">Nama Department <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control" style="text-transform:uppercase;" autocomplete="off" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Status <span class="text-danger">*</span></label>
                        <select name="status" id="editStatus" class="form-control" required>
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formDelete" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

@endsection

@push('scripts')
<script>
$(function () {
    $('#btnTambah').on('click', function () {
        $('#formTambah')[0].reset();
        $('#modalTambah').modal('show');
    });

    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $('#editName').val($(this).data('name'));
        $('#editStatus').val($(this).data('status'));
        $('#formEdit').attr('action', '/department/' + id);
        $('#modalEdit').modal('show');
    });

    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id'), name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Department?', text: name + ' akan dihapus permanen.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: 'var(--danger)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) {
                var form = document.getElementById('formDelete');
                form.action = '/department/' + id;
                form.submit();
            }
        });
    });
});
</script>
@endpush
