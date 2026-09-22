{{--
    Tombol Approve/Reject Cost Request sesuai role & status — dipakai di beberapa
    tempat (table Review per Budget Plan, modal View Budget) supaya tidak
    duplikasi markup. Membutuhkan variable $cr (BudgetRequest).
--}}
@if($cr->status === 'SUBMITTED' && Auth::user()->hasRole(['superadmin','head']))
<button type="button" class="btn btn-success btn-sm btn-head-approve" data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve"><i class="fas fa-check"></i></button>
<button type="button" class="btn btn-danger btn-sm btn-head-reject" data-id="{{ $cr->id }}" title="Reject"><i class="fas fa-times"></i></button>
@endif
@if($cr->status === 'CHECKED' && Auth::user()->hasRole(['superadmin','finance','finance_manager']))
<button type="button" class="btn btn-success btn-sm btn-finance-verify" data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Verify"><i class="fas fa-check-double"></i></button>
<button type="button" class="btn btn-danger btn-sm btn-finance-reject" data-id="{{ $cr->id }}" title="Reject"><i class="fas fa-times"></i></button>
@endif
@if($cr->status === 'VERIFIED' && Auth::user()->hasRole(['superadmin','approver']))
<button type="button" class="btn btn-success btn-sm btn-ceo-approve" data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve"><i class="fas fa-check"></i></button>
<button type="button" class="btn btn-danger btn-sm btn-ceo-reject" data-id="{{ $cr->id }}" title="Reject"><i class="fas fa-times"></i></button>
@endif
