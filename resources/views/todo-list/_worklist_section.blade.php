{{--
    Satu worklist Cost Request grouped per Budget Plan, dengan modal
    "View Budget" (breakdown Chart of Account + NOB coloring) — persis pola
    "Review Cost Request" yang dulu ada di budget-request/index.blade.php,
    sekarang dipakai ulang untuk tiap role (Head/Cost Control/CEO) di halaman
    Todo List.

    Approve/Reject dilakukan per CR dari drawer detail (klik nomor CR), bukan
    lewat bulk action di kartu ini: satu tempat memutuskan, dengan rincian CR
    terbuka di depan mata saat memutuskannya.

    Wajib diisi dari pemanggil:
      $key          : string unik per section (dipakai untuk id elemen, mis. 'head')
      $title        : judul card
      $statusLabel  : label status yang sedang direview (mis. 'Submitted')
      $groups       : Collection PO-level (MN-03) —
                      [{po, customer, pm, qty, amount, plans: [{label, pm, po_code,
                        items, coa_groups, is_nob_full, cr_nob}]}]
--}}
@if($groups->isNotEmpty())
<div id="tl-{{ $key }}" class="card card-warning card-outline shadow-sm mt-3 todo-worklist" data-section="{{ $key }}">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">
            <i class="fas fa-clipboard-check mr-1"></i>
            {{ $title }}
            <span class="badge badge-secondary ml-1">{{ $groups->sum('qty') }}</span>
            <span class="text-muted ml-1" style="font-size:11px;">dari {{ $groups->count() }} PO</span>
        </h3>
    </div>

    <div class="card-body p-0">
        <div style="padding:6px 16px;border-bottom:1px solid var(--border);">
            <button type="button" class="btn btn-link todo-toggle-all p-0" style="font-size:11.5px;font-weight:600;text-decoration:none;" data-state="collapsed">
                <i class="fas fa-expand-alt mr-1"></i>Expand All
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0 todo-tbl" style="font-size:12.5px;">
                <thead class="bg-light">
                    <tr>
                        <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                        <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                        <th scope="col" class="text-center text-nowrap" style="width:1%;">Aging</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $poi => $poGroup)
                    @php
                        /*
                         | Satu PO yang cuma punya SATU Budget Plan tidak perlu dua
                         | baris grup: keduanya mewakili himpunan CR yang sama persis,
                         | dan barisnya jadi terbaca seperti dua pengelompokan padahal
                         | hanya satu. Untuk kasus itu lapis Budget Plan dilewati, dan
                         | baris CR-nya digantung langsung pada baris PO.
                         |
                         | Lapis Budget Plan tetap muncul begitu satu PO punya lebih
                         | dari satu plan — di situ ia benar-benar memisahkan sesuatu.
                         */
                        $planTunggal = $poGroup['plans']->count() === 1;

                        // Kelas ini dipasang di SETIAP baris CR di bawah PO ini,
                        // berapa pun jumlah plannya — dipakai checkbox pilih-semua
                        // di level PO supaya tetap bekerja pada kedua bentuk.
                        $kelasSemuaCr = 'todo-' . $key . '-poall-' . $poi;
                    @endphp

                    {{-- LAPIS 1 — PURCHASE ORDER (MN-03). --}}
                    <tr class="todo-po-row" data-target="todo-{{ $key }}-po-{{ $poi }}">
                        <td colspan="3">
                            <i class="fas fa-chevron-right todo-caret mr-2"></i>
                            <strong>{{ $poGroup['po'] }}</strong>
                            {{-- Manager hanya ikut ditulis kalau ada. Budget cost center (Overhead/
                                 Payroll) tidak punya PM, dan "(NAMA - -)" cuma menambah
                                 tanda hubung yang tidak mewakili apa pun. --}}
                            @php $poSub = $poGroup['customer'] . (($poGroup['pm'] && $poGroup['pm'] !== '-') ? ' - ' . $poGroup['pm'] : ''); @endphp
                            <span class="text-muted" style="font-size:11px;">({{ $poSub }})</span>
                            <span class="badge badge-secondary ml-1">{{ $poGroup['qty'] }} CR</span>
                            @if(!$planTunggal)
                                <span class="badge badge-light border ml-1" style="font-size:9px;">{{ $poGroup['plans']->count() }} budget plan</span>
                            @endif
                        </td>
                        <td class="text-right text-nowrap" style="font-weight:700;">
                            {{ number_format($poGroup['amount'], 0, ',', '.') }}
                        </td>
                        <td class="text-center text-nowrap">
                            @if($planTunggal)
                                {{-- Lapis Budget Plan dilewati, jadi tombol rincian
                                     anggarannya pindah ke baris PO ini. --}}
                                <button aria-label="View Budget" type="button" class="btn btn-outline-secondary btn-sm todo-view-budget"
                                        data-target="#mdlTodoCoa-{{ $key }}-{{ $poi }}-0" style="padding:1px 8px;font-size:10.5px;" title="View Budget">
                                    <i class="fas fa-chart-pie"></i>
                                </button>
                            @endif
                        </td>
                    </tr>

                    @foreach($poGroup['plans'] as $pi => $group)
                    @php
                        $gi = $poi . '-' . $pi;
                        // Baris CR menggantung pada PO kalau plannya tunggal,
                        // pada plan kalau tidak.
                        $indukBarisCr = $planTunggal
                            ? 'todo-' . $key . '-po-' . $poi
                            : 'todo-' . $key . '-grp-' . $gi;
                    @endphp

                    @if(!$planTunggal)
                    {{-- LAPIS 2 — BUDGET PLAN. Hanya dirender saat satu PO memang
                         punya lebih dari satu plan. --}}
                    <tr class="todo-group-row todo-{{ $key }}-po-{{ $poi }}" style="display:none;"
                        data-target="todo-{{ $key }}-grp-{{ $gi }}">
                        <td colspan="3" style="padding-left:28px;">
                            <i class="fas fa-chevron-right todo-caret mr-2"></i>
                            <strong>{{ $group['label'] }}</strong>
                            <span class="text-muted" style="font-size:11px;">({{ $group['pm'] }})</span>
                            <span class="badge badge-secondary ml-1">{{ $group['items']->count() }}</span>
                        </td>
                        <td class="text-right text-nowrap" style="font-weight:700;">
                            {{ number_format($group['items']->sum('amount'), 0, ',', '.') }}
                        </td>
                        <td class="text-center text-nowrap">
                            <button aria-label="View Budget" type="button" class="btn btn-outline-secondary btn-sm todo-view-budget" data-target="#mdlTodoCoa-{{ $key }}-{{ $gi }}" style="padding:1px 8px;font-size:10.5px;" title="View Budget">
                                <i class="fas fa-chart-pie"></i>
                            </button>
                        </td>
                    </tr>
                    @endif

                    @foreach($group['items'] as $cr)
                    @php
                        $crNob = $group['cr_nob'][$cr->id] ?? null;
                        $crNobClass = $crNob['full'] ?? false ? 'text-danger' : ($crNob['item'] ?? false ? 'rv-nob-item' : '');
                        // Pencarian ikut menjangkau No. PO, sesuai grouping baru.
                        $crSearch = strtolower($cr->code . ' ' . $cr->description . ' '
                            . ($cr->beneficiary?->nickname ?? '') . ' ' . $poGroup['po']);
                        $crAging = $cr->aging_days;
                        $crAgingClass = $crAging >= 3 ? 'text-danger' : ($crAging >= 1 ? 'rv-nob-item' : 'text-muted');
                    @endphp
                    <tr class="{{ $indukBarisCr }} {{ $kelasSemuaCr }} todo-row {{ $crNobClass }}" style="display:none;"
                        data-pm="{{ $group['pm'] }}" data-po="{{ $poGroup['po'] }}" data-search="{{ $crSearch }}">
                        <td class="text-nowrap">
                            <button type="button" class="cr-number-btn todo-open-drawer" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
                            @if($crNob['full'] ?? false)<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                        </td>
                        <td class="{{ $crNobClass }}">{{ Str::limit($cr->description, 150) }}</td>
                        <td class="text-nowrap {{ $crNobClass }}">{{ $cr->beneficiary?->nickname ?? '-' }}</td>
                        <td class="text-right text-nowrap {{ $crNobClass }}"><strong>{{ $cr->amount_formatted }}</strong></td>
                        <td class="text-center text-nowrap {{ $crAgingClass }}">{{ $cr->aging_label }}</td>
                    </tr>
                    @endforeach
                    @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:var(--surface-2);">
                        <th colspan="3" class="text-right">Subtotal</th>
                        <th class="text-right text-nowrap">
                            {{ number_format($groups->sum('amount'), 0, ',', '.') }}
                        </th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

