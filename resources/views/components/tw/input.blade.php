{{--
    <x-tw.input> — kolom isian Tailwind (prefix tw): div.form-group + label +
    input + petunjuk + pesan galat.

    Props:
      label     : teks label (opsional; tanpa label = tanpa <label>)
      name      : atribut name PERSIS apa adanya (boleh details[0][x])
      id        : id kontrol; bila kosong diturunkan dari name (karakter selain
                  huruf/angka/_/- diganti "-"). Baris yang di-clone sebaiknya
                  mengirim id sendiri.
      type      : text | number | date | email | password | ... (default text)
      value     : nilai awal. old() TIDAK dipanggil di sini — kirim dari
                  pemanggil (:value="old('x', $model->x)").
      error     : teks galat. Terisi -> aria-invalid, aria-describedby, pesan
                  role="alert", tepi/latar danger. @error TIDAK dipanggil di sini.
      hint      : teks petunjuk (dihubungkan lewat aria-describedby)
      required  : true -> atribut required + penanda * pada label
      disabled  : true -> atribut disabled

    Atribut lain (placeholder, min, max, step, maxlength, autocomplete,
    data-*, readonly, ...) diteruskan ke <input>. Wadah tetap div.form-group
    karena tesys-ui.js menghubungkan label-for hanya bila .form-group berisi
    satu kontrol.

    Ukuran font kontrol dipasang di WADAH (div), bukan di <input>: reboot BS
    menetapkan font-size/line-height inherit pada input tanpa layer, jadi
    ukuran harus diwarisi.

    Hasil ukur (E2b, docs/ui/tailwind-evidence): font-size/line-height diwarisi dari
    wadah (benar); label diselaraskan dengan label legacy form-group (luxe.css:535-540:
    12px/500/text-dim/margin-bottom 5px) — kelas label di bawah memakai warna yang sama
    dan ukuran token 12,5px yang di dalam form-group legacy kalah 0,5px (deviasi diterima,
    tampil sama dengan label legacy di sebelahnya); kontras tepi --border vs latar 1,3:1
    (temuan desain legacy, bukan komponen); fokus = satu outline (bukan ring) + tepi
    accent-text; rounded-sm! diperlukan hanya saat fokus (legacy :focus-visible radius 4px);
    target sentuh 34px (sama dengan legacy).
--}}
@props([
    'label'    => null,
    'name'     => null,
    'id'       => null,
    'type'     => 'text',
    'value'    => null,
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

    $kontrol = 'tw:block tw:w-full tw:h-ctl tw:px-3 tw:border tw:rounded-sm! tw:text-text tw:placeholder:text-text-faint tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-accent-text! tw:focus-visible:border-accent-text tw:disabled:bg-surface-2 tw:disabled:text-text-faint tw:disabled:cursor-not-allowed';
    $status  = $adaError
        ? 'tw:border-danger tw:bg-danger-bg'
        : 'tw:border-border tw:bg-surface';

    $tambahan = [
        'class' => $kontrol . ' ' . $status,
        'type'  => $type,
    ];
    if ($dasar) { $tambahan['id'] = $dasar; }
    if ($name !== null) { $tambahan['name'] = $name; }
    if ($value !== null) { $tambahan['value'] = $value; }
    if ($required) { $tambahan['required'] = true; }
    if ($disabled) { $tambahan['disabled'] = true; }
    if ($adaError) { $tambahan['aria-invalid'] = 'true'; }
    if ($deskripsi !== '') { $tambahan['aria-describedby'] = $deskripsi; }
@endphp

<div class="form-group tw:text-control tw:leading-body">
    @if ($label !== null && $label !== '')
        <label @if ($dasar) for="{{ $dasar }}" @endif class="tw:text-label tw:font-label tw:text-text-dim">{{ $label }}@if ($required)<span class="tw:text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif

    <input {{ $attributes->merge($tambahan) }}>

    @if ($adaHint)
        <div @if ($idHint) id="{{ $idHint }}" @endif class="tw:mt-1 tw:text-caption tw:text-text-dim">{{ $hint }}</div>
    @endif
    @if ($adaError)
        <div @if ($idError) id="{{ $idError }}" @endif role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $error }}</div>
    @endif
</div>
