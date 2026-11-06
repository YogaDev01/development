{{--
    Field Customer pada form Create/Edit Team.

    Selama tabel team_customers belum dimigrasi, field ini tetap pilihan tunggal
    karena kolom teams.customer_id hanya menampung satu customer.

    Variabel: $customers, $selectedCustomerIds.
--}}
@php
    $customerMulti = \App\Models\Team::supportsCustomerList();
    // Setelah validasi gagal, pilihan yang dikosongkan tidak boleh kembali terisi.
    $customerTerpilih = collect(old('customer_sync') ? old('customer_ids', []) : $selectedCustomerIds)
        ->filter()->map(fn ($i) => (int) $i)->all();
@endphp
<div class="form-group">
    <label class="font-weight-600">Customer</label>
    <input type="hidden" name="customer_sync" value="1">
    <select name="customer_ids[]" {!! $customerMulti ? 'id="selCustomers" multiple' : 'class="form-control"' !!}>
        @unless($customerMulti)
        <option value="">&mdash; Pilih Customer &mdash;</option>
        @endunless
        @foreach($customers as $cust)
        <option value="{{ $cust->id }}" {{ in_array((int) $cust->id, $customerTerpilih, true) ? 'selected' : '' }}>{{ $cust->name }}</option>
        @endforeach
    </select>
    @error('customer_ids.*')<small class="text-danger d-block">{{ $message }}</small>@enderror
    <small class="form-text text-muted">Dipakai Trouble Ticket: satu customer terisi otomatis untuk anggota team ini; lebih dari satu dipilih saat membuat tiket.</small>
</div>

@if($customerMulti)
@push('scripts')
<script>
$(function () {
    TesysSearchableSelect.init('#selCustomers', { placeholder: 'Pilih Customer...', searchPlaceholder: 'Cari Customer...' });
});
</script>
@endpush
@endif
