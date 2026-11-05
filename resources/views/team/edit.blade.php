@extends('layouts.app')

@section('title', 'Edit Team')

@php $denganStruktur = \App\Models\Team::supportsStructure(); @endphp

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Edit Team</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/team') }}">Team</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>

        <form action="{{ url('/team/' . $team->id) }}" method="POST" id="frmTeam">
            @csrf
            @method('PUT')

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header"><h3 class="card-title">Informasi Team</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-600">Nama Team <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       style="text-transform:uppercase;" value="{{ old('name', $team->name) }}" required autocomplete="off">
                                @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">— Pilih Department —</option>
                                    @foreach($departments as $dep)
                                    <option value="{{ $dep->id }}" {{ old('department_id', $team->department_id) == $dep->id ? 'selected' : '' }}>{{ $dep->name }}</option>
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
                                    <option value="{{ $cust->id }}" {{ old('customer_id', $team->customer_id) == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Dipakai Trouble Ticket: customer &amp; site terisi otomatis untuk anggota team ini.</small>
                            </div>
                        </div>
                        @endif
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-600">Leader <span class="text-danger">*</span></label>
                                <select name="leader_id" id="selLeader" class="form-control @error('leader_id') is-invalid @enderror" required>
                                    <option value="">— Pilih —</option>
                                    @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ old('leader_id', $team->leader_id) == $u->id ? 'selected' : '' }}>{{ strtoupper($u->username) }}</option>
                                    @endforeach
                                </select>
                                @error('leader_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-600">Status <span class="text-danger">*</span></label>
                                @if($isSuperadmin)
                                <select name="status" class="form-control" required>
                                    <option value="ACTIVE" {{ old('status', $team->status) === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                                    <option value="INACTIVE" {{ old('status', $team->status) === 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                                </select>
                                @else
                                {{-- Status team menentukan akses anggota: hanya superadmin (diabaikan server bila dikirim lainnya). --}}
                                <input type="hidden" name="status" value="{{ $team->status }}">
                                <input type="text" class="form-control" value="{{ $team->status }}" readonly aria-label="Status team">
                                <small class="text-muted d-block mt-1">Diatur Superadmin.</small>
                                @endif
                            </div>
                        </div>
                        @if($denganStruktur)
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Induk Team</label>
                                @if($canEditParent)
                                <select name="parent_team_id" class="form-control @error('parent_team_id') is-invalid @enderror">
                                    <option value="">&mdash; Tanpa Induk &mdash;</option>
                                    @foreach($parentOptions as $p)
                                    <option value="{{ $p->id }}" {{ (string) old('parent_team_id', $team->parent_team_id) === (string) $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->code }}){{ $p->status !== 'ACTIVE' ? ' — INACTIVE' : '' }}</option>
                                    @endforeach
                                </select>
                                @error('parent_team_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                <small class="text-muted d-block mt-1">Hanya untuk struktur organisasi; tidak memberi akses data.</small>
                                @else
                                <input type="text" class="form-control" value="{{ $team->parent->name ?? '— Tanpa Induk —' }}" readonly>
                                <small class="text-muted d-block mt-1">Induk team diatur Superadmin.</small>
                                @endif
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
                    @unless($membersLocked)
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddMember">
                        <i class="fas fa-plus mr-1"></i> Tambah Member
                    </button>
                    @endunless
                </div>
                <div class="card-body p-0">
                    @error('members')<div class="alert alert-danger m-3 mb-0" role="alert">{{ $message }}</div>@enderror
                    <div class="px-3 pt-3 text-muted" style="font-size:11.5px;">
                        @if($membersLocked)
                        Team ini memiliki Akses PO, jadi menambah atau mengeluarkan anggota hanya dapat dilakukan Superadmin.
                        Anda tetap dapat mengubah posisi, level, dan primary.
                        @else
                        Hanya anggota aktif yang ditampilkan. Menghapus baris atau memilih INACTIVE = anggota keluar dari team;
                        datanya tetap tersimpan sebagai riwayat di halaman Detail.
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:1%;">No</th>
                                    <th scope="col" style="width:30%;">User</th>
                                    <th scope="col" style="width:24%;">Position</th>
                                    <th scope="col" style="width:10%;">Level</th>
                                    @if($denganStruktur)
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
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Update</button>
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

    $existingMembersData = $team->activeMembers->map(fn ($m) => [
        'id'         => $m->id,
        'user_id'    => $m->user_id,
        // Label dari relasi (bukan daftar user ACTIVE), supaya anggota yang
        // akunnya sudah nonaktif tetap terbaca dan ditandai.
        'label'      => strtoupper($m->user->username ?? ('#' . $m->user_id)) . ' (' . ($m->user->name ?? '-') . ')'
                        . (($m->user->status ?? 'ACTIVE') !== 'ACTIVE' ? ' — akun nonaktif' : ''),
        'inactive'   => ($m->user->status ?? 'ACTIVE') !== 'ACTIVE',
        'position'   => $m->position,
        'level'      => $m->level,
        'is_primary' => (bool) ($m->is_primary ?? false),
        'status'     => $m->status,
    ])->values();
@endphp

@push('scripts')
<script>
$(function () {
    // Semua teks dari data (nama, posisi) di-escape sebelum disisipkan ke HTML.
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html().replace(/"/g, '&quot;'); }

    var users = @json($userOptionsData);
    var userOptions = '<option value="">— Pilih User —</option>' +
        users.map(function (u) { return '<option value="' + esc(u.id) + '">' + esc(u.label) + '</option>'; }).join('');

    var existingMembers = @json($existingMembersData);

    var memberIdx = 0;
    var denganPrimary = @json($denganStruktur);
    // Team ber-Akses PO & bukan superadmin: anggota tidak bisa ditambah/dikeluarkan.
    var terkunci = @json($membersLocked);

    function labelUser(id) {
        var u = users.find(function (x) { return String(x.id) === String(id); });
        return u ? u.label : ('#' + id);
    }

    function makeMemberRow(i, prefill) {
        prefill = prefill || {};
        var kunci = terkunci && prefill.id;
        var opts = userOptions;
        // Anggota yang akunnya nonaktif tidak ada di daftar user ACTIVE:
        // tampilkan tetap supaya bisa dipilih INACTIVE / dihapus barisnya.
        if (prefill.inactive) {
            opts += '<option value="' + esc(prefill.user_id) + '">' + esc(prefill.label) + '</option>';
        }
        if (prefill.user_id) {
            opts = opts.replace('value="' + esc(prefill.user_id) + '"', 'value="' + esc(prefill.user_id) + '" selected');
        }
        var kolomUser = kunci
            ? '<td class="align-middle' + (prefill.inactive ? ' text-danger' : '') + '"><input type="hidden" name="members[' + i + '][user_id]" value="' + esc(prefill.user_id) + '">' + esc(prefill.label || labelUser(prefill.user_id)) + '</td>'
            : '<td><select name="members[' + i + '][user_id]" class="form-control form-control-sm" required>' + opts + '</select></td>';
        var kolomStatus = kunci
            ? '<td class="align-middle"><input type="hidden" name="members[' + i + '][status]" value="ACTIVE">ACTIVE</td>'
            : '<td><select name="members[' + i + '][status]" class="form-control form-control-sm">' +
                '<option value="ACTIVE"' + (prefill.status !== 'INACTIVE' ? ' selected' : '') + '>ACTIVE</option>' +
                '<option value="INACTIVE"' + (prefill.status === 'INACTIVE' ? ' selected' : '') + '>INACTIVE</option>' +
              '</select></td>';
        return '<tr class="member-row">' +
            // Nomor urut di span sendiri: renumberMembers() hanya mengganti
            // teks span, hidden id tetap terkirim.
            '<td class="text-center align-middle"><span class="member-row-no"></span>' +
                (prefill.id ? '<input type="hidden" name="members[' + i + '][id]" value="' + esc(prefill.id) + '">' : '') +
            '</td>' +
            kolomUser +
            '<td><input type="text" name="members[' + i + '][position]" class="form-control form-control-sm" value="' + esc(prefill.position || '') + '" placeholder="Leader/Supervisor/..."></td>' +
            '<td><input type="number" min="0" step="1" name="members[' + i + '][level]" class="form-control form-control-sm text-center" value="' + esc(prefill.level ?? 0) + '" required></td>' +
            (denganPrimary
                ? '<td class="text-center align-middle"><input type="checkbox" name="members[' + i + '][is_primary]" value="1" aria-label="Team primary"' + (prefill.is_primary ? ' checked' : '') + '></td>'
                : '') +
            kolomStatus +
            '<td class="text-center align-middle">' +
                (kunci ? '' : '<button type="button" class="btn btn-danger btn-sm btn-remove-member" title="Keluarkan dari team"><i class="fas fa-trash"></i></button>') +
            '</td>' +
        '</tr>';
    }

    function renumberMembers() {
        $('.member-row').each(function (i) { $(this).find('.member-row-no').text(i + 1); });
    }

    if (existingMembers.length) {
        var html = '';
        existingMembers.forEach(function (m) { html += makeMemberRow(memberIdx++, m); });
        $('#memberBody').html(html);
    } else {
        $('#memberBody').html(makeMemberRow(memberIdx++, null));
    }
    renumberMembers();

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

});
</script>
@endpush
