@extends('layouts.app')

@section('title', 'Tambah Team')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah Team</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/team') }}">Team</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </div>

        <form action="{{ url('/team') }}" method="POST" id="frmTeam">
            @csrf

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header"><h3 class="card-title">Informasi Team</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Nama Team <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       style="text-transform:uppercase;" value="{{ old('name') }}" required autocomplete="off">
                                @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">— Pilih Department —</option>
                                    @foreach($departments as $dep)
                                    <option value="{{ $dep->id }}" {{ old('department_id') == $dep->id ? 'selected' : '' }}>{{ $dep->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if(\App\Models\Team::supportsCustomer())
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Customer</label>
                                <select name="customer_id" class="form-control">
                                    <option value="">&mdash; Pilih Customer &mdash;</option>
                                    @foreach($customers as $cust)
                                    <option value="{{ $cust->id }}" {{ old('customer_id') == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Dipakai Trouble Ticket: customer &amp; site terisi otomatis untuk anggota team ini.</small>
                            </div>
                        </div>
                        @endif
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Leader <span class="text-danger">*</span></label>
                                <select name="leader_id" id="selLeader" class="form-control @error('leader_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Leader —</option>
                                    @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ old('leader_id') == $u->id ? 'selected' : '' }}>{{ strtoupper($u->username) }} ({{ $u->name }})</option>
                                    @endforeach
                                </select>
                                @error('leader_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                <small class="text-muted d-block mt-1">Leader wajib juga tercatat sebagai member ACTIVE di bawah.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">Team Members</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddMember">
                        <i class="fas fa-plus mr-1"></i> Tambah Member
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:1%;">No</th>
                                    <th scope="col" style="width:22%;">User</th>
                                    <th scope="col" style="width:16%;">Position</th>
                                    <th scope="col" style="width:9%;">Level</th>
                                    <th scope="col" style="width:15%;">CA Limit</th>
                                    <th scope="col" style="width:12%;">Join Date</th>
                                    <th scope="col" style="width:10%;">Status</th>
                                    <th scope="col" style="width:5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="memberBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <a href="{{ url('/team') }}" class="btn btn-secondary"><i class="fas fa-times mr-1"></i> Batal</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </div>
        </form>

    </div>
</div>

@endsection

@php
    $userOptionsData = $users->map(fn ($u) => [
        'id'    => $u->id,
        'label' => strtoupper($u->username) . ' (' . $u->name . ')',
    ])->values();
@endphp

@push('scripts')
<script>
$(function () {
    var userOptions = '<option value="">— Pilih User —</option>' +
        @json($userOptionsData)
            .map(function (u) { return '<option value="' + u.id + '">' + u.label + '</option>'; }).join('');

    var memberIdx = 0;

    function fmtRp(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v ? parseInt(v).toLocaleString('id-ID') : '';
    }

    function makeMemberRow(i, prefill) {
        prefill = prefill || {};
        var opts = userOptions;
        if (prefill.user_id) {
            opts = opts.replace('value="' + prefill.user_id + '"', 'value="' + prefill.user_id + '" selected');
        }
        return '<tr class="member-row">' +
            '<td class="text-center align-middle member-row-no"></td>' +
            '<td><select name="members[' + i + '][user_id]" class="form-control form-control-sm" required>' + opts + '</select></td>' +
            '<td><input type="text" name="members[' + i + '][position]" class="form-control form-control-sm" value="' + (prefill.position || '') + '" placeholder="Leader/Supervisor/..."></td>' +
            '<td><input type="number" min="0" step="1" name="members[' + i + '][level]" class="form-control form-control-sm text-center" value="' + (prefill.level ?? 0) + '" required></td>' +
            '<td><input type="text" name="members[' + i + '][ca_limit]" class="form-control form-control-sm text-right member-ca-limit" value="' + (prefill.ca_limit || '') + '" placeholder="0"></td>' +
            '<td><input type="date" name="members[' + i + '][join_at]" class="form-control form-control-sm" value="' + (prefill.join_at || '') + '"></td>' +
            '<td><select name="members[' + i + '][status]" class="form-control form-control-sm">' +
                '<option value="ACTIVE"' + (prefill.status !== 'INACTIVE' ? ' selected' : '') + '>ACTIVE</option>' +
                '<option value="INACTIVE"' + (prefill.status === 'INACTIVE' ? ' selected' : '') + '>INACTIVE</option>' +
            '</select></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-member"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

    function renumberMembers() {
        $('.member-row').each(function (i) { $(this).find('.member-row-no').text(i + 1); });
    }

    $('#btnAddMember').on('click', function () {
        $('#memberBody').append(makeMemberRow(memberIdx++, null));
        renumberMembers();
    });

    $(document).on('click', '.btn-remove-member', function () {
        if ($('.member-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Minimal 1 member', confirmButtonColor: '#92232b' });
            return;
        }
        $(this).closest('tr').remove();
        renumberMembers();
    });

    $(document).on('input', '.member-ca-limit', function () {
        this.value = fmtRp(this.value);
    });

    // Baris pertama: default leader (kalau sudah dipilih)
    $('#btnAddMember').trigger('click');

    $('#selLeader').on('change', function () {
        var val = $(this).val();
        var firstSelect = $('.member-row:first select[name^="members"][name$="[user_id]"]');
        if (val && firstSelect.length && !firstSelect.val()) {
            firstSelect.val(val);
        }
    });

    $('#frmTeam').on('submit', function () {
        $('.member-ca-limit').each(function () {
            this.value = this.value.replace(/\./g, '');
        });
    });
});
</script>
@endpush
