<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Basis controller aplikasi.
 *
 * Sebelumnya kelas ini menyuntikkan WorkflowService lewat konstruktor, sehingga
 * SETIAP controller membangun layanan itu di setiap permintaan - padahal
 * layanannya sendiri sudah usang: ia memakai status PENDING_APPROVER /
 * PENDING_FINANCE yang tidak ada lagi di enum, dan satu-satunya pemakainya
 * (BudgetRequestApprovalController) tidak pernah terpasang di route.
 *
 * Penyuntikan itu dicabut lebih dulu, tapi kedua kelasnya sendiri baru
 * benar-benar DIHAPUS pada 26 Sep 2026 - sampai saat itu komentar di sini
 * menyatakan "keduanya sudah dihapus" sementara berkasnya masih ada, yang
 * menyesatkan siapa pun yang mencarinya.
 *
 * Alur approval yang berlaku ada di BudgetRequestController, dan kamus
 * statusnya di App\Support\WorkflowStatus.
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
