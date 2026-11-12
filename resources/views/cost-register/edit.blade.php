@extends('layouts.app')

@section('title', 'Edit Expense')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('cost-register.index') }}" class="btn btn-secondary btn-sm mr-3" aria-label="Kembali ke daftar Expense">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Edit Expense {{ $cr->display_code }}</h1>
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

        @php $penolakan = $cr->logs()->where('action', 'rejected')->first(); @endphp
        @if($penolakan)
        <div class="alert alert-warning" style="font-size:12.5px;">
            <i class="fas fa-undo mr-1"></i>
            Ditolak CEO ({{ $penolakan->user_name }}, {{ $penolakan->created_at->format('d M Y H:i') }}):
            <strong>{{ $penolakan->notes }}</strong>
        </div>
        @endif

        @if($cr->status !== 'DRAFT')
        {{-- Edit Superadmin di luar Draft (CostRegister::isEditableBy). --}}
        <div class="alert alert-warning py-2" style="font-size:12.5px;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <strong>Edit Superadmin — Expense berstatus {{ $cr->status }}.</strong>
            Perubahan dicatat rinci (nilai lama &rarr; baru) di Log Expense dan User Activity Log.
            Batch Treasury membaca nominal Expense secara langsung: mengubah rincian/nominal setelah masuk batch
            atau dibayar ikut mengubah angka batch &amp; riwayat pembayaran, tanpa transaksi koreksi.
        </div>
        @endif

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-body">
                <form action="{{ route('cost-register.update', $cr->id) }}" method="POST" enctype="multipart/form-data" id="frmExpense" novalidate>
                    @csrf
                    @method('PUT')

                    @include('cost-register._form')

                    <div class="text-right mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
