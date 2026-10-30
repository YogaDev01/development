{{--
    <x-tw.textarea> — area teks Tailwind (prefix tw): div.form-group + label +
    textarea + petunjuk + pesan galat. API mengikuti <x-tw.input> (F4-b, aditif).

    Props:
      label     : teks label (opsional; tanpa label = tanpa <label>)
      name      : atribut name PERSIS apa adanya
      id        : id kontrol; bila kosong diturunkan dari name (aturan sama dengan x-tw.input)
      value     : isi awal (ter-escape). old() TIDAK dipanggil di sini — kirim dari pemanggil.
      rows      : jumlah baris awal (default 4)
      error     : teks galat -> aria-invalid, aria-describedby, pesan role="alert", tepi/latar danger
      hint      : teks petunjuk (dihubungkan lewat aria-describedby)
      required  : true -> atribut required + penanda * pada label
      disabled  : true -> atribut disabled

    Atribut lain (placeholder, maxlength, data-*, readonly, ...) diteruskan ke <textarea>.
    Wadah tetap div.form-group (kontrak label-for tesys-ui.js). Ukuran font diwarisi dari
    wadah (reboot BS textarea{font-size:inherit}), sama seperti x-tw.input. Tinggi mengikuti
    rows (tanpa h-ctl); resize vertikal saja. Tanpa kelas `!` baru selain rounded-sm!/outline
    yang sudah dipakai x-tw.input (legacy radius/outline fokus).
--}}
@props([
    'label'    => null,
    'name'     => null,
    'id'       => null,
    'value'    => null,
    'rows'     => 4,
    'error'    => null,
    'hint'     => null,
    'required' => false,
    'disabled' => false,
])

@php
    $dasar = $id ?: ($name ? trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $name), '-') : null);
    $dasar = $dasar !== '' ? $dasar : null;

    $adaHint  = $hint !== null && $hint !== '';
    $adaError = $error !== null && $error !== '';
    $idHint   = ($adaHint && $dasar) ? $dasar . '-hint' : null;
    $idError  = ($adaError && $dasar) ? $dasar . '-error' : null;
    $deskripsi = trim(($idHint ?? '') . ' ' . ($idError ?? ''));

    $kontrol = 'tw:block tw:w-full tw:px-3 tw:py-2 tw:border tw:rounded-sm! tw:resize-y tw:text-text tw:placeholder:text-text-faint tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-accent-text! tw:focus-visible:border-accent-text tw:disabled:bg-surface-2 tw:disabled:text-text-faint tw:disabled:cursor-not-allowed';
    $status  = $adaError
        ? 'tw:border-danger tw:bg-danger-bg'
        : 'tw:border-border tw:bg-surface';

    $tambahan = [
        'class' => $kontrol . ' ' . $status,
        'rows'  => (int) $rows > 0 ? (int) $rows : 4,
    ];
    if ($dasar) { $tambahan['id'] = $dasar; }
    if ($name !== null) { $tambahan['name'] = $name; }
    if ($required) { $tambahan['required'] = true; }
    if ($disabled) { $tambahan['disabled'] = true; }
    if ($adaError) { $tambahan['aria-invalid'] = 'true'; }
    if ($deskripsi !== '') { $tambahan['aria-describedby'] = $deskripsi; }
@endphp

<div class="form-group tw:text-control tw:leading-body">
    @if ($label !== null && $label !== '')
        <label @if ($dasar) for="{{ $dasar }}" @endif class="tw:text-label tw:font-label tw:text-text-dim">{{ $label }}@if ($required)<span class="tw:text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif

    <textarea {{ $attributes->merge($tambahan) }}>{{ $value }}</textarea>

    @if ($adaHint)
        <div @if ($idHint) id="{{ $idHint }}" @endif class="tw:mt-1 tw:text-caption tw:text-text-dim">{{ $hint }}</div>
    @endif
    @if ($adaError)
        <div @if ($idError) id="{{ $idError }}" @endif role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $error }}</div>
    @endif
</div>
