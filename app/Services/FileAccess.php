<?php

namespace App\Services;

use App\Http\Controllers\Concerns\BuildsCrWorklist;
use App\Http\Controllers\Concerns\ScopesBudgetPlanVisibility;
use App\Http\Controllers\ProjectMonitoringController;
use App\Models\BudgetPlanAttachment;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealizationAttachment;
use App\Models\CashReceipt;
use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\ProjectAttachment;
use App\Models\ProjectMonitoringLogAttachment;
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
    // Aturan keterlihatan Budget Plan (lampiran BP) — sama dengan daftarnya.
    use ScopesBudgetPlanVisibility;

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

            // ---- Assignment: hanya pemberi/penerima tugas (atau superadmin)
            'assignment_attachments'           => $this->allowsAssignment($path),

            // ---- Trouble Ticket: mengikuti keterlihatan tiketnya
            'trouble_ticket_attachments'       => $this->allowsTroubleTicket($path),

            /*
            | ---- Treasury, Cost Register, Customer, Surat Jalan, Monitoring
            |
            | Kelima folder ini sebelumnya jatuh ke `default => true`, yaitu
            | "cukup login". Artinya SETIAP pengguna yang login - termasuk role
            | guest, yang di route lain hanya diberi akses baca master data -
            | bisa mengunduh bukti transfer Treasury, dokumen pelanggan, dan
            | bukti terima Surat Jalan asalkan tahu path berkasnya.
            |
            | Itu bertentangan dengan catatan kelas di atas, yang menyebut
            | "bukti transfer" sebagai contoh justru dari berkas yang dulu
            | bocor dan kini harus dilindungi. Bukti transfer Treasury ada di
            | daily_transaction_attachments - salah satu yang jatuh ke default.
            |
            | Aturannya tetap tidak ditulis ulang di sini: masing-masing
            | meminjam gerbang menu modulnya sendiri, sama dengan yang dipakai
            | route index-nya, sehingga "bisa membuka halamannya" selalu setara
            | dengan "bisa membuka lampirannya".
            */
            'daily_transaction_attachments'    => $this->allowsMenu('finance-daily-transaction'),
            'cost_register_attachments'        => $this->allowsMenu('finance-daily-transaction'),
            'customer_attachments'             => $this->allowsMenu('customer'),
            'site_documents'                   => $this->allowsMenu('site'),
            'delivery_note_evidence'           => $this->allowsMenu('delivery-note'),

            // Lampiran log Project Monitoring mengikuti Access Group proyek,
            // aturan yang sama dengan halaman Project Monitoring itu sendiri.
            'monitoring_attachments'           => $this->allowsMonitoring($path),

            // Foto profil. Sengaja terbuka untuk semua pengguna login: avatar
            // memang ditampilkan di navbar, daftar Team dan bubble chat.
            'avatars'                          => true,
            'covers'                           => true,

            /*
            | ---- Sisanya DITOLAK.
            |
            | Bawaan lama (true) berarti setiap folder unggahan BARU otomatis
            | terbuka untuk semua pengguna login sampai seseorang ingat
            | menambahkannya di sini - dan yang lupa tidak menghasilkan gejala
            | apa pun, jadi tidak akan pernah ketahuan. Dengan ditolak sebagai
            | bawaan, folder yang terlewat langsung terlihat (lampirannya 403)
            | dan tinggal didaftarkan di atas.
            */
            default                            => false,
        };
    }

    /**
     * Gerbang menu, cermin persis App\Http\Middleware\CheckMenu - bukan aturan
     * baru. Dipakai folder lampiran yang keterlihatannya memang sama dengan
     * "boleh membuka halaman modulnya".
     */
    private function allowsMenu(string $key): bool
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        if (! $user->role) {
            return false;
        }

        if (strtolower($user->role->name) === 'superadmin') {
            return true;
        }

        return $user->role->hasMenu($key);
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

        if ($query->exists()) {
            return true;
        }

        // Jalur BACA-SAJA (K7): pelaku yang pernah menindak CR ini tetap boleh
        // membuka lampirannya — sama dengan detail CR (BudgetRequestController::showJson).
        return (new BudgetRequest)->forceFill(['id' => $crId])->wasActedOnBy(Auth::user());
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

    /**
     * Lampiran log Project Monitoring. Pemiliknya ditelusuri lewat RELASI
     * (attachment -> log -> project_id), bukan lewat tabel project_attachments
     * seperti lampiran PO - keduanya folder berbeda dengan tabel berbeda.
     *
     * Aturannya disamakan dengan ProjectMonitoringController::canManageProject():
     * role istimewa melihat semua, sisanya dibatasi Access Group-nya. Berkas
     * tanpa baris pemilik ditolak, sama seperti lampiran Cost Request.
     */
    private function allowsMonitoring(string $path): bool
    {
        $projectId = ProjectMonitoringLogAttachment::where('file_path', $path)
            ->first()?->log?->project_id;

        if (! $projectId) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        // Daftar role-nya DIRUJUK dari controller-nya, bukan disalin ke sini.
        // Menyalin berarti dua daftar yang harus diingat untuk diubah bersama,
        // dan yang satu pasti terlupakan.
        if ($user->hasRole(ProjectMonitoringController::MENTION_BYPASS_ROLES)) {
            return true;
        }

        return $user->accessibleProjectIds()->contains($projectId);
    }

    /**
     * Lampiran Assignment terlihat hanya oleh pemberi atau penerima tugas
     * (atau superadmin) — sama dengan siapa yang boleh membuka tugasnya di
     * AssignmentController. Berkas tanpa baris pemilik ditolak.
     */
    private function allowsAssignment(string $path): bool
    {
        $assignment = \App\Models\AssignmentAttachment::where('path', $path)
            ->first()?->assignment;

        if (! $assignment) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->id === $assignment->assigner_id
            || $user->id === $assignment->assignee_id
            || $user->hasRole('superadmin');
    }

    /**
     * Lampiran Trouble Ticket terlihat bila tiketnya terlihat — scope yang
     * sama persis dengan daftar & detail tiket (TroubleTicket::scopeVisibleTo).
     * Berkas tanpa baris pemilik ditolak.
     */
    private function allowsTroubleTicket(string $path): bool
    {
        $ticketId = \App\Models\TroubleTicketAttachment::where('storage_path', $path)->value('trouble_ticket_id');

        if (! $ticketId) {
            return false;
        }

        return \App\Models\TroubleTicket::visibleTo(Auth::user())->whereKey($ticketId)->exists();
    }

    /**
     * Lampiran PO/BAST — scope yang sama dengan detail PO (review MEDIUM-3,
     * 6 Nov 2026). Sebelumnya semua role selain manager/head boleh membuka
     * lampiran PO mana pun asalkan tahu path-nya.
     *
     * Boleh bila salah satu:
     *  1) PO terlihat di detail (User::canSeeProject — full scope / Access
     *     Group individu ∪ team);
     *  2) pernah menindak PO ini (jalur baca-saja K7);
     *  3) boleh membuka halaman Invoice & Cash Receipt dan BUKAN manager/head
     *     — aturan yang sama dengan keterlihatan invoice
     *     (ScopesInvoiceVisibility / allowsInvoice): pemegang menu invoice di
     *     luar manager/head melihat semua invoice, dan BAST/No. PO dibuka dari
     *     sana saat menagih.
     * Role tanpa salah satu di atas (mis. tanpa menu PO/Invoice dan tanpa
     * Access Group) ditolak.
     */
    private function allowsProject(string $path): bool
    {
        /** @var User $user */
        $user = Auth::user();

        $projectId = ProjectAttachment::where('file_path', $path)->value('project_id');

        if (! $projectId) {
            return false;
        }

        if ($user->canSeeProject((int) $projectId)) {
            return true;
        }

        if ((new \App\Models\Project)->forceFill(['id' => $projectId])->wasActedOnBy($user)) {
            return true;
        }

        return ! $user->hasRole(['manager', 'head']) && $this->allowsMenu('invoice-cash-receipt');
    }

    /**
     * Lampiran Budget Plan — scope yang sama dengan daftar & detail Budget
     * Plan (ScopesBudgetPlanVisibility::scopeVisibleTo): budget proyek per
     * Access Group (individu ∪ team), overhead per cost center, PAYROLL hanya
     * role payroll. Sebelumnya semua role selain manager/head — dan budget
     * overhead/payroll untuk siapa pun — boleh membuka lampirannya
     * (review MEDIUM-3, 6 Nov 2026).
     */
    private function allowsBudgetPlan(string $path): bool
    {
        $bpId = BudgetPlanAttachment::where('path', $path)->value('budget_plan_id');

        if (! $bpId) {
            return false;
        }

        $query = \App\Models\BudgetPlan::whereKey($bpId);
        $this->scopeVisibleTo($query);

        return $query->exists();
    }
}
