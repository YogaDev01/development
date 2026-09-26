{{--
    Reusable clickable workflow-status stepper.

    Props:
      steps  : array of [label, count, color, statusKey]  (statusKey harus cocok dengan value data-status di <tr>)
      title  : judul card (opsional)
      icon   : class fontawesome untuk icon judul (opsional)
      total  : total row (untuk hitung proporsi dist-bar), default sum semua count
      table  : id elemen <table> DataTable yang mau difilter (tanpa '#')
      field  : nama data-attribute di <tr> yang menyimpan status, default "status"
--}}
@php
    $total = $total ?? collect($steps)->sum(fn($s) => $s[1]);
    $field = $field ?? 'status';
    $compact = $compact ?? false;

    /*
    | Label multi-kata dipecah satu kata per baris ("Settle Approved" jadi dua
    | baris). Label sepanjang itu memaksa kolom grid melebar, dan dengan 13
    | tahap alurnya jadi memanjang sampai perlu digulir. Memecahnya membuat
    | lebar kolom ditentukan kata terpanjang, bukan seluruh frasa.
    */
    $wfLabel = fn ($text) => implode('<br>', array_map('e', preg_split('/\s+/', trim((string) $text))));
@endphp

@once
<style>
    .wf-tl-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
        row-gap: 18px;
    }
    .wf-tl-step { position: relative; text-align: center; padding: 0 2px; cursor: pointer; transition: transform .15s ease; }
    .wf-tl-step::before {
        content: ""; position: absolute; top: 11px; left: 0; right: 0; height: 2px;
        background: var(--border); z-index: 0;
    }
    .wf-tl-step:first-child::before { left: 50%; }
    .wf-tl-step:last-child::before  { right: 50%; }
    .wf-tl-node {
        position: relative; z-index: 1; min-width: 26px; padding: 5px 8px; font-size: 12px;
        /* Cincin ini BUKAN hiasan: tugasnya melubangi garis penghubung
           yang lewat di belakang badge. Karena itu warnanya harus warna
           PERMUKAAN tempat ia berdiri, bukan putih. Di kartu putih
           keduanya kebetulan sama, jadi salahnya tidak pernah terlihat —
           begitu kartunya gelap, tiap badge jadi punya halo putih. */
        box-shadow: 0 0 0 3px var(--surface);
    }
    .wf-tl-label { margin-top: 7px; font-size: 12px; line-height: 1.25; }

    /* Label dulu memakai utilitas Bootstrap .text-dark / .text-muted.
       .text-dark adalah #343A40 yang ditulis !important — di tema gelap ia
       tinta gelap di atas kartu gelap, dan tidak ada aturan tema yang bisa
       mengalahkannya. Diganti dua kelas milik komponen ini sendiri supaya
       warnanya ikut token. */
    .wf-tl-label.is-on  { color: var(--text); font-weight: 600; }
    .wf-tl-label.is-off { color: var(--text-faint); }

    /* Node tanpa hitungan: dulu .badge-light (putih). Di tema gelap itu
       bercak putih. Sekarang ia permukaan redam dengan garis tepi tipis —
       terbaca "kosong" lewat bentuk, bukan lewat terangnya. */
    .wf-tl-node.is-empty {
        background: var(--surface-2) !important;
        /* --text-dim, bukan --text-faint: di atas --surface-2 yang gelap,
           faint hanya 4,4:1 — di bawah ambang AA untuk angka sekecil ini. */
        color: var(--text-dim) !important;
        border: 1px solid var(--border);
    }
    .wf-tl-step:hover { transform: translateY(-2px); }
    .wf-tl-step:hover .wf-tl-node { box-shadow: 0 0 0 3px var(--surface), 0 0 0 5px var(--accent-ring); }
    .wf-tl-step.wf-active .wf-tl-node { box-shadow: 0 0 0 3px var(--surface), 0 0 0 5px var(--accent-ring); }
    .wf-tl-step.wf-active .wf-tl-label { text-decoration: underline; }
    .wf-dist-bar { display:flex; height:6px; border-radius:3px; overflow:hidden; background:var(--surface-2); }
    .wf-dist-bar .seg { flex: 0 0 auto; height: 100%; }

    /* Mode compact: panel tipis, untuk ditaruh di content-header.
       MN-10A — padding vertikal dan jarak antarbaris dirapatkan. Strip ini
       muncul di atas SETIAP halaman PO / Budgeting / Cost Request, jadi tiap
       piksel tingginya langsung mengurangi jumlah baris tabel yang terlihat
       tanpa menggulir. Garis tepi dipakai menggantikan bayangan supaya
       batasnya tetap terbaca di tema gelap, tempat bayangan hitam tak
       kelihatan. */
    .wf-compact {
        background: var(--surface); border-radius: var(--radius-md); padding: 8px 16px;
        border: 1px solid var(--border); margin-bottom: 4px;
    }
    .wf-compact .wf-tl-grid { grid-template-columns: repeat(auto-fit, minmax(64px, 1fr)); row-gap: 8px; }
    .wf-compact .wf-tl-node { min-width: 20px; padding: 2px 6px; font-size: 11px; }
    .wf-compact .wf-tl-label { margin-top: 3px; font-size: 10.5px; }
    .wf-compact .wf-dist-bar { height: 4px; }

    /* Di layar sempit strip ini paling mudah menjadi dinding badge. Kolomnya
       dipersempit lagi supaya membungkus rapi, bukan memaksa gulir mendatar. */
    @media (max-width: 575.98px) {
        .wf-compact { padding: 6px 10px; }
        .wf-compact .wf-tl-grid { grid-template-columns: repeat(auto-fit, minmax(56px, 1fr)); row-gap: 6px; }
        .wf-compact .wf-tl-label { font-size: 10px; }
    }
