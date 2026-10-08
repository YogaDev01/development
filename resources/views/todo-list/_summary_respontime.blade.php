{{--
    Summary Respontime — baris = USER, kolom = MODUL, sel = rata-rata waktu
    respon (durasi "masuk tahap → diaksi") + jumlah aksi. Dihitung di
    TodoListController::buildRespontimeTable() (lihat definisinya di sana).

    "-" pada sel = user belum pernah merespon di modul itu. Treasury & Invoice
    memakai rantai timestamp dokumennya (bukan log transisi).
--}}
@php
    /* Format durasi detik -> ringkas (hari/jam/menit/detik). */
    $fmt = function ($s) {
        if ($s === null) return null;
        $s = (int) $s;
        if ($s < 60)    return $s . ' dtk';
        $m = intdiv($s, 60);
        if ($m < 60)    return $m . ' mnt';
        $h = intdiv($m, 60); $mm = $m % 60;
        if ($h < 24)    return $h . ' j' . ($mm ? ' ' . $mm . ' m' : '');
        $d = intdiv($h, 24); $hh = $h % 24;
        return $d . ' hr' . ($hh ? ' ' . $hh . ' j' : '');
    };
    $cellHtml = function ($cell) use ($fmt) {
        if (!$cell) {
            return '<span class="text-muted">-</span>';
        }
        return '<div style="font-weight:600;">' . e($fmt($cell['avg_seconds'])) . '</div>'
             . '<div class="text-muted" style="font-size:10px;">' . (int) $cell['count'] . '×</div>';
    };
@endphp

<div id="summaryResponWrap">
    <p class="text-muted mb-2" style="font-size:11.5px;">
        <i class="fas fa-info-circle mr-1"></i>
        Rata-rata waktu tiap user menanggapi dokumen di tahapnya — dihitung sejak dokumen
        masuk ke tahap (aksi sebelumnya) sampai user mengaksikannya. Angka kecil = jumlah aksi.
    </p>

    @if(empty($respontimeTable['rows']))
        <div class="text-center text-muted py-4" style="font-size:12.5px;">
            <i class="fas fa-stopwatch mr-1"></i> Belum ada data respon yang bisa dihitung.
        </div>
    @else
    <div class="table-responsive">
    <table class="table table-bordered mb-0" style="font-size:12.5px;min-width:820px;">
        <thead class="bg-light">
            <tr>
                <th scope="col" class="text-nowrap" style="width:1%;">User</th>
                @foreach($respontimeTable['modules'] as $module)
                <th scope="col" class="text-center text-nowrap" style="width:14%;">{{ $module }}</th>
                @endforeach
                <th scope="col" class="text-center text-nowrap" style="width:1%;">Rata-rata</th>
            </tr>
        </thead>
        <tbody>
            @foreach($respontimeTable['rows'] as $row)
            <tr>
                <td class="text-nowrap"><strong>{{ $row['user'] }}</strong></td>
                @foreach($respontimeTable['modules'] as $module)
                <td class="text-center text-nowrap">{!! $cellHtml($row['cells'][$module] ?? null) !!}</td>
                @endforeach
                <td class="text-center text-nowrap">
                    @if($row['avg_seconds'] !== null)
                        <div style="font-weight:700;">{{ $fmt($row['avg_seconds']) }}</div>
                        <div class="text-muted" style="font-size:10px;">{{ (int) $row['count'] }}×</div>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background:var(--surface-2);">
                <th class="text-right text-nowrap">Rata-rata Modul</th>
                @foreach($respontimeTable['modules'] as $module)
                <th class="text-center text-nowrap">{!! $cellHtml($respontimeTable['moduleAvg'][$module] ?? null) !!}</th>
                @endforeach
                <th></th>
            </tr>
        </tfoot>
    </table>
    </div>
    @endif
</div>
