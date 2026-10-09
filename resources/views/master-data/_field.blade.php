{{-- Satu field form master data. Variabel: $name, $f (definisi field), $first (bool). --}}
@if(!empty($f['section']))
    <div class="col-12 master-form-section {{ !empty($first) ? 'is-first' : '' }}">{{ $f['section'] }}</div>
@endif
<div class="col-md-{{ $f['col'] ?? 12 }} {{ !empty($f['create_only']) ? 'js-create-only' : '' }} {{ !empty($f['edit_only']) ? 'js-edit-only' : '' }}">
    <div class="form-group">
        <label class="font-weight-bold" for="fld_{{ $name }}">
            {{ $f['label'] }} @if(!empty($f['required']))<span class="text-danger">*</span>@endif
        </label>

        @if($f['type'] === 'textarea')
            <textarea name="{{ $name }}" id="fld_{{ $name }}" rows="2"
                      class="form-control js-master-field @error($name) is-invalid @enderror">{{ old($name, $values[$name] ?? null) }}</textarea>
        @elseif(in_array($f['type'], ['select', 'cascade']))
            <select name="{{ $name }}" id="fld_{{ $name }}"
                    class="form-control js-master-field @error($name) is-invalid @enderror"
                    @if(!empty($f['searchable']) || $f['type'] === 'cascade') data-searchable="1" @endif
                    @if($f['type'] === 'cascade') data-level="{{ $f['level'] }}" data-parent="{{ $f['parent'] }}" @endif
                    @if(!empty($f['cascade_child'])) data-child="{{ $f['cascade_child'] }}" @endif>
                <option value="">— Pilih {{ $f['label'] }} —</option>
                @foreach($f['options'] ?? [] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old($name, $values[$name] ?? null) === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        @else
            <input type="{{ $f['type'] === 'decimal' ? 'number' : 'text' }}"
                   @if($f['type'] === 'decimal') step="any" @endif
                   name="{{ $name }}" id="fld_{{ $name }}" value="{{ old($name, $values[$name] ?? null) }}"
                   placeholder="{{ $f['placeholder'] ?? '' }}" autocomplete="off"
                   class="form-control js-master-field @error($name) is-invalid @enderror">
        @endif

        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>
