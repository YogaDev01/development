{{-- Log aktivitas tiket: kejadian workflow saja (tanpa pesan chat), terbaru di
     atas. Dipakai tab "Log" di halaman detail DAN modal Info dari daftar
     (keputusan 19 Okt 2026), supaya keduanya tidak pernah bercerita berbeda.

     Variabel: $statusLog (koleksi TroubleTicketMessage bertipe EVENT).
     $tautChat = true hanya di halaman detail, tempat baris log bisa diklik
     untuk melompat ke timeline chat. --}}
@php
    $tautChat = $tautChat ?? false;
    $ikonLog = [
        'created' => 'fa-plus', 'assigned' => 'fa-user-plus', 'reassigned' => 'fa-exchange-alt',
        'responded' => 'fa-eye', 'responded_remote' => 'fa-satellite-dish',
        'remote_started' => 'fa-satellite-dish',
        'plan_created' => 'fa-calendar-check', 'plan_updated' => 'fa-calendar-alt', 'trip_due' => 'fa-route',
        'plan_budget_approved' => 'fa-file-invoice-dollar', 'plan_budget_rejected' => 'fa-times',
        'cost_request_paid' => 'fa-money-bill-wave',
        'request_submitted' => 'fa-paper-plane', 'request_edited' => 'fa-pen', 'request_approved' => 'fa-check-circle',
        'request_rejected' => 'fa-times-circle', 'request_cancelled' => 'fa-ban',
        'request_resubmitted' => 'fa-redo', 'cost_request_status' => 'fa-file-invoice-dollar',
        'settlement_submitted' => 'fa-paper-plane', 'settlement_approved' => 'fa-check-circle',
        'settlement_returned' => 'fa-undo', 'financial_closed' => 'fa-coins',
        'travel_started' => 'fa-car', 'travel_cancelled' => 'fa-ban',
        'arrived_on_site' => 'fa-map-marker-alt', 'progress_started' => 'fa-tools',
        'progress_done' => 'fa-clipboard-check', 'close_rejected' => 'fa-undo',
        'closed' => 'fa-flag-checkered',
        // Kejadian alur lama — tetap terbaca di log tiket yang sudah ada.
        'chat_stopped' => 'fa-comment-slash', 'chat_reopened' => 'fa-comments',
        'plan_submitted' => 'fa-route', 'plan_corrected' => 'fa-edit',
        'plan_approved' => 'fa-check', 'plan_rejected' => 'fa-times',
    ];
@endphp
<ol class="tt-steps">
    @forelse($statusLog as $ev)
        <li class="tt-step" @if($tautChat) data-msg-id="{{ $ev->id }}" title="Klik untuk melihat di timeline chat" @endif>
            <span class="tt-step-ic"><i class="fas {{ $ikonLog[$ev->event] ?? 'fa-circle' }}"></i></span>
            <div class="tt-step-body">
                <div class="tt-step-head">
                    {{-- Jeda antar kejadian (+durasi) dihapus 19 Okt 2026:
                         jam tiap baris sudah cukup, ukurannya dibaca di tab SLA. --}}
                    <strong>{{ $ev->event_label }}</strong>
                </div>
                <div class="tt-step-meta">{{ $ev->actor_name ?? 'System' }} · {{ $ev->created_at?->format('d M Y H:i') }}</div>
                @php $visitLog = in_array($ev->event, ['plan_created', 'plan_updated', 'reassigned'], true) ? ($ev->metadata['visit_site'] ?? null) : null; @endphp
                @if($visitLog)
                    <div class="tt-step-note">Plan Date visit {{ \Illuminate\Support\Carbon::parse($visitLog)->format('d/m/Y') }}</div>
                @endif
                {{-- Budget pengajuan & Cost Request yang terbit darinya (20 Okt
                     2026). Dirangkai di PHP, bukan lewat @if berjajar: .tt-step-note
                     memakai white-space:pre-wrap, jadi indentasi Blade ikut
                     tercetak dan barisnya terlihat menjorok. --}}
                @php
                    $metaLog = $ev->metadata ?? [];
                    $biayaLog = implode(' · ', array_filter([
                        $metaLog['budget_plan'] ?? null,
                        isset($metaLog['cost_total']) ? 'Rp ' . number_format((int) $metaLog['cost_total'], 0, ',', '.') : null,
                        isset($metaLog['cost_request']) ? 'CR ' . $metaLog['cost_request'] : null,
                    ]));
                @endphp
                @if($biayaLog !== '')<div class="tt-step-note">{{ $biayaLog }}</div>@endif
                {{-- Perubahan supervisor atas pengajuan (Q7): baris +/−/~ dan header, total lama → baru ada di keterangan. --}}
                @if(! empty($metaLog['perubahan']))
                    @php
                        $pb = $metaLog['perubahan'];
                        $barisUbah = array_merge(
                            array_map(fn ($x) => '+ ' . $x, $pb['ditambah'] ?? []),
                            array_map(fn ($x) => '− ' . $x, $pb['dihapus'] ?? []),
                            array_map(fn ($x) => '~ ' . $x, $pb['diubah'] ?? []),
                            $pb['header'] ?? []
                        );
                    @endphp
                    @if($barisUbah)<div class="tt-step-note">{{ implode("\n", $barisUbah) }}</div>@endif
                @endif
                @if($ev->event === 'reassigned' && ($ev->metadata['to_name'] ?? null))
                    <div class="tt-step-note">{{ $ev->metadata['from_name'] ?? '—' }} &rarr; {{ $ev->metadata['to_name'] }}</div>
                @endif
                @if($ev->body)<div class="tt-step-note">{{ $ev->event === 'request_edited' ? $ev->body : \Illuminate\Support\Str::limit($ev->body, 140) }}</div>@endif
            </div>
        </li>
    @empty
        <x-empty title="No status changes yet" icon="fas fa-history" compact />
    @endforelse
</ol>
