{{--
    Satu worklist Cost Request grouped per Budget Plan, dengan checkbox + bulk
    approve/reject dan modal "View Budget" (breakdown Chart of Account + NOB
    coloring) — persis pola "Review Cost Request" yang dulu ada di
    budget-request/index.blade.php, sekarang dipakai ulang untuk tiap role
    (Head/Cost Control/CEO) di halaman Todo List.

    Wajib diisi dari pemanggil:
      $key          : string unik per section (dipakai untuk id elemen, mis. 'head')
      $title        : judul card
      $statusLabel  : label status yang sedang direview (mis. 'Submitted')
      $groups       : Collection PO-level (MN-03) —
                      [{po, customer, pm, qty, amount, plans: [{label, pm, po_code,
                        items, coa_groups, is_nob_full, cr_nob}]}]
      $approveUrl   : action form bulk approve
      $rejectUrl    : action form bulk reject
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

    <div class="todo-bulk-bar">
        <div class="d-flex align-items-center justify-content-between">
            <div style="font-size:12.5px;color:var(--text);"><strong class="todo-bulk-count">0</strong> CR dipilih<span class="todo-bulk-amount"></span></div>
            <div class="d-flex" style="gap:8px;">
                <button type="button" class="btn btn-outline-success btn-sm todo-bulk-approve" disabled>
                    <i class="fas fa-check mr-1"></i>Approve
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm todo-bulk-reject" disabled>
                    <i class="fas fa-times mr-1"></i>Reject
                </button>
            </div>
        </div>
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
                        <th scope="col" style="width:36px;"><input type="checkbox" class="todo-check-all"></th>
                        <th scope="col" class="text-nowrap" style="width:235px;">CR Number</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="text-nowrap" style="width:140px;">Beneficiary</th>
                        <th scope="col" class="text-right text-nowrap" style="width:130px;">Amount</th>
                        <th scope="col" class="text-center text-nowrap" style="width:95px;">Aging</th>
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
                        <td><input type="checkbox" class="todo-check-group" data-group="{{ $kelasSemuaCr }}"></td>
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
                        <td><input type="checkbox" class="todo-check-group" data-group="todo-{{ $key }}-grp-{{ $gi }}"></td>
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
                    <tr class="{{ $indukBarisCr }} {{ $kelasSemuaCr }} todo-row {{ $crNobClass }}" data-export-key="cr:{{ $cr->id }}" style="display:none;"
                        data-pm="{{ $group['pm'] }}" data-po="{{ $poGroup['po'] }}" data-search="{{ $crSearch }}">
                        <td><input type="checkbox" class="todo-check" value="{{ $cr->id }}" data-amount="{{ $cr->amount }}"></td>
                        {{-- Jangan nowrap: nomor CR plus badge NOB bisa melebihi lebar
                             kolom, dan dengan table-layout:fixed sisanya menimpa kolom
                             Description. Badge dibiarkan turun ke baris berikutnya. --}}
                        <td>
                            <button type="button" class="cr-number-btn todo-open-drawer text-nowrap" data-id="{{ $cr->id }}">{{ $cr->code }}</button>
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
                        <th colspan="4" class="text-right">Subtotal</th>
                        <th class="text-right text-nowrap">
                            {{ number_format($groups->sum('amount'), 0, ',', '.') }}
                        </th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- FORM tersembunyi untuk bulk approve/reject — ids diisi JS dari checkbox --}}
    <form id="frmTodoBulkApprove-{{ $key }}" action="{{ $approveUrl }}" method="POST" style="display:none;">
        @csrf
    </form>
    <form id="frmTodoBulkReject-{{ $key }}" action="{{ $rejectUrl }}" method="POST" style="display:none;">
        @csrf
        <textarea name="notes"></textarea>
    </form>
</div>

@include('todo-list._budget_modals', ['key' => $key, 'groups' => $groups])
@endif