</style>
@endonce

@push('scripts')
@once
<script>
(function () {
    $(function () {
        $('.wf-seg').each(function () {
            $(this).css('width', $(this).data('width') + '%');
        });
    });

    // Filter DataTable berdasarkan status yang diklik pada stepper.
    // Scoped per-table lewat settings.nTable.id, sehingga aman dipakai di banyak halaman sekaligus.
    var activeFilters = {};

    $.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData, counter) {
        var tableId = settings.nTable.id;
        var filter = activeFilters[tableId];
        if (!filter) return true;
        var $row = $(settings.aoData[index].nTr);
        var val = ($row.data(filter.field) || '').toString();
        return val === filter.status;
    });

    $(document).on('click', '.wf-tl-step', function () {
        var $step     = $(this);
        var $wrapper  = $step.closest('[data-wf-table]');
        var tableId   = $wrapper.data('wf-table');
        var field     = $wrapper.data('wf-field') || 'status';
        var status    = $step.data('status');
        var $siblings = $wrapper.find('.wf-tl-step');

        if (!tableId || !$('#' + tableId).length) return;
        var table = $('#' + tableId).DataTable();

        if ($step.hasClass('wf-active')) {
            // klik ulang -> reset filter
            $siblings.removeClass('wf-active');
            delete activeFilters[tableId];
        } else {
            $siblings.removeClass('wf-active');
            $step.addClass('wf-active');
            activeFilters[tableId] = { field: field, status: status };
        }
        table.draw();
    });

    // Reset filter stepper untuk 1 tabel, tanpa reload halaman.
    window.TesysWorkflowStepper = {
        clear: function (tableId) {
            if (!tableId || !$('#' + tableId).length) return;
            delete activeFilters[tableId];
            $('[data-wf-table="' + tableId + '"] .wf-tl-step').removeClass('wf-active');
            $('#' + tableId).DataTable().draw();
        }
    };
})();
</script>
@endonce
@endpush

@if($compact)
<div class="wf-compact" data-wf-table="{{ $table }}" data-wf-field="{{ $field }}">
    <div class="wf-tl-grid">
        @foreach($steps as $s)
            <div class="wf-tl-step" role="button" tabindex="0" data-status="{{ $s[3] }}">
                <span class="badge wf-tl-node {{ $s[1] > 0 ? 'badge-' . $s[2] : 'is-empty' }}">{{ $s[1] }}</span>
                <div class="wf-tl-label {{ $s[1] > 0 ? 'is-on' : 'is-off' }}">{!! $wfLabel($s[0]) !!}</div>
            </div>
        @endforeach
    </div>
</div>
@else
<div class="card shadow-sm" data-wf-table="{{ $table }}" data-wf-field="{{ $field }}">
    @if(!empty($title))
    <div class="card-header">
        <h3 class="card-title font-weight-bold">
            @if(!empty($icon))<i class="{{ $icon }} mr-1"></i>@endif {{ $title }}
        </h3>
    </div>
    @endif
    <div class="card-body py-3">

        <div class="wf-dist-bar mb-4">
            @foreach($steps as $s)
                @if($s[1] > 0)
                    @php $segPct = round($s[1] / max(1, $total) * 100, 2); @endphp
                    <div class="seg bg-{{ $s[2] }} wf-seg" data-width="{{ $segPct }}" title="{{ $s[0] }}: {{ $s[1] }}"></div>
                @endif
            @endforeach
        </div>

        <div class="wf-tl-grid">
            @foreach($steps as $s)
                <div class="wf-tl-step" role="button" tabindex="0" data-status="{{ $s[3] }}">
                    <span class="badge wf-tl-node {{ $s[1] > 0 ? 'badge-' . $s[2] : 'is-empty' }}">{{ $s[1] }}</span>
                    <div class="wf-tl-label {{ $s[1] > 0 ? 'is-on' : 'is-off' }}">{!! $wfLabel($s[0]) !!}</div>
                </div>
            @endforeach
        </div>

    </div>
</div>
@endif
