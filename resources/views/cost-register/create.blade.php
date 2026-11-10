@extends('layouts.app')

@section('title', 'Tambah Expense')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('cost-register.index') }}" class="btn btn-secondary btn-sm mr-3" aria-label="Kembali ke daftar Expense">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah Expense</h1>
            </div>
        </div>

        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-body">
                <form action="{{ route('cost-register.store') }}" method="POST" enctype="multipart/form-data" id="frmExpense" novalidate>
                    @csrf

                    @include('cost-register._form')

                    <div class="text-right mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Draft</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
