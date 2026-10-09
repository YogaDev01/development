{{-- Detail + riwayat + komentar satu tugas — tampilan "email thread": header
     ala email (from/to/subject) lalu timeline ala chat (bubble komentar,
     pill status di tengah). Variabel: $assignment (dengan logs, attachments,
     assigner, assignee ter-load). --}}
@php
    $me = auth()->id();
    $initials = function (?string $name) {
        $name = trim((string) $name);
        if ($name === '') return '?';
        $parts = preg_split('/\s+/', $name);
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    };
@endphp

{{-- ===== HEADER ala email: avatar pengirim, from -> to, subjek, meta ===== --}}
<div class="asg-mail-head">
    <div class="asg-chat-av asg-av-lg">{{ $initials($assignment->assigner->name ?? null) }}</div>
    <div class="flex-grow-1 min-w-0">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:6px;">
            <div>
                <div style="font-weight:700;font-size:13px;color:var(--text);">{{ $assignment->assigner->name ?? '—' }}</div>
                <div style="font-size:12px;color:var(--text-faint);">
                    kepada <strong>{{ $assignment->assignee->name ?? '—' }}</strong>
                </div>
            </div>
            <div style="font-size:11px;color:var(--text-faint);white-space:nowrap;">
                {{ $assignment->created_at?->format('d/m/Y H:i') }}
            </div>
        </div>
        <h5 class="mt-2 mb-0" style="font-weight:700;color:var(--text);">{{ $assignment->title }}</h5>
        <div class="mt-1">
            <span class="badge badge-{{ $assignment->status_color }}">{{ $assignment->status_label }}</span>
            <span class="badge badge-{{ $assignment->priority_color }}">Prioritas {{ $assignment->priority_label }}</span>
            @if ($assignment->due_date)
                <span class="badge {{ $assignment->is_overdue ? 'badge-danger' : 'badge-light' }}">
                    <i class="fas fa-calendar-alt mr-1"></i>{{ $assignment->due_date->format('d/m/Y') }}
                </span>
            @endif
        </div>
    </div>
</div>

@if ($assignment->description)
    <div class="asg-mail-body">{{ $assignment->description }}</div>
@endif

@if ($assignment->attachments->isNotEmpty())
    <div class="asg-mail-attachments">
        @foreach ($assignment->attachments as $att)
            <a href="{{ $att->url }}" target="_blank" rel="noopener" class="asg-attachment-chip">
                <i class="fas fa-paperclip"></i>
                <span class="text-truncate">{{ $att->original_name }}</span>
                <span class="text-muted">({{ number_format($att->size / 1024, 0) }} KB)</span>
            </a>
        @endforeach
    </div>
@endif

<hr class="my-3">

{{-- ===== TIMELINE ala chat: event status = pill tengah, komentar = bubble ===== --}}
<div class="asg-chat">
    @forelse ($assignment->logs as $log)
        @if ($log->is_comment)
            @php $mine = $log->actor_id && $me && (int) $log->actor_id === (int) $me; @endphp
            <div class="asg-chat-row {{ $mine ? 'mine' : '' }}">
                <div class="asg-chat-av">{{ $initials($log->actor_name) }}</div>
                <div class="min-w-0">
                    <div class="asg-chat-meta">{{ $mine ? 'Anda' : $log->actor_name }} · {{ $log->created_at?->format('d/m H:i') }}</div>
                    <div class="asg-chat-bubble">{{ $log->notes }}</div>
                </div>
            </div>
        @else
            {{-- Event status (create/read/start_progress/close/cancel/reopen):
                 pill tunggal di tengah, tanpa avatar/role — cukup nama + waktu. --}}
            <div class="asg-event-row">
                <span class="badge badge-{{ $log->action_color }}">{{ $log->action_label }}</span>
                <span class="asg-event-meta">{{ $log->actor_name ?? '—' }} · {{ $log->created_at?->format('d/m/Y H:i') }}</span>
            </div>
        @endif
    @empty
        <div class="text-muted text-center py-3" style="font-size:13px;">Belum ada riwayat.</div>
    @endforelse
</div>

@if ($assignment->can_comment)
    <form method="POST" action="{{ route('assignment.comment', $assignment) }}" class="form-assignment-comment asg-composer">
        @csrf
        <div class="asg-chat-av">{{ $initials(auth()->user()->name ?? null) }}</div>
        <input type="text" name="body" class="form-control form-control-sm asg-composer-input" maxlength="500"
               placeholder="Tulis komentar…" required>
        <button type="submit" class="btn btn-primary btn-sm asg-composer-send"><i class="fas fa-paper-plane"></i></button>
    </form>
@else
    <div class="text-muted text-center" style="font-size:12px;"><i class="fas fa-lock mr-1"></i>Komentar dimatikan — tugas ini sudah {{ strtolower($assignment->status_label) }}.</div>
@endif
