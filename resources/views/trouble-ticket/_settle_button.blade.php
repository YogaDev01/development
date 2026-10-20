{{-- Tombol Settlement, dipakai di DALAM panel tahap mana pun (20 Okt 2026).

     Dipisah jadi partial supaya markupnya tidak disalin ke tiap panel: tombol
     ini menyatu dengan Swipe for travel / Form Close / Ubah Tanggal, bukan
     berdiri sebagai panel sendiri di atas — pertanggungjawaban uang memang
     bagian dari pekerjaan tahap itu, bukan blok terpisah. Nomor CR tidak ikut
     di label: ia sudah tertulis di judul modalnya sendiri.

     Variabel: $t, $canSettle (dari $actionVars). --}}
@if($canSettle ?? false)
    <button type="button" class="btn btn-primary btn-sm btn-block tt-btn-wide mt-2" data-toggle="modal" data-target="#mdlTtSettle">
        <i class="fas fa-file-invoice-dollar mr-1"></i> Settlement
    </button>
    @if($t->budgetRequest->status === 'SETTLE_REJECTED')
        <div class="tt-stage-note">Settlement sebelumnya ditolak — perbaiki lalu simpan lagi.</div>
    @endif
@endif
