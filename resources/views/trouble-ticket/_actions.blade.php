{{-- Tombol aksi tiket. Dirender dua kali di show.blade: kepala (desktop) dan panel
     Action (mobile). Server tetap memeriksa ulang setiap aksi. Variabel dari show. --}}
                @if($bisaMulai)
                    <form method="POST" action="{{ route('trouble-ticket.start', $t) }}" class="tt-confirm" data-title="Mulai troubleshooting?" data-text="Timer resolusi mulai berjalan sejak saat ini.">
                        @csrf <button class="btn btn-primary btn-sm"><i class="fas fa-tools mr-1"></i> Start Progress</button>
                    </form>
                @endif
                @if($bisaTutup)
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#mdlTtClosure"><i class="fas fa-flag-checkered mr-1"></i> Tutup Tiket</button>
                @endif
                @if($canReviewPlan)
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#mdlTtApprove"><i class="fas fa-check mr-1"></i> Setujui Plan</button>
                    <button class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#mdlTtReject"><i class="fas fa-times mr-1"></i> Tolak Plan</button>
                @endif
                @if($bisaAlihkan || $bisaKoreksi)
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-shield mr-1"></i> Leader
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" style="font-size:13px;">
                            @if($bisaAlihkan)<a class="dropdown-item" href="#" data-toggle="modal" data-target="#mdlTtReassign"><i class="fas fa-exchange-alt mr-2"></i>Alihkan Teknisi</a>@endif
                            @if($bisaKoreksi)<a class="dropdown-item" href="#" data-toggle="modal" data-target="#mdlTtCorrect"><i class="fas fa-edit mr-2"></i>Koreksi Plan</a>@endif
                        </div>
                    </div>
                @endif
