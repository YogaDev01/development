{{-- Detail + riwayat satu tugas. Dirender sebagai partial (AJAX) untuk modal
     di papan Assignment. Variabel: $assignment (dengan logs, attachments,
     assigner, assignee ter-load). --}}
<div class="mb-3">
    <h5 style="font-weight:700;color:var(--text);">{{ $assignment->title }}</h5>
    <span class="badge badge-{{ $assignment->status_color }}">{{ $assignment->status_label }}</span>
    <span class="badge badge-{{ $assignment->priority_color }}">Prioritas {{ $assignment->priority_label }}</span>
    @if ($assignment->due_date)
        <span class="badge {{ $assignment->is_overdue ? 'badge-danger' : 'badge-light' }}">
            <i class="fas fa-calendar-alt mr-1"></i>{{ $assignment->due_date->format('d/m/Y') }}
        </span>
    @endif
</div>

@if ($assignment->description)
    <div class="mb-3" style="white-space:pre-line;font-size:13px;color:var(--text);">{{ $assignment->description }}</div>
@endif

<div class="row mb-3" style="font-size:13px;">
    <div class="col-md-6"><span class="text-muted">Pemberi:</span> <strong>{{ $assignment->assigner->name ?? '—' }}</strong></div>
    <div class="col-md-6"><span class="text-muted">Penerima:</span> <strong>{{ $assignment->assignee->name ?? '—' }}</strong></div>
</div>

@if ($assignment->attachments->isNotEmpty())
    <div class="mb-3">
        <div class="font-weight-bold mb-1" style="font-size:13px;"><i class="fas fa-paperclip mr-1"></i>Lampiran</div>
        <ul class="list-unstyled mb-0" style="font-size:13px;">
            @foreach ($assignment->attachments as $att)
                <li>
                    <a href="{{ $att->url }}" target="_blank" rel="noopener">
                        <i class="fas fa-file mr-1"></i>{{ $att->original_name }}
                    </a>
                    <span class="text-muted">({{ number_format($att->size / 1024, 0) }} KB)</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="font-weight-bold mb-2" style="font-size:13px;"><i class="fas fa-history mr-1"></i>Riwayat</div>
@if ($assignment->logs->isEmpty())
    <div class="text-muted" style="font-size:13px;">Belum ada riwayat.</div>
@else
    <ul class="list-unstyled mb-0">
        @foreach ($assignment->logs as $log)
            <li class="mb-2" style="font-size:13px;border-left:3px solid var(--border);padding-left:10px;">
                <span class="badge badge-{{ $log->action_color }}">{{ $log->action_label }}</span>
                <span class="text-muted">oleh</span> <strong>{{ $log->actor_name ?? '—' }}</strong>
                @if ($log->actor_role)<span class="text-muted">({{ $log->actor_role }})</span>@endif
                <span class="text-muted"> · {{ $log->created_at?->format('d/m/Y H:i') }}</span>
                @if ($log->notes)
                    <div class="text-muted" style="margin-top:2px;">“{{ $log->notes }}”</div>
                @endif
            </li>
        @endforeach
    </ul>
@endif
