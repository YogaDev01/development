{{--
    Bar multi-select + form tersembunyi untuk section flat Todo List.
    Dipakai mesin bulk .todo-bulk-* (JS di index): section pembungkus wajib
    punya class .todo-worklist dan data-section="{{ $key }}".
      $key          : kunci section (lihat TodoListController::BULK_ROUTES)
      $approveLabel : teks tombol approve (mis. 'Approve', 'Verify')
      $canReject    : tampilkan tombol Reject (default true)
      $unit         : satuan di teks hitungan (default 'item')
--}}
@php $canReject = $canReject ?? true; $unit = $unit ?? 'item'; @endphp
<div class="todo-bulk-bar">
    <div class="d-flex align-items-center justify-content-between">
        <div style="font-size:12.5px;color:var(--text);"><strong class="todo-bulk-count">0</strong> {{ $unit }} dipilih<span class="todo-bulk-amount"></span></div>
        <div class="d-flex" style="gap:8px;">
            <button type="button" class="btn btn-outline-success btn-sm todo-bulk-approve" disabled>
                <i class="fas fa-check mr-1"></i>{{ $approveLabel }}
            </button>
            @if($canReject)
            <button type="button" class="btn btn-outline-danger btn-sm todo-bulk-reject" disabled>
                <i class="fas fa-times mr-1"></i>Reject
            </button>
            @endif
        </div>
    </div>
</div>
<form id="frmTodoBulkApprove-{{ $key }}" action="{{ route('todo-list.bulk', [$key, 'approve']) }}" method="POST" style="display:none;">
    @csrf
</form>
@if($canReject)
<form id="frmTodoBulkReject-{{ $key }}" action="{{ route('todo-list.bulk', [$key, 'reject']) }}" method="POST" style="display:none;">
    @csrf
    <textarea name="notes"></textarea>
</form>
@endif
