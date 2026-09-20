{{--
    <x-stat-cards> — baris KPI ringkas.

    Dipakai 20+ halaman index. Bentuk prop-nya SENGAJA tidak diubah supaya
    semua pemanggil lama tetap jalan:

      cards : array of [label, value, color, icon, sub?]

        label : nama angka (ditulis huruf kapital kecil oleh CSS)
        value : angkanya
        color : nada warna — dipakai untuk ikon, bukan untuk menghias.
                Menerima nama Bootstrap lama (primary/success/warning/danger/
                secondary/info/purple) dan dipetakan ke nada design system.
        icon  : kelas Font Awesome (opsional)
        sub   : keterangan satu baris di bawah angka (opsional, baru)

    Gaya-nya ada di tesys-ui-components.css (.ts-kpi*), bukan di sini, agar
    satu perubahan berlaku ke seluruh halaman.
--}}
@php
    // Peta nama warna lama -> nada design system.
    $tonePeta = [
        'primary'   => 'progress',
        'info'      => 'progress',
        'success'   => 'positive',
        'warning'   => 'attention',
        'danger'    => 'danger',
        'secondary' => 'neutral',
        'light'     => 'neutral',
        'dark'      => 'neutral',
        'purple'    => 'purple',
        'indigo'    => 'purple',
        'accent'    => 'accent',
    ];
@endphp

<div class="ts-kpi-row">
    @foreach($cards as $c)
        @php
            [$label, $value, $color, $icon, $sub] = array_pad((array) $c, 5, null);
            $tone = $tonePeta[strtolower((string) $color)] ?? 'neutral';
        @endphp
        <div class="ts-kpi">
            @if($icon)
                <span class="ts-kpi-ic ts-tone-{{ $tone }}" aria-hidden="true"><i class="{{ $icon }}"></i></span>
            @endif
            <span class="ts-kpi-text">
                <span class="ts-kpi-label">{{ $label }}</span>
                <span class="ts-kpi-value">{{ $value }}</span>
                @if($sub)
                    <span class="ts-kpi-sub">{{ $sub }}</span>
                @endif
            </span>
        </div>
    @endforeach
</div>
