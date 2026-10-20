{{--
    Summary Respontime — baris = USER AKTIF, kolom = MODUL, sel = rata-rata
    waktu respon (durasi "masuk tahap → diaksi") + jumlah aksi. Dihitung di
    TodoListController::buildRespontimeTable() (lihat definisinya di sana);
    user nonaktif sudah dikeluarkan di sana, bukan di sini.

    "-" pada sel = user belum pernah merespon di modul itu. Treasury & Invoice
    memakai rantai timestamp dokumennya (bukan log transisi).

    Tabel ini bisa disortir per kolom (klik header) — baris dirender ulang oleh
    JS dari window.__respontimeData, bukan dari markup PHP di bawah, supaya
    sortir ASC/DESC tidak perlu reload. Default: Rata-rata ascending (dari
    server, lihat buildRespontimeTable()). JS-nya ada di todo-list/index.blade.php
    (jQuery baru dimuat di bawah; lihat catatan yang sama di _summary_task_list).
--}}
@if(empty($respontimeTable['rows']))
<div id="summaryResponWrap">
    <p class="text-muted mb-2" style="font-size:11.5px;">
        <i class="fas fa-info-circle mr-1"></i>
        Rata-rata waktu tiap user menanggapi dokumen di tahapnya — dihitung sejak dokumen
        masuk ke tahap (aksi sebelumnya) sampai user mengaksikannya. Angka kecil = jumlah aksi.
    </p>
    <div class="text-center text-muted py-4" style="font-size:12.5px;">
        <i class="fas fa-stopwatch mr-1"></i> Belum ada data respon yang bisa dihitung.
    </div>
</div>
@else
@php
    // Format durasi detik -> ringkas (hari/jam/menit/detik), dipakai footer
    // (tidak ikut disortir, jadi cukup dirender sekali di server).
    $fmt = fn ($s) => $s === null ? null : \App\Http\Controllers\TodoListController::formatDuration($s);
    $footCellHtml = function ($cell) use ($fmt) {
        if (!$cell) return '<span class="text-muted">-</span>';
        return '<div style="font-weight:600;">' . e($fmt($cell['avg_seconds'])) . '</div>'
             . '<div class="text-muted" style="font-size:10px;">' . (int) $cell['count'] . '&times;</div>';
    };
@endphp
<script>window.__respontimeData = @json($respontimeTable);</script>

<div id="summaryResponWrap">
    <p class="text-muted mb-2" style="font-size:11.5px;">
        <i class="fas fa-info-circle mr-1"></i>
        Rata-rata waktu tiap user menanggapi dokumen di tahapnya — dihitung sejak dokumen
        masuk ke tahap (aksi sebelumnya) sampai user mengaksikannya. Angka kecil = jumlah aksi.
        Klik judul kolom untuk mengurutkan.
    </p>

    <div class="table-responsive">
    <table class="table table-bordered mb-0" id="summaryResponTable" data-ts-card="off" style="font-size:12.5px;min-width:820px;">
        <thead class="bg-light">
            <tr>
                <th scope="col" class="text-nowrap sr-sort" data-key="user" role="button" tabindex="0" style="width:1%;">
                    User <i class="fas fa-sort" style="opacity:.35;font-size:10px;"></i>
                </th>
                @foreach($respontimeTable['modules'] as $module)
                <th scope="col" class="text-center text-nowrap sr-sort" data-key="{{ $module }}" role="button" tabindex="0" style="width:14%;">
                    {{ $module }} <i class="fas fa-sort" style="opacity:.35;font-size:10px;"></i>
                </th>
                @endforeach
                <th scope="col" class="text-center text-nowrap sr-sort" data-key="avg_seconds" role="button" tabindex="0" style="width:1%;">
                    Rata-rata <i class="fas fa-sort" style="opacity:.35;font-size:10px;"></i>
                </th>
            </tr>
        </thead>
        <tbody id="summaryResponBody">
            {{-- Diisi JS dari window.__respontimeData — lihat renderRespon() di todo-list/index.blade.php. --}}
        </tbody>
        <tfoot>
            <tr style="background:var(--surface-2);">
                <th class="text-right text-nowrap">Rata-rata Modul</th>
                @foreach($respontimeTable['modules'] as $module)
                <th class="text-center text-nowrap">{!! $footCellHtml($respontimeTable['moduleAvg'][$module] ?? null) !!}</th>
                @endforeach
                <th></th>
            </tr>
        </tfoot>
    </table>
    </div>
</div>
@endif
