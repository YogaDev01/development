{{--
    <x-workflow-flow> — perjalanan satu dokumen di dalam alurnya.

    Menampilkan tahap yang sudah dilewati, tahap sekarang, dan tahap yang
    belum — plus keadaan berhenti untuk dokumen yang ditolak/dibatalkan.
    Bentuknya sama di semua modul (Cost Request, Budget Plan, Invoice,
    Treasury), karena urutannya diambil dari App\Support\WorkflowStatus dan
    digambar oleh satu renderer: public/js/tesys-workflow.js.

    Ini BUKAN <x-workflow-stepper>. Stepper menunjukkan JUMLAH dokumen per
    status dan dipakai untuk menyaring tabel; komponen ini menunjukkan
    posisi SATU dokumen.

    Props:
      status  : kode status dokumen, mis. 'SUBMITTED'
      context : modulnya — 'budget-request' (default), 'budget-plan',
                'invoice', 'daily-transaction'

    Contoh:
      <x-workflow-flow :status="$cr->status" context="budget-request" />
--}}
@props([
    'status'  => null,
    'context' => 'budget-request',
])

@php
    $tahapAlur = \App\Support\WorkflowStatus::flowSteps($context);
@endphp

@if(!empty($tahapAlur))
    {{-- Definisi tahap cukup dikirim SEKALI per konteks, meski komponennya
         dipakai di banyak baris pada halaman yang sama. --}}
    @once('ts-flow-' . $context)
        @push('scripts')
        <script>
            if (window.TesysWorkflow) {
                window.TesysWorkflow.define(@json($context), @json($tahapAlur));
            }
        </script>
        @endpush
    @endonce

    <div {{ $attributes->merge(['class' => 'ts-flow-slot']) }}
         data-ts-flow="{{ $context }}"
         data-ts-status="{{ $status }}"></div>
@endif
