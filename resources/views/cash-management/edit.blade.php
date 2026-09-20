@extends('layouts.app')

@section('title', 'Edit Cash Flow')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                    Edit {{ $cashFlow->code }}
                </h1>
                <small class="text-muted">Hanya dokumen berstatus DRAFT yang bisa diubah.</small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/cash-management') }}">Cash Management</a></li>
                <li class="breadcrumb-item"><a href="{{ route('cash-management.show', $cashFlow->id) }}">{{ $cashFlow->code }}</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>

        <form action="{{ route('cash-management.update', $cashFlow->id) }}" method="POST" id="formCashFlow">
            @csrf
            @method('PUT')

            @include('cash-management._form')

            <div class="d-flex justify-content-end mb-4" style="gap:8px;">
                <a href="{{ route('cash-management.show', $cashFlow->id) }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-save mr-1"></i> Update
                </button>
            </div>
        </form>

    </div>
</div>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
</style>
@endpush
