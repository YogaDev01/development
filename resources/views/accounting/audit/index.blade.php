@extends('layouts.app')

@section('title', 'Journal Audit Trail')

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Journal Audit Trail</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">Journal Audit Trail</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Accounting" title="Journal Audit Trail"
            description="Jejak perubahan jurnal, mapping, akun, dan periode: siapa, kapan, apa, nilai sebelum/sesudah, dan alasannya. Hanya baca." />

        <x-tw.card padding="sm">
            <form method="GET" class="form-row align-items-end">
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Objek</label>
                    <select name="entity" class="form-control form-control-sm"><option value="">Semua</option>
                        @foreach($entities as $k => $l)<option value="{{ $k }}" @selected(request('entity') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Aksi</label>
                    <select name="action" class="form-control form-control-sm"><option value="">Semua</option>
                        @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">User</label>
                    <select name="user_id" class="form-control form-control-sm"><option value="">Semua</option>
                        @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->username }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">No. Jurnal</label><input name="number" value="{{ request('number') }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Dari</label><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Sampai</label><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm"></div>
                <div class="form-group col-12 mb-2"><button class="btn btn-sm btn-primary">Filter</button></div>
            </form>
        </x-tw.card>

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr><th>Waktu</th><th>User</th><th>Objek</th><th>Aksi</th><th>Status</th><th>Alasan</th><th>Sebelum → Sesudah</th></tr></thead>
                    <tbody>
                    @forelse($logs as $l)
                        <tr>
                            <td class="text-nowrap">{{ $l->created_at?->format('d M Y H:i') }}</td>
                            <td>{{ $l->user->username ?? '-' }}</td>
                            <td class="text-nowrap">
                                @if($l->entity_type === 'journal')<a href="{{ route('accounting.journal.show', $l->entity_id) }}">{{ $numbers[$l->entity_id] ?? 'Jurnal #' . $l->entity_id }}</a>
                                @else{{ $entities[$l->entity_type] ?? $l->entity_type }} #{{ $l->entity_id }}@endif
                            </td>
                            <td>{{ $l->action }}</td>
                            <td class="text-nowrap">{{ $l->from_status ? $l->from_status . ' → ' . ($l->to_status ?? '-') : ($l->to_status ?? '') }}</td>
                            <td>{{ $l->reason }}</td>
                            <td>
                                @if($l->old_values || $l->new_values)
                                <details><summary class="text-primary" style="cursor:pointer">Lihat</summary>
                                    <pre class="mb-0 small" style="max-width:420px;white-space:pre-wrap">{{ json_encode(['sebelum' => $l->old_values, 'sesudah' => $l->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="Belum ada catatan" message="Perubahan akuntansi akan tercatat di sini." /></td></tr>
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
