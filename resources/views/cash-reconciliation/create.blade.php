@extends('layouts.app')

@section('title', 'Rekonsiliasi Baru')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger rounded-0 shadow-sm mb-0"><ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="tw:sr-only">
            <h1>Rekonsiliasi Baru</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('cash-reconciliation.index') }}">Reconciliation</a></li>
                <li class="breadcrumb-item active">Baru</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Rekonsiliasi Baru"
            description="Langkah 1–3: pilih rekening, periode, dan isi saldo akhir sesuai rekening koran. Saldo awal rekening harus sudah disetujui pada awal rekening koran pertama." />

        <x-tw.card>
            <form method="POST" action="{{ route('cash-reconciliation.store') }}">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Rekening <span class="text-danger">*</span></label>
                        <select name="source_fund_id" class="form-control form-control-sm" required>
                            <option value="">— pilih —</option>
                            @foreach($funds as $f)<option value="{{ $f->id }}" @selected((int) old('source_fund_id') === $f->id)>{{ $f->name }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-3"><label>Periode Dari <span class="text-danger">*</span></label>
                        <input type="date" name="period_start" value="{{ old('period_start') }}" class="form-control form-control-sm" required></div>
                    <div class="form-group col-md-3"><label>Periode Sampai <span class="text-danger">*</span></label>
                        <input type="date" name="period_end" value="{{ old('period_end') }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                    <div class="form-group col-md-4"><label>Saldo Akhir Rekening Koran (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="statement_ending_balance" value="{{ old('statement_ending_balance') }}" class="form-control form-control-sm" required placeholder="25.000.000"></div>
                </div>
                <button class="btn btn-primary btn-sm">Buat</button>
                <a href="{{ route('cash-reconciliation.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
            </form>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
