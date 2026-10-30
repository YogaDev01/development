{{-- Baris tab Database (gabungan Cash Advance + Cost Payment, read-only). Dimuat lewat BudgetRequestController::tab(). --}}
@foreach($rows as $cr)
    @php
        $isCa  = $cr->isCashAdvance();
        $isNob = (bool) $cr->is_nob;
        /* Kolom "Cost": sumber nilai berbeda menurut jenis —
           Cash Advance  -> nilai Settlement (realisasi); bila settlement masih 0
                            (belum disettle) pakai nilai Request,
           Cost Payment  -> nilai Request. */
        $caReal = (int) ($cr->realization_sum ?? 0);
        $cost   = $isCa ? ($caReal > 0 ? $caReal : (int) $cr->amount) : (int) $cr->amount;
    @endphp
    <tr data-id="{{ $cr->id }}"
        data-status="{{ $cr->status }}"
        data-type="{{ $cr->type?->name ?? '' }}"
        data-jenis="{{ $isCa ? 'Cash Advance' : 'Cost Payment' }}"
        data-pminternal="{{ strtoupper($cr->pm_internal_name) }}"
        data-po="{{ $cr->budgetPlan?->project ? '[' . $cr->budgetPlan->project->code . '] ' . $cr->budgetPlan->project->name : '' }}"
        data-beneficiary="{{ $cr->beneficiary?->nickname ?? '' }}"
        data-pic="{{ $cr->beneficiary?->pic?->pic ?? '' }}"
        data-requestor="{{ $cr->applicant?->username ? strtoupper($cr->applicant->username) : '' }}"
        class="{{ $isNob ? 'row-nob' : '' }}">
        <td class="text-nowrap"><span class="tw:tabular-nums tw:text-text-faint">{{ $loop->iteration }}</span></td>

        <td class="text-nowrap cr-col-code">
            <button type="button" class="cr-number-btn cr-copy-code" data-code="{{ $cr->code }}" title="Klik untuk menyalin kode">
                {{ $cr->code }}
            </button>
        </td>

        <td class="cr-col-desc" data-lbl="Description">
            {{-- Satu baris: deskripsi (tebal; merah bila NOB), dipotong 60 karakter. --}}
            <div title="{{ $cr->description }}" class="tw:flex tw:flex-nowrap tw:max-sm:flex-wrap tw:items-center tw:gap-x-2 tw:gap-y-1 tw:max-sm:justify-end tw:text-control tw:font-heading tw:leading-tight {{ $isNob ? 'tw:text-danger' : 'tw:text-text' }}">
                <span>{{ Str::limit($cr->description, 60) }}</span>
            </div>
        </td>

        <td class="text-nowrap" data-lbl="Jenis">
            <x-tw.badge dot :tone="$isCa ? 'progress' : 'neutral'">{{ $isCa ? 'Cash Advance' : 'Cost Payment' }}</x-tw.badge>
        </td>

        <td class="text-nowrap" data-order="{{ $cr->created_at->format('Y-m-d') }}" data-lbl="Tanggal">
            <span class="tw:text-data tw:tabular-nums tw:text-text-dim">{{ $cr->created_at->format('d/m/Y') }}</span>
        </td>

        <td class="text-nowrap" data-order="{{ $cr->paid_at?->format('Y-m-d') ?? '' }}" data-lbl="Tanggal Paid">
            <span class="tw:text-data tw:tabular-nums {{ $cr->paid_at ? 'tw:text-text-dim' : 'tw:text-text-faint' }}">{{ $cr->paid_at?->format('d/m/Y') ?? '-' }}</span>
        </td>

        <td data-lbl="Requestor">
            <span class="tw:text-data tw:text-text">{{ $cr->applicant?->username ? strtoupper($cr->applicant->username) : '-' }}</span>
        </td>

        <td data-lbl="PIC">
            <div class="tw:max-sm:text-right">
                {{-- Satu nilai: PIC bila ada, kalau kosong/"-" pakai beneficiary. --}}
                @php
                    $picNama = trim((string) ($cr->beneficiary?->pic?->pic ?? ''));
                    $picNama = ($picNama === '' || $picNama === '-') ? trim((string) ($cr->beneficiary?->nickname ?? '')) : $picNama;
                @endphp
                <span class="tw:block tw:text-data tw:leading-tight tw:text-text">{{ ($picNama === '' || $picNama === '-') ? '-' : $picNama }}</span>
            </div>
        </td>

        <td class="text-right text-nowrap col-raw-cost" data-lbl="Cost" data-raw="{{ $cost }}" data-order="{{ $cost }}">
            <button type="button" class="cr-cell-link btn-detail-cr tw:tabular-nums" data-id="{{ $cr->id }}"
                    data-tab="{{ $isCa ? '#crPaneRealization' : '#crPaneDetails' }}"
                    title="{{ $isCa ? 'Detail Settlement' : 'Detail Request' }}">
                {{ $cost > 0 ? number_format($cost, 0, ',', '.') : '-' }}
            </button>
        </td>

        <td class="text-nowrap" data-card="status">
            <x-status :status="$cr->status"
                      context="budget-request"
                      class="cr-status-link btn-detail-cr"
                      data-id="{{ $cr->id }}"
                      data-tab="#crPaneLogs"
                      role="button"
                      tabindex="0" />
        </td>

        @php $closeAt = $cr->second_paid_at ? \Illuminate\Support\Carbon::parse($cr->second_paid_at) : null; @endphp
        <td class="text-nowrap" data-order="{{ $closeAt?->format('Y-m-d') ?? '' }}" data-lbl="Close Date">
            <span class="tw:text-data tw:tabular-nums {{ $closeAt ? 'tw:text-text-dim' : 'tw:text-text-faint' }}">{{ $closeAt?->format('d/m/Y') ?? '-' }}</span>
        </td>
    </tr>
@endforeach
