@extends('layouts.app')

@php $isEdit = $fund->exists; @endphp

@section('title', $isEdit ? 'Ubah Rekening' : 'Tambah Rekening')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger rounded-0 shadow-sm mb-0"><ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="tw:sr-only">
            <h1>{{ $isEdit ? 'Ubah Rekening' : 'Tambah Rekening' }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('bank-accounts.index') }}">Bank Accounts</a></li>
                <li class="breadcrumb-item active">{{ $isEdit ? 'Ubah' : 'Tambah' }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="{{ $isEdit ? 'Ubah Rekening' : 'Tambah Rekening' }}"
            description="Saldo awal diatur terpisah dari halaman detail rekening (perlu verifikasi)." />

        <x-tw.card>
            <form method="POST" action="{{ $isEdit ? route('bank-accounts.update', $fund->id) : route('bank-accounts.store') }}">
                @csrf
                @if($isEdit) @method('PUT') @endif
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Kode Rekening</label>
                        <input type="text" name="code" value="{{ old('code', $fund->code) }}" class="form-control form-control-sm" maxlength="30" placeholder="Otomatis bila kosong">
                    </div>
                    <div class="form-group col-md-5">
                        <label>Nama Rekening <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $fund->name) }}" class="form-control form-control-sm" required maxlength="255">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Tipe <span class="text-danger">*</span></label>
                        <select name="account_type" class="form-control form-control-sm" required>
                            @foreach($types as $k => $label)
                                <option value="{{ $k }}" @selected(old('account_type', $fund->account_type) === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Bank</label>
                        <select name="bank_id" class="form-control form-control-sm">
                            <option value="">— tidak ada —</option>
                            @foreach($banks as $b)
                                <option value="{{ $b->id }}" @selected((int) old('bank_id', $fund->bank_id) === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Nomor Rekening</label>
                        <input type="text" name="no_account" value="{{ old('no_account', $fund->no_account) }}" class="form-control form-control-sm" maxlength="50">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Pemegang Rekening</label>
                        <input type="text" name="account_holder" value="{{ old('account_holder', $fund->account_holder) }}" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Cabang</label>
                        <input type="text" name="branch" value="{{ old('branch', $fund->branch) }}" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="form-group col-md-2">
                        <label>Mata Uang <span class="text-danger">*</span></label>
                        <input type="text" name="currency" value="{{ old('currency', $fund->currency ?? 'IDR') }}" class="form-control form-control-sm" maxlength="3" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control form-control-sm" required>
                            @foreach(['ACTIVE' => 'Aktif', 'INACTIVE' => 'Nonaktif'] as $k => $label)
                                <option value="{{ $k }}" @selected(old('status', $fund->status ?: 'ACTIVE') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-12">
                        <label>Keterangan</label>
                        <textarea name="description" rows="2" class="form-control form-control-sm" maxlength="1000">{{ old('description', $fund->description) }}</textarea>
                    </div>
                </div>
                <div class="d-flex" style="gap:8px;">
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                    <a href="{{ $isEdit ? route('bank-accounts.show', $fund->id) : route('bank-accounts.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
                </div>
            </form>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
