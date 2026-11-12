{{--
    Daftar CR dalam Batch — grouping per Budget Plan/PO + modal "View Budget"
    (breakdown Chart of Account). Dipakai halaman show dan tab Item drawer
    daftar Treasury (DailyTransactionController::itemsHtml). Aksi baris
    (Keluarkan / Takeout / Reject) membaca id batch dari [data-batch-id];
    perilakunya di public/js/tesys-treasury-batch.js.

    Variabel: $batch, $groupedItems
--}}
<style>
#dtItemsBody tr.dt-group-row { cursor:pointer; background:var(--info-bg) !important; }
#dtItemsBody tr.dt-group-row:hover { background:var(--surface-3) !important; }
#dtItemsBody tr.dt-group-row td { background:transparent !important; }
#dtItemsBody .dt-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
#dtItemsBody .dt-caret.rotated { transform:rotate(90deg); }

/* MODAL VIEW BUDGET — Chart of Account & sub-item, sama pola dengan Todo List */
.dt-coa-row { cursor:pointer; background:var(--surface-3); }
.dt-coa-row:hover { background:var(--surface-3); }
.dt-coa-caret { transition:transform .15s ease; }
.dt-coa-caret.rotated { transform:rotate(90deg); }
.dt-variance-pos { color:var(--success) !important; }
.dt-variance-neg { color:var(--danger) !important; }

