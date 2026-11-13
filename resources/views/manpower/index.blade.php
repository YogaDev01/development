@extends('layouts.app')

@section('title', 'Manpower Request')

@section('content')
@php $badges = \App\Models\ManpowerRequest::STATUS_BADGES; @endphp
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Manpower Request</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">HRIS</li>
                <li class="breadcrumb-item active">Manpower Request</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Recruitment Management" title="Manpower Request" description="Pengajuan kebutuhan pegawai dan status pemenuhannya.">
            <x-slot:actions>
                <x-tw.button href="{{ route('manpower.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Ajukan Kebutuhan</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        @php
            $steps = collect($badges)->map(fn ($c, $st) => [$st, (int) ($counts[$st] ?? 0), $c, $st])->values()->all();
            $groups = [
                ['Fase 1: Pengajuan',   ['DRAFT', 'SUBMITTED', 'REJECTED']],
                ['Fase 2: Persetujuan', ['APPROVED']],
                ['Fase 3: Pemenuhan',   ['PROCESS', 'PARTIALLY DONE', 'DONE', 'CANCEL']],
            ];
        @endphp
        <x-workflow-stepper :steps="$steps" :compact="true" :groups="$groups" table="tblManpower" field="status" />

        @include('partials.list-toolbar', [
            'prefix' => 'mp',
            'filters' => [['id' => 'Dept', 'label' => 'Divisi'], ['id' => 'Requester', 'label' => 'Pemohon']],
            'placeholder' => 'No / pemohon / project',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblManpower" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col" class="text-nowrap">No</th><th scope="col">Pemohon</th><th scope="col">Divisi / Project</th><th scope="col" class="text-nowrap">Dibutuhkan</th>
                        <th scope="col" class="text-center text-nowrap">Headcount</th><th scope="col">Status</th><th scope="col" class="text-right"><span class="sr-only">Aksi</span></th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $m)
                        @php $ds = $m->displayStatus(); $hc = $m->details->where('status', '!=', 'CANCELED'); @endphp
                        <tr data-status="{{ $ds }}" data-dept="{{ $m->department->name ?? '-' }}" data-requester="{{ $m->requester->name ?? '-' }}">
                            <td class="text-nowrap" data-order="{{ $m->request_date->format('Y-m-d') }}-{{ str_pad($m->id, 8, '0', STR_PAD_LEFT) }}"><a href="{{ route('manpower.show', $m) }}" class="font-weight-bold">{{ $m->request_no }}</a><div class="small text-muted">{{ $m->request_date->format('d M Y') }}</div></td>
                            <td>{{ $m->requester->name ?? '-' }}</td>
                            <td>{{ $m->department->name ?? '-' }}<div class="small text-muted">{{ $m->project->name ?? '' }}</div></td>
                            <td class="text-nowrap" data-order="{{ $m->needed_date?->format('Y-m-d') }}">{{ $m->needed_date?->format('d M Y') ?? '-' }}</td>
                            <td class="text-center text-nowrap">{{ $hc->where('status', 'DONE')->count() }} / {{ $hc->count() }}</td>
                            <td><span class="badge badge-{{ $badges[$ds] ?? 'secondary' }}">{{ $ds }}</span></td>
                            <td class="text-right"><a href="{{ route('manpower.show', $m) }}" class="btn btn-sm btn-outline-secondary" title="Lihat"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="Belum ada pengajuan" message="Pengajuan kebutuhan pegawai akan tampil di sini." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>
    </div>
  </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

@push('scripts')
<script src="@aset('js/tesys-list-table.js')"></script>
<script>
$(function () {
    TesysListTable.init({
        table: '#tblManpower', order: [[0, 'desc']], search: '#mpSearch', clear: '#mpClear',
        filters: [
            { target: '#mpFilterDept', key: 'dept', label: 'Semua Divisi' },
            { target: '#mpFilterRequester', key: 'requester', label: 'Semua Pemohon' }
        ]
    });
});
</script>
@endpush
