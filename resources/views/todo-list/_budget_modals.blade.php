{{--
    MODAL VIEW BUDGET — breakdown Chart of Account per Budget Plan.

    Dipisah dari _worklist_section supaya section settlement (yang tabelnya
    ditulis langsung di index.blade.php, bukan lewat partial worklist) bisa
    memakai modal yang sama persis, bukan menyalinnya.

    Wajib diisi dari pemanggil:
      $key    : string unik per section (dipakai untuk id modal)
      $groups : Collection PO-level yang sama dengan _worklist_section
--}}
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
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Budget</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;">Used</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;" title="Nilai pengajuan yang sedang menunggu di tahap Anda">Request</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;" title="Pengajuan ke-2 (kelebihan settlement Cash Advance) yang sedang direview">Expense Claim</th>
                            <th scope="col" class="text-right text-nowrap" style="color:var(--text-faint);font-size:11px;width:1%;" title="Sudah di-approve (Approved / Second Approved), belum diproses ke Treasury">Approved</th>
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
                            <td class="{{ $coaNobClass }}" style="font-weight:700;">
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
                            <td class="text-right text-nowrap" style="font-weight:700;color:var(--danger);">{{ ($coa['expense_claim'] ?? 0) > 0 ? $fmt($coa['expense_claim']) : '-' }}</td>
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
                                @if($sub['request_amount'] > 0 || $sub['approved_amount'] > 0 || ($sub['expense_claim'] ?? 0) > 0)
                                @if(count($sub['cr_items']))
                                <button type="button" class="btn btn-outline-secondary todo-si-toggle" data-target="{{ $subClass }}-row" style="font-size:9px;line-height:1;padding:2px 6px;margin-left:6px;">
                                    {{ count($sub['cr_items']) }} CR <i class="fas fa-chevron-down ml-1" style="font-size:8px;"></i>
                                </button>
                                @endif
                                @endif
                            </td>
                            <td class="text-right text-nowrap"><strong>{{ $sub['total_formatted'] }}</strong></td>
                            <td class="text-right text-nowrap">{{ $sub['used_formatted'] }}</td>
                            <td class="text-right text-nowrap" style="color:var(--warning);">{{ $sub['request_formatted'] ?? '-' }}</td>
                            <td class="text-right text-nowrap" style="color:var(--danger);">{{ $sub['expense_claim_formatted'] ?? '-' }}</td>
                            <td class="text-right text-nowrap" style="color:var(--success);">{{ $sub['approved_formatted'] ?? '-' }}</td>
                            <td class="text-right text-nowrap {{ $subColorClass }}">{{ $sub['remaining_formatted'] }}</td>
                        </tr>

                        @if(count($sub['cr_items']))
                        <tr class="{{ $subClass }}-row" style="display:none;">
                            <td colspan="7" style="background:var(--surface-2);padding:10px 14px 10px 48px;">
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
                        <tr><td colspan="7" class="text-center text-muted py-3">Belum ada detail budget.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--surface-2);">
                            <th class="text-right">Total</th>
                            <th class="text-right" style="color:var(--info);">{{ $fmt($group['coa_groups']->sum('budget')) }}</th>
                            <th class="text-right">{{ $fmt($group['coa_groups']->sum('used')) }}</th>
                            <th class="text-right" style="color:var(--warning);">{{ $fmt($group['coa_groups']->sum('request')) }}</th>
                            <th class="text-right" style="color:var(--danger);">{{ $fmt($group['coa_groups']->sum('expense_claim')) }}</th>
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
