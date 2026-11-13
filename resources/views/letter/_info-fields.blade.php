{{-- Field informasi surat (dipakai modal Buat & tab Informasi). $v = Letter|null --}}
@php
    $type = old('type', $v->type ?? 'external');
@endphp
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Kategori surat *</label>
        <select name="category_id" class="form-control" required>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $v->category_id ?? null) == $c->id)>{{ $c->code }} - {{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-6">
        <label>Tipe surat *</label>
        <select name="type" class="form-control js-letter-type" required>
            <option value="external" @selected($type === 'external')>External (ke customer)</option>
            <option value="internal" @selected($type === 'internal')>Internal (ke karyawan/tim)</option>
        </select>
    </div>
    <div class="form-group col-md-6 js-wrap-customer">
        <label>Customer *</label>
        <select name="customer_id" class="form-control">
            <option value="">- pilih -</option>
            @foreach ($form['customers'] as $x)
                <option value="{{ $x->id }}" @selected(old('customer_id', $v->customer_id ?? null) == $x->id)>{{ $x->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-6 js-wrap-employee">
        <label>Karyawan</label>
        <select name="employee_id" class="form-control">
            <option value="">- tidak ditentukan -</option>
            @foreach ($form['employees'] as $x)
                <option value="{{ $x->id }}" @selected(old('employee_id', $v->employee_id ?? null) == $x->id)>{{ $x->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-6">
        <label>Tim</label>
        <select name="team_id" class="form-control">
            <option value="">- tidak ditentukan -</option>
            @foreach ($form['teams'] as $x)
                <option value="{{ $x->id }}" @selected(old('team_id', $v->team_id ?? null) == $x->id)>{{ $x->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-6">
        <label>Departemen *</label>
        <select name="department_id" class="form-control" required>
            @foreach ($form['departments'] as $x)
                <option value="{{ $x->id }}" @selected(old('department_id', $v->department_id ?? null) == $x->id)>{{ $x->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-12">
        <label>No. referensi</label>
        <input type="text" name="reference_no" class="form-control" maxlength="100" value="{{ old('reference_no', $v->reference_no ?? '') }}" placeholder="opsional">
    </div>
</div>
@if ($errors->any())
    <div class="text-danger small">{{ $errors->first() }}</div>
@endif