{{-- MODAL VIEW BUDGET — breakdown Chart of Account per Budget Plan --}}
@foreach($groups as $poi => $poGroup)
@foreach($poGroup['plans'] as $pi => $group)
@php $gi = $poi . '-' . $pi; @endphp
<div class="modal fade" id="mdlTodoCoa-{{ $key }}-{{ $gi }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <div>
                    <h5 class="modal-title text-white mb-0">{{ $group['label'] }} <small style="color:var(--text-faint);">({{ $group['pm'] }})</small></h5>
                    <div style="color:var(--text-faint);font-size:11px;margin-top:2px;">PO Reference: <strong style="color:var(--accent);">{{ $group['po_code'] ?? '-' }}</strong></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            {{-- overflow-x: tabel rincian CoA ini lebih lebar dari layar tablet.
                 Tanpa gulir mendatar di sini, lebarnya mendorong SELURUH halaman
                 melebar — termasuk saat modalnya masih tertutup. --}}
            <div class="modal-body p-0" style="max-height:75vh;overflow-y:auto;overflow-x:auto;">
                @php $fmt = fn ($v) => number_format($v, 0, ',', '.'); @endphp
                <table class="table table-bordered mb-0" style="font-size:12px;min-width:680px;">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" style="color:var(--text-faint);font-size:11px;">Chart of Account</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Price</th>
                            <th scope="col" class="text-center text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Qty</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Budget</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Used</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;" title="Nilai pengajuan yang sedang menunggu di tahap Anda">Request</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;" title="Sudah lolos 3 tahap approval, tinggal menunggu diproses">Approved</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($group['coa_groups'] as $ci => $coa)
                        @php
                            $coaColorClass = $coa['remaining'] >= 0 ? 'text-success' : 'text-danger';
                            $coaNobClass = $coa['has_nob'] ? 'text-danger' : '';
                            $coaClass = 'todocoa-' . $key . '-' . $gi . '-' . $ci;
                        @endphp
                        <tr class="todo-coa-row" data-target="{{ $coaClass }}" style="cursor:pointer;background:var(--surface-3);">
                            <td colspan="3" class="{{ $coaNobClass }}" style="font-weight:700;">
                                <button type="button" class="btn btn-outline-secondary" style="padding:1px 6px;margin-right:6px;" tabindex="-1">
                                    <i class="fas fa-chevron-right todo-coa-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>
                                </button>
                                {{ $coa['account'] }}
                                <span class="badge badge-secondary ml-1" style="font-size:9px;">{{ $coa['items']->count() }}</span>
                                @if($coa['has_nob'])<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                @if($coa['approved'] > 0)<span class="badge badge-success ml-1" style="font-size:9px;">Approved</span>@endif
                                @if($coa['request'] > 0)<span class="badge badge-warning ml-1" style="font-size:9px;">New Request</span>@endif
                            </td>
                            <td class="text-right text-nowrap {{ $coaNobClass }}" style="font-weight:700;">{{ $fmt($coa['budget']) }}</td>
                            <td class="text-right text-nowrap {{ $coaNobClass }}" style="font-weight:700;">{{ $fmt($coa['used']) }}</td>
                            <td class="text-right text-nowrap" style="font-weight:700;color:var(--warning);">{{ $coa['request'] > 0 ? $fmt($coa['request']) : '-' }}</td>
                            <td class="text-right text-nowrap" style="font-weight:700;color:var(--success);">{{ $coa['approved'] > 0 ? $fmt($coa['approved']) : '-' }}</td>
                            <td class="text-right text-nowrap {{ $coaColorClass }}" style="font-weight:700;">{{ $fmt($coa['remaining']) }}</td>
                        </tr>

                        @foreach($coa['items'] as $si => $sub)
                        @php
                            $isNobItem = $sub['used'] > $sub['total'];
                            $subColorClass = $isNobItem ? 'text-danger' : ($sub['remaining'] >= 0 ? 'text-success' : 'text-danger');
                            $subWouldBeNob = $sub['remaining'] < 0;
                            $subClass = $coaClass . '-si-' . $si;
                        @endphp
                        <tr class="{{ $coaClass }}" style="display:none;">
                            <td style="padding-left:24px;">
                                {{ $sub['deskripsi'] ?: '-' }}
                                @if($isNobItem)<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                @if($sub['approved_amount'] > 0)
                                <span class="badge badge-success ml-1" style="font-size:9px;">Approved</span>
                                @endif
                                @if($sub['request_amount'] > 0)
                                <span class="badge badge-warning ml-1" style="font-size:9px;">New Request</span>
                                @endif
                                @if($sub['request_amount'] > 0 || $sub['approved_amount'] > 0)
                                @if(count($sub['cr_items']))
                                <button type="button" class="btn btn-outline-secondary todo-si-toggle" data-target="{{ $subClass }}-row" style="font-size:9px;line-height:1;padding:2px 6px;margin-left:6px;">
                                    {{ count($sub['cr_items']) }} CR <i class="fas fa-chevron-down ml-1" style="font-size:8px;"></i>
                                </button>
                                @endif
                                @endif
                            </td>
                            <td class="text-right text-nowrap">{{ $fmt($sub['total']) }}</td>
                            <td class="text-center text-nowrap">{{ rtrim(rtrim(number_format($sub['qty'], 2, ',', '.'), '0'), ',') }} {{ $sub['unit'] }}</td>
                            <td class="text-right text-nowrap"><strong>{{ $sub['total_formatted'] }}</strong></td>
                            <td class="text-right text-nowrap">{{ $sub['used_formatted'] }}</td>
                            <td class="text-right text-nowrap" style="color:var(--warning);">{{ $sub['request_formatted'] ?? '-' }}</td>
                            <td class="text-right text-nowrap" style="color:var(--success);">{{ $sub['approved_formatted'] ?? '-' }}</td>
                            <td class="text-right text-nowrap {{ $subColorClass }}">{{ $sub['remaining_formatted'] }}</td>
                        </tr>

                        @if(count($sub['cr_items']))
                        <tr class="{{ $subClass }}-row" style="display:none;">
                            <td colspan="8" style="background:var(--surface-2);padding:10px 14px 10px 48px;">
                                <table class="table table-sm table-bordered mb-0" style="width:auto;max-width:100%;font-size:11px;color:var(--text);">
                                    <thead style="background:var(--surface-2);">
                                        <tr>
                                            <th scope="col" class="text-nowrap" style="width:1%;">Date</th>
                                            <th scope="col">Description</th>
                                            <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                            <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                                            <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                            <th scope="col" class="text-center text-nowrap" style="width:1%;">Takeout</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($sub['cr_items'] as $cri)
                                        @php
                                            $criCr = $cri['is_history'] ? null : $group['items']->firstWhere('id', $cri['cr_id']);
                                            $criTakeout  = $cri['is_takeout'] ?? false;
                                            $criNobClass = ($cri['is_history'] || $criTakeout) ? '' : ($group['is_nob_full'] ? 'text-danger' : ($subWouldBeNob ? 'rv-nob-item' : ''));
                                            /*
                                             | Satu baris di sini = SATU ITEM pengajuan (request_detail),
                                             | bukan CR utuh — jadi aksi yang masuk akal di sini adalah
                                             | takeout/restore item itu, bukan approve/reject seluruh CR
                                             | (yang tetap ada di bulk bar worklist & drawer detail CR).
                                             | Gerbang perannya dipinjam dari BudgetRequest::canModerateItems(),
                                             | sumber yang sama dengan yang ditegakkan oleh
                                             | BudgetRequestController::takeoutDetail().
                                             */
                                            $criBolehModerasi = $criCr?->canModerateItems(Auth::user()) ?? false;
                                            $criCoret = $criTakeout ? 'text-decoration:line-through;color:var(--text-faint);' : '';
                                        @endphp
                                        <tr @if($cri['is_history']) style="color:var(--text-faint);" @endif class="{{ $criNobClass }}">
                                            <td class="text-nowrap" style="{{ $criCoret }}">{{ $cri['date_formatted'] }}</td>
                                            <td style="{{ $criCoret }}">
                                                {{ $cri['description'] }}
                                                @if($criTakeout)
                                                <span class="badge badge-warning" style="font-size:8px;text-decoration:none;" title="{{ $cri['takeout_notes'] }}">Takeout &middot; {{ $cri['takeout_stage_label'] ?? '-' }}</span>
                                                @elseif($cri['stage'] === 'request')
                                                <span class="badge badge-warning" style="font-size:8px;">New Request</span>
                                                @elseif($cri['stage'] === 'approved')
                                                <span class="badge badge-success" style="font-size:8px;">Approved</span>
                                                @endif
                                                @if($criNobClass === 'text-danger')<span class="badge badge-danger ml-1" style="font-size:8px;">NOB</span>@endif
                                                @if($criTakeout && $cri['takeout_notes'])
                                                <br><small class="text-muted" style="text-decoration:none;">Alasan: {{ $cri['takeout_notes'] }}</small>
                                                @endif
                                            </td>
                                            <td class="text-nowrap" style="{{ $criCoret }}">{{ $cri['beneficiary'] }}</td>
                                            <td class="text-right text-nowrap" style="{{ $criCoret }}"><strong>{{ $cri['amount_formatted'] }}</strong></td>
                                            <td class="text-nowrap">
                                                <span class="badge badge-secondary" style="font-size:9px;">{{ \App\Models\BudgetRequest::statusLabelFor($cri['status']) }}</span>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <div class="d-flex justify-content-center" style="gap:4px;">
                                                    <button aria-label="View" type="button" class="btn btn-info btn-sm todo-open-drawer" data-id="{{ $cri['cr_id'] }}" title="View"><i class="fas fa-eye"></i></button>
                                                    @if($criBolehModerasi && $criTakeout)
                                                    <button type="button" class="btn btn-outline-secondary btn-sm td-restore-detail"
                                                            data-cr="{{ $cri['cr_id'] }}" data-detail="{{ $cri['detail_id'] }}"
                                                            title="Kembalikan item"><i class="fas fa-undo"></i></button>
                                                    @elseif($criBolehModerasi)
                                                    <button type="button" class="btn btn-outline-danger btn-sm td-takeout-detail"
                                                            data-cr="{{ $cri['cr_id'] }}" data-detail="{{ $cri['detail_id'] }}"
                                                            data-desc="{{ $cri['description'] }}"
                                                            title="Takeout item"><i class="fas fa-cut"></i></button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">Belum ada detail budget.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--surface-2);">
                            <th colspan="3" class="text-right">Total</th>
                            <th class="text-right" style="color:var(--info);">{{ $fmt($group['coa_groups']->sum('budget')) }}</th>
                            <th class="text-right">{{ $fmt($group['coa_groups']->sum('used')) }}</th>
                            <th class="text-right" style="color:var(--warning);">{{ $fmt($group['coa_groups']->sum('request')) }}</th>
                            <th class="text-right" style="color:var(--success);">{{ $fmt($group['coa_groups']->sum('approved')) }}</th>
                            <th class="text-right">{{ $fmt($group['coa_groups']->sum('remaining')) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endforeach
@endif
