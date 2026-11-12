{{-- Pilihan aset (belum DISPOSED). Param: $assets, $name (default asset_id), $id, $only (status yang boleh, opsional). --}}
<select name="{{ $name ?? 'asset_id' }}" id="{{ $id }}" class="form-control form-control-sm js-asset-select" required>
    <option value="">— Pilih aset —</option>
    @foreach($assets as $a)
        @if(empty($only) || in_array($a->status, $only, true))
            <option value="{{ $a->id }}" data-search="{{ $a->code }}" @selected(old($name ?? 'asset_id') == $a->id)>{{ $a->code }} — {{ $a->name }} ({{ $a->status }})</option>
        @endif
    @endforeach
</select>
