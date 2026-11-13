{{--
    Tombol Approve/Reject Cost Request sesuai role & status — dipakai di beberapa
    tempat (table Review per Budget Plan, modal View Budget) supaya tidak
    duplikasi markup. Membutuhkan variable $cr (BudgetRequest).
    Pengaju tidak melihat tombol atas CR-nya sendiri (kecuali Superadmin).
--}}
@php
    $stageRoles = fn ($s) => array_merge(['superadmin'], \App\Models\BudgetRequest::STAGE_ROLES[$s] ?? []);
    $bukanSendiri = ! $cr->isSelfApprovalBlockedFor(Auth::user());
@endphp
@if($cr->status === 'SUBMITTED' && $bukanSendiri && Auth::user()->hasRole($stageRoles('SUBMITTED')))
<button type="button" class="btn btn-success btn-sm btn-head-approve" data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve"><i class="fas fa-check"></i></button>
<button type="button" class="btn btn-danger btn-sm btn-head-reject" data-id="{{ $cr->id }}" title="Reject"><i class="fas fa-times"></i></button>
@endif
@if($cr->status === 'CHECKED' && $bukanSendiri && Auth::user()->hasRole($stageRoles('CHECKED')))
<button type="button" class="btn btn-success btn-sm btn-finance-verify" data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Verify"><i class="fas fa-check-double"></i></button>
<button type="button" class="btn btn-danger btn-sm btn-finance-reject" data-id="{{ $cr->id }}" title="Reject"><i class="fas fa-times"></i></button>
@endif
@if($cr->status === 'VERIFIED' && $bukanSendiri && Auth::user()->hasRole($stageRoles('VERIFIED')))
<button type="button" class="btn btn-success btn-sm btn-ceo-approve" data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve"><i class="fas fa-check"></i></button>
<button type="button" class="btn btn-danger btn-sm btn-ceo-reject" data-id="{{ $cr->id }}" title="Reject"><i class="fas fa-times"></i></button>
@endif
