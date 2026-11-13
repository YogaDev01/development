@extends('layouts.app')

@section('title', 'Assignment')

@section('content')
<div class="content">
    <div class="container-fluid">
        {{-- Judul & breadcrumb dipindah shell ke topbar (pola Cost Request). --}}
        <div class="tw:sr-only">
            <h1>Assignment</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Workflow</a></li>
                <li class="breadcrumb-item active">Assignment</li>
            </ol>
        </div>
        <x-tw.page-header class="tw:py-4 tw:mb-4" title="Tugas Antar Rekan Kerja"
            description="Beri tugas ke rekan kerja dan pantau tugas yang ditugaskan kepada Anda.">
        </x-tw.page-header>

        @include('assignment._board', [
            'assignedToMe' => $assignedToMe,
            'createdByMe'  => $createdByMe,
            'assignees'    => $assignees,
        ])
    </div>
</div>
@endsection

@push('styles')
{{-- Utilitas tw: (x-tw.*) dimuat per halaman, sama seperti Cost Request. --}}
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
