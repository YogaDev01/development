@extends('layouts.app')

@section('title', 'Transfer Baru')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger rounded-0 shadow-sm mb-0"><ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="tw:sr-only">
            <h1>Transfer Baru</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('cash-transfers.index') }}">Internal Transfer</a></li>
                <li class="breadcrumb-item active">Baru</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Internal Transfer Baru"
            description="Menunggu persetujuan pengguna lain sebelum memengaruhi saldo." />

        <x-tw.card>
            <form method="POST" action="{{ route('cash-transfers.store') }}" enctype="multipart/form-data">
                @csrf
                @include('partials.period-lock-field', ['class' => 'mb-3'])
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" class="form-control form-control-sm" required></div>
                    <div class="form-group col-md-4"><label>Dari Rekening <span class="text-danger">*</span></label>
                        <select name="from_fund_id" class="form-control form-control-sm" required>
                            <option value="">— pilih —</option>
                            @foreach($funds as $f)<option value="{{ $f->id }}" @selected((int) old('from_fund_id') === $f->id)>{{ $f->name }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-4"><label>Ke Rekening <span class="text-danger">*</span></label>
                        <select name="to_fund_id" class="form-control form-control-sm" required>
                            <option value="">— pilih —</option>
                            @foreach($funds as $f)<option value="{{ $f->id }}" @selected((int) old('to_fund_id') === $f->id)>{{ $f->name }}</option>@endforeach
                        </select></div>
                    <div class="form-group col-md-4"><label>Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="amount" value="{{ old('amount') }}" class="form-control form-control-sm" required placeholder="10.000.000"></div>
                    <div class="form-group col-md-4"><label>Biaya Transfer (Rp)</label>
                        <input type="text" name="fee" value="{{ old('fee', '0') }}" class="form-control form-control-sm"></div>
                    <div class="form-group col-md-4"><label>Lampiran (PDF/JPG/PNG)</label>
                        <input type="file" name="attachment" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png"></div>
                    <div class="form-group col-12"><label>Keterangan</label>
                        <input type="text" name="description" value="{{ old('description') }}" class="form-control form-control-sm" maxlength="255"></div>
                </div>
                <button class="btn btn-primary btn-sm">Ajukan</button>
                <a href="{{ route('cash-transfers.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
            </form>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
