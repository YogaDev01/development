{{-- Panel tahap — bagian bawah tab Summary (desktop) dan panel Action (mobile).
     Isinya mengikuti tahap tiket; server tetap memeriksa ulang setiap aksi.
     Variabel dari show.blade: $t + flag tombol. --}}

{{-- Settlement (20 Okt 2026) tidak lagi jadi panel sendiri: tombolnya
     disisipkan ke DALAM panel tahap lewat _settle_button.blade.php, menyatu
     dengan Swipe for travel / Form Close / Ubah Tanggal. $adaPanelTahap
     menjaga agar tiket yang tahapnya tidak memunculkan panel apa pun
     (mis. sudah Closed) tetap punya pintu Settlement. --}}
@php
    $adaPanelTahap = $t->status === 'PLAN'
        || ($bisaBerangkat && $t->status === 'TRIP')
        || $dalamJalan
        || $bisaMulai
        || ($bisaTutup && $t->status !== 'PLAN' && ! ($t->status === 'TRIP' && ($bisaBerangkat || $dalamJalan)))
        || $t->status === 'PROGRESS_DONE';
@endphp

@if($t->status === 'PLAN')
    <div class="tt-stage">
        <div class="tt-stage-title">Plan visit {{ $t->visit_site?->format('d/m/Y') ?? '—' }}</div>
        {{-- Ringkasan biaya & nomor CR TIDAK diulang di sini (20 Okt 2026):
             keduanya sudah terbaca di timeline chat, tab Log, dan tab Tiket.
             Panel ini khusus untuk hal yang masih perlu dikerjakan. --}}

        {{-- Approval plan & biaya oleh supervisor (20 Okt 2026). Tiket tetap di
             tahap Plan; approve membuat Cost Request berstatus Draft. --}}
        @if($t->plan_budget_status === \App\Models\TroubleTicket::PLAN_BUDGET_PENDING)
            @if($canApprovePlanBudget)
                {{-- Satu pintu: modal review memuat rincian lengkap beserta
                     tombol Approve dan Reject sekaligus (20 Okt 2026). --}}
                {{-- Jarak antar tombol panel ini SELALU dari mt-2 tombol di
                     bawahnya, tidak pernah dari mb-2 tombol di atasnya — supaya
                     satu aturan saja yang menentukan dan jaraknya seragam. --}}
                <button type="button" class="btn btn-success btn-sm btn-block tt-btn-wide" data-toggle="modal" data-target="#mdlTtPlanReview">
                    <i class="fas fa-clipboard-check mr-1"></i> Review Plan &amp; Budget
                </button>
            @else
                <div class="tt-stage-note mb-2">Menunggu approval supervisor atas tanggal &amp; biaya plan.</div>
            @endif
        @elseif($t->plan_budget_status === \App\Models\TroubleTicket::PLAN_BUDGET_REJECTED)
            <div class="tt-stage-note text-danger mb-2">
                Plan &amp; biaya ditolak{{ $t->plan_budget_notes ? ': ' . $t->plan_budget_notes : '' }}. Perbaiki lalu simpan lagi.
            </div>
        @endif
        @if($bisaTutup)
            <button type="button" class="btn btn-success btn-sm btn-block tt-btn-wide {{ $canApprovePlanBudget ? 'mt-2' : '' }}" data-toggle="modal" data-target="#mdlTtClosure">
                <i class="fas fa-flag-checkered mr-1"></i> Form Close
            </button>
        @endif
        {{-- Ubah tanggal plan: supervisor saja (keputusan 19 Okt 2026). --}}
        @if($canCreatePlan)
            <button type="button" class="btn btn-outline-primary btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtPlan">
                <i class="fas fa-calendar-alt mr-1"></i> Ubah Tanggal
            </button>
        @endif
        @include('trouble-ticket._settle_button')
        {{-- Tanggal plan sudah tiba: tiket TIDAK naik sendiri ke tahap Trip
             (keputusan 19 Okt 2026) — teknisi yang menggesernya, dan geseran
             itu sekaligus memulai perjalanan (keputusan 20 Okt 2026). Geser
             penuh membuka modal evidence perjalanan yang wajib diisi. Swipe
             dan keterangannya ditaruh paling bawah panel (20 Okt 2026) supaya
             keduanya menyatu, bukan terpisah oleh tombol lain. --}}
        @if($bisaBerangkat)
            <div class="tt-swipe mt-2" data-modal="#mdlTtTravel">
                <span class="tt-swipe-knob" tabindex="0" role="button" aria-label="Geser untuk mulai perjalanan"><i class="fas fa-arrow-right"></i></span>
                <span class="tt-swipe-text">Swipe for travel</span>
            </div>
            <div class="tt-stage-note">Geser Swipe for travel untuk berangkat — tiket masuk tahap Trip.</div>
        @endif
    </div>
