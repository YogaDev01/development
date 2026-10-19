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
                        <i class="fas fa-exchange-alt mr-1"></i> Change Teknisi
                    </button>
                @endif
                {{-- Stop Chat: hanya superadmin (keputusan 19 Okt 2026). Tiket
                     CLOSED tidak lagi menutup diskusi sendiri, jadi penutupnya
                     harus tindakan sadar. --}}
                @if($canLockChat ?? false)
                    <form method="POST" action="{{ route('trouble-ticket.chat-lock', $t) }}" class="tt-confirm m-0"
                          data-title="{{ $t->chatLocked() ? 'Buka kembali diskusi?' : 'Hentikan diskusi?' }}"
                          data-text="{{ $t->chatLocked() ? 'Semua yang bisa melihat tiket dapat mengirim pesan lagi.' : 'Tidak ada lagi pesan baru pada tiket ini.' }}">
                        @csrf
                        <input type="hidden" name="lock" value="{{ $t->chatLocked() ? 0 : 1 }}">
                        <button class="btn btn-outline-{{ $t->chatLocked() ? 'secondary' : 'danger' }} btn-sm">
                            <i class="fas fa-comment-slash mr-1"></i> {{ $t->chatLocked() ? 'Buka Chat' : 'Stop Chat' }}
                        </button>
                    </form>
                @endif
