@extends('layouts.app')

@section('title', 'Adjustment Baru')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger rounded-0 shadow-sm mb-0"><ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="tw:sr-only">
            <h1>Adjustment Baru</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('cash-adjustments.index') }}">Cash Adjustment</a></li>
                <li class="breadcrumb-item active">Baru</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Cash Adjustment Baru"
            description="Menunggu persetujuan pengguna lain. Biaya Bank selalu keluar dan Bunga selalu masuk." />

        <x-tw.card>
            <form method="POST" action="{{ route('cash-adjustments.store') }}" enctype="multipart/form-data">
                @csrf
                @include('partials.period-lock-field', ['class' => 'mb-3'])
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Rekening <span class="text-danger">*</span></label>
                        <select name="source_fund_id" class="form-control form-control-sm" required>
                            <option value="">— pilih —</option>
                            @foreach($funds as $f)<option value="{{ $f->id }}" @selected((int) old('source_fund_id') === $f->id)>{{ $f->name }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-3"><label>Tanggal Efektif <span class="text-danger">*</span></label>
                        <input type="date" name="effective_date" value="{{ old('effective_date', now()->toDateString()) }}" class="form-control form-control-sm" required></div>
                    <div class="form-group col-md-5"><label>Jenis <span class="text-danger">*</span></label>
                        <select name="type" class="form-control form-control-sm" required>
                            @foreach($types as $k => [$label, $dir])<option value="{{ $k }}" @selected(old('type') === $k)>{{ $label }}{{ $dir ? ' (' . ($dir === 'IN' ? 'masuk' : 'keluar') . ')' : '' }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-3"><label>Arah (bila jenis tidak menentukan)</label>
                        <select name="direction" class="form-control form-control-sm">
                            <option value="">—</option>
                            <option value="IN" @selected(old('direction') === 'IN')>Masuk (menambah saldo)</option>
                            <option value="OUT" @selected(old('direction') === 'OUT')>Keluar (mengurangi saldo)</option>
                        </select></div>
                    <div class="form-group col-md-3"><label>Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="amount" value="{{ old('amount') }}" class="form-control form-control-sm" required></div>
                    <div class="form-group col-md-6"><label>Lampiran (PDF/JPG/PNG)</label>
                        <input type="file" name="attachment" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png"></div>
                    <div class="form-group col-12"><label>Alasan <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="2" class="form-control form-control-sm" required minlength="5" maxlength="500">{{ old('reason') }}</textarea></div>
                </div>
                <button class="btn btn-primary btn-sm">Ajukan</button>
                <a href="{{ route('cash-adjustments.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
            </form>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
