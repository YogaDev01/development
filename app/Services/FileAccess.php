<?php

namespace App\Services;

use App\Http\Controllers\Concerns\BuildsCrWorklist;
use App\Models\BudgetPlanAttachment;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealizationAttachment;
use App\Models\CashReceipt;
use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\ProjectAttachment;
use App\Models\RealizationDetailAttachment;
use App\Models\RequestAttachment;
use App\Models\RequestDetailAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Siapa boleh membuka berkas yang mana.
 *
 * Sebelumnya seluruh lampiran disimpan di disk "public" dan dilayani langsung
 * lewat /storage/... — tanpa sesi, tanpa peran, tanpa batas. Siapa pun yang
 * memegang URL-nya (atau menebaknya) bisa mengunduh invoice, BAST, bukti
 * transfer dan bukti settlement. Sekarang berkas disimpan di disk privat dan
 * hanya bisa keluar lewat SecureFileController, yang bertanya ke kelas ini.
 *
 * Aturannya sengaja TIDAK ditulis ulang di sini. Untuk Cost Request dipakai
 * scopeByRole() yang sama persis dengan daftar CR; untuk Invoice dipakai
 * aturan Access Group yang sama dengan index invoice. Menyalin aturan berarti
 * dua tempat yang bisa berbeda diam-diam — dan yang satu pasti terlupakan.
 *
 * Direktori yang belum punya aturan khusus jatuh ke aturan dasar: harus login.
 * Itu bukan kelalaian melainkan pilihan — menebak aturan untuk modul yang
 * belum punya konsep kepemilikan akan mengunci pengguna yang sah, dan
 * "wajib login" sudah menutup kebocoran ke publik yang jadi pokok masalahnya.
 */
class FileAccess
{
    use BuildsCrWorklist;

    /**
     * Apakah user yang sedang login boleh membuka berkas pada path ini.
     *
     * $path relatif terhadap akar disk, mis. "invoice_attachments/abc.pdf".
     */
    public function allows(string $path): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $folder = explode('/', ltrim($path, '/'))[0] ?? '';

        return match ($folder) {
            // ---- Cost Request: aturan daftar CR, apa adanya
            'cost_request_attachments',
            'cost_request_detail_attachments',
            'cost_request_realizations',
            'cost_request_realization_details' => $this->allowsCostRequest($folder, $path),

            // ---- Invoice & bukti pembayaran: aturan Access Group invoice
            'invoice_attachments',
            'cash_receipt_proofs'              => $this->allowsInvoice($folder, $path),

            // ---- Purchase Order / BAST: Access Group proyek
            'project_attachments',
            'po_number_attachments'            => $this->allowsProject($path),

            // ---- Budget Plan: lampiran mengikuti CR-nya lewat budget plan
            'budget_plan_attachments'          => $this->allowsBudgetPlan($path),

            // ---- Sisanya: cukup login (lihat catatan kelas)
            default                            => true,
        };
    }

    /**
     * Lampiran CR terlihat kalau CR-nya sendiri terlihat. scopeByRole() yang
     * dipakai di sini persis yang dipakai daftar Cost Request, jadi tidak ada
     * kemungkinan lampiran lolos untuk CR yang barisnya saja tidak tampil.
     */
    private function allowsCostRequest(string $folder, string $path): bool
    {
        /*
        | Pemiliknya ditelusuri lewat RELASI model, bukan join dengan nama tabel
        | yang diketik ulang di sini.
        |
        | Versi sebelumnya menulis `request_details` padahal tabelnya bernama
        | `request_detail` (lihat RequestDetail::$table) — nama tabel di aplikasi
        | ini memang tidak seragam jamak/tunggal: `budget_request` dan
        | `request_detail` tunggal, sementara `budget_plans` dan
        | `budget_request_realizations` jamak. Akibatnya setiap permintaan
        | lampiran item CR mati dengan "Base table or view not found", yang
        | keluar sebagai 500 — gambarnya tidak pernah termuat di viewer.
        |
        | Relasi mengambil nama tabelnya dari model, jadi salah ketik seperti
        | itu tidak mungkin terulang dan perubahan nama tabel cukup di satu
        | tempat.
        */
        $crId = match ($folder) {
            'cost_request_attachments' => RequestAttachment::where('path', $path)->value('budget_request_id'),

            'cost_request_detail_attachments' => RequestDetailAttachment::where('path', $path)
                ->first()?->requestDetail?->budget_request_id,

            'cost_request_realizations' => BudgetRequestRealizationAttachment::where('path', $path)
                ->value('budget_request_id'),

            'cost_request_realization_details' => RealizationDetailAttachment::where('path', $path)
                ->first()?->realization?->budget_request_id,

            default => null,
        };

        // Berkas tanpa baris pemilik: tidak ada yang bisa memastikan siapa yang
        // berhak, jadi ditolak. Ini juga menutup file yatim sisa penghapusan.
        if (! $crId) {
            return false;
        }

        $query = BudgetRequest::whereKey($crId);
        $this->scopeByRole($query);

        return $query->exists();
    }

    /** Lampiran invoice & bukti cash receipt mengikuti keterlihatan invoicenya. */
    private function allowsInvoice(string $folder, string $path): bool
    {
        $invoiceId = $folder === 'invoice_attachments'
            ? InvoiceAttachment::where('file_path', $path)->value('invoice_id')
            : CashReceipt::where('proof_file', $path)->value('invoice_id');

        if (! $invoiceId) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        // Aturan sama dengan InvoiceCashReceiptController::scopeVisibleTo().
        if (! $user->hasRole(['manager', 'head'])) {
            return true;
        }

        $poId = Invoice::whereKey($invoiceId)->value('po_id');

        return $poId && $user->accessibleProjectIds()->contains($poId);
    }

    /** Lampiran PO/BAST mengikuti Access Group proyek untuk manager & head. */
    private function allowsProject(string $path): bool
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole(['manager', 'head'])) {
            return true;
        }

        $projectId = ProjectAttachment::where('file_path', $path)->value('project_id');

        return $projectId && $user->accessibleProjectIds()->contains($projectId);
    }

    /**
     * Lampiran Budget Plan: proyeknya dicek untuk manager & head; budget
     * overhead/payroll (tanpa project_id) mengikuti aturan dasar.
     */
    private function allowsBudgetPlan(string $path): bool
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole(['manager', 'head'])) {
            return true;
        }

        $projectId = BudgetPlanAttachment::where('path', $path)
            ->join('budget_plans', 'budget_plans.id', '=', 'budget_plan_attachments.budget_plan_id')
            ->value('budget_plans.project_id');

        if (! $projectId) {
            return true;
        }

        return $user->accessibleProjectIds()->contains($projectId);
    }
}