@endif

{{-- Tahap Plan punya swipe-nya sendiri di panel atas; panel ini untuk tiket
     yang sudah berada di tahap Trip tapi belum berangkat. --}}
@if($bisaBerangkat && $t->status === 'TRIP')
    <div class="tt-stage">
        {{-- Geser penuh membuka modal evidence perjalanan: evidence wajib
             (15 Okt 2026), jadi geser saja tidak langsung mengirim. --}}
        <div class="tt-swipe" data-modal="#mdlTtTravel">
            <span class="tt-swipe-knob" tabindex="0" role="button" aria-label="Geser untuk mulai perjalanan"><i class="fas fa-arrow-right"></i></span>
            <span class="tt-swipe-text">Swipe for travel</span>
        </div>
        {{-- Selebar panel (bukan separuh baris) tapi tetap ramping — tombol
             setinggi swipe terasa terlalu besar. Di tahap Trip form close
             tertutup bagi TEKNISI tiket ini (keputusan 20 Okt 2026); peran
             lain tetap bisa menutup — lihat canClose(). --}}
        @if($bisaTutup)
            <button type="button" class="btn btn-success btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtClosure">
                <i class="fas fa-flag-checkered mr-1"></i> Form Close
            </button>
        @endif
        @include('trouble-ticket._settle_button')
    </div>
@endif

@if($dalamJalan)
    <div class="tt-stage">
        <form method="POST" action="{{ route('trouble-ticket.arrive', $t) }}" class="tt-confirm m-0"
              data-title="Konfirmasi On Site?" data-text="Pastikan site yang Anda datangi sudah benar.">
            @csrf
            <button class="btn btn-success btn-sm btn-block tt-btn-wide"><i class="fas fa-map-marker-alt mr-1"></i> On Site</button>
        </form>
        <button type="button" class="btn btn-outline-danger btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtCancelTrip">
            <i class="fas fa-ban mr-1"></i> Cancel Visit
        </button>
        {{-- Form close disembunyikan dari TEKNISI selama perjalanan dan
             setelah On Site (keputusan 15 Okt 2026); peran lain tetap bisa
             menutup tiket dari tahap ini. --}}
        @if($bisaTutup)
            <button type="button" class="btn btn-success btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtClosure">
                <i class="fas fa-flag-checkered mr-1"></i> Form Close
            </button>
        @endif
        <div class="tt-stage-note">Cancel Visit membatalkan perjalanan dan menjadwalkan ulang tanggal visit.</div>
        @include('trouble-ticket._settle_button')
    </div>
@endif

