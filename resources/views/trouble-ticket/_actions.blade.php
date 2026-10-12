{{-- Tombol aksi tiket di KEPALA kartu chat (desktop) dan panel Action (mobile).
     Seluruh aksi tahap — Remote, Create Plan, perjalanan, form close, approval —
     HANYA dirender di _stage.blade (kaki panel Summary), supaya tidak ada dua
     pintu untuk aksi yang sama. Di sini tinggal pengalihan teknisi.

     Dulu tombol ini dropdown Bootstrap; menunya dipasang ulang setiap kali
     panel dirender dua kali (kepala + panel Action), sehingga posisinya
     meloncat saat diklik. Satu tombol langsung ke modal menghilangkan
     persoalan itu sekaligus memangkas satu ketukan. --}}
                @if($bisaAlihkan)
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#mdlTtReassign">
                        <i class="fas fa-exchange-alt mr-1"></i> Swap Teknisi
                    </button>
                @endif
