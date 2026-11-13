@extends('layouts.app')

@section('title', 'Cash Reports')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Cash Reports</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item active">Cash Reports</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Cash Reports"
            description="Laporan kas dan bank. Semua angka berasal dari sumber yang sama dengan Cash Overview dan Cashflow." />

        <div class="row">
            @foreach($reports as $key => $label)
                <div class="col-md-4 mb-3">
                    <x-tw.card padding="sm">
                        <a href="{{ route('cash-reports.show', $key) }}" class="font-weight-bold">{{ $label }}</a>
                    </x-tw.card>
                </div>
            @endforeach
        </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
