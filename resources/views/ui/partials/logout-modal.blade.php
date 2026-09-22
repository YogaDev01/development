{{-- Konfirmasi logout — submit ke route POST /logout yang sudah ada --}}
<div class="overlay" id="logoutOverlay">
    <div class="modal" role="dialog" aria-label="Konfirmasi logout">
        <div class="modal-head">
            <div class="modal-icon" style="background:var(--accent-tint); color:var(--accent-text);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
            </div>
            <button type="button" class="modal-close js-close-modal" aria-label="Tutup">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="modal-title">Keluar dari Tesys?</div>
        <div class="modal-body">Sesi Anda akan diakhiri dan Anda perlu login kembali untuk mengakses data.</div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary js-close-modal">Batal</button>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-primary">Ya, Logout</button>
            </form>
        </div>
    </div>
</div>
