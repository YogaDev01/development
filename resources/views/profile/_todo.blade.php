{{--
    Tab TODO LIST — 3 tabel (Cost Request, Budgeting, Purchase Order),
    kolom Tahap Action & Qty, subtotal di footer. Dimuat LAZY via AJAX saat
    tab pertama kali dibuka (halaman Todo berat — tidak dihitung saat render
    profil). Lihat ProfileController::todoSummary() + JS di show.blade.php.
--}}
<div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap:8px;">
    <div>
        <h6 class="font-weight-bold mb-0" style="font-size:13px;"><i class="fas fa-tasks mr-1 text-muted"></i> Todo List Saya</h6>
        <small class="text-muted">Ringkasan tugas tertunda Anda per modul. Klik tahap untuk membuka halaman Todo List.</small>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSummaryTask">
        <i class="fas fa-chart-pie mr-1"></i> Summary Task
    </button>
</div>

<div id="todoSummaryBox">
    {{-- Skeleton saat memuat --}}
    <div id="todoSummaryLoading" class="row">
        @for($i = 0; $i < 3; $i++)
        <div class="col-lg-4 mb-2">
            <span class="skeleton sk-line d-block" style="height:22px;width:60%;"></span>
            <span class="skeleton sk-line d-block mt-2" style="height:90px;"></span>
        </div>
        @endfor
    </div>
    <div id="todoSummaryContent" class="row" style="display:none;"></div>
    <div id="todoSummaryEmpty" class="text-muted" style="display:none;font-size:12.5px;">
        <i class="fas fa-check-circle text-success mr-1"></i> Tidak ada tugas tertunda. 🎉
    </div>
    <div id="todoSummaryFoot" class="text-muted mt-1" style="display:none;font-size:11.5px;"></div>
</div>
