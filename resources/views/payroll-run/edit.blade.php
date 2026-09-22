@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/payroll-run') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Edit Payroll Run</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/payroll-run') }}">Payroll</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>

        <form action="{{ url('/payroll-run/' . $run->id) }}" method="POST" id="formPayroll">
            @csrf
            @method('PUT')

            {{-- INFORMASI RUN --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-file-invoice-dollar mr-2" style="color:var(--accent);"></i> Informasi Payroll
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Periode <span class="text-danger">*</span></label>
                                <input type="month" name="period" class="form-control @error('period') is-invalid @enderror"
                                       value="{{ old('period', $run->period) }}" required>
                                @error('period')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-600">Budget Payroll</label>
                                <select name="budget_plan_id" class="form-control @error('budget_plan_id') is-invalid @enderror">
                                    <option value="">— Pilih Budget Payroll —</option>
                                    @foreach($budgetPlans as $bp)
                                        <option value="{{ $bp->id }}" {{ old('budget_plan_id', $run->budget_plan_id) == $bp->id ? 'selected' : '' }}>
                                            [{{ $bp->code }}] {{ $bp->description }} — {{ $bp->costCenter->name ?? '-' }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Sumber dana dari cost center bertipe PAYROLL.</small>
                                @error('budget_plan_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">Deskripsi</label>
                                <input type="text" name="description" class="form-control"
                                       value="{{ old('description', $run->description) }}" placeholder="mis. GAJI BULANAN" style="text-transform:uppercase;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- DAFTAR KARYAWAN --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas fa-users mr-2" style="color:var(--accent);"></i> Daftar Karyawan
                    </h3>
                    <div class="ml-auto d-flex align-items-center" style="gap:10px;">
                        <span class="badge badge-primary px-3 py-2" style="font-size:13px;">
                            Total: <span id="grandTotal">0</span>
                        </span>
                        <button type="button" class="btn btn-primary btn-sm" id="btnAddRow">
                            <i class="fas fa-plus mr-1"></i> Tambah Baris
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="lineTable">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                    <th scope="col" style="min-width:200px;">Karyawan</th>
                                    <th scope="col" class="text-nowrap" style="width:160px;">Net (Rp)</th>
                                    <th scope="col" class="text-nowrap" style="width:150px;">Dibebankan</th>
                                    <th scope="col" style="min-width:180px;">Project (bila PROJECT)</th>
                                    <th scope="col" class="text-center text-nowrap" style="width:1%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="lineBody"></tbody>
                        </table>
                    </div>
                    <div class="p-2 bg-light border-top">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Minimal 1 baris. Pilih karyawan untuk mengisi gaji otomatis dari gaji default (tetap bisa diubah).
                        </small>
                    </div>
                </div>
                <div class="card-footer d-flex">
                    <a href="{{ url('/payroll-run') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success ml-auto">
                        <i class="fas fa-save mr-1"></i> Update
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

{{-- Holder data untuk JS --}}
<textarea id="employeesHolder" style="display:none;">{{ json_encode($employees) }}</textarea>
<textarea id="projectsHolder" style="display:none;">{{ json_encode($projects->map(fn($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name])) }}</textarea>
<textarea id="existingLinesHolder" style="display:none;">{{ json_encode($run->lines->map(fn($l) => [
    'employee_id'       => $l->employee_id,
    'net_amount'        => $l->net_amount,
    'charge_type'       => $l->charge_type,
    'charge_project_id' => $l->charge_project_id,
])) }}</textarea>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
#lineTable td { vertical-align:middle !important; }
.net-input { text-align:right; }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var employees = JSON.parse(document.getElementById('employeesHolder').value);
    var projects  = JSON.parse(document.getElementById('projectsHolder').value);
    var idx = 0;

    function employeeOptions(selectedId) {
        var html = '<option value="">— Pilih Karyawan —</option>';
        employees.forEach(function (e) {
            var sel = String(e.id) === String(selectedId) ? ' selected' : '';
            html += '<option value="' + e.id + '"' + sel + '>' + e.name + '</option>';
        });
        return html;
    }

    function projectOptions(selectedId) {
        var html = '<option value="">— Pilih Project —</option>';
        projects.forEach(function (p) {
            var sel = String(p.id) === String(selectedId) ? ' selected' : '';
            html += '<option value="' + p.id + '"' + sel + '>[' + (p.code || '-') + '] ' + p.name + '</option>';
        });
        return html;
    }

    function fmtNum(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v ? parseInt(v, 10).toLocaleString('id-ID') : '';
    }

    function addRow(data) {
        data = data || {};
        var i = idx++;

        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-center row-no"></td>' +
            '<td><select name="lines[' + i + '][employee_id]" class="form-control form-control-sm emp-select">' +
                employeeOptions(data.employee_id) + '</select></td>' +
            '<td><input type="text" name="lines[' + i + '][net_amount]" class="form-control form-control-sm net-input" ' +
                'inputmode="numeric" value="' + (data.net_amount ? fmtNum(data.net_amount) : '') + '"></td>' +
            '<td><select name="lines[' + i + '][charge_type]" class="form-control form-control-sm charge-select">' +
                '<option value="OVERHEAD"' + (data.charge_type === 'PROJECT' ? '' : ' selected') + '>Overhead</option>' +
                '<option value="PROJECT"' + (data.charge_type === 'PROJECT' ? ' selected' : '') + '>Project</option>' +
                '</select></td>' +
            '<td><select name="lines[' + i + '][charge_project_id]" class="form-control form-control-sm project-select" ' +
                (data.charge_type === 'PROJECT' ? '' : 'disabled') + '>' +
                projectOptions(data.charge_project_id) + '</select></td>' +
            '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-del-row"><i class="fas fa-trash"></i></button></td>';

        document.getElementById('lineBody').appendChild(tr);
        renumber();
    }

    function renumber() {
        document.querySelectorAll('#lineBody tr').forEach(function (tr, n) {
            tr.querySelector('.row-no').textContent = (n + 1);
        });
    }

    function recalc() {
        var total = 0;
        document.querySelectorAll('.net-input').forEach(function (inp) {
            total += parseInt((inp.value || '0').replace(/\D/g, '')) || 0;
        });
        document.getElementById('grandTotal').textContent = total.toLocaleString('id-ID');
    }

    // Pilih karyawan -> auto-isi net dari gaji default
    $(document).on('change', '.emp-select', function () {
        var id  = this.value;
        var emp = employees.find(function (e) { return String(e.id) === String(id); });
        var row = $(this).closest('tr');
        var net = row.find('.net-input');

        if (emp && (!net.val() || net.val() === '0')) {
            net.val(fmtNum(emp.default_salary));
            recalc();
        }
    });

    // Charge type -> enable/disable project
    $(document).on('change', '.charge-select', function () {
        var row = $(this).closest('tr');
        var proj = row.find('.project-select');
        if (this.value === 'PROJECT') {
            proj.prop('disabled', false);
        } else {
            proj.prop('disabled', true).val('');
        }
    });

    // Format ribuan + recalc
    $(document).on('input', '.net-input', function () {
        var pos = this.value.length - this.selectionStart;
        this.value = fmtNum(this.value);
        this.selectionStart = this.selectionEnd = Math.max(0, this.value.length - pos);
        recalc();
    });

    // Hapus baris
    $(document).on('click', '.btn-del-row', function () {
        $(this).closest('tr').remove();
        renumber();
        recalc();
    });

    $('#btnAddRow').on('click', function () { addRow(); });

    // Submit guard: minimal 1 baris + tiap baris punya karyawan
    $('#formPayroll').on('submit', function (e) {
        var rows = document.querySelectorAll('#lineBody tr');
        if (rows.length === 0) {
            e.preventDefault();
            Swal.fire('Belum ada karyawan', 'Tambahkan minimal satu baris karyawan.', 'warning');
            return;
        }
        var invalid = false;
        rows.forEach(function (tr) {
            if (!tr.querySelector('.emp-select').value) invalid = true;
        });
        if (invalid) {
            e.preventDefault();
            Swal.fire('Data belum lengkap', 'Ada baris tanpa karyawan terpilih.', 'warning');
        }
    });

    // Render baris existing
    var existing = JSON.parse(document.getElementById('existingLinesHolder').value);
    if (existing.length) {
        existing.forEach(function (row) { addRow(row); });
    } else {
        addRow();
    }
    recalc();

});
</script>
@endpush