{{--
    Summary Task — baris = TAHAP alur kerja (Role + nama tahap), kolom = MODUL.
    Dipakai modal "Summary Task" di toolbar Todo List; sejak tab "Summary Task"
    dihapus, ini SATU-SATUNYA tampilan Summary Task.

    Sel kosong ("·") berarti tahap itu memang tidak ada pada modul tsb — beda
    arti dari "0" (tahapnya ada, antreannya kosong). $summaryTable dihitung di
    TodoListController::buildSummaryTable().

    Filter Modul & Role dikerjakan di sisi klien (tabel statis, ~20 baris —
    tidak perlu DataTable apalagi reload halaman). Kolom Total dan seluruh baris
    Total Task IKUT dihitung ulang mengikuti filter, supaya angka yang tampil
    selalu menjumlahkan tepat sel-sel yang sedang terlihat.

    JS-nya ADA DI todo-list/index.blade.php, bukan di sini: partial ini dirender
    di dalam body, sedangkan jQuery baru dimuat di bagian bawah layout — script
    inline di berkas ini akan jalan sebelum $ ada dan langsung mati.
--}}
@php
    // Urutan role mengikuti kemunculan pertama di tabel, bukan diurutkan abjad,
    // supaya daftar filternya terbaca searah dengan alur kerja.
    $stRoles = collect($summaryTable['rows'])->pluck('role')->unique()->values();

    // Format durasi detik -> ringkas (dtk/mnt/jam/hari), sumber tunggal sama
    // dengan yang dipakai controller (TodoListController::formatDuration()).
    $stFmtDur = fn ($s) => $s === null ? null : \App\Http\Controllers\TodoListController::formatDuration($s);
@endphp

<style>
#summaryTaskWrap .dropdown-item.active,
#summaryTaskWrap .dropdown-item:active {
    background: var(--accent);
    color: #fff;
}
</style>

<div id="summaryTaskWrap">

    <div class="custom-toolbar mb-2 d-flex align-items-center justify-content-start flex-wrap" style="gap:8px;">
        <button aria-label="Clear Filter" type="button" id="stClearFilter"
                class="btn btn-outline-secondary btn-sm" title="Clear Filter">
            <i class="fas fa-eraser"></i>
        </button>

        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-secondary col-mf-btn" id="stModuleBtn"
                    data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                <span id="stModuleLabel">All Module</span>
                <i class="fas fa-caret-down ml-1"></i>
            </button>
            <div class="dropdown-menu" style="min-width:210px;">
                <button type="button" class="dropdown-item st-module-opt active" data-value="">All Module</button>
                <div class="dropdown-divider"></div>
                @foreach($summaryTable['modules'] as $module)
                <button type="button" class="dropdown-item st-module-opt" data-value="{{ $module }}">{{ $module }}</button>
                @endforeach
            </div>
        </div>

        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-secondary col-mf-btn" id="stRoleBtn"
                    data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                <span id="stRoleLabel">All Role</span>
                <i class="fas fa-caret-down ml-1"></i>
            </button>
            <div class="dropdown-menu" style="min-width:180px;">
                <button type="button" class="dropdown-item st-role-opt active" data-value="">All Role</button>
                <div class="dropdown-divider"></div>
                @foreach($stRoles as $role)
                <button type="button" class="dropdown-item st-role-opt" data-value="{{ $role }}">{{ $role }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="table-responsive">
    <table class="table table-bordered mb-0" id="summaryTaskTable" style="font-size:12.5px;min-width:720px;">
        <thead class="bg-light">
            <tr>
                <th scope="col" style="width:1%;" class="text-nowrap">Role</th>
                <th scope="col" class="text-nowrap">Tahap</th>
                @foreach($summaryTable['modules'] as $module)
                <th scope="col" class="text-center col-wrap-header" style="width:11%;" data-module="{{ $module }}">{{ $module }}</th>
                @endforeach
                <th scope="col" class="text-center text-nowrap" style="width:1%;">Total</th>
                <th scope="col" class="text-center text-nowrap" style="width:1%;" title="Rata-rata lama dokumen menunggu di tahap ini sebelum diaksi">Avg Time</th>
            </tr>
        </thead>
        <tbody>
            @foreach($summaryTable['rows'] as $row)
            <tr data-role="{{ $row['role'] }}">
                <td class="text-nowrap"><strong>{{ $row['role'] }}</strong></td>
                <td class="text-nowrap">{{ $row['label'] }}</td>
                @foreach($summaryTable['modules'] as $module)
                @php $cell = $row['cells'][$module] ?? null; @endphp
                <td class="text-center text-nowrap" data-module="{{ $module }}"
                    @if($cell) data-count="{{ $cell['count'] }}" data-avg-sum="{{ $cell['avg_sum'] }}" data-avg-count="{{ $cell['avg_count'] }}" @endif>
                    @if(!$cell)
                        <span style="color:var(--text-faint);" title="Tahap ini tidak ada pada modul {{ $module }}">&middot;</span>
                    @elseif($cell['count'] <= 0)
                        <span class="text-muted">-</span>
                    @else
                        <a href="{{ $cell['url'] }}" target="_blank" class="summary-num-link" title="Lihat data">{{ $cell['count'] }}</a>
                    @endif
                </td>
                @endforeach
                <td class="text-center text-nowrap summary-total-cell {{ $row['total'] > 0 ? 'summary-total-pos' : 'summary-total-zero' }}"
                    data-total-cell>{{ $row['total'] }}</td>
                <td class="text-center text-nowrap" data-avg-cell>
                    @if($row['avg_seconds'] !== null)
                        {{ $stFmtDur($row['avg_seconds']) }}
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
            </tr>
            @endforeach
            <tr id="stEmptyRow" style="display:none;">
                <td colspan="{{ count($summaryTable['modules']) + 4 }}" class="text-center text-muted py-3">
                    Tidak ada tahap yang cocok dengan filter yang sedang aktif.
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr style="background:var(--surface-2);">
                <th colspan="2" class="text-right">Total Task</th>
                @foreach($summaryTable['modules'] as $module)
                <th class="text-center text-nowrap" data-module="{{ $module }}" data-foot-module>{{ $summaryTable['moduleTotals'][$module] }}</th>
                @endforeach
                <th class="text-center text-nowrap" data-foot-grand>{{ $summaryTable['grandTotal'] }}</th>
                <th class="text-center text-nowrap" data-foot-avg>{{ $stFmtDur($summaryTable['grandAvgSeconds']) ?? '-' }}</th>
            </tr>
        </tfoot>
    </table>
    </div>

    <div style="padding:10px 4px 0;font-size:11px;color:var(--text-faint);">
        <i class="fas fa-info-circle mr-1"></i>
        Angka Cost Request mengikuti hak akses (access group) Anda; modul lain dihitung menyeluruh.
        Sel <strong>&middot;</strong> berarti tahap itu tidak ada pada modul tersebut.
    </div>
</div>

