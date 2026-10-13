{{-- Panel tahap — bagian bawah tab Summary (desktop) dan panel Action (mobile).
     Isinya mengikuti tahap tiket; server tetap memeriksa ulang setiap aksi.
     Variabel dari show.blade: $t + flag tombol. --}}

@if($bisaRemote)
    <div class="tt-stage">
        <div class="tt-stage-title">Pilih jalur penanganan</div>
        <div class="tt-stage-acts">
            <form method="POST" action="{{ route('trouble-ticket.remote', $t) }}" class="tt-confirm m-0"
                  data-title="Tangani secara Remote?" data-text="Tiket diselesaikan tanpa kunjungan ke site.">
                @csrf
                <button class="btn btn-outline-primary btn-sm btn-block"><i class="fas fa-satellite-dish mr-1"></i> Remote</button>
            </form>
            <button type="button" class="btn btn-primary btn-sm btn-block mt-0" data-toggle="modal" data-target="#mdlTtPlan">
                <i class="fas fa-calendar-check mr-1"></i> Create Plan
            </button>
        </div>
        <div class="tt-stage-note">Remote = selesai tanpa kunjungan. Create Plan = jadwalkan kunjungan ke site.</div>
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
        @else
            <div class="tt-stage-note">Tiket masuk tahap Trip saat tanggal plan visit tiba.</div>
        @endif
    </div>
@endif

@if($bisaBerangkat)
    <div class="tt-stage">
        <form method="POST" action="{{ route('trouble-ticket.travel.start', $t) }}" id="frmTtTravel" class="tt-submit-once m-0">@csrf</form>
        {{-- Swipe for travel langsung jalan: geser penuh sudah tindakan sengaja, dialog konfirmasi hanya menambah satu ketukan. --}}
        <div class="tt-swipe" data-form="frmTtTravel" data-confirm="0">
            <span class="tt-swipe-knob" tabindex="0" role="button" aria-label="Geser untuk mulai perjalanan"><i class="fas fa-arrow-right"></i></span>
            <span class="tt-swipe-text">Swipe for travel</span>
        </div>
    </div>
@endif

@if($dalamJalan)
    <div class="tt-stage">
        <form method="POST" action="{{ route('trouble-ticket.arrive', $t) }}" class="tt-confirm m-0"
              data-title="Konfirmasi On Site?" data-text="Pastikan site yang Anda datangi sudah benar.">
            @csrf
            <button class="btn btn-success btn-sm btn-block"><i class="fas fa-map-marker-alt mr-1"></i> On Site</button>
        </form>
        <button type="button" class="btn btn-outline-danger btn-sm btn-block mt-2" data-toggle="modal" data-target="#mdlTtCancelTrip">
            <i class="fas fa-ban mr-1"></i> Cancel Visit
        </button>
    </div>
@endif

@if($bisaMulai)
    <div class="tt-stage">
        <form method="POST" action="{{ route('trouble-ticket.start', $t) }}" id="frmTtStart" class="tt-submit-once m-0">@csrf</form>
        <div class="tt-swipe" data-form="frmTtStart" data-confirm="Mulai progres?" data-text="Pengerjaan di site dimulai.">
            <span class="tt-swipe-knob" tabindex="0" role="button" aria-label="Geser untuk mulai progres"><i class="fas fa-arrow-right"></i></span>
            <span class="tt-swipe-text">Ready Progress</span>
        </div>
    </div>
@endif

{{-- Tahap Plan punya panelnya sendiri di atas (Ubah Tanggal + Form Close). --}}
@if($bisaTutup && $t->status !== 'PLAN')
    <div class="tt-stage">
        <div class="tt-stage-title">{{ $t->status === 'REMOTE' ? 'Penanganan remote' : 'Pengerjaan di site' }}</div>
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
        @else
            <button type="button" class="btn btn-success btn-sm btn-block" data-toggle="modal" data-target="#mdlTtClosure">
                <i class="fas fa-flag-checkered mr-1"></i> Form Close
            </button>
        @endif
        <div class="tt-stage-note">RCA, Sub-RCA, keterangan &amp; evidence. Setelah dikirim, tiket menunggu approval supervisor.</div>
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
