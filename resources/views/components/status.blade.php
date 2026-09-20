{{--
    <x-status> — badge status workflow.

    Menggantikan pola `badge badge-{{ $warna }}` yang peta warnanya ditulis
    ulang di tiap halaman. Label, keterangan, dan nada warna diambil dari
    App\Support\WorkflowStatus.

    Props:
      status      : kode status mentah, mis. 'SUBMITTED'
      context     : 'budget-request' memakai label dari BudgetRequest::statusLabelFor()
      description : true  -> keterangan tampil sebagai baris kedua
                    false -> keterangan hanya jadi tooltip (default)
      size        : 'sm' | 'md' (default 'sm')

    Atribut lain diteruskan apa adanya, sehingga pemanggil tetap bisa
    menambahkan class/data-* miliknya (mis. pemicu modal detail).

    Contoh:
      <x-status :status="$cr->status" context="budget-request" />
      <x-status :status="$cr->status" context="budget-request" :description="true" />
--}}
@props([
    'status'      => null,
    'context'     => 'default',
    'description' => false,
    'size'        => 'sm',
])

@php
    $meta = \App\Support\WorkflowStatus::meta($status, $context);
    $judul = $meta['description'] ? $meta['label'] . ' — ' . $meta['description'] : $meta['label'];
@endphp

@if($description && $meta['description'])
    <span {{ $attributes->merge(['class' => 'ts-status-block']) }}>
        <span class="ts-status ts-status-{{ $meta['tone'] }} ts-status-{{ $size }}">{{ $meta['label'] }}</span>
        <span class="ts-status-desc">{{ $meta['description'] }}</span>
    </span>
@else
    <span {{ $attributes->merge([
        'class' => 'ts-status ts-status-' . $meta['tone'] . ' ts-status-' . $size,
        'title' => $judul,
    ]) }}>{{ $meta['label'] }}</span>
@endif
