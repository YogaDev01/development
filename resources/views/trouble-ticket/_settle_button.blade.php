{{-- Tombol Settlement, dipakai di DALAM panel tahap mana pun (20 Okt 2026).

     Dipisah jadi partial supaya markupnya tidak disalin ke tiap panel: tombol
     ini menyatu dengan Swipe for travel / Form Close / Ubah Tanggal, bukan
     berdiri sebagai panel sendiri di atas — pertanggungjawaban uang memang
     bagian dari pekerjaan tahap itu, bukan blok terpisah.

     SATU TOMBOL PER CR yang berhak (pengajuan biaya bisa menghasilkan banyak CR
     per tiket). Modal settlement merender satu CR sekaligus ($settleCr); CR lain
     dibuka lewat ?settle=<id CR>. Nomor CR hanya ditulis bila ada lebih dari satu.

     Variabel: $t, $canSettle, $settleList, $settleCr (dari $actionVars). --}}
@if($canSettle ?? false)
    @foreach($settleList as $crSettle)
        @if($crSettle->id === $settleCr->id)
            <button type="button" class="btn btn-primary btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtSettle">
                <i class="fas fa-file-invoice-dollar mr-1"></i> Settlement{{ $settleList->count() > 1 ? ' ' . $crSettle->code : '' }}
            </button>
        @else
            <a class="btn btn-outline-primary btn-sm btn-block tt-btn-wide mt-2" href="{{ route('trouble-ticket.show', [$t, 'settle' => $crSettle->id]) }}">
                <i class="fas fa-file-invoice-dollar mr-1"></i> Settlement {{ $crSettle->code }}
            </a>
        @endif
        @if($crSettle->status === 'SETTLE_REJECTED')
            <div class="tt-stage-note">Settlement {{ $crSettle->code }} sebelumnya ditolak — perbaiki lalu simpan lagi.</div>
        @endif
    @endforeach
@endif
