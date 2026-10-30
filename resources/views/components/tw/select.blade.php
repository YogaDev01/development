{{--
    <x-tw.select> — pilihan native Tailwind (prefix tw): div.form-group + label +
    <select> + petunjuk + pesan galat. Slot = <option>/<optgroup> dari
    pemanggil (opsi terpilih ditandai pemanggil; old() tidak dipanggil di sini).

    Props sama dengan x-tw.input, kecuali tanpa type/value dan ditambah:
      placeholder : bila diisi, opsi pertama kosong <option value="">…</option>

    TesysSearchableSelect/TomSelect tetap bisa di-init pada id kontrol; komponen
    tidak menyentuh .tss-*, .ts-wrapper, maupun .flatpickr-*. Atribut lain
    (data-*, onchange, ...) diteruskan ke <select>.

    TIDAK mendukung atribut multiple: tinggi tetap kelas h-ctl (satu baris) akan
    memotong daftar pilihan. Pilihan ganda tetap memakai select legacy.

    Ukuran font kontrol dipasang di WADAH (div): reboot BS menetapkan
    font-size/line-height inherit pada select tanpa layer.

    Hasil ukur (E2b, docs/ui/tailwind-evidence): tinggi 34px, panah native Chrome/Edge
    tampil normal; label seperti x-tw.input; fokus = satu outline + tepi accent-text;
    rounded-sm! diperlukan hanya saat fokus (legacy :focus-visible radius 4px).
--}}
@props([
    'label'       => null,
    'name'        => null,
    'id'          => null,
    'error'       => null,
    'hint'        => null,
    'required'    => false,
    'disabled'    => false,
    'placeholder' => null,
])

@php
    $dasar = $id ?: ($name ? trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $name), '-') : null);
    $dasar = $dasar !== '' ? $dasar : null;

    $adaHint  = $hint !== null && $hint !== '';
    $adaError = $error !== null && $error !== '';
    $idHint   = ($adaHint && $dasar) ? $dasar . '-hint' : null;
    $idError  = ($adaError && $dasar) ? $dasar . '-error' : null;
    $deskripsi = trim(($idHint ?? '') . ' ' . ($idError ?? ''));

    $kontrol = 'tw:block tw:w-full tw:h-ctl tw:px-3 tw:border tw:rounded-sm! tw:text-text tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-accent-text! tw:focus-visible:border-accent-text tw:disabled:bg-surface-2 tw:disabled:text-text-faint tw:disabled:cursor-not-allowed';
    $status  = $adaError
        ? 'tw:border-danger tw:bg-danger-bg'
        : 'tw:border-border tw:bg-surface';

    $tambahan = ['class' => $kontrol . ' ' . $status];
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

    <select {{ $attributes->merge($tambahan) }}>
        @if ($placeholder !== null && $placeholder !== '')
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

    @if ($adaHint)
        <div @if ($idHint) id="{{ $idHint }}" @endif class="tw:mt-1 tw:text-caption tw:text-text-dim">{{ $hint }}</div>
    @endif
    @if ($adaError)
        <div @if ($idError) id="{{ $idError }}" @endif role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $error }}</div>
    @endif
</div>
