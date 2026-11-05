{{--
    Bagian "Akses PO (Access Group)" pada form Create/Edit Team.

    Anggota ACTIVE team ACTIVE mendapat akses PO & cost center dari Access
    Group yang dipilih di sini (User::accessibleProjectIds() /
    accessibleCostCenterIds()), DITAMBAH Access Group individu masing-masing.
    Struktur organisasi (leader / induk team) tidak memberi akses apa pun.

    Hanya superadmin yang boleh mengubah ($canEditAccess, dicek ulang di
    TeamController). Pengguna lain melihat daftarnya saja.

    Variabel: $accessGroups (id, name, po_count), $selectedAccessGroupIds, $canEditAccess.
--}}
@php
    $terpilih = collect(old('access_group_ids', $selectedAccessGroupIds))->map(fn ($i) => (int) $i)->all();
@endphp
<div class="card card-primary card-outline shadow-sm">
    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
        <h3 class="card-title"><i class="fas fa-key mr-1"></i> Akses PO (Access Group)</h3>
        <span class="text-muted ml-auto" style="font-size:11.5px;">
            Berlaku untuk seluruh anggota aktif team ini, selain Access Group individu masing-masing.
        </span>
    </div>
    <div class="card-body">
        @if($canEditAccess)
            <input type="hidden" name="access_group_sync" value="1">
            @error('access_group_ids')<div class="alert alert-danger py-2" role="alert">{{ $message }}</div>@enderror
            @if($accessGroups->isEmpty())
                <x-empty icon="fas fa-key" title="Belum ada Access Group" message="Buat Access Group lebih dulu di menu User & Access." />
            @else
            <input type="search" class="form-control form-control-sm mb-2" id="agFilter" placeholder="Cari Access Group" aria-label="Cari Access Group" style="max-width:280px;">
            <div class="row" id="agList" style="max-height:240px;overflow-y:auto;">
                @foreach($accessGroups as $ag)
                <div class="col-md-4 col-sm-6 ag-item" data-name="{{ strtolower($ag->name) }}">
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" class="custom-control-input" id="ag{{ $ag->id }}"
                               name="access_group_ids[]" value="{{ $ag->id }}" {{ in_array((int) $ag->id, $terpilih, true) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="ag{{ $ag->id }}">
                            {{ $ag->name }} <span class="text-muted" style="font-size:11px;">· {{ $ag->po_count }} PO</span>
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        @else
            @php $milik = $accessGroups->whereIn('id', $terpilih); @endphp
            @forelse($milik as $ag)
                <span class="badge badge-light border px-2 py-1 mr-1 mb-1">{{ $ag->name }} · {{ $ag->po_count }} PO</span>
            @empty
                <span class="text-muted" style="font-size:12px;">Team ini belum diberi Access Group.</span>
            @endforelse
            <small class="text-muted d-block mt-2">Akses PO team diatur Superadmin.</small>
        @endif
    </div>
</div>

@if($canEditAccess)
@push('scripts')
<script>
$(function () {
    $('#agFilter').on('input', function () {
        var q = this.value.trim().toLowerCase();
        $('#agList .ag-item').each(function () {
            $(this).toggle(!q || String($(this).data('name')).indexOf(q) !== -1);
        });
    });
});
</script>
@endpush
@endif
