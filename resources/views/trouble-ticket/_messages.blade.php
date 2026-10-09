{{-- Item timeline Trouble Ticket. Variabel:
       $messages : koleksi TroubleTicketMessage (urut lama → baru)
       $ctx      : ['tech' => [user id teknisi tiket ini], 'sup' => [user id leader team]]
     Isi pesan selalu di-escape ({{ }}); tidak ada HTML dari pengguna. --}}
@php
    use App\Models\TroubleTicket;

    $me   = (int) auth()->id();
    $tech = $ctx['tech'] ?? [];
    $sup  = $ctx['sup'] ?? [];
    $ikonEvent = [
        'created' => 'fa-plus-circle', 'assigned' => 'fa-user-plus', 'reassigned' => 'fa-exchange-alt',
        'responded' => 'fa-eye', 'visit_decided' => 'fa-route', 'visit_corrected' => 'fa-edit',
        'departed' => 'fa-car', 'arrived' => 'fa-map-marker-alt', 'progress_started' => 'fa-tools',
        'closure_requested' => 'fa-flag-checkered', 'closure_approved' => 'fa-check-circle',
        'closure_rejected' => 'fa-times-circle', 'cancelled' => 'fa-ban',
    ];
@endphp
@foreach($messages as $msg)
    @php
        $meta = $msg->metadata ?? [];
        $peran = in_array((int) $msg->user_id, $tech, true) ? 'Teknisi'
            : (in_array((int) $msg->user_id, $sup, true) ? 'Leader Team' : ($msg->actor_role ? ucwords(str_replace('_', ' ', $msg->actor_role)) : null));
    @endphp
    @if($msg->is_event)
        <div class="tt-event" data-id="{{ $msg->id }}">
            <div class="tt-event-box">
                <i class="fas {{ $ikonEvent[$msg->event] ?? 'fa-info-circle' }} mr-1" aria-hidden="true"></i>
                <strong>{{ $msg->event_label }}</strong>
                @switch($msg->event)
                    @case('assigned')
                        → {{ $meta['to_name'] ?? '-' }}
                        @break
                    @case('reassigned')
                        : {{ $meta['from_name'] ?? '-' }} → {{ $meta['to_name'] ?? '-' }}
                        @break
                    @case('visit_decided')
                    @case('visit_corrected')
                        : {{ TroubleTicket::VISIT_DECISIONS[$meta['decision'] ?? ''] ?? '-' }}
                        @if(! empty($meta['visit_site']))· kunjungan {{ \Illuminate\Support\Carbon::parse($meta['visit_site'])->format('d/m/Y') }}@endif
                        @if(! empty($meta['target_online']))· target online {{ \Illuminate\Support\Carbon::parse($meta['target_online'])->format('d/m/Y') }}@endif
                        @if(! empty($meta['previous']['decision']))
                            (sebelumnya {{ TroubleTicket::VISIT_DECISIONS[$meta['previous']['decision']] ?? $meta['previous']['decision'] }})
                        @endif
                        @break
                    @case('arrived')
                        · travel {{ TroubleTicket::formatDuration($meta['travel_seconds'] ?? null) }}
                        @break
                    @case('responded')
                        {{ ! empty($meta['first']) ? '(respons pertama)' : '' }}
                        @break
                    @case('closure_requested')
                    @case('closure_approved')
                    @case('closure_rejected')
                        · pengajuan #{{ $meta['attempt'] ?? '-' }}
                        @if(! empty($meta['rca']))<div class="tt-event-note">RCA: {{ $meta['rca'] }}{{ ! empty($meta['sub_rca']) ? ' / ' . $meta['sub_rca'] : '' }} · Action: {{ $meta['action'] ?? '-' }}</div>@endif
                        @break
                @endswitch
                <span class="text-muted">· {{ $msg->actor_name ?? 'Sistem' }} · {{ $msg->created_at?->format('d/m/Y H:i:s') }}</span>
                @if($msg->body)
                    <div class="tt-event-note">{{ $msg->body }}</div>
                @endif
                @include('trouble-ticket._attachments', ['items' => $msg->attachments])
            </div>
        </div>
    @else
        <div class="tt-msg {{ (int) $msg->user_id === $me ? 'is-mine' : '' }} {{ $peran === 'Teknisi' ? 'is-tech' : '' }}" data-id="{{ $msg->id }}">
            <div class="tt-bubble">
                <div class="tt-bubble-head">
                    <strong>{{ (int) $msg->user_id === $me ? 'Anda' : ($msg->actor_name ?? '-') }}</strong>
                    @if($peran)<span>· {{ $peran }}</span>@endif
                    <span>· {{ $msg->created_at?->format('d/m/Y H:i') }}</span>
                </div>
                @if($msg->body)
                    <div class="tt-bubble-body">{{ $msg->body }}</div>
                @endif
                @include('trouble-ticket._attachments', ['items' => $msg->attachments])
            </div>
        </div>
    @endif
@endforeach
