{{-- Section "Tugas / Assignment" di halaman Todo List. Hanya meneruskan data
     yang disiapkan TodoListController::index() ke papan Assignment bersama. --}}
@include('assignment._board', [
    'assignedToMe' => $assignmentsAssignedToMe,
    'createdByMe'  => $assignmentsCreatedByMe,
    'assignees'    => $assignmentAssignees,
])
