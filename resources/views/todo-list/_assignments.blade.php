{{-- Section "Tugas / Assignment" di halaman Todo List. Hanya meneruskan data
     yang disiapkan TodoListController::index() ke papan Assignment bersama. --}}
@include('assignment._board', [
    'assignedToMe' => $assignmentsAssignedToMe,
    'createdByMe'  => $assignmentsCreatedByMe,
    'assignees'    => $assignmentAssignees,
])
<div class="text-right small mt-1">
    <span class="text-muted">Tugas Closed/Cancelled hanya ditampilkan {{ \App\Http\Controllers\AssignmentController::RIWAYAT_HARI }} hari terakhir.</span>
    <a href="{{ route('assignment.index') }}">Lihat semua</a>
</div>
