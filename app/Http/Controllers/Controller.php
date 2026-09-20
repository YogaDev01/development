<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Basis controller aplikasi.
 *
 * Sebelumnya kelas ini menyuntikkan WorkflowService lewat konstruktor, sehingga
 * SETIAP controller (45 buah) membangun layanan itu di setiap permintaan -
 * padahal layanannya sendiri sudah usang: ia memakai status PENDING_APPROVER /
 * PENDING_FINANCE yang tidak ada lagi di enum, dan satu-satunya pemakainya
 * (BudgetRequestApprovalController) tidak pernah terpasang di route. Keduanya
 * sudah dihapus; alur approval yang berlaku ada di BudgetRequestController dan
 * kamus statusnya di App\Support\WorkflowStatus.
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
