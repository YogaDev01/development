@extends('layouts.app')

@section('title', 'Assignment')

@section('content')
<div class="content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Assignment</h1>
                <div style="color:var(--text-faint);font-size:13px;margin-top:2px;">Beri tugas ke rekan kerja dan pantau tugas yang ditugaskan kepada Anda.</div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Workflow</a></li>
                <li class="breadcrumb-item active">Assignment</li>
            </ol>
        </div>

        @include('assignment._board', [
            'assignedToMe' => $assignedToMe,
            'createdByMe'  => $createdByMe,
            'assignees'    => $assignees,
        ])
    </div>
</div>
@endsection
