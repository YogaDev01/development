{{-- Baris tab Database (gabungan Cash Advance + Cost Payment, read-only). Dimuat lewat BudgetRequestController::tab(). --}}
@foreach($rows as $cr)
    @php
        $isCa  = $cr->isCashAdvance();
        $isNob = (bool) $cr->is_nob;
        /* Kolom "Cost": sumber nilai berbeda menurut jenis —
           Cash Advance  -> nilai Settlement (realisasi),
           Cost Payment  -> nilai Request. */
        $cost  = $isCa ? (int) ($cr->realization_sum ?? 0) : (int) $cr->amount;
    @endphp
    <tr data-id="{{ $cr->id }}"
        data-status="{{ $cr->status }}"
        data-type="{{ $cr->type?->name ?? '' }}"
        data-jenis="{{ $isCa ? 'Cash Advance' : 'Cost Payment' }}"
        data-pminternal="{{ strtoupper($cr->pm_internal_name) }}"
        data-po="{{ $cr->budgetPlan?->project ? '[' . $cr->budgetPlan->project->code . '] ' . $cr->budgetPlan->project->name : '' }}"
        data-beneficiary="{{ $cr->beneficiary?->nickname ?? '' }}"
        data-pic="{{ $cr->beneficiary?->pic?->pic ?? '' }}"
        class="{{ $isNob ? 'row-nob' : '' }}">
        <td class="text-nowrap">{{ $loop->iteration }}</td>

        <td class="text-nowrap cr-col-code">
            <button type="button" class="cr-number-btn btn-review-cr" data-id="{{ $cr->id }}">
                {{ $cr->code }}
            </button>
        </td>

        <td class="cr-col-desc">
            <div style="font-size:13px;font-weight:600;">
                @if($isNob)
                    <span class="badge badge-danger px-1 py-1 mr-1" style="font-size:9px;">NOB</span>
                @endif
                {{ Str::limit($cr->description, 200) }}
            </div>
            <div style="font-size:10px;color:var(--text-faint);margin-top:2px;">
                {{ $cr->po_code ?? '-' }} - {{ $cr->type?->name ?? '-' }}, {{ strtoupper($cr->pm_internal_name) }}
            </div>
        </td>

        <td class="text-nowrap">
            <span class="badge {{ $isCa ? 'badge-info' : 'badge-secondary' }} px-2 py-1" style="font-size:10px;">
                {{ $isCa ? 'Cash Advance' : 'Cost Payment' }}
            </span>
        </td>

        <td class="text-nowrap" data-order="{{ $cr->created_at->format('Y-m-d') }}">
            {{ $cr->created_at->format('d/m/Y') }}
        </td>

        <td class="text-nowrap" data-order="{{ $cr->paid_at?->format('Y-m-d') ?? '' }}">
            {{ $cr->paid_at?->format('d/m/Y') ?? '-' }}
        </td>

        <td class="text-nowrap">
            {{ $cr->applicant?->username ? strtoupper($cr->applicant->username) : '-' }}
        </td>

        <td class="text-nowrap">
            {{ $cr->beneficiary?->pic?->pic ?? '-' }}
            <div style="font-size:10px;color:var(--text-faint);margin-top:2px;">{{ $cr->beneficiary?->nickname ?? '-' }}</div>
        </td>

        <td class="text-right text-nowrap col-raw-cost" data-raw="{{ $cost }}">
            <button type="button" class="cr-cell-link btn-detail-cr" data-id="{{ $cr->id }}"
                    data-tab="{{ $isCa ? '#crPaneRealization' : '#crPaneDetails' }}"
                    title="{{ $isCa ? 'Detail Settlement' : 'Detail Request' }}">
                {{ $cost > 0 ? number_format($cost, 0, ',', '.') : '-' }}
            </button>
        </td>

        <td class="text-nowrap">
            <x-status :status="$cr->status"
                      context="budget-request"
                      class="cr-status-link btn-detail-cr"
                      data-id="{{ $cr->id }}"
                      data-tab="#crPaneLogs"
                      role="button"
                      tabindex="0" />
        </td>
    </tr>
@endforeach
