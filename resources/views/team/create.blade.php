@extends('layouts.app')

@section('title', 'Tambah Team')

@php $denganPrimary = \App\Models\Team::supportsStructure(); @endphp

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
                        @if(\App\Models\Team::supportsStructure())
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Induk Team</label>
                                <select name="parent_team_id" class="form-control @error('parent_team_id') is-invalid @enderror">
                                    <option value="">&mdash; Tanpa Induk &mdash;</option>
                                    @foreach($parentOptions as $p)
                                    <option value="{{ $p->id }}" {{ old('parent_team_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->code }})</option>
                                    @endforeach
                                </select>
                                @error('parent_team_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                <small class="text-muted d-block mt-1">Hanya untuk struktur organisasi; tidak memberi akses data.</small>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            @include('team._access_groups')

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">Team Members</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddMember">
                        <i class="fas fa-plus mr-1"></i> Tambah Member
                    </button>
                </div>
                <div class="card-body p-0">
                    @error('members')<div class="alert alert-danger m-3 mb-0" role="alert">{{ $message }}</div>@enderror
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:1%;">No</th>
                                    <th scope="col" style="width:30%;">User</th>
                                    <th scope="col" style="width:24%;">Position</th>
                                    <th scope="col" style="width:10%;">Level</th>
                                    @if($denganPrimary)
                                    <th scope="col" class="text-center" style="width:10%;" title="Team utama user (maksimal satu per user)">Primary</th>
                                    @endif
                                    <th scope="col" style="width:12%;">Status</th>
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
    // Teks dari data di-escape sebelum disisipkan ke HTML.
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html().replace(/"/g, '&quot;'); }

    var userOptions = '<option value="">— Pilih User —</option>' +
        @json($userOptionsData)
            .map(function (u) { return '<option value="' + esc(u.id) + '">' + esc(u.label) + '</option>'; }).join('');

    var memberIdx = 0;
    var denganPrimary = @json($denganPrimary);

    function makeMemberRow(i, prefill) {
        prefill = prefill || {};
        var opts = userOptions;
        if (prefill.user_id) {
            opts = opts.replace('value="' + prefill.user_id + '"', 'value="' + prefill.user_id + '" selected');
        }
        return '<tr class="member-row">' +
            '<td class="text-center align-middle member-row-no"></td>' +
            '<td><select name="members[' + i + '][user_id]" class="form-control form-control-sm" required>' + opts + '</select></td>' +
            '<td><input type="text" name="members[' + i + '][position]" class="form-control form-control-sm" value="' + esc(prefill.position || '') + '" placeholder="Leader/Supervisor/..."></td>' +
            '<td><input type="number" min="0" step="1" name="members[' + i + '][level]" class="form-control form-control-sm text-center" value="' + (prefill.level ?? 0) + '" required></td>' +
            (denganPrimary
                ? '<td class="text-center align-middle"><input type="checkbox" name="members[' + i + '][is_primary]" value="1" aria-label="Team primary"' + (prefill.is_primary ? ' checked' : '') + '></td>'
                : '') +
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

    // Baris pertama: default leader (kalau sudah dipilih)
    $('#btnAddMember').trigger('click');

    $('#selLeader').on('change', function () {
        var val = $(this).val();
        var firstSelect = $('.member-row:first select[name^="members"][name$="[user_id]"]');
        if (val && firstSelect.length && !firstSelect.val()) {
            firstSelect.val(val);
        }
    });
});
</script>
@endpush
