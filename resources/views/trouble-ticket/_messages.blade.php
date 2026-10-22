{{-- Item timeline Trouble Ticket. Variabel:
       $messages : koleksi TroubleTicketMessage (urut lama → baru)
       $ctx      : ['tech' => [user id teknisi tiket ini], 'sup' => [user id leader team]]
     Isi pesan selalu di-escape ({{ }}); tidak ada HTML dari pengguna. --}}
@php
    use App\Models\TroubleTicket;

    $tech = $ctx['tech'] ?? [];
    $sup  = $ctx['sup'] ?? [];
    $ikonEvent = [
        'created' => 'fa-plus-circle', 'assigned' => 'fa-user-plus', 'reassigned' => 'fa-exchange-alt',
        'responded' => 'fa-eye', 'responded_remote' => 'fa-satellite-dish',
        'remote_started' => 'fa-satellite-dish',
        'plan_created' => 'fa-calendar-check', 'plan_updated' => 'fa-calendar-alt', 'trip_due' => 'fa-route',
        'plan_budget_approved' => 'fa-file-invoice-dollar', 'plan_budget_rejected' => 'fa-times-circle',
        'cost_request_paid' => 'fa-money-bill-wave',
        'request_submitted' => 'fa-paper-plane', 'request_edited' => 'fa-pen', 'request_approved' => 'fa-check-circle',
        'request_rejected' => 'fa-times-circle', 'request_cancelled' => 'fa-ban',
        'travel_started' => 'fa-car', 'travel_cancelled' => 'fa-ban',
        'chat_stopped' => 'fa-comment-slash', 'chat_reopened' => 'fa-comments',
        'arrived_on_site' => 'fa-map-marker-alt', 'progress_started' => 'fa-tools',
        'progress_done' => 'fa-clipboard-check', 'close_rejected' => 'fa-undo',
        'closed' => 'fa-flag-checkered',
        // Kejadian alur lama (sebelum 12 Okt 2026) — tetap terbaca di timeline.
        'plan_submitted' => 'fa-route', 'plan_corrected' => 'fa-edit',
        'plan_approved' => 'fa-check-circle', 'plan_rejected' => 'fa-times-circle',
    ];
