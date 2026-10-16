{{-- Panel tahap — bagian bawah tab Summary (desktop) dan panel Action (mobile).
     Isinya mengikuti tahap tiket; server tetap memeriksa ulang setiap aksi.
     Variabel dari show.blade: $t + flag tombol. --}}

@if($bisaRemote)
    <div class="tt-stage">
        <div class="tt-stage-title">Jalur Penanganan</div>
        {{-- Satu tombol = satu baris penuh, seirama panel tahap lain. Dulu ia
             duduk di grid dua kolom .tt-stage-acts, jadi lebarnya separuh.
             Tanpa dialog konfirmasi: Remote tidak menutup tiket dan masih bisa
             berbalik ke Create Plan, jadi satu ketukan cukup. --}}
        <form method="POST" action="{{ route('trouble-ticket.remote', $t) }}" class="tt-submit-once m-0">
            @csrf
            <button class="btn btn-outline-primary btn-sm btn-block tt-btn-wide"><i class="fas fa-satellite-dish mr-1"></i> Remote</button>
        </form>
        <div class="tt-stage-note">Remote = penanganan jarak jauh tanpa kunjungan. Bila belum Solved, Create Plan untuk visit.</div>
    </div>
@endif

@if($t->status === 'PLAN')
    <div class="tt-stage">
        <div class="tt-stage-title">Plan visit {{ $t->visit_site?->format('d/m/Y') ?? '—' }}</div>
        @if($canActAsTech)
            <div class="tt-stage-acts">
                <button type="button" class="btn btn-outline-primary btn-sm btn-block" data-toggle="modal" data-target="#mdlTtPlan">
                    <i class="fas fa-calendar-alt mr-1"></i> Ubah Tanggal
                </button>
                <button type="button" class="btn btn-success btn-sm btn-block mt-0" data-toggle="modal" data-target="#mdlTtClosure">
                    <i class="fas fa-flag-checkered mr-1"></i> Form Close
                </button>
            </div>
            <div class="tt-stage-note">Ubah Tanggal untuk mengganti tanggal visit.</div>
        @else
            <div class="tt-stage-note">Tiket masuk tahap Trip saat tanggal plan visit tiba.</div>
        @endif
    </div>
@endif

@if($bisaBerangkat)
    <div class="tt-stage">
        {{-- Geser penuh membuka modal evidence perjalanan: evidence wajib
             (15 Okt 2026), jadi geser saja tidak langsung mengirim. --}}
        <div class="tt-swipe" data-modal="#mdlTtTravel">
            <span class="tt-swipe-knob" tabindex="0" role="button" aria-label="Geser untuk mulai perjalanan"><i class="fas fa-arrow-right"></i></span>
            <span class="tt-swipe-text">Swipe for travel</span>
        </div>
        {{-- Selebar panel (bukan separuh baris) tapi tetap ramping — tombol
             setinggi swipe terasa terlalu besar. --}}
        <button type="button" class="btn btn-success btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtClosure">
            <i class="fas fa-flag-checkered mr-1"></i> Form Close
        </button>
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
        @unless($isTechnician)
            <button type="button" class="btn btn-success btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtClosure">
                <i class="fas fa-flag-checkered mr-1"></i> Form Close
            </button>
        @endunless
        <div class="tt-stage-note">Cancel Visit membatalkan perjalanan dan menjadwalkan ulang tanggal visit.</div>
    </div>
@endif

@if($bisaMulai)
    <div class="tt-stage">
        <form method="POST" action="{{ route('trouble-ticket.start', $t) }}" id="frmTtStart" class="tt-submit-once m-0">@csrf</form>
        <div class="tt-swipe" data-form="frmTtStart" data-confirm="Mulai progres?" data-text="Pengerjaan di site dimulai.">
            <span class="tt-swipe-knob" tabindex="0" role="button" aria-label="Geser untuk mulai progres"><i class="fas fa-arrow-right"></i></span>
            <span class="tt-swipe-text">Ready Progress</span>
        </div>
        {{-- Form close disembunyikan dari TEKNISI selama perjalanan dan
             setelah On Site (keputusan 15 Okt 2026); peran lain tetap bisa
             menutup tiket dari tahap ini. --}}
        @unless($isTechnician)
            <button type="button" class="btn btn-success btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtClosure">
                <i class="fas fa-flag-checkered mr-1"></i> Form Close
            </button>
        @endunless
    </div>
@endif

{{-- Tahap Plan & Trip punya panelnya sendiri di atas (jadwal + Form Close). --}}
@if($bisaTutup && ! in_array($t->status, ['PLAN', 'TRIP'], true))
    <div class="tt-stage">
        <div class="tt-stage-title">{{ $t->status === 'REMOTE' ? 'Jalur penanganan' : 'Pengerjaan di site' }}</div>
        @if($t->status === 'REMOTE')
            {{-- Di tahap Remote teknisi masih boleh berbalik menjadwalkan kunjungan. --}}
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
    </div>
@endif

{{-- Supervisor boleh membuat/mengubah plan juga (keputusan 15 Okt 2026).
     Panel tahap lain milik teknisi, jadi ia dapat panelnya sendiri. --}}
@if($bisaPlan && ! $canActAsTech)
    <div class="tt-stage">
        <div class="tt-stage-title">{{ $t->status === 'PLAN' ? 'Plan visit ' . ($t->visit_site?->format('d/m/Y') ?? '—') : 'Plan visit' }}</div>
        <button type="button" class="btn btn-outline-primary btn-sm btn-block" data-toggle="modal" data-target="#mdlTtPlan">
            <i class="fas fa-calendar-check mr-1"></i> {{ $t->status === 'PLAN' ? 'Ubah Tanggal' : 'Create Plan' }}
        </button>
        <div class="tt-stage-note">{{ $t->status === 'PLAN' ? 'Ubah Tanggal untuk mengganti tanggal visit.' : 'Create Plan untuk menentukan tanggal visit.' }}</div>
    </div>
@endif

@if($t->status === 'PROGRESS_DONE')
    <div class="tt-stage">
        <div class="tt-stage-title">Menunggu approval supervisor</div>
        @if($canApproveClose)
            <button type="button" class="btn btn-success btn-sm btn-block" data-toggle="modal" data-target="#mdlTtApprove">
                <i class="fas fa-check mr-1"></i> Close
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm btn-block mt-2" data-toggle="modal" data-target="#mdlTtReject">
                <i class="fas fa-undo mr-1"></i> Reject Close
            </button>
        @else
            <div class="tt-stage-note">Form close dikirim {{ $t->progress_done_at?->format('d/m/Y H:i') }}. Hanya supervisor yang dapat menyetujui penutupan.</div>
        @endif
    </div>
@endif