.cr-number-btn { background:transparent;border:none;padding:2px 8px;border-radius:var(--radius-sm);color:var(--info);font-weight:700;font-size:12px;transition:background .15s ease;text-decoration:none;display:inline-block;cursor:pointer; }
.cr-number-btn:hover { background:rgba(37,99,235,0.1);color:#1d4ed8;text-decoration:none; }

/* CR yang di-takeout dari batch — tampilkan pudar & dicoret */
.dt-takeout-row { color:var(--text-faint); }
.dt-takeout-desc { text-decoration:line-through; }
</style>
<div class="dt-items-wrap" data-batch-id="{{ $batch->id }}">
@php
    $NOB_RED = 'var(--danger)'; $NOB_YELLOW = 'var(--warning)'; $OK_GREEN = 'var(--success)';
    $fmt = fn ($v) => number_format($v, 0, ',', '.');
@endphp
<div style="padding-bottom:6px;">
    <button type="button" class="btn btn-link dt-toggle-all p-0" style="font-size:11.5px;font-weight:600;text-decoration:none;" data-state="collapsed">
        <i class="fas fa-expand-alt mr-1"></i>Expand All
    </button>
</div>
<div class="table-responsive">
    <table class="table table-bordered mb-0" style="font-size:12px;">
        <thead class="bg-light">
            <tr>
                <th scope="col" class="text-nowrap" style="width:1%;">CR Number</th>
                <th scope="col">Description</th>
                <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                <th scope="col" class="text-right text-nowrap" style="width:1%;">Amount</th>
                <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
            </tr>
        </thead>
        <tbody id="dtItemsBody">
            @forelse($groupedItems as $g => $group)
            <tr class="dt-group-row" data-target="dt-grp-{{ $g }}" style="cursor:pointer;background:var(--info-bg);">
                <td colspan="3" style="font-weight:700;">
                    <i class="fas fa-chevron-right dt-caret mr-2" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>
                    {{ $group['label'] }}
                    <span class="text-muted" style="font-size:11px;">({{ $group['pm'] }})</span>
                    <span class="badge badge-secondary ml-1" style="font-size:9px;">{{ $group['items']->count() }} CR</span>
                </td>
                <td class="text-right text-nowrap" style="font-weight:700;">{{ $fmt($group['subtotal']) }}</td>
                <td class="text-center text-nowrap">
                    <button aria-label="View Budget" type="button" class="btn btn-outline-secondary btn-sm dt-view-budget" data-target="#mdlDtCoa-{{ $g }}" style="padding:1px 8px;font-size:10.5px;" title="View Budget">
                        <i class="fas fa-chart-pie"></i>
                    </button>
                </td>
            </tr>

            @foreach($group['items'] as $item)
            @php
                // MN-09 — baris batch bisa berasal dari Cost
                // Request ATAU Cost Register. Perbedaannya
                // diselesaikan di sini sekali, lewat helper
                // seragam di DailyTransactionItem.
                $isRegister = $item->isCostRegister();
                $isSecondPay = $item->isSecondPayment();
                $cr         = $item->budgetRequest;
                $isTakeout  = $item->status === 'REJECTED';
                $rowCode    = $item->sourceCode();
                $rowDesc    = $item->sourceDescription();
                $rowBen     = $item->sourceBeneficiaryName();
                $rowAmount  = number_format($item->sourceAmount(), 0, ',', '.');
                $rowUrl     = $isRegister
                    ? url('/expense')
                    : ('/budget-request?open=' . ($cr->id ?? ''));
            @endphp
            <tr class="dt-grp-{{ $g }} {{ $isTakeout ? 'dt-takeout-row' : '' }}" style="display:none;">
                <td class="text-nowrap">
                    <a href="{{ $rowUrl }}" target="_blank" class="cr-number-btn">{{ $rowCode }}</a>
                </td>
                <td class="{{ $isTakeout ? 'dt-takeout-desc' : '' }}">
                    @if($isRegister)
                        <span class="badge badge-info mr-1" style="font-size:9px;">EXPENSE</span>
                    @elseif($isSecondPay)
                        {{-- Baris ini membayar SELISIH Settlement, bukan nilai CR.
                             Ditandai jelas supaya tidak dibaca sebagai transfer ganda. --}}
                        <span class="badge badge-warning mr-1" style="font-size:9px;" title="Kekurangan bayar hasil Settlement">2ND PAYMENT</span>
                    @endif
                    {{ Str::limit($rowDesc, 130) }}
                    @if($isTakeout)<span class="badge badge-light border ml-1" style="font-size:9px;">Takeout</span>@endif
                </td>
                <td class="text-nowrap">{{ $rowBen }}</td>
                <td class="text-right text-nowrap"><strong>{{ $rowAmount }}</strong></td>
                <td class="text-center text-nowrap">
                    @php
                        $bolehTakeout = !$isTakeout && $batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','approver']);
                        // Superadmin: Takeout juga dari batch DONE / PAID (tanpa Reject).
                        $bolehTakeoutSuper = !$isTakeout && ($batch->isDone() || $batch->isPaid()) && Auth::user()->hasRole(['superadmin']);
                        $bolehKeluarDraft = !$isTakeout && $batch->isDraft() && Auth::user()->hasRole(['superadmin','finance','finance_manager','treasury']);
                    @endphp
                    <div class="action-buttons ts-actions">
                        @if($bolehKeluarDraft)
                        <button type="button" class="ts-action-primary is-danger btn-draft-remove-item"
                                data-item-id="{{ $item->id }}" data-code="{{ $rowCode }}"
                                title="Keluarkan dari draft" aria-label="Keluarkan {{ $rowCode }} dari draft">
                            <i class="fas fa-times" aria-hidden="true"></i><span class="ts-al">Keluarkan</span>
                        </button>
                        @endif

                        <a href="{{ $rowUrl }}" target="_blank"
                           class="ts-action-primary is-neutral"
                           title="{{ $isRegister ? 'Lihat Expense' : 'Lihat Cost Request' }}">
                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Lihat</span>
                        </a>

                        @if($bolehTakeoutSuper)
                        <button type="button" class="ts-action-primary is-danger btn-takeout-cr-item"
                                data-item-id="{{ $item->id }}" data-code="{{ $rowCode }}"
                                title="Takeout (Superadmin)" aria-label="Takeout {{ $rowCode }}">
                            <i class="fas fa-times" aria-hidden="true"></i><span class="ts-al">Takeout</span>
                        </button>
                        @endif

                        @if($bolehTakeout)
                        <button type="button" class="ts-action-more"
                                aria-haspopup="true" aria-expanded="false"
                                title="Aksi lain" aria-label="Aksi lain untuk {{ $rowCode }}">
                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                        </button>
                        <div class="ts-action-menu dropdown-menu" hidden>
                            <button type="button" class="dropdown-item is-danger btn-takeout-cr-item"
                                    data-item-id="{{ $item->id }}" data-code="{{ $rowCode }}">
                                <i class="fas fa-times"></i>Takeout
                            </button>
                            @unless($isRegister)
                            <button type="button" class="dropdown-item is-danger btn-takeout-cr-item"
                                    data-mode="reject"
                                    data-item-id="{{ $item->id }}" data-code="{{ $rowCode }}">
                                <i class="fas fa-ban"></i>Reject
                            </button>
                            @endunless
                        </div>
                        @endif

                    </div>
                </td>
            </tr>
            @endforeach
            @empty
            <tr>
                <td colspan="5" class="text-center text-muted py-4">Tidak ada CR dalam batch ini.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background:var(--surface-2);">
                <th colspan="3" class="text-right">Total (INCLUDED)</th>
                <th class="text-right text-nowrap">{{ $fmt($groupedItems->sum('subtotal')) }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>
</div>

<div class="dt-coa-modals" data-batch-id="{{ $batch->id }}">
{{-- MODAL VIEW BUDGET — breakdown Chart of Account per grup (PO/Budget Plan), pola sama seperti Todo List --}}
@foreach($groupedItems as $g => $group)
<div class="modal fade" id="mdlDtCoa-{{ $g }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <div>
                    <h5 class="modal-title text-white mb-0">{{ $group['label'] }} <small style="color:var(--text-faint);">({{ $group['pm'] }})</small></h5>
                    @if($group['po_code'])
                    <div style="color:var(--text-faint);font-size:11px;margin-top:2px;">PO Reference: <strong style="color:var(--accent);">{{ $group['po_code'] }}</strong></div>
                    @endif
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0" style="max-height:75vh;overflow-y:auto;">
                <table class="table table-bordered mb-0" style="font-size:12px;">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" style="color:var(--text-faint);font-size:11px;">Chart of Account</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Price</th>
                            <th scope="col" class="text-center text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Qty</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Budget</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Used</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Request</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($group['coa_groups'] as $ci => $coa)
                        @php
                            $coaColorClass = $coa['remaining'] >= 0 ? 'dt-variance-pos' : 'dt-variance-neg';
                            $coaNobClass = $coa['has_nob'] ? 'dt-variance-neg' : '';
                            $coaClass = 'dtcoa-' . $g . '-' . $ci;
                            $coaHasRequest = $coa['items']->contains(fn ($i) => $i['request_amount'] > 0);
                        @endphp
                        <tr class="dt-coa-row" data-target="{{ $coaClass }}" style="cursor:pointer;background:var(--surface-3);">
                            <td colspan="3" class="{{ $coaNobClass }}" style="font-weight:700;">
                                <button type="button" class="btn btn-outline-secondary" style="padding:1px 6px;margin-right:6px;" tabindex="-1">
                                    <i class="fas fa-chevron-right dt-coa-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>
                                </button>
                                {{ $coa['account'] }}
                                <span class="badge badge-secondary ml-1" style="font-size:9px;">{{ $coa['items']->count() }}</span>
                                @if($coa['has_nob'])<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                @if($coaHasRequest)<span class="badge badge-warning ml-1" style="font-size:9px;">Request</span>@endif
                            </td>
                            <td class="text-right text-nowrap {{ $coaNobClass }}" style="font-weight:700;">{{ $fmt($coa['budget']) }}</td>
                            <td class="text-right text-nowrap {{ $coaNobClass }}" style="font-weight:700;">{{ $fmt($coa['used']) }}</td>
                            <td class="text-right text-nowrap" style="font-weight:700;color:var(--warning);">{{ $coa['request'] > 0 ? $fmt($coa['request']) : '-' }}</td>
                            <td class="text-right text-nowrap {{ $coaColorClass }}" style="font-weight:700;">{{ $fmt($coa['remaining']) }}</td>
                        </tr>

                        @foreach($coa['items'] as $si => $sub)
                        @php
                            $isNobItem = $sub['used'] > $sub['total'];
                            $subColorClass = $isNobItem ? 'dt-variance-neg' : ($sub['remaining'] >= 0 ? 'dt-variance-pos' : 'dt-variance-neg');
                            $subRowNobClass = $isNobItem ? 'dt-variance-neg' : '';
                            $subClass = $coaClass . '-si-' . $si;
                        @endphp
                        <tr class="{{ $coaClass }} {{ $subRowNobClass }}" style="display:none;">
                            <td style="padding-left:24px;">
                                {{ $sub['deskripsi'] ?: '-' }}
                                @if($isNobItem)<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                @if($sub['request_amount'] > 0)
                                <span class="badge badge-warning ml-1" style="font-size:9px;">Request</span>
                                @if(count($sub['cr_items']))
                                <button type="button" class="btn btn-outline-secondary dt-si-toggle" data-target="{{ $subClass }}-row" style="font-size:9px;padding:1px 7px;margin-left:6px;">
                                    {{ count($sub['cr_items']) }} CR <i class="fas fa-chevron-down ml-1"></i>
                                </button>
                                @endif
                                @endif
                            </td>
                            <td class="text-right text-nowrap">{{ $sub['amount_formatted'] }}</td>
                            <td class="text-center text-nowrap">{{ rtrim(rtrim(number_format($sub['qty'], 2, ',', '.'), '0'), ',') }} {{ $sub['unit'] }}</td>
                            <td class="text-right text-nowrap"><strong>{{ $sub['total_formatted'] }}</strong></td>
                            <td class="text-right text-nowrap">{{ $sub['used_formatted'] }}</td>
                            <td class="text-right text-nowrap" style="color:var(--warning);">{{ $sub['request_formatted'] ?? '-' }}</td>
                            <td class="text-right text-nowrap {{ $subColorClass }}">{{ $sub['remaining_formatted'] }}</td>
                        </tr>

                        @if(count($sub['cr_items']))
                        <tr class="{{ $subClass }}-row" style="display:none;">
                            <td colspan="7" style="background:var(--surface-2);padding:10px 14px 10px 48px;">
                                <table class="table table-sm table-bordered mb-0" style="font-size:11px;color:var(--text);width:auto;max-width:100%;">
                                    <thead style="background:var(--surface-2);">
                                        <tr>
                                            <th scope="col" class="text-nowrap" style="width:1%;">Date</th>
                                            <th scope="col">Description</th>
                                            <th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>
                                            <th scope="col" class="text-right text-nowrap" style="width:1%;">Price</th>
                                            <th scope="col" class="text-center text-nowrap" style="width:1%;">Qty</th>
                                            <th scope="col" class="text-right text-nowrap" style="width:1%;">Total</th>
                                            <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($sub['cr_items'] as $cri)
                                        <tr @if($cri['item_status'] === 'REJECTED') style="color:var(--text-faint);text-decoration:line-through;" @endif>
                                            <td class="text-nowrap">{{ $cri['date_formatted'] }}</td>
                                            <td>
                                                {{ $cri['description'] }}
                                                @if($cri['item_status'] === 'REJECTED')
                                                <span class="badge badge-light border" style="font-size:8px;text-decoration:none;display:inline-block;">Takeout</span>
                                                @elseif($cri['item_status'] === 'HISTORY')
                                                <span class="badge badge-light border" style="font-size:8px;text-decoration:none;display:inline-block;">Settled</span>
                                                @else
                                                <span class="badge badge-warning" style="font-size:8px;display:inline-block;">Request</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">{{ $cri['beneficiary'] }}</td>
                                            <td class="text-right text-nowrap">{{ $cri['price_formatted'] }}</td>
                                            <td class="text-center text-nowrap">{{ $cri['qty'] }}</td>
                                            <td class="text-right text-nowrap"><strong>{{ $cri['amount_formatted'] }}</strong></td>
                                            <td class="text-center text-nowrap">
                                                @php
                                                    $bolehTakeoutItem = $cri['item_status'] === 'INCLUDED' && $batch->isPendingApprover() && Auth::user()->hasRole(['superadmin','approver']);
                                                    $bolehTakeoutSuperItem = $cri['item_status'] === 'INCLUDED' && ($batch->isDone() || $batch->isPaid()) && Auth::user()->hasRole(['superadmin']);
                                                    $bolehKeluarDraftItem = $cri['item_status'] === 'INCLUDED' && $batch->isDraft() && Auth::user()->hasRole(['superadmin','finance','finance_manager','treasury']);
                                                @endphp
                                                <div class="action-buttons ts-actions">
                                                    @if($bolehKeluarDraftItem)
                                                    <button type="button" class="ts-action-primary is-danger btn-draft-remove-item"
                                                            data-item-id="{{ $cri['item_id'] }}" data-code="{{ $cri['cr_code'] }}"
                                                            title="Keluarkan dari draft" aria-label="Keluarkan {{ $cri['cr_code'] }} dari draft">
                                                        <i class="fas fa-times" aria-hidden="true"></i><span class="ts-al">Keluarkan</span>
                                                    </button>
                                                    @endif

                                                    <a href="/budget-request?open={{ $cri['cr_id'] }}" target="_blank"
                                                       class="ts-action-primary is-neutral" title="Lihat Cost Request">
                                                        <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Lihat</span>
                                                    </a>

                                                    @if($bolehTakeoutSuperItem)
                                                    <button type="button" class="ts-action-primary is-danger btn-takeout-cr-item"
                                                            data-item-id="{{ $cri['item_id'] }}" data-code="{{ $cri['cr_code'] }}"
                                                            title="Takeout (Superadmin)" aria-label="Takeout {{ $cri['cr_code'] }}">
                                                        <i class="fas fa-times" aria-hidden="true"></i><span class="ts-al">Takeout</span>
                                                    </button>
                                                    @endif

                                                    @if($bolehTakeoutItem)
                                                    <button type="button" class="ts-action-more"
                                                            aria-haspopup="true" aria-expanded="false"
                                                            title="Aksi lain" aria-label="Aksi lain untuk {{ $cri['cr_code'] }}">
                                                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                                    </button>
                                                    <div class="ts-action-menu dropdown-menu" hidden>
                                                        <button aria-label="Takeout" type="button"
                                                                class="dropdown-item is-danger btn-takeout-cr-item"
                                                                data-item-id="{{ $cri['item_id'] }}" data-code="{{ $cri['cr_code'] }}">
                                                            <i class="fas fa-times"></i>Takeout
                                                        </button>
                                                        <button aria-label="Reject" type="button"
                                                                class="dropdown-item is-danger btn-takeout-cr-item"
                                                                data-mode="reject"
                                                                data-item-id="{{ $cri['item_id'] }}" data-code="{{ $cri['cr_code'] }}">
                                                            <i class="fas fa-ban"></i>Reject
                                                        </button>
                                                    </div>
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
                        <tr><td colspan="7" class="text-center text-muted py-3">{{ $g ? 'Belum ada detail budget.' : 'CR ini tidak terhubung ke Budget Plan manapun.' }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--surface-2);">
                            <th colspan="3" class="text-right">Total</th>
                            <th class="text-right" style="color:var(--info);">{{ $fmt($group['coa_groups']->sum('budget')) }}</th>
                            <th class="text-right">{{ $fmt($group['coa_groups']->sum('used')) }}</th>
                            <th class="text-right" style="color:var(--warning);">{{ $fmt($group['coa_groups']->sum('request')) }}</th>
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
</div>