@endphp
@foreach($messages as $msg)
    @php
        $meta = $msg->metadata ?? [];
        $peran = in_array((int) $msg->user_id, $tech, true) ? 'Teknisi'
            : (in_array((int) $msg->user_id, $sup, true) ? 'Leader Team' : ($msg->actor_role ? ucwords(str_replace('_', ' ', $msg->actor_role)) : null));
    @endphp
    @if($msg->is_event)
        {{-- Sisi kejadian mengikuti peran pemicunya: teknisi di kanan. --}}
        <div class="tt-event {{ $peran === 'Teknisi' ? 'is-tech' : '' }}" data-id="{{ $msg->id }}">
            <span class="tt-event-av">{{ mb_strtoupper(mb_substr($msg->actor_name ?? 'S', 0, 1)) }}{{ mb_strtoupper(mb_substr(\Illuminate\Support\Str::after($msg->actor_name ?? '', ' '), 0, 1)) }}</span>
            <div class="tt-event-box">
                <div class="tt-event-head">
                    <strong>{{ $msg->actor_name ?? 'System' }}</strong>
                    <span class="tt-event-time">· {{ $msg->created_at?->format('d M Y H:i') }}</span>
                </div>
                @php
                    // Kotak hanya dipakai saat kejadian punya keterangan; tanpa
                    // keterangan chip berdiri sendiri.
                    // Baris biaya/CR juga sebuah keterangan, jadi ikut membuat
                    // chip-nya berkotak (20 Okt 2026).
                    $adaBiaya = isset($meta['budget_plan']) || isset($meta['cost_total']) || isset($meta['cost_request']);
                    $adaCatatan = filled($msg->body)
                        || $adaBiaya
                        || (in_array($msg->event, ['progress_done', 'closed'], true) && ! empty($meta['rca']));
                @endphp
                <div class="{{ $adaCatatan ? 'tt-event-card' : '' }}">
                <span class="tt-event-chip">
                <i class="fas {{ $ikonEvent[$msg->event] ?? 'fa-info-circle' }} mr-1" aria-hidden="true"></i>
                {{ $msg->event_label }}
                @switch($msg->event)
                    @case('assigned')
                        → {{ $meta['to_name'] ?? '-' }}
                        @break
                    @case('reassigned')
                        : {{ $meta['from_name'] ?? '-' }} → {{ $meta['to_name'] ?? '-' }}
                        @if(! empty($meta['visit_site']))
                            — Date visit {{ \Illuminate\Support\Carbon::parse($meta['visit_site'])->format('d/m/Y') }}
                        @endif
                        @break
                    @case('plan_created')
                    @case('plan_updated')
                        @if(! empty($meta['visit_site']))
                            — Date visit {{ \Illuminate\Support\Carbon::parse($meta['visit_site'])->format('d/m/Y') }}
                        @endif
                        @break
                    @case('plan_submitted')
                    @case('plan_corrected')
                        : {{ TroubleTicket::VISIT_DECISIONS[$meta['decision'] ?? ''] ?? '-' }}
                        @if(! empty($meta['visit_site']))· visit {{ \Illuminate\Support\Carbon::parse($meta['visit_site'])->format('d/m/Y') }}@endif
                        @if(! empty($meta['target_online']))· target online {{ \Illuminate\Support\Carbon::parse($meta['target_online'])->format('d/m/Y') }}@endif
                        @if(! empty($meta['previous']['decision']))
                            (previously {{ TroubleTicket::VISIT_DECISIONS[$meta['previous']['decision']] ?? $meta['previous']['decision'] }})
                        @endif
                        @break
                    @case('plan_rejected')
                        @if(! empty($meta['previous']['decision']))
                            (previous plan {{ TroubleTicket::VISIT_DECISIONS[$meta['previous']['decision']] ?? $meta['previous']['decision'] }})
                        @endif
                        @break
                @endswitch
                </span>
                {{-- Budget pengajuan & Cost Request yang terbit darinya ditulis
                     di BARIS SENDIRI di bawah chip (20 Okt 2026): menempelkannya
                     ke label membuat satu baris panjang yang sulit dipindai.
                     Rangkaiannya disusun di PHP supaya tidak ada spasi sisa
                     dari Blade. --}}
                @php
                    $bagianBiaya = array_filter([
                        $meta['budget_plan'] ?? null,
                        isset($meta['cost_total']) ? 'Rp ' . number_format((int) $meta['cost_total'], 0, ',', '.') : null,
                        isset($meta['cost_request']) ? 'CR ' . $meta['cost_request'] : null,
                    ]);
                @endphp
                @if($bagianBiaya)
                    <div class="tt-event-note">{{ implode(' · ', $bagianBiaya) }}</div>
                @endif
                {{-- Perubahan supervisor atas pengajuan (Q7): satu baris per perubahan. --}}
                @if(! empty($meta['perubahan']))
                    @php
                        $pb = $meta['perubahan'];
                        $barisUbah = array_merge(
                            array_map(fn ($x) => '+ ' . $x, $pb['ditambah'] ?? []),
                            array_map(fn ($x) => '− ' . $x, $pb['dihapus'] ?? []),
                            array_map(fn ($x) => '~ ' . $x, $pb['diubah'] ?? []),
                            $pb['header'] ?? []
                        );
                    @endphp
                    @foreach($barisUbah as $baris)<div class="tt-event-note">{{ $baris }}</div>@endforeach
                @endif
                @if(in_array($msg->event, ['progress_done', 'closed'], true) && ! empty($meta['rca']))
                    <div class="tt-event-note">RCA: {{ $meta['rca'] }}{{ ! empty($meta['sub_rca']) ? ' / ' . $meta['sub_rca'] : '' }}</div>
                @endif
                @if($msg->body)
                    <div class="tt-event-note">{{ $msg->body }}</div>
                @endif
                </div>
                @include('trouble-ticket._attachments', ['items' => $msg->attachments])
            </div>
        </div>
    @else
        {{-- Sisi gelembung mengikuti PERAN, bukan siapa yang membuka halaman
             (keputusan 15 Okt 2026): teknisi di kanan, peran lain di kiri. --}}
        <div class="tt-msg {{ $peran === 'Teknisi' ? 'is-tech' : '' }}" data-id="{{ $msg->id }}">
            <span class="tt-msg-av">{{ mb_strtoupper(mb_substr($msg->actor_name ?? '?', 0, 1)) }}{{ mb_strtoupper(mb_substr(\Illuminate\Support\Str::after($msg->actor_name ?? '', ' '), 0, 1)) }}</span>
            <div class="tt-msg-col">
                <div class="tt-bubble-head">
                    <span class="tt-bubble-name">{{ $msg->actor_name ?? '-' }}</span>
                    <span class="tt-bubble-time">· {{ $msg->created_at?->format('d M Y H:i') }}</span>
                </div>
                {{-- Pesan teks polos tanpa lampiran tidak diberi kotak. --}}
                <div class="tt-bubble {{ $msg->attachments->isEmpty() ? 'is-plain' : '' }}">
                    @if($msg->body)
                        <div class="tt-bubble-body">{{ $msg->body }}</div>
                    @endif
                    @include('trouble-ticket._attachments', ['items' => $msg->attachments])
                </div>
            </div>
        </div>
    @endif
@endforeach