@if($bisaMulai)
    <div class="tt-stage">
        <form method="POST" action="{{ route('trouble-ticket.start', $t) }}" id="frmTtStart" class="tt-submit-once m-0">@csrf</form>
        <div class="tt-swipe" data-form="frmTtStart" data-confirm="Mulai progres?" data-text="Pengerjaan di site dimulai.">
            <span class="tt-swipe-knob" tabindex="0" role="button" aria-label="Geser untuk mulai progres"><i class="fas fa-arrow-right"></i></span>
            <span class="tt-swipe-text">Ready Progress</span>
        </div>
        {{-- Setelah On Site TIDAK ada form close (keputusan 19 Okt 2026):
             pengerjaan di site harus dimulai lebih dulu, lalu ditutup dari
             tahap In Progress. --}}
        @include('trouble-ticket._settle_button')
    </div>
@endif

{{-- Tahap Plan selalu punya panelnya sendiri di atas (jadwal + Form Close);
     tahap Trip hanya bila panel teknisinya ikut dirender — peran lain yang
     tidak melihat panel itu tetap butuh pintu Form Close di sini. --}}
@if($bisaTutup && $t->status !== 'PLAN' && ! ($t->status === 'TRIP' && ($bisaBerangkat || $dalamJalan)))
    <div class="tt-stage">
        <div class="tt-stage-title">{{ match ($t->status) { 'OPEN', 'REMOTE' => 'Jalur penanganan', 'TRIP' => 'Perjalanan ke site', default => 'Pengerjaan di site' } }}</div>
        @if($t->status === 'REMOTE' && $bisaPlan)
            {{-- Di tahap Remote teknisi masih boleh berbalik menjadwalkan
                 kunjungan. Ini SATU-SATUNYA pintu Create Plan di tahap ini
                 (20 Okt 2026): dulu supervisor dapat panel "Plan visit"
                 tersendiri di bawah, sehingga tombolnya tampil dua kali. --}}
            <div class="tt-stage-acts">
                <button type="button" class="btn btn-success btn-sm btn-block" data-toggle="modal" data-target="#mdlTtClosure">
                    <i class="fas fa-flag-checkered mr-1"></i> Form Close
                </button>
                <button type="button" class="btn btn-primary btn-sm btn-block mt-0" data-toggle="modal" data-target="#mdlTtPlan">
                    <i class="fas fa-calendar-check mr-1"></i> Create Plan
                </button>
            </div>
        <div class="tt-stage-note">Create Plan untuk menentukan tanggal visit.</div>
        @else
            <button type="button" class="btn btn-success btn-sm btn-block" data-toggle="modal" data-target="#mdlTtClosure">
                <i class="fas fa-flag-checkered mr-1"></i> Form Close
            </button>
        @endif
        @include('trouble-ticket._settle_button')
    </div>
@endif

{{-- Panel "Plan visit" tersendiri untuk supervisor DIHAPUS 20 Okt 2026:
     satu-satunya tahap yang menyisakannya adalah Remote, dan panel "Jalur
     penanganan" di atas sudah memuat Create Plan di sana — dua panel berarti
     dua tombol untuk aksi yang sama. --}}

@if($t->status === 'PROGRESS_DONE')
    <div class="tt-stage">
        <div class="tt-stage-title">Waiting approval supervisor</div>
        @if($canApproveClose)
            <button type="button" class="btn btn-success btn-sm btn-block" data-toggle="modal" data-target="#mdlTtApprove">
                <i class="fas fa-check mr-1"></i> Close
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm btn-block mt-2" data-toggle="modal" data-target="#mdlTtReject">
                <i class="fas fa-undo mr-1"></i> Reject Close
            </button>
        @else
            <div class="tt-stage-note">Form close dikirim {{ $t->progress_done_at?->format('d/m/Y H:i') }}.</div>
        @endif
        @include('trouble-ticket._settle_button')
    </div>
@endif

{{-- Tiket yang tahapnya tidak memunculkan panel apa pun — mis. sudah Closed —
     tetap perlu pintu Settlement selama CR-nya belum dipertanggungjawabkan. --}}
@if(($canSettle ?? false) && ! $adaPanelTahap)
    <div class="tt-stage">
        <div class="tt-stage-title">Settlement</div>
        @include('trouble-ticket._settle_button')
    </div>
@endif
