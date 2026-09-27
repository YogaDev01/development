<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\BudgetRequestRealizationAttachment;
use App\Models\RequestDetail;
use App\Models\RequestAttachment;
use App\Models\RequestType;
use App\Models\BudgetPlan;
use App\Models\Cluster;
use App\Models\Beneficiary;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\User;
use App\Services\NotifyService;
use App\Services\RoleDirectory;
use App\Support\BudgetConsumption;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class BudgetRequestController extends Controller
{
    use \App\Http\Controllers\Concerns\BuildsCrWorklist;
    use \App\Http\Controllers\Concerns\TransitionsStatus;
    // Alur Settlement Cash Advance — dipisah untuk memperkecil kelas ini.
    use \App\Http\Controllers\Concerns\HandlesCrSettlement;

    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI SUMBER DANA
    |--------------------------------------------------------------------------
    | Cost Request mengikuti sumber dana dari Budget Plan-nya:
    |   - Budget proyek     -> variable cost, di-scope lewat access group
    |   - Budget cost center -> overhead / payroll, di-scope lewat ROLE
    */

    /** Kategori account per jenis sumber dana (harus sama dgn accounts.category) */
    private const ACCOUNT_CATEGORY = [
        'PROJECT'  => 'Variable Cost',
        'OVERHEAD' => 'Overhead',
        'PAYROLL'  => 'Fixed Cost',
    ];

    /*
    | ATURAN LAMPIRAN
    |  - Cash Advance : Lampiran Tambahan (global) WAJIB, Lampiran Item opsional
    |                   (bukti final baru ada saat settlement).
    |  - Tipe lain    : Lampiran Item WAJIB di tiap baris rincian, global opsional.
    |  - Settlement   : Lampiran Item wajib di tiap baris realisasi, global opsional.
    */

    /** Nama RequestType (case-insensitive) yang dihitung sebagai Cash Advance. */
    private const CASH_ADVANCE_TYPE_NAMES = ['cash advance', 'cash-advance', 'cashadvance'];

    /**
     * Batas API publik (/api/public/budget-request*).
     *
     * Lebar rentang tanggal maksimal, dan jumlah baris maksimal per
     * permintaan. Konsumen (Excel Power Query) menarik per periode, jadi satu
     * tahun per permintaan sudah lebih dari cukup; yang butuh lebih panjang
     * memanggil beberapa kali dan menggabungkannya sendiri.
     */
    private const API_MAX_RENTANG_HARI = 366;
    private const API_MAX_BARIS        = 20000;

    private function typeIsCashAdvance(?int $typeId): bool
    {
        if (! $typeId) return false;

        $name = strtolower(trim(RequestType::find($typeId)->name ?? ''));
        return in_array($name, self::CASH_ADVANCE_TYPE_NAMES, true);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function normalizeMoney(?string $value): int
    {
        if ($value === null || trim($value) === '') return 0;
        $stripped = preg_replace('/\D/', '', $value);
        return $stripped === '' ? 0 : (int) $stripped;
    }

    /**
     * Apakah user yang login berhak melihat detail satu Cost Request —
     * cermin persis dari scopeByRole() (trait), termasuk cabang "CR milik
     * sendiri bila user tidak punya access group". Dipakai showJson() yang
     * route-nya dipanggil dari beberapa halaman (Cost Request, Treasury,
     * Todo List) sehingga tidak bisa digerbang satu menu.
     */
    private function canViewCr(BudgetRequest $cr): bool
    {
        $query = BudgetRequest::whereKey($cr->getKey());
        $this->scopeByRole($query);
        return $query->exists();
    }

    /**
     * Ambil CR + pastikan user berhak melihatnya, lalu kembalikan modelnya.
     *
     * Dulu hanya showJson() yang memanggil canViewCr(); semua aksi lain cukup
     * findOrFail($id). Akibatnya daftar CR tersaring rapi per Access Group,
     * tapi URL /budget-request/{id}/edit — dan setiap aksi workflow — tetap
     * bisa dibuka dengan mengetik id milik proyek orang lain. Aturan yang
     * dipakai sama persis dengan daftar (scopeByRole), jadi tidak ada versi
     * kedua yang bisa menyimpang.
     *
     * Untuk role approval (Head/Finance/CEO/superadmin) scopeByRole() memang
     * tidak membatasi apa pun, jadi pemeriksaan ini tidak mengubah alur mereka.
     */
    private function findVisibleCr($id, array $with = []): BudgetRequest
    {
        $cr = BudgetRequest::with($with)->findOrFail($id);

        abort_unless($this->canViewCr($cr), 403, 'Anda tidak memiliki akses ke Cost Request ini.');

        return $cr;
    }

    /**
     * Pastikan semua budget_detail_id yang dikirim benar-benar milik
     * Budget Plan yang dipilih (cegah user memilih sub-item dari budget lain).
     */
    private function detailsBelongToPlan(BudgetPlan $bp, Request $request): bool
    {
        $ids = collect((array) $request->input('details', []))
            ->pluck('budget_detail_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) return true;

        $validIds = $bp->details()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id);

        return $ids->diff($validIds)->isEmpty();
    }

    /**
     * Upsert rincian CR. Baris yang membawa 'id' (existing) di-update di tempat —
     * supaya lampiran per-item (request_detail_attachments, FK cascade) tidak
     * ikut terhapus saat CR di-edit. Baris tanpa 'id' dibuat baru; baris lama
     * yang tidak dikirim lagi dihapus (beserta file lampiran per-item-nya).
     *
     * @return array<int|string, \App\Models\RequestDetail>  map: key form -> detail
     */
    /*
    |--------------------------------------------------------------------------
    | LIMIT CASH ADVANCE PER PIC
    |--------------------------------------------------------------------------
    | Beneficiary terhubung ke satu PIC (beneficiaries.pic_id), dan semua Cash
    | Advance milik PIC yang sama berbagi satu limit. Angkanya diambil dari
    | Master PIC: kolom `token` = batas jumlah pengajuan, kolom `nominal` =
    | batas total rupiah. Kolom yang kosong/0 berarti aspek itu tidak dibatasi.
    |
    | Jumlah pengajuan dihitung dari CR yang sudah mencapai tahap PAID (uangnya
    | benar-benar keluar), sedangkan nominal dihitung sejak CR dibuat. Keduanya
    | lepas begitu CR CLOSED (atau CANCELLED).
    */

    /** Ambang "mendekati batas": sisa pengajuan <= 2 atau sisa nominal <= 20%. */
    private const CA_WARN_REMAINING_COUNT = 2;
    private const CA_WARN_REMAINING_RATIO = 0.2;

    /**
     * Pemakaian limit CA milik PIC pemilik $beneficiaryId. Null kalau
     * beneficiary tidak ada, belum punya PIC, atau PIC-nya tidak mengisi
     * token maupun nominal (berarti tidak ada batas yang berlaku).
     */
    private function cashAdvanceQuota(?int $beneficiaryId, ?int $excludeCrId = null): ?array
    {
        $ben = $beneficiaryId ? Beneficiary::with('pic')->find($beneficiaryId) : null;
        if (! $ben || ! $ben->pic_id) return null;

        // Batas dari Master PIC. Token bebas huruf/angka, jadi hanya dipakai
        // kalau isinya memang angka; kosong/0 = aspek itu tanpa batas.
        $pic         = $ben->pic;
        $limitCount  = is_numeric($pic?->token) ? (int) $pic->token : null;
        $limitAmount = $pic?->nominal !== null ? (int) $pic->nominal : null;
        if (! $limitCount)  $limitCount  = null;
        if (! $limitAmount) $limitAmount = null;
        if ($limitCount === null && $limitAmount === null) return null;

        $names = self::CASH_ADVANCE_TYPE_NAMES;

        $base = fn () => BudgetRequest::query()
            ->join('beneficiaries', 'beneficiaries.id', '=', 'budget_request.beneficiary_id')
            ->join('request_type', 'request_type.id', '=', 'budget_request.type_id')
            ->where('beneficiaries.pic_id', $ben->pic_id)
            ->whereRaw(
                'LOWER(TRIM(request_type.name)) IN (' . implode(',', array_fill(0, count($names), '?')) . ')',
                $names
            )
            ->when($excludeCrId, fn ($q) => $q->where('budget_request.id', '!=', $excludeCrId));

        // Jumlah pengajuan: hanya yang sudah cair (PAID ke atas) dan belum tutup.
        $usedCount = (int) $base()
            ->whereIn('budget_request.status', BudgetRequest::CA_COUNTED_STATUSES)
            ->count();

        // Nominal: seluruh CR yang belum lepas (termasuk yang masih diproses).
        $usedAmount = (int) $base()
            ->whereNotIn('budget_request.status', BudgetRequest::CA_QUOTA_RELEASED_STATUSES)
            ->sum('budget_request.amount');

        return [
            'pic_id'           => (int) $ben->pic_id,
            'pic_name'         => $pic?->pic ?? '-',
            'used_count'       => $usedCount,
            'used_amount'      => $usedAmount,
            'limit_count'      => $limitCount,
            'limit_amount'     => $limitAmount,
            'remaining_count'  => $limitCount  === null ? null : max(0, $limitCount - $usedCount),
            'remaining_amount' => $limitAmount === null ? null : max(0, $limitAmount - $usedAmount),
        ];
    }

    /** Total nominal rincian yang sedang diajukan (amount x qty). */
    private function requestedDetailTotal(Request $request): int
    {
        $total = 0;
        foreach ((array) $request->input('details', []) as $row) {
            $total += $this->normalizeMoney($row['amount'] ?? null) * max(1, (int) ($row['qty'] ?? 1));
        }
        return $total;
    }

    /**
     * Limit CA terlampaui? Mengembalikan pesan error, atau null kalau lolos.
     */
    private function cashAdvanceQuotaError(?int $beneficiaryId, int $amount, ?int $excludeCrId = null): ?string
    {
        $q = $this->cashAdvanceQuota($beneficiaryId, $excludeCrId);
        if (! $q) return null;

        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');

        if ($q['remaining_count'] !== null && $q['remaining_count'] < 1) {
            return 'Limit Cash Advance PIC "' . $q['pic_name'] . '" sudah habis: '
                . $q['used_count'] . ' dari ' . $q['limit_count'] . ' pengajuan masih berjalan. '
                . 'Selesaikan (Close) pengajuan yang berjalan dulu.';
        }

        if ($q['remaining_amount'] !== null && $amount > $q['remaining_amount']) {
            return 'Limit nominal Cash Advance PIC "' . $q['pic_name'] . '" tidak mencukupi. '
                . 'Terpakai ' . $rp($q['used_amount']) . ' dari ' . $rp($q['limit_amount'])
                . ', sisa ' . $rp($q['remaining_amount']) . ', sedangkan pengajuan ini ' . $rp($amount) . '.';
        }

        return null;
    }

    /**
     * Peringatan "limit mendekati batas" setelah CR tersimpan/disubmit.
     * Null kalau masih jauh dari batas.
     */
    private function cashAdvanceQuotaWarning(?int $beneficiaryId): ?string
    {
        $q = $this->cashAdvanceQuota($beneficiaryId);
        if (! $q) return null;

        $rp   = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $warn = [];

        if ($q['remaining_count'] !== null && $q['remaining_count'] <= self::CA_WARN_REMAINING_COUNT) {
            $warn[] = $q['remaining_count'] === 0
                ? 'jatah pengajuan HABIS (' . $q['used_count'] . '/' . $q['limit_count'] . ')'
                : 'sisa ' . $q['remaining_count'] . ' pengajuan lagi (' . $q['used_count'] . '/' . $q['limit_count'] . ')';
        }

        if ($q['remaining_amount'] !== null
            && $q['remaining_amount'] <= $q['limit_amount'] * self::CA_WARN_REMAINING_RATIO) {
            $warn[] = $q['remaining_amount'] === 0
                ? 'limit nominal HABIS (' . $rp($q['used_amount']) . '/' . $rp($q['limit_amount']) . ')'
                : 'sisa limit nominal tinggal ' . $rp($q['remaining_amount']);
        }

        if (! $warn) return null;

        return 'Perhatian - limit Cash Advance PIC "' . $q['pic_name'] . '": '
            . implode(' dan ', $warn) . '. Limit pulih setelah pengajuan berjalan di-Close.';
    }

    /** Endpoint AJAX: info limit CA untuk beneficiary terpilih di form. */
    public function caQuota(Request $request)
    {
        $q = $this->cashAdvanceQuota(
            $request->beneficiary_id ? (int) $request->beneficiary_id : null,
            $request->cr_id ? (int) $request->cr_id : null
        );

        if (! $q) {
            return response()->json(['has_quota' => false]);
        }

        $rp      = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $habis   = ($q['remaining_count'] !== null && $q['remaining_count'] < 1)
                || ($q['remaining_amount'] !== null && $q['remaining_amount'] < 1);
        $warnCnt = $q['remaining_count'] !== null && $q['remaining_count'] <= self::CA_WARN_REMAINING_COUNT;
        $warnAmt = $q['remaining_amount'] !== null
                && $q['remaining_amount'] <= $q['limit_amount'] * self::CA_WARN_REMAINING_RATIO;

        return response()->json(array_merge($q, [
            'has_quota'                  => true,
            'used_amount_formatted'      => $rp($q['used_amount']),
            'limit_amount_formatted'     => $q['limit_amount'] === null ? null : $rp($q['limit_amount']),
            'remaining_amount_formatted' => $q['remaining_amount'] === null ? null : $rp($q['remaining_amount']),
            'level'                      => $habis ? 'blocked' : (($warnCnt || $warnAmt) ? 'warning' : 'ok'),
        ]));
    }

    /**
     * Ada minimal satu Lampiran Tambahan (global)? Untuk edit ($cr diisi),
     * lampiran existing yang tidak dicentang hapus ikut dihitung.
     */
    private function hasGlobalAttachment(Request $request, ?BudgetRequest $cr = null): bool
    {
        $hasNew = collect($request->file('attachments', []))
            ->contains(function ($row) {
                $file = is_array($row) ? ($row['file'] ?? null) : $row;
                return $file && $file->isValid();
            });
        if ($hasNew) return true;

        if (! $cr) return false;

        $del = (array) $request->input('delete_attachments', []);
        return $cr->attachments()->whereNotIn('id', $del ?: [0])->exists();
    }

    /**
     * Nomor baris Rincian Pengajuan yang belum punya Lampiran Item (file baru
     * maupun lampiran existing yang dipertahankan). Baris kosong diabaikan,
     * mengikuti aturan skip di syncDetails().
     *
     * @return int[]
     */
    private function detailsMissingItemAttachment(Request $request, ?BudgetRequest $cr = null): array
    {
        $rows  = (array) $request->input('details', []);
        $files = (array) $request->file('details', []);
        $del   = (array) $request->input('delete_detail_attachments', []);

        $missing = [];
        $no      = 0;

        foreach ($rows as $key => $row) {
            if (! $this->normalizeMoney($row['amount'] ?? null) && blank($row['budget_detail_id'] ?? null)) {
                continue;
            }
            $no++;

            $hasNew = collect((array) ($files[$key]['attachments'] ?? []))
                ->contains(fn ($f) => $f && $f->isValid());
            if ($hasNew) continue;

            $detailId = $row['id'] ?? null;
            if ($cr && $detailId && \App\Models\RequestDetailAttachment::where('request_detail_id', (int) $detailId)
                    ->whereNotIn('id', $del ?: [0])->exists()) {
                continue;
            }

            $missing[] = $no;
        }

        return $missing;
    }

    /**
     * Validasi aturan lampiran saat store/update. Mengembalikan pesan error,
     * atau null kalau lolos.
     */
    private function attachmentRuleError(Request $request, ?int $typeId, ?BudgetRequest $cr = null): ?string
    {
        if ($this->typeIsCashAdvance($typeId)) {
            return $this->hasGlobalAttachment($request, $cr)
                ? null
                : 'Pengajuan Cash Advance wajib melampirkan minimal 1 file di "Lampiran Tambahan / Pendukung".';
        }

        $missing = $this->detailsMissingItemAttachment($request, $cr);
        if (! $missing) return null;

        return 'Setiap baris Rincian Pengajuan wajib punya "Lampiran Item". Baris yang belum berlampiran: no. '
            . implode(', ', $missing) . '.';
    }

    private function syncDetails(BudgetRequest $cr, Request $request): array
    {
        $rows = (array) $request->input('details', []);

        // Kumpulkan id existing yang masih dikirim; sisanya dihapus.
        $keepIds = collect($rows)->pluck('id')->filter()->map(fn ($i) => (int) $i)->all();
        $stale   = $cr->details()->with('attachments')->whereNotIn('id', $keepIds ?: [0])->get();
        foreach ($stale as $d) {
            foreach ($d->attachments as $att) {
                Storage::disk('secure')->delete($att->path);
            }
            $d->delete(); // cascade menghapus baris request_detail_attachments
        }

        $existing = $cr->details()->get()->keyBy('id');
        $map = [];

        foreach ($rows as $key => $row) {
            $amount = $this->normalizeMoney($row['amount'] ?? null);
            $qty    = (int) ($row['qty'] ?? 1);
            $total  = $amount * $qty;

            $budgetDetailId = $row['budget_detail_id'] ?? null;
            if (!$amount && blank($budgetDetailId)) continue;

            $budgetDetail = $budgetDetailId
                ? \App\Models\BudgetPlanDetail::with('account')->find($budgetDetailId)
                : null;

            /*
            | Pelampauan anggaran per baris disimpan sebagai kolom (is_nob +
            | nob_amount), bukan sebagai awalan teks '[NOB][1.500.000]' di
            | catatan. Catatan adalah milik pengguna; menaruh status keuangan
            | di sana berarti status itu bisa terhapus saat catatannya diedit,
            | dan setiap pembacanya harus mengurai teks berformat ribuan.
            | Safety net sisi server ini tetap cermin dari logika di JS.
            */
            $notes = $row['notes'] ?? null;
            // Penanda lama dibersihkan bila masih terbawa dari form/impor.
            $notesClean = preg_replace('/^\[NOB\]\[[\d.,]+\]\s*/i', '', (string) $notes);
            $remaining = $budgetDetail?->remainingAmount() ?? 0;
            $isRowNob = $budgetDetail && $total > $remaining;
            $notes = $notesClean !== '' ? $notesClean : null;

            $payload = [
                'budget_detail_id' => $budgetDetail->id ?? null,
                'account_id'       => $budgetDetail->account_id ?? null,
                'description'      => $budgetDetail
                    ? trim(($budgetDetail->account?->name ?? '-') . ($budgetDetail->deskripsi ? ' — ' . $budgetDetail->deskripsi : ''))
                    : ($row['description'] ?? null),
                'amount'           => $amount,
                'qty'              => $qty,
                'total'            => $total,
                'notes'            => $notes,
                'is_nob'           => $isRowNob,
                'nob_amount'       => $isRowNob ? max(0, $total - $remaining) : 0,
            ];

            $rowId  = (int) ($row['id'] ?? 0);
            $detail = $rowId && $existing->has($rowId) ? $existing->get($rowId) : null;

            if ($detail) {
                $detail->update($payload);
            } else {
                $detail = $cr->details()->create($payload);
            }

            $map[$key] = $detail;
        }

        $cr->recalculateAmount();

        return $map;
    }

    /**
     * Simpan lampiran per rincian CR.
     *  - hapus lampiran per-item yang dicentang (delete_detail_attachments[])
     *  - simpan file baru dari details[key][attachments][]
     *
     * @param  array<int|string, \App\Models\RequestDetail>  $detailMap  key form -> detail
     */
    private function storeDetailAttachments(BudgetRequest $cr, Request $request, array $detailMap): void
    {
        $deleteIds = (array) $request->input('delete_detail_attachments', []);
        if (!empty($deleteIds)) {
            $detailIds = $cr->details()->pluck('id');
            $toDelete  = \App\Models\RequestDetailAttachment::whereIn('id', $deleteIds)
                ->whereIn('request_detail_id', $detailIds)->get();
            foreach ($toDelete as $att) {
                Storage::disk('secure')->delete($att->path);
                $att->delete();
            }
        }

        $files = $request->file('details', []);
        foreach ((array) $files as $key => $row) {
            $rowFiles = $row['attachments'] ?? null;
            if (blank($rowFiles) || !isset($detailMap[$key])) continue;

            $detail = $detailMap[$key];
            foreach ((array) $rowFiles as $file) {
                if (!$file || !$file->isValid()) continue;
                $path = $file->store('cost_request_detail_attachments', 'secure');
                $detail->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                    'uploaded_by'   => Auth::id(),
                ]);
            }
        }
    }

    private function syncRealizations(BudgetRequest $cr, Request $request): void
    {
        /*
        | Baris realisasi memang dibuat ulang tiap submit, TAPI lampirannya
        | tidak ikut dibuang: settlement bisa ditolak (kembali ke PAID) lalu
        | diajukan lagi, dan bukti yang sudah diunggah harus tetap ada. Karena
        | itu lampiran lama dipetakan per request_detail_id dulu, lalu
        | di-"pindah" ke baris realisasi yang baru dengan mengubah
        | realization_id-nya — filenya tidak pernah disentuh.
        |
        | Yang benar-benar dihapus hanya lampiran yang dicentang hapus oleh
        | user (delete_realization_attachments[]).
        */
        $hapusIds = array_filter((array) $request->input('delete_realization_attachments', []));

        /*
        | FK realization_detail_attachments ON DELETE CASCADE: begitu baris
        | realisasi dihapus, baris lampirannya ikut lenyap. Jadi datanya
        | di-SNAPSHOT dulu (file di disk tidak disentuh), lalu dibuat ulang
        | menunjuk baris realisasi yang baru.
        */
        $hapusIds      = array_map('strval', $hapusIds);
        $lamaPerDetail = [];

        foreach ($cr->realizations()->with('attachments')->get() as $old) {
            foreach ($old->attachments as $att) {
                if (in_array((string) $att->id, $hapusIds, true)) {
                    Storage::disk('secure')->delete($att->path);
                    continue;
                }

                $lamaPerDetail[(int) $old->request_detail_id][] = [
                    'original_name' => $att->original_name,
                    'stored_name'   => $att->stored_name,
                    'path'          => $att->path,
                    'mime_type'     => $att->mime_type,
                    'size'          => $att->size,
                    'uploaded_by'   => $att->uploaded_by,
                ];
            }
        }

        $cr->realizations()->delete();

        foreach ((array) $request->input('realizations', []) as $i => $row) {
            $amount   = $this->normalizeMoney($row['amount'] ?? null);
            $detailId = $row['request_detail_id'] ?? null;

            if (!$amount && blank($detailId)) continue;

            $detail = $detailId ? RequestDetail::find($detailId) : null;

            $realization = $cr->realizations()->create([
                'request_detail_id' => $detail->id ?? null,
                'description'       => $detail->description ?? null,
                'account_id'        => $detail->account_id ?? null,
                'amount'            => $amount,
                'qty'               => 1,
                'total'             => $amount,
                'notes'             => $row['notes'] ?? null,
            ]);

            // Lampiran lama milik item yang sama dibuat ulang di baris baru.
            foreach ($lamaPerDetail[(int) ($detail->id ?? 0)] ?? [] as $att) {
                $realization->attachments()->create($att);
            }
            unset($lamaPerDetail[(int) ($detail->id ?? 0)]);

            $rowFiles = $request->file("realizations.$i.attachments", []);
            foreach ((array) $rowFiles as $file) {
                if (!$file || !$file->isValid()) continue;
                $path = $file->store('cost_request_realization_details', 'secure');
                $realization->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                    'uploaded_by'   => Auth::id(),
                ]);
            }
        }

        // Lampiran yang item-nya tidak ikut disubmit lagi tidak punya induk —
        // barisnya sudah hilang ikut cascade, filenya dibuang di sini.
        foreach ($lamaPerDetail as $atts) {
            foreach ($atts as $att) {
                Storage::disk('secure')->delete($att['path']);
            }
        }
    }

    private function storeAttachments(BudgetRequest $cr, Request $request): void
    {
        // File upload ada di $request->file(), BUKAN $request->input().
        // Struktur field: attachments[i][file]  ->  file('attachments')[i]['file']
        $groups = $request->file('attachments', []);

        foreach ((array) $groups as $row) {
            // Tiap baris berupa ['file' => UploadedFile] (atau langsung UploadedFile)
            $file = is_array($row) ? ($row['file'] ?? null) : $row;

            if (!$file || !$file->isValid()) continue;

            $path = $file->store('cost_request_attachments', 'secure');
            $cr->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'stored_name'   => basename($path),
                'path'          => $path,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => Auth::id(),
            ]);
        }
    }

    private function storeRealizationAttachments(BudgetRequest $cr, Request $request): void
    {
        foreach ($request->file('realization_attachments', []) as $file) {
            if (!$file || !$file->isValid()) continue;
            $path = $file->store('cost_request_realizations', 'secure');
            $cr->realizationAttachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'stored_name'   => basename($path),
                'path'          => $path,
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => Auth::id(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HAK AKSES SUMBER DANA INTERNAL
    |--------------------------------------------------------------------------
    | scopeByRole(), overheadPrivileged(), canSeeOverhead(),
    | accessibleOverheadCostCenterIds(), canSeePayroll() dipindah ke trait
    | BuildsCrWorklist (dipakai juga oleh TodoListController).
    |--------------------------------------------------------------------------
    */

    /** Budget plan ACTIVE yang boleh dipakai user ini untuk mengajukan CR */
    private function getAvailableBudgetPlans()
    {
        /** @var User $user */
        $user               = Auth::user();
        $role               = strtolower($user->effectiveRole()?->name ?? '');
        $projectPrivileged  = in_array($role, [
            'superadmin', 'approver', 'admin', 'finance', 'finance_manager',
            'cost_control', 'treasury', 'collection', 'accounting',
        ]);
        $canPayroll         = $this->canSeePayroll();
        $overheadPrivileged = $this->overheadPrivileged();
        $overheadCcIds      = $this->accessibleOverheadCostCenterIds();

        $projectIds = $user->accessGroup
            ? $user->accessGroup->details->pluck('project_id')->filter()->values()
            : collect();

        return BudgetPlan::where('status', 'ACTIVE')
            ->where(function ($q) use ($projectPrivileged, $projectIds, $canPayroll, $overheadPrivileged, $overheadCcIds) {

                // Budget proyek: PO boleh status apa saja KECUALI PAID/CLOSING/CLOSED
                // (proyek yang sudah selesai dibayar/ditutup tidak lagi bisa dipakai
                // mengajukan CR baru), DAN sesuai access group PO user.
                if ($projectPrivileged) {
                    $q->whereHas('project', fn ($p) => $p->whereNotIn('status', ['PAID', 'CLOSING', 'CLOSED']));
                } elseif ($projectIds->isNotEmpty()) {
                    $q->whereHas('project', fn ($p) =>
                        $p->whereNotIn('status', ['PAID', 'CLOSING', 'CLOSED'])->whereIn('id', $projectIds)
                    );
                }

                // Budget overhead (dan tipe cost center non-payroll lain — Tax/Asset/
                // Liability/Capital/Adjustment): privileged lihat semua; lainnya per access group
                if ($overheadPrivileged) {
                    $q->orWhereHas('costCenter', fn ($c) =>
                        $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->where('status', 'ACTIVE')
                    );
                } elseif ($overheadCcIds->isNotEmpty()) {
                    $q->orWhereHas('costCenter', fn ($c) =>
                        $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->where('status', 'ACTIVE')
                          ->whereIn('id', $overheadCcIds)
                    );
                }

                // Budget payroll: role-gated
                if ($canPayroll) {
                    $q->orWhereHas('costCenter', fn ($c) =>
                        $c->where('type', 'PAYROLL')->where('status', 'ACTIVE')
                    );
                }
            })
            ->with(['project.customer', 'project.details.cluster', 'costCenter'])
            ->get()
            ->sortBy(fn ($bp) => strtoupper(($bp->project?->customer?->name ?? $bp->costCenter?->name ?? '') . '|' . $bp->description))
            ->values();
    }

    /** Account sesuai jenis sumber dana */
    private function getAccountsFor(string $sourceType)
    {
        $category = self::ACCOUNT_CATEGORY[strtoupper($sourceType)] ?? null;

        $query = Account::active()->orderBy('name');

        if ($category) {
            $query->byCategory($category);
        }

        return $query->get();
    }

    /*
    |--------------------------------------------------------------------------
    | NOB CHECK — apakah total melebihi sisa budget
    |--------------------------------------------------------------------------
    */

    private function isNOB(BudgetPlan $bp, int $amount, ?int $excludeCrId = null): bool
    {
        /*
        | Daftar statusnya diambil dari BudgetConsumption::COMMITTED, bukan
        | ditulis ulang di sini. Versi lama menuliskannya langsung dan lupa
        | 'CHECKED' - CR yang sudah lolos Head tapi belum diverifikasi Finance
        | tidak terhitung memakan anggaran, sehingga sisa budget yang dilaporkan
        | selalu lebih besar dari kenyataan dan peringatan NOB terlambat muncul.
        */
        $used = BudgetRequest::where('budget_plan_id', $bp->id)
            ->whereIn('status', BudgetConsumption::COMMITTED)
            ->when($excludeCrId, fn($q) => $q->where('id', '!=', $excludeCrId))
            ->sum('amount');

        return ($used + $amount) > ($bp->amount ?? 0);
    }

    /**
     * Berapa rupiah pengajuan ini melampaui sisa anggaran (0 bila tidak).
     *
     * Disimpan sebagai angka, bukan disisipkan ke teks: angka bisa dijumlahkan
     * pada laporan, teks tidak.
     */
    private function nobOverage(BudgetPlan $bp, int $amount, ?int $excludeCrId = null): int
    {
        $used = BudgetRequest::where('budget_plan_id', $bp->id)
            ->whereIn('status', BudgetConsumption::COMMITTED)
            ->when($excludeCrId, fn ($q) => $q->where('id', '!=', $excludeCrId))
            ->sum('amount');

        return (int) max(0, ($used + $amount) - ($bp->amount ?? 0));
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    /** Batas baris yang dirender ke satu tab (DataTables memproses semua baris di browser). */
    private const TAB_MAX_ROWS = 1000;

    /** @return array{0:string,1:string} [dateFrom, dateTo] — default awal bulan s/d hari ini. */
    private function crDateRange(Request $request): array
    {
        return [
            $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->format('Y-m-d'),
            $request->filled('date_to')   ? $request->date_to   : now()->format('Y-m-d'),
        ];
    }

    /**
     * Query dasar daftar CR: rentang tanggal + scoping role. Rentang tanggal
     * memakai perbandingan langsung pada kolom, bukan whereDate() — fungsi di
     * sisi kolom membuat MySQL tidak bisa memakai indeks (scan seluruh tabel).
     */
    private function crBaseQuery(string $dateFrom, string $dateTo)
    {
        $query = BudgetRequest::query()
            ->where('created_at', '>=', $dateFrom . ' 00:00:00')
            ->where('created_at', '<=', $dateTo . ' 23:59:59');

        $this->scopeByRole($query);

        return $query;
    }

    /** Hitung CR per status (satu query agregat) untuk angka stepper. */
    private function crStatusCounts($query)
    {
        return (clone $query)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');
    }

    /**
     * Halaman index hanya memuat KERANGKA 3 tab (Cash Advance / Cost Payment /
     * Database) + angka stepper. Baris tabel TIDAK dirender di sini — dimuat
     * lewat tab() ketika tab dibuka, supaya halaman awal ringan.
     */
    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->crDateRange($request);

        $base     = $this->crBaseQuery($dateFrom, $dateTo);
        $caCounts = $this->crStatusCounts((clone $base)->cashAdvance());
        $cpCounts = $this->crStatusCounts((clone $base)->costPayment());

        $activeTab = in_array($request->query('tab'), ['ca', 'cp', 'db'], true) ? $request->query('tab') : 'ca';

        return view('budget-request.index', [
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
            'caCounts'  => $caCounts,
            'cpCounts'  => $cpCounts,
            'caTotal'   => (int) $caCounts->sum(),
            'cpTotal'   => (int) $cpCounts->sum(),
            'activeTab' => $activeTab,
        ]);
    }

    /**
     * Isi tabel satu tab (HTML fragment) — dipanggil AJAX saat tab dibuka.
     *   ca : Cash Advance (alur penuh, ada Settlement/Refund)
     *   cp : Cost Payment (selain Cash Advance; tanpa Settlement)
     *   db : gabungan keduanya, kolom nilai "Cost" (Settlement utk CA, Request utk CP)
     */
    public function tab(Request $request, string $tab)
    {
        abort_unless(in_array($tab, ['ca', 'cp', 'db'], true), 404);

        [$dateFrom, $dateTo] = $this->crDateRange($request);
        $query = $this->crBaseQuery($dateFrom, $dateTo);

        if ($tab === 'ca') {
            $query->cashAdvance();
        } elseif ($tab === 'cp') {
            $query->costPayment();
        }

        $total     = (clone $query)->count();
        $maxRows   = self::TAB_MAX_ROWS;
        $truncated = $total > $maxRows;

        if ($tab === 'ca') {
            $query->with(['type', 'budgetPlan.project.manager', 'applicant', 'details', 'beneficiary.pic', 'realizations']);
        } elseif ($tab === 'cp') {
            // Tanpa 'realizations': Cost Payment tidak punya Settlement.
            $query->with(['type', 'budgetPlan.project.manager', 'applicant', 'details', 'beneficiary.pic']);
        } else {
            // Database: read-only & ringan — nilai Settlement cukup withSum, tanpa memuat baris realisasi.
            $query->with(['type', 'budgetPlan.project.manager', 'applicant', 'beneficiary.pic'])
                ->withSum('realizations as realization_sum', 'total');
        }

        $rows = $query->latest()->limit($maxRows)->get();

        return view('budget-request._table', compact('tab', 'rows', 'total', 'maxRows', 'truncated'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    | budgetPlanLabel(), buildReviewCoaBreakdown(), buildCrNobMap() dipindah ke
    | trait BuildsCrWorklist (dipakai juga oleh TodoListController).
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $types = RequestType::whereNotNull('name')->where('name', '!=', '')->orderBy('name')->get();

        // Budget plan ACTIVE: budget proyek (PO WAITING_PO/ACTIVE) + budget
        // cost center sesuai hak role. Tidak dibatasi access group.
        $budgetPlans = $this->getAvailableBudgetPlans();

        $clusters      = Cluster::where('status', 'ACTIVE')->orderBy('name')->get();
        $beneficiaries = Beneficiary::where('status', 'ACTIVE')->orderBy('complete_name')->get();

        // Account dikelompokkan per jenis sumber; dropdown di-swap oleh JS
        $accountsBySource = [
            'PROJECT'  => $this->getAccountsFor('PROJECT'),
            'OVERHEAD' => $this->getAccountsFor('OVERHEAD'),
            'PAYROLL'  => $this->getAccountsFor('PAYROLL'),
        ];

        $accounts = $accountsBySource['PROJECT'];   // kompatibilitas

        return view('budget-request.create', compact(
            'types', 'budgetPlans', 'clusters', 'beneficiaries', 'accounts', 'accountsBySource'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        // Cluster (Project) hanya relevan untuk CR yang bersumber dari budget PROYEK.
        // Budget cost center (overhead/payroll) tidak punya cluster.
        $sourcePlan = BudgetPlan::with('costCenter')->find($request->budget_plan_id);
        $needCluster = $sourcePlan ? $sourcePlan->isProjectBudget() : true;

        $request->validate([
            'type_id'               => 'required|exists:request_type,id',
            'budget_plan_id'        => 'required|exists:budget_plans,id',
            'cluster_id'            => ($needCluster ? 'required' : 'nullable') . '|exists:clusters,id',
            'beneficiary_id'        => 'required|exists:beneficiaries,id',
            'description'           => 'required|string|max:500',
            'email'                 => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                if (!$value) {
                    return;
                }
                if (preg_match('/\s/', $value)) {
                    $fail('Email tidak boleh mengandung spasi. Gunakan format email@domain.com,email2@domain.com.');
                    return;
                }
                foreach (explode(',', $value) as $address) {
                    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                        $fail('Format email tidak valid: "' . $address . '".');
                        return;
                    }
                }
            }],
            'notes'                 => 'nullable|string',
            'details'               => 'required|array|min:1',
            'details.*.budget_detail_id' => 'required|exists:budget_plan_details,id',
            'details.*.notes'            => 'required|string',
            'details.*.amount'           => 'required',
            'details.*.qty'              => 'required|integer|min:1',
            'details.*.attachments'      => 'nullable|array',
            'details.*.attachments.*'    => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        if ($msg = $this->attachmentRuleError($request, (int) $request->type_id)) {
            return back()->withInput()->with('error', $msg);
        }

        // Limit Cash Advance per PIC (lewat beneficiary) — blokir kalau habis.
        if ($this->typeIsCashAdvance((int) $request->type_id)) {
            $msg = $this->cashAdvanceQuotaError(
                (int) $request->beneficiary_id,
                $this->requestedDetailTotal($request)
            );
            if ($msg) return back()->withInput()->with('error', $msg);
        }

        $budgetPlan = BudgetPlan::with(['project', 'costCenter'])->findOrFail($request->budget_plan_id);

        if (!$this->detailsBelongToPlan($budgetPlan, $request)) {
            return back()->withInput()
                ->with('error', 'Sub-item budget yang dipilih tidak sesuai dengan Budget Plan ini.');
        }

        // GUARD: cegah pengajuan CR pada budget yang tidak berhak diakses user
        if ($budgetPlan->isCostCenterBudget()) {

            if ($budgetPlan->source_type === 'PAYROLL') {
                $allowed = $this->canSeePayroll();
            } else {
                // Overhead: privileged boleh semua; lainnya harus punya cost center
                // spesifik ini di access group-nya.
                $allowed = $this->overheadPrivileged()
                    || $this->accessibleOverheadCostCenterIds()->contains((int) $budgetPlan->cost_center_id);
            }

            if (!$allowed) {
                return back()->withInput()
                    ->with('error', 'Anda tidak berhak mengajukan Cost Request pada budget ini.');
            }
        }

        // Hitung total dari details
        $totalAmount = 0;
        foreach ((array) $request->input('details', []) as $row) {
            $amt = $this->normalizeMoney($row['amount'] ?? null);
            $totalAmount += $amt * (int) ($row['qty'] ?? 1);
        }

        /*
        | Status NOB disimpan sebagai kolom (is_nob + nob_amount), bukan
        | ditempelkan sebagai awalan '[NOB] ' pada deskripsi. Deskripsi adalah
        | kalimat pengguna; menaruh status anggaran di dalamnya membuat status
        | itu bisa diketik sendiri, terhapus saat diedit, dan harus diurai
        | ulang di setiap tempat yang menampilkannya.
        */
        $isNob     = $this->isNOB($budgetPlan, $totalAmount);
        $nobAmount = $this->nobOverage($budgetPlan, $totalAmount);
        $description = strtoupper(trim($request->description));

        /*
        | Kode CR: CR-{MM}{YY}{sumber(4)}{seq(4)}
        |   - Budget proyek      -> slot sumber = kode PO
        |   - Budget cost center -> slot sumber = angka kode CC
        | Slot cluster 4 digit sudah dihapus dari format (lihat
        | BudgetRequest::generateCode()); cluster tetap tersimpan di kolomnya
        | sendiri, hanya tidak lagi ikut menyusun nomor.
        | Keunikan tetap dijamin oleh counter + pengecekan exists di generateCode().
        */
        if ($budgetPlan->isCostCenterBudget()) {
            $sourceCode = preg_replace('/\D/', '', $budgetPlan->costCenter?->code ?? '');
            $sourceCode = $sourceCode !== '' ? $sourceCode : '0000';
        } else {
            $sourceCode = $budgetPlan->project?->code ?? '0000';
        }

        $code = BudgetRequest::generateCode($sourceCode);

        DB::transaction(function () use ($request, $code, $description, $isNob, $nobAmount) {
            $cr = BudgetRequest::create([
                'code'           => $code,
                'description'    => $description,
                'is_nob'         => $isNob,
                'nob_amount'     => $nobAmount,
                'amount'         => 0,
                'notes'          => $request->notes,
                'email'          => $request->email,
                'applicant_id'   => Auth::id(),
                'beneficiary_id' => $request->beneficiary_id,
                'type_id'        => $request->type_id,
                'cluster_id'     => $request->cluster_id,
                'budget_plan_id' => $request->budget_plan_id,
                'status'         => 'DRAFT',
            ]);

            $detailMap = $this->syncDetails($cr, $request);
            $this->storeDetailAttachments($cr, $request, $detailMap);
            $this->storeAttachments($cr, $request);
            $cr->addLog('DRAFT', 'DRAFT', 'created');
        });

        return redirect('/budget-request')
            ->with('success', 'Cost Request berhasil dibuat: ' . $code . ($isNob ? ' [NOB]' : ''));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON
    |--------------------------------------------------------------------------
    */

    /**
     * Aksi workflow satuan yang tersedia untuk $user pada $cr saat ini.
     *
     * Satu katalog dipakai bersama oleh semua layar yang menampilkan detail CR
     * (drawer Todo List, modal Cost Request), supaya tombol yang tampil selalu
     * sama dengan yang benar-benar diizinkan method aksinya. Setiap entri
     * menyebut sendiri apakah catatan wajib: reject SELALU wajib beralasan
     * (method-nya memvalidasi `notes` required), approve/check/close tidak.
     *
     * Bentuk entri:
     *   key            penanda unik untuk JavaScript
     *   label          teks tombol
     *   url            endpoint POST
     *   variant        kelas warna Bootstrap tanpa prefix "btn-"
     *   icon           kelas Font Awesome
     *   title          judul modal konfirmasi
     *   confirm        kalimat penjelas akibat aksi
     *   notes_required true = alasan wajib diisi
     */
    private function workflowActions(BudgetRequest $cr, ?User $user): array
    {
        if (! $user) {
            return [];
        }

        $aksi = [];

        $tambah = function (string $key, string $label, string $route, string $variant, string $icon, string $title, string $confirm, bool $notesRequired) use (&$aksi, $cr) {
            $aksi[] = [
                'key'            => $key,
                'label'          => $label,
                'url'            => route($route, $cr->id),
                'variant'        => $variant,
                'icon'           => $icon,
                'title'          => $title,
                'confirm'        => $confirm,
                'notes_required' => $notesRequired,
            ];
        };

        // ── Approval pengajuan ──
        if ($cr->status === 'SUBMITTED' && $user->hasRole(['superadmin', 'head'])) {
            $tambah('head-approve', 'Approve', 'budget-request.head-approve', 'success', 'fa-check', 'Head Approve',
                'Approve CR ' . $cr->code . '? Status akan menjadi CHECKED dan diteruskan ke Cost Control.', false);
            $tambah('head-reject', 'Reject', 'budget-request.head-reject', 'danger', 'fa-times', 'Tolak CR',
                'CR ' . $cr->code . ' akan menjadi REJECTED.', true);
        }

        if ($cr->status === 'CHECKED' && $user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $tambah('finance-verify', 'Verify', 'budget-request.finance-verify', 'success', 'fa-check-double', 'Cost Control Verify',
                'Verifikasi CR ' . $cr->code . '? Status akan menjadi VERIFIED dan menunggu approval CEO.', false);
            $tambah('finance-reject', 'Reject', 'budget-request.finance-reject', 'danger', 'fa-times', 'Tolak CR',
                'CR ' . $cr->code . ' akan menjadi REJECTED.', true);
        }

        if ($cr->status === 'VERIFIED' && $user->hasRole(['superadmin', 'approver'])) {
            $tambah('ceo-approve', 'Approve', 'budget-request.ceo-approve', 'success', 'fa-check', 'CEO Approve',
                'Approve CR ' . $cr->code . '? Status akan menjadi APPROVED dan siap masuk batch pembayaran.', false);
            $tambah('ceo-reject', 'Reject', 'budget-request.ceo-reject', 'danger', 'fa-times', 'Tolak CR',
                'CR ' . $cr->code . ' akan menjadi REJECTED.', true);
        }

        // ── Settlement ──
        if ($cr->status === 'REALIZED' && $user->hasRole(['superadmin', 'head'])) {
            $tambah('head-check-settlement', 'Check', 'budget-request.head-check-settlement', 'success', 'fa-check-double', 'Settlement — Head Check',
                'Check Settlement CR ' . $cr->code . '? Status akan berubah menjadi SETTLE CHECKED, menunggu verifikasi Finance.', false);
            $tambah('head-reject-settlement', 'Reject', 'budget-request.head-reject-settlement', 'danger', 'fa-times', 'Tolak Settlement',
                'CR ' . $cr->code . ' akan dikembalikan ke status PAID untuk diajukan ulang Settlement-nya.', true);
        }

        if ($cr->status === 'SETTLE_CHECKED' && $user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $tambah('finance-verify-settlement', 'Verify', 'budget-request.finance-verify-settlement', 'success', 'fa-check-double', 'Settlement — Finance Verify',
                'Verifikasi Settlement CR ' . $cr->code . '? Status akan berubah menjadi SETTLE VERIFIED, menunggu Close Finance Manager.', false);
            $tambah('finance-reject-settlement', 'Reject', 'budget-request.finance-reject-settlement', 'danger', 'fa-times', 'Tolak Settlement',
                'CR ' . $cr->code . ' akan dikembalikan ke status PAID untuk diajukan ulang Settlement-nya.', true);
        }

        if ($cr->status === 'SETTLE_VERIFIED' && $user->hasRole(['superadmin', 'finance_manager'])) {
            $tambah('ceo-close-settlement', 'Close', 'budget-request.ceo-close-settlement', 'danger', 'fa-lock', 'Settlement — Close',
                'Close Settlement CR ' . $cr->code . '? Status akan berubah menjadi CLOSED.', false);
            $tambah('manager-reject-settlement', 'Reject', 'budget-request.manager-reject-settlement', 'danger', 'fa-times', 'Tolak Settlement',
                'CR ' . $cr->code . ' akan dikembalikan ke status PAID. Second Payment yang belum dibayar ikut dibatalkan.', true);
        }

        return $aksi;
    }

    public function showJson(Request $request, $id)
    {
        /*
         | Rincian Budget Plan (kunci 'budget') adalah bagian TERMAHAL di
         | endpoint ini: per baris anggaran ia memanggil usedAmount(),
         | remainingAmount(), plus satu query costRequestDetails() beserta
         | eager-load-nya. Modal detail Cost Request tidak lagi punya tab
         | Budget, jadi ongkos itu hanya dibayar oleh pemanggil yang benar-
         | benar menampilkannya (daily-transaction/show) lewat ?with_budget=1.
         |
         | Penandaan NOB TIDAK bergantung pada blok ini — is_nob adalah kolom
         | tersimpan (di header CR maupun tiap baris rincian), jadi badge NOB
         | tetap muncul walau payload ini kosong.
         */
        $denganBudget = $request->boolean('with_budget');

        $cr = BudgetRequest::with([
            'type', 'cluster', 'budgetPlan.project.customer', 'budgetPlan.costCenter',
            'budgetPlan.details.account',
            'applicant', 'beneficiary.bank',
            'details.account', 'details.budgetDetail', 'details.attachments',
            'attachments',
            'realizations.account', 'realizations.requestDetail', 'realizations.attachments',
            'realizationAttachments',
            'secondPayments.dailyTransactionItems.dailyTransaction',
            'statusLogs',
        ])->findOrFail($id);

        abort_unless($this->canViewCr($cr), 403);

        $bp = $cr->budgetPlan;

        return response()->json([
            'id'               => $cr->id,
            'code'             => $cr->code,
            'description'      => $cr->description,
            'amount'           => $cr->amount,
            'amount_formatted' => $cr->amount_formatted,
            'notes'            => $cr->notes,
            'email'            => $cr->email,
            'status'           => $cr->status,
            'status_label'     => $cr->status_label,
            'status_color'     => $cr->status_color,
            'is_cash_advance'  => $cr->isCashAdvance(),
            // Apakah user yang login boleh takeout/restore item pada tahap CR ini.
            'can_moderate_items' => $cr->canModerateItems(Auth::user()),
            /*
             | Aksi workflow yang BOLEH dilakukan user ini pada CR ini sekarang.
             | Dihitung di server, bukan ditebak di JavaScript: gerbang role &
             | status yang sama persis dengan method aksinya masing-masing —
             | kalau daftar ini dibangun di layar, cepat atau lambat tombolnya
             | muncul untuk orang yang lalu ditolak 403 saat menekannya.
             */
            'workflow_actions' => $this->workflowActions($cr, Auth::user()),
            'is_nob'           => (bool) $cr->is_nob,
            'type'             => $cr->type?->name ?? '-',
            'cluster'          => $cr->cluster?->name ?? '-',
            'budget_plan'      => $cr->budgetPlan
                ? '[' . $cr->budgetPlan->code . '] ' . $cr->budgetPlan->description
                : '-',
            'budget_plan_id'   => $cr->budget_plan_id,
            'code_reference'   => $bp
                ? ($bp->isProjectBudget() ? ($bp->project?->code ?? '-') : ($bp->costCenter?->code ?? '-'))
                : '-',
            'project'          => $cr->budgetPlan?->project?->name ?? '-',
            'applicant'        => $cr->applicant?->name ?? '-',
            'beneficiary'      => $cr->beneficiary?->complete_name ?? '-',
            'bank'             => $cr->beneficiary?->bank?->name ?? '-',
            'account_number'   => $cr->beneficiary?->account_number ?? '-',
            // P-01: refund hanya dihitung setelah settlement (lihat
            // BudgetRequest::refundAmount()).
            'refund'           => $cr->refundAmount(),
            'refund_formatted' => $cr->refund_formatted,
            /*
             | Settlement bisa MELEBIHI pengajuan. Refund tidak pernah negatif
             | (lihat BudgetRequest::refundAmount()), jadi kalau hanya refund
             | yang dikirim, kelebihan itu tampil sebagai "Refund 0" — benar
             | secara angka, tapi menyembunyikan bahwa perusahaan justru masih
             | punya kewajiban bayar. Selisihnya dikirim terpisah supaya layar
             | detail bisa menamainya dengan benar: Second Payment.
             */
            'settlement_overage'           => max(0, $cr->settlement_overage),
            'settlement_overage_formatted' => number_format(max(0, $cr->settlement_overage), 0, ',', '.'),
            'payment_notes'    => $cr->statusLogs->firstWhere('action_type', 'paid')?->notes ?? null,
            'created_at'       => $cr->created_at->format('d M Y H:i'),
            'submitted_at'     => $cr->submitted_at?->format('d M Y H:i'),
            'approved_at'      => $cr->approved_at?->format('d M Y H:i'),
            'paid_at'          => $cr->paid_at?->format('d M Y H:i'),

            'details' => $cr->details->map(function ($d) {
                $bd        = $d->budgetDetail;
                $budget    = $bd->total ?? 0;
                $used      = $bd?->usedAmount() ?? 0;
                $remaining = $bd?->remainingAmount() ?? 0;

                return [
                    'id'                  => $d->id,
                    'budget_detail_id'    => $d->budget_detail_id,
                    'description'         => $d->description,
                    'account'             => $d->account?->name ?? '-',
                    'amount'              => $d->amount,
                    'amount_formatted'    => number_format($d->amount, 0, ',', '.'),
                    'qty'                 => $d->qty,
                    'total'               => $d->total,
                    'total_formatted'     => number_format($d->total, 0, ',', '.'),
                    'notes'               => $d->notes,
                    // Status NOB per baris ikut dikirim sebagai data, supaya
                    // JavaScript tidak perlu mengendus teks catatan.
                    'is_nob'              => (bool) $d->is_nob,
                    'nob_amount'          => (int) $d->nob_amount,
                    'nob_amount_formatted' => number_format($d->nob_amount, 0, ',', '.'),
                    'budget_formatted'    => number_format($budget, 0, ',', '.'),
                    'used_formatted'      => number_format($used, 0, ',', '.'),
                    'remaining_formatted' => number_format($remaining, 0, ',', '.'),
                    'status'              => $d->status ?? 'INCLUDED',
                    'is_takeout'          => $d->isTakenOut(),
                    'takeout_by_name'     => $d->takeout_by_name,
                    'takeout_stage'       => $d->takeout_stage,
                    'takeout_stage_label' => $d->isTakenOut() ? $d->takeout_stage_label : null,
                    'takeout_notes'       => $d->takeout_notes,
                    'takeout_at'          => $d->takeout_at?->format('d M Y H:i'),
                    'attachments'         => $d->attachments->map(fn ($a) => [
                        'id'             => $a->id,
                        'original_name'  => $a->original_name,
                        'path'           => $a->path,
                        'is_image'       => $a->isImage(),
                        'is_pdf'         => $a->isPdf(),
                        'size_formatted' => $a->size_formatted,
                    ])->values(),
                ];
            }),

            'realizations' => $cr->realizations->map(fn($r) => [
                'request_detail_id' => $r->request_detail_id,
                'cr_item'           => $r->requestDetail?->description ?? ($r->description ?? '-'),
                'total_cr'          => $r->requestDetail?->total ?? 0,
                'total_cr_formatted'=> number_format($r->requestDetail?->total ?? 0, 0, ',', '.'),
                'actual'            => $r->amount,
                'actual_formatted'  => number_format($r->amount, 0, ',', '.'),
                'notes'             => $r->notes,
                // Kolom lama dipertahankan agar kompatibel bila ada kode lain yang masih memakainya
                'description'       => $r->description,
                'account'           => $r->account?->name ?? '-',
                'amount'            => $r->amount,
                'amount_formatted'  => number_format($r->amount, 0, ',', '.'),
                'qty'               => $r->qty,
                'total'             => $r->total,
                'total_formatted'   => number_format($r->total, 0, ',', '.'),
                'attachments'       => $r->attachments->map(fn ($a) => [
                    'id'             => $a->id,
                    'original_name'  => $a->original_name,
                    'path'           => $a->path,
                    'is_image'       => $a->isImage(),
                    'is_pdf'         => $a->isPdf(),
                    'size_formatted' => $a->size_formatted,
                ])->values(),
            ]),
            'total_realization'           => $cr->total_realization,
            'total_realization_formatted' => $cr->total_realization_formatted,

            // batch_code: Second Payment dibayar lewat batch Treasury, jadi
            // statusnya tidak cukup PENDING/PAID — pengguna perlu tahu apakah
            // ia sudah diambil Treasury atau masih menunggu dimasukkan batch.
            'second_payments' => $cr->secondPayments->map(function ($p) {
                $activeItem = $p->dailyTransactionItems
                    ->first(fn ($i) => $i->status === 'INCLUDED'
                        && in_array($i->dailyTransaction?->status, ['PENDING_APPROVER', 'DONE', 'PAID']));

                return [
                    'id'              => $p->id,
                    'amount'          => $p->amount,
                    'amount_formatted'=> $p->amount_formatted,
                    'status'          => $p->status,
                    'paid_at'         => $p->paid_at?->format('d M Y H:i'),
                    'batch_id'        => $activeItem?->dailyTransaction?->id,
                    'batch_code'      => $activeItem?->dailyTransaction?->code,
                ];
            })->values(),

            'realization_attachments' => $cr->realizationAttachments->map(fn($a) => [
                'original_name' => $a->original_name,
                'path'          => $a->path,
                'size_formatted'=> $a->size_formatted,
                'is_image'      => $a->isImage(),
                'is_pdf'        => $a->isPdf(),
            ]),

            'attachments_count' => $cr->attachments->count(),
            'attachments' => $cr->attachments->map(fn($a) => [
                'original_name'  => $a->original_name,
                'path'           => $a->path,
                'is_image'       => $a->isImage(),
                'is_pdf'         => $a->isPdf(),
                'size_formatted' => $a->size_formatted ?? null,
            ])->values(),

            'logs' => $cr->statusLogs->map(fn($l) => [
                'action_label' => $l->action_label,
                'action_color' => $l->action_color,
                'status_from'  => $l->status_from,
                'status_to'    => $l->status_to,
                'status_from_label' => \App\Models\BudgetRequest::statusLabelFor($l->status_from),
                'status_to_label'   => \App\Models\BudgetRequest::statusLabelFor($l->status_to),
                'actor'        => $l->actor_name ?? 'System',
                'user_id'      => $l->acted_by,
                'role'         => $l->actor_role,
                'notes'        => $l->notes,
                'date'         => $l->created_at->format('d M Y H:i'),
            ]),

            // Ringkasan Budget Plan yang terhubung — dipakai tab "Budget" pada modal detail CR,
            // struktur mengikuti BudgetPlanController::showJson() supaya konsisten.
            'budget' => ($bp && $denganBudget) ? [
                'id'               => $bp->id,
                'code'             => $bp->code,
                'code_reference'   => $bp->isProjectBudget()
                    ? ($bp->project?->code ?? '-')
                    : ($bp->costCenter?->code ?? '-'),
                'description'      => $bp->description,
                'status'           => $bp->status,
                'status_color'     => $bp->status_color,
                'amount_formatted' => $bp->amount_formatted,
                'used_amount'      => $bp->usedAmount(),
                'used_amount_formatted' => number_format($bp->usedAmount(), 0, ',', '.'),
                'usage_percentage' => $bp->amount > 0
                    ? round(($bp->usedAmount() / $bp->amount) * 100, 1)
                    : 0,
                'details' => $bp->details->map(function ($d) {
                    $used      = $d->usedAmount();
                    $remaining = $d->remainingAmount();

                    $crDetails = collect();
                    if (\Illuminate\Support\Facades\Schema::hasColumn('request_detail', 'budget_detail_id')) {
                        $crDetails = $d->costRequestDetails()
                            ->whereHas('budgetRequest', fn ($q) => $q->whereIn('status', ['PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED']))
                            ->with(['budgetRequest:id,code,description,beneficiary_id,created_at', 'budgetRequest.beneficiary:id,nickname', 'realization'])
                            ->get()
                            ->map(function ($rd) {
                                $amount = (int) ($rd->realization?->total ?? $rd->total);
                                return [
                                    'cr_id'                => $rd->budgetRequest?->id ?? null,
                                    'cr_date_formatted'    => $rd->budgetRequest?->created_at?->format('d M Y') ?? '-',
                                    'beneficiary'          => $rd->budgetRequest?->beneficiary?->nickname ?? '-',
                                    'notes'                => $rd->notes ?: '-',
                                    'unit_price_formatted' => number_format((int) $rd->amount, 0, ',', '.'),
                                    'qty'                  => (float) $rd->qty,
                                    'amount'               => $amount,
                                    'amount_formatted'     => number_format($amount, 0, ',', '.'),
                                ];
                            })
                            ->values();
                    }

                    return [
                        'id'                  => $d->id,
                        'account'             => $d->account?->name ?? '-',
                        'deskripsi'           => $d->deskripsi,
                        'amount_formatted'    => number_format($d->amount, 0, ',', '.'),
                        'qty'                 => (float) $d->qty,
                        'unit'                => $d->unit,
                        'total'               => $d->total,
                        'total_formatted'     => number_format($d->total, 0, ',', '.'),
                        'used'                => $used,
                        'used_formatted'      => number_format($used, 0, ',', '.'),
                        'remaining'           => $remaining,
                        'remaining_formatted' => number_format($remaining, 0, ',', '.'),
                        'cr_details'          => $crDetails,
                    ];
                }),
            ] : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $cr = $this->findVisibleCr($id, ['details.account', 'details.attachments', 'attachments', 'budgetPlan.costCenter']);

        if (!$cr->isEditable()) {
            return redirect('/budget-request')
                ->with('error', 'Cost Request hanya dapat diedit saat berstatus DRAFT.');
        }

        $types         = RequestType::whereNotNull('name')->where('name', '!=', '')->orderBy('name')->get();
        $budgetPlans   = $this->getAvailableBudgetPlans();
        $clusters      = Cluster::where('status', 'ACTIVE')->orderBy('name')->get();
        $beneficiaries = Beneficiary::where('status', 'ACTIVE')->orderBy('complete_name')->get();

        $accountsBySource = [
            'PROJECT'  => $this->getAccountsFor('PROJECT'),
            'OVERHEAD' => $this->getAccountsFor('OVERHEAD'),
            'PAYROLL'  => $this->getAccountsFor('PAYROLL'),
        ];

        // Account mengikuti sumber dana budget plan CR ini
        $accounts = $this->getAccountsFor($cr->budgetPlan?->source_type ?? 'PROJECT');

        return view('budget-request.edit', compact(
            'cr', 'types', 'budgetPlans', 'clusters', 'beneficiaries', 'accounts', 'accountsBySource'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $cr = $this->findVisibleCr($id);

        if (!$cr->isEditable()) {
            return redirect('/budget-request')
                ->with('error', 'Cost Request hanya dapat diedit saat berstatus DRAFT.');
        }

        // Cluster hanya wajib bila budget plan bersumber dari PROYEK
        $sourcePlan  = BudgetPlan::with('costCenter')->find($request->budget_plan_id);
        $needCluster = $sourcePlan ? $sourcePlan->isProjectBudget() : true;

        $request->validate([
            'type_id'               => 'required|exists:request_type,id',
            'budget_plan_id'        => 'required|exists:budget_plans,id',
            'cluster_id'            => ($needCluster ? 'required' : 'nullable') . '|exists:clusters,id',
            'beneficiary_id'        => 'required|exists:beneficiaries,id',
            'description'           => 'required|string|max:500',
            'email'                 => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                if (!$value) {
                    return;
                }
                if (preg_match('/\s/', $value)) {
                    $fail('Email tidak boleh mengandung spasi. Gunakan format email@domain.com,email2@domain.com.');
                    return;
                }
                foreach (explode(',', $value) as $address) {
                    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                        $fail('Format email tidak valid: "' . $address . '".');
                        return;
                    }
                }
            }],
            'notes'                 => 'nullable|string',
            'details'               => 'required|array|min:1',
            'details.*.id'               => 'nullable|integer',
            'details.*.budget_detail_id' => 'required|exists:budget_plan_details,id',
            'details.*.notes'            => 'required|string',
            'details.*.amount'           => 'required',
            'details.*.qty'              => 'required|integer|min:1',
            'details.*.attachments'      => 'nullable|array',
            'details.*.attachments.*'    => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'delete_attachments'        => 'nullable|array',
            'delete_detail_attachments' => 'nullable|array',
        ]);

        if ($msg = $this->attachmentRuleError($request, (int) $request->type_id, $cr)) {
            return back()->withInput()->with('error', $msg);
        }

        // Limit Cash Advance per PIC — CR yang sedang diedit dikecualikan dari
        // hitungan supaya nominal lamanya tidak dihitung dua kali.
        if ($this->typeIsCashAdvance((int) $request->type_id)) {
            $msg = $this->cashAdvanceQuotaError(
                (int) $request->beneficiary_id,
                $this->requestedDetailTotal($request),
                $cr->id
            );
            if ($msg) return back()->withInput()->with('error', $msg);
        }

        $budgetPlan = BudgetPlan::findOrFail($request->budget_plan_id);

        if (!$this->detailsBelongToPlan($budgetPlan, $request)) {
            return back()->withInput()
                ->with('error', 'Sub-item budget yang dipilih tidak sesuai dengan Budget Plan ini.');
        }

        $totalAmount = 0;
        foreach ((array) $request->input('details', []) as $row) {
            $amt = $this->normalizeMoney($row['amount'] ?? null);
            $totalAmount += $amt * (int) ($row['qty'] ?? 1);
        }

        $isNob     = $this->isNOB($budgetPlan, $totalAmount, $cr->id);
        $nobAmount = $this->nobOverage($budgetPlan, $totalAmount, $cr->id);
        $description = strtoupper(trim($request->description));

        DB::transaction(function () use ($request, $cr, $description, $isNob, $nobAmount) {
            $cr->update([
                'description'    => $description,
                'is_nob'         => $isNob,
                'nob_amount'     => $nobAmount,
                'notes'          => $request->notes,
                'email'          => $request->email,
                'beneficiary_id' => $request->beneficiary_id,
                'type_id'        => $request->type_id,
                'cluster_id'     => $request->cluster_id,
                'budget_plan_id' => $request->budget_plan_id,
            ]);

            $detailMap = $this->syncDetails($cr, $request);
            $this->storeDetailAttachments($cr, $request, $detailMap);

            $deleteIds = (array) $request->input('delete_attachments', []);
            if (!empty($deleteIds)) {
                $toDelete = RequestAttachment::where('budget_request_id', $cr->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($toDelete as $att) {
                    Storage::disk('secure')->delete($att->path);
                    $att->delete();
                }
            }

            $this->storeAttachments($cr, $request);
        });

        return redirect('/budget-request')
            ->with('success', 'Cost Request berhasil diupdate.' . ($isNob ? ' [NOB]' : ''));
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT — DRAFT → SUBMITTED
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $cr = $this->findVisibleCr($id, ['type']);

        if ($cr->status !== 'DRAFT') {
            return back()->with('error', 'Hanya CR berstatus DRAFT yang dapat disubmit.');
        }

        // Aturan lampiran — Cash Advance wajib Lampiran Tambahan (global),
        // tipe lain wajib Lampiran Item di tiap baris rincian.
        if ($cr->isCashAdvance()) {
            if ($cr->attachments()->count() === 0) {
                return back()->with('error', 'Tidak bisa submit: pengajuan Cash Advance wajib punya minimal 1 file di "Lampiran Tambahan / Pendukung".');
            }
        } else {
            $withoutAtt = $cr->details()
                ->where('status', '!=', 'TAKEOUT')
                ->whereDoesntHave('attachments')
                ->count();

            if ($withoutAtt > 0) {
                return back()->with('error', 'Tidak bisa submit: masih ada ' . $withoutAtt . ' baris Rincian Pengajuan tanpa "Lampiran Item".');
            }
        }

        // Limit Cash Advance per PIC — dicek ulang di sini karena limit bisa
        // terpakai CR lain sejak draft ini dibuat.
        if ($cr->isCashAdvance()) {
            $msg = $this->cashAdvanceQuotaError($cr->beneficiary_id, (int) $cr->amount, $cr->id);
            if ($msg) return back()->with('error', $msg);
        }

        $cr->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);
        $cr->addLog('DRAFT', 'SUBMITTED', 'submitted');

        NotifyService::send(
            $this->headUsers(),
            'Cost Request menunggu approval',
            Auth::user()->name . ' mengajukan Cost Request "' . $cr->description . '" (' . $cr->code . ') untuk disetujui.',
            route('budget-request.index'),
            'workflow'
        );

        $res = back()->with('success', 'Cost Request berhasil diajukan ke Head.');

        if ($cr->isCashAdvance() && $warn = $this->cashAdvanceQuotaWarning($cr->beneficiary_id)) {
            $res->with('warning', $warn);
        }

        return $res;
    }

    /** Role Head — penerima notifikasi saat CR disubmit manager. */
    private function headUsers()
    {
        // Satu sumber daftar peran: App\Services\RoleDirectory.
        return app(RoleDirectory::class)->heads();
    }

    /** Role Finance — penerima notifikasi saat CR disetujui Head / diajukan realisasi. */
    private function financeUsers()
    {
        return app(RoleDirectory::class)->finance(withCostControl: true);
    }

    private function approverUsers()
    {
        return app(RoleDirectory::class)->approvers();
    }

    /*
    |--------------------------------------------------------------------------
    | HEAD APPROVE — SUBMITTED → CHECKED
    |--------------------------------------------------------------------------
    */

    public function headApprove(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'head'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'SUBMITTED', ['status' => 'CHECKED'], function ($cr) use ($request) {
            $cr->addLog('SUBMITTED', 'CHECKED', 'head_approved', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

        NotifyService::send(
            $this->financeUsers(),
            'Cost Request menunggu verifikasi Finance',
            $user->name . ' (Head) menyetujui Cost Request "' . $cr->description . '" (' . $cr->code . ').',
            route('budget-request.index'),
            'workflow'
        );

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Cost Request disetujui Head',
                'Cost Request "' . $cr->description . '" (' . $cr->code . ') disetujui Head dan diteruskan ke Finance.',
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()
            ->with('success', 'CR disetujui Head dan diteruskan ke Finance.');
    }

    /*
    |--------------------------------------------------------------------------
    | HEAD REJECT — SUBMITTED → REJECTED
    |--------------------------------------------------------------------------
    */

    public function headReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'head'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'SUBMITTED', ['status' => 'REJECTED'], function ($cr) use ($request) {
            $cr->addLog('SUBMITTED', 'REJECTED', 'head_rejected', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Cost Request ditolak',
                'Cost Request "' . $cr->description . '" (' . $cr->code . ') ditolak Head: ' . $request->notes,
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()->with('success', 'CR ditolak. Menunggu keputusan Manager (Revise/Cancel).');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE VERIFY — CHECKED → VERIFIED
    |--------------------------------------------------------------------------
    */

    public function financeVerify(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'CHECKED', ['status' => 'VERIFIED', 'approved_at' => now()], function ($cr) use ($request) {
            $cr->addLog('CHECKED', 'VERIFIED', 'finance_approved', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Cost Request diverifikasi Finance',
                'Cost Request "' . $cr->description . '" (' . $cr->code . ') diverifikasi Finance dan menunggu approval CEO.',
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()
            ->with('success', 'CR diverifikasi Finance dan menunggu approval CEO.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE REJECT — CHECKED → REJECTED
    |--------------------------------------------------------------------------
    */

    public function financeReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'CHECKED', ['status' => 'REJECTED'], function ($cr) use ($request) {
            $cr->addLog('CHECKED', 'REJECTED', 'finance_rejected', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Cost Request ditolak',
                'Cost Request "' . $cr->description . '" (' . $cr->code . ') ditolak Finance: ' . $request->notes,
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()->with('success', 'CR ditolak. Menunggu keputusan Manager (Revise/Cancel).');
    }

    /*
    |--------------------------------------------------------------------------
    | CEO/APPROVER — VERIFIED → APPROVED (individual, terpisah dari batching)
    |--------------------------------------------------------------------------
    */

    public function ceoApprove(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'approver'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'VERIFIED', ['status' => 'APPROVED'], function ($cr) use ($request) {
            $cr->addLog('VERIFIED', 'APPROVED', 'cr_approved', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Cost Request disetujui CEO',
                'Cost Request "' . $cr->description . '" (' . $cr->code . ') disetujui CEO dan siap masuk batch pembayaran.',
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()->with('success', 'CR disetujui CEO dan siap masuk batch pembayaran.');
    }

    public function ceoReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'approver'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'VERIFIED', ['status' => 'REJECTED'], function ($cr) use ($request) {
            $cr->addLog('VERIFIED', 'REJECTED', 'cr_rejected', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Cost Request ditolak',
                'Cost Request "' . $cr->description . '" (' . $cr->code . ') ditolak CEO: ' . $request->notes,
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()->with('success', 'CR ditolak. Menunggu keputusan Manager (Revise/Cancel).');
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGER — keputusan setelah Reject: Revise (→ DRAFT) atau Cancel (→ CANCELLED)
    |--------------------------------------------------------------------------
    */

    public function managerRevise(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'manager', 'supervisor'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk merevisi CR ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $cr = $this->findVisibleCr($id);
        if ($cr->status !== 'REJECTED') return back()->with('error', 'Status tidak valid.');

        DB::transaction(function () use ($cr, $request) {
            // Reset semua item yang sempat di-takeout approval — CR yang direvisi
            // kembali utuh, Manager bebas mengedit lagi rinciannya.
            $cr->details()->where('status', 'TAKEOUT')->update([
                'status'          => 'INCLUDED',
                'takeout_by'      => null,
                'takeout_by_name' => null,
                'takeout_role'    => null,
                'takeout_stage'   => null,
                'takeout_notes'   => null,
                'takeout_at'      => null,
            ]);

            $cr->update(['status' => 'DRAFT']);
            $cr->recalculateAmount();
            $cr->addLog('REJECTED', 'DRAFT', 'revised', $request->notes);
        });

        return back()->with('success', 'CR dikembalikan ke Draft untuk direvisi.');
    }

    public function managerCancel(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk membatalkan CR ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'REJECTED', ['status' => 'CANCELLED'], function ($cr) use ($request) {
            $cr->addLog('REJECTED', 'CANCELLED', 'cancelled', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

        return back()->with('success', 'CR dibatalkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | BULK APPROVE/REJECT — dipakai section Review per Budget Plan, checkbox
    | dulu baru satu tombol Approve/Reject untuk semua yang dicentang. Setiap
    | CR ditransisikan sesuai status & role aktor saat ini (tidak asumsikan
    | semua CR yang dicentang ada di stage yang sama).
    |--------------------------------------------------------------------------
    */

    /** @return bool true jika CR berhasil ditransisikan (status+role cocok). */
    private function applyReviewApprove(BudgetRequest $cr, User $user): bool
    {
        if ($cr->status === 'SUBMITTED' && $user->hasRole(['superadmin', 'head'])) {
            $cr->update(['status' => 'CHECKED']);
            $cr->addLog('SUBMITTED', 'CHECKED', 'head_approved', null);
            NotifyService::send(
                $this->financeUsers(),
                'Cost Request menunggu verifikasi Finance',
                $user->name . ' (Head) menyetujui Cost Request "' . $cr->description . '" (' . $cr->code . ').',
                route('budget-request.index'),
                'workflow'
            );
            return true;
        }

        if ($cr->status === 'CHECKED' && $user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $cr->update(['status' => 'VERIFIED', 'approved_at' => now()]);
            $cr->addLog('CHECKED', 'VERIFIED', 'finance_approved', null);
            return true;
        }

        if ($cr->status === 'VERIFIED' && $user->hasRole(['superadmin', 'approver'])) {
            $cr->update(['status' => 'APPROVED']);
            $cr->addLog('VERIFIED', 'APPROVED', 'cr_approved', null);
            return true;
        }

        return false;
    }

    /** @return bool true jika CR berhasil ditolak (status+role cocok). */
    private function applyReviewReject(BudgetRequest $cr, User $user, string $notes): bool
    {
        $fromStatus = $cr->status;
        $actionType = match ($fromStatus) {
            'SUBMITTED' => $user->hasRole(['superadmin', 'head']) ? 'head_rejected' : null,
            'CHECKED'   => $user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control']) ? 'finance_rejected' : null,
            'VERIFIED'  => $user->hasRole(['superadmin', 'approver']) ? 'cr_rejected' : null,
            default     => null,
        };

        if (!$actionType) return false;

        $cr->update(['status' => 'REJECTED']);
        $cr->addLog($fromStatus, 'REJECTED', $actionType, $notes);

        return true;
    }

    public function reviewBulkApprove(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $ids  = (array) $request->input('ids', []);

        $count = 0;
        foreach (BudgetRequest::whereIn('id', $ids)->get() as $cr) {
            if ($this->applyReviewApprove($cr, $user)) $count++;
        }

        return back()->with('success', $count . ' CR berhasil di-approve.');
    }

    public function reviewBulkReject(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $request->validate(['notes' => 'required|string|max:500']);
        $ids = (array) $request->input('ids', []);

        $count = 0;
        foreach (BudgetRequest::whereIn('id', $ids)->get() as $cr) {
            if ($this->applyReviewReject($cr, $user, $request->notes)) $count++;
        }

        return back()->with('success', $count . ' CR berhasil ditolak. Menunggu keputusan Manager (Revise/Cancel).');
    }

    /*
    |--------------------------------------------------------------------------
    | TAKEOUT / RESTORE SUB-ITEM — Head/Finance/CEO mengeluarkan satu baris
    | rincian dari pengajuan tanpa menolak seluruh CR. Nilai CR otomatis
    | berkurang; item TAKEOUT tetap tampil di detail (dicoret). Bila SEMUA
    | item ter-takeout, CR otomatis menjadi REJECTED.
    |--------------------------------------------------------------------------
    */

    public function takeoutDetail(Request $request, $id, $detailId)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        /** @var User $user */
        $user = Auth::user();
        $cr   = $this->findVisibleCr($id, ['details', 'applicant']);

        if (!$cr->canModerateItems($user)) {
            abort(403, 'Anda tidak berwenang men-takeout item pada tahap approval ini.');
        }

        $detail = $cr->details->firstWhere('id', (int) $detailId);
        if (!$detail) {
            return back()->with('error', 'Item tidak ditemukan pada CR ini.');
        }
        if ($detail->isTakenOut()) {
            return back()->with('error', 'Item ini sudah di-takeout.');
        }

        $stage      = $cr->status;
        $stageLabel = \App\Models\RequestDetail::STAGE_LABELS[$stage] ?? $stage;

        DB::transaction(function () use ($cr, $detail, $user, $stage, $stageLabel, $request) {
            $detail->update([
                'status'          => 'TAKEOUT',
                'takeout_by'      => $user->id,
                'takeout_by_name' => $user->name,
                'takeout_role'    => $user->role->name ?? null,
                'takeout_stage'   => $stage,
                'takeout_notes'   => $request->notes,
                'takeout_at'      => now(),
            ]);

            $cr->recalculateAmount();

            $remaining = $cr->details()->where('status', '!=', 'TAKEOUT')->count();
            $itemLabel = $detail->description ?: ($detail->notes ?: 'item');
            $itemAmt   = number_format((int) $detail->total, 0, ',', '.');

            if ($remaining === 0) {
                $cr->update(['status' => 'REJECTED']);
                $cr->addLog($stage, 'REJECTED', 'all_items_takeout',
                    'Item terakhir "' . $itemLabel . '" (' . $itemAmt . ') ditakeout oleh ' . $stageLabel
                    . '. Semua item ter-takeout — CR otomatis Rejected. Alasan: ' . $request->notes);
            } else {
                $cr->addLog($stage, $stage, 'item_takeout',
                    'Item "' . $itemLabel . '" (' . $itemAmt . ') ditakeout oleh ' . $stageLabel
                    . '. Alasan: ' . $request->notes);
            }
        });

        $cr->refresh();

        if ($cr->status === 'REJECTED' && $cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Cost Request ditolak (semua item di-takeout)',
                'Seluruh item pada Cost Request "' . $cr->description . '" (' . $cr->code . ') telah di-takeout oleh ' . $stageLabel . '. CR otomatis Rejected.',
                route('budget-request.index'),
                'workflow'
            );

            return back()->with('success', 'Item terakhir di-takeout — CR otomatis menjadi Rejected.');
        }

        return back()->with('success', 'Item berhasil di-takeout. Nilai CR sekarang ' . $cr->amount_formatted . '.');
    }

    public function restoreDetail(Request $request, $id, $detailId)
    {
        /** @var User $user */
        $user = Auth::user();
        $cr   = $this->findVisibleCr($id, ['details']);

        if (!$cr->canModerateItems($user)) {
            abort(403, 'Anda tidak berwenang mengembalikan item pada tahap approval ini.');
        }

        $detail = $cr->details->firstWhere('id', (int) $detailId);
        if (!$detail) {
            return back()->with('error', 'Item tidak ditemukan pada CR ini.');
        }
        if (!$detail->isTakenOut()) {
            return back()->with('error', 'Item ini tidak dalam status takeout.');
        }

        $stage      = $cr->status;
        $stageLabel = \App\Models\RequestDetail::STAGE_LABELS[$stage] ?? $stage;

        DB::transaction(function () use ($cr, $detail, $stage, $stageLabel) {
            $itemLabel = $detail->description ?: ($detail->notes ?: 'item');

            $detail->update([
                'status'          => 'INCLUDED',
                'takeout_by'      => null,
                'takeout_by_name' => null,
                'takeout_role'    => null,
                'takeout_stage'   => null,
                'takeout_notes'   => null,
                'takeout_at'      => null,
            ]);

            $cr->recalculateAmount();
            $cr->addLog($stage, $stage, 'item_restore',
                'Item "' . $itemLabel . '" dikembalikan (di-restore) oleh ' . $stageLabel . '.');
        });

        $cr->refresh();

        return back()->with('success', 'Item dikembalikan. Nilai CR sekarang ' . $cr->amount_formatted . '.');
    }

    /*
    |--------------------------------------------------------------------------
    | FORM REALISASI — GET, tampilkan form sebelum submitRealization()
    |--------------------------------------------------------------------------
    */

    public function realizeForm($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'manager', 'supervisor'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengajukan realisasi CR ini.');
        }

        // 'realizations' di-eager-load supaya kalau settlement ini pernah
        // ditolak (REALIZED -> PAID) & disubmit ulang, kolom "Description
        // Settle" & "Settle" ke-prefill dari input sebelumnya (bukan reset).
        $budgetRequest = $this->findVisibleCr($id, [
            'details.account', 'realizations.attachments', 'realizationAttachments',
        ]);

        if ($budgetRequest->status !== 'PAID') {
            return redirect()->route('budget-request.index')
                ->with('error', 'Hanya CR berstatus PAID yang dapat diajukan realisasi.');
        }

        return view('budget-request.realize', compact('budgetRequest'));
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT REALISASI — PAID → REALIZED (manager)
    |--------------------------------------------------------------------------
    */

    public function submitRealization(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'manager', 'supervisor'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengajukan realisasi CR ini.');
        }

        $request->validate([
            'realizations'                     => 'required|array|min:1',
            'realizations.*.request_detail_id' => 'required|exists:request_detail,id',
            // Settle boleh 0 (mis. uang dikembalikan utuh); kosong dianggap 0.
            'realizations.*.amount'            => 'nullable',
            'realizations.*.attachments'       => 'nullable|array',
            'realizations.*.attachments.*'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'realization_attachments'          => 'nullable|array',
            'realization_attachments.*'        => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            // Lampiran lama yang dicentang hapus oleh user.
            'delete_realization_attachments'        => 'nullable|array',
            'delete_realization_global_attachments' => 'nullable|array',
        ]);

        $cr = $this->findVisibleCr($id);

        if ($cr->status !== 'PAID') {
            return back()->with('error', 'Hanya CR berstatus PAID yang dapat diajukan realisasi.');
        }

        $validDetailIds = $cr->details()->where('status', '!=', 'TAKEOUT')->pluck('id')->map(fn ($i) => (int) $i);
        $submittedIds   = collect((array) $request->input('realizations', []))
            ->pluck('request_detail_id')->filter()->map(fn ($i) => (int) $i);

        if ($submittedIds->diff($validDetailIds)->isNotEmpty()) {
            return back()->withInput()
                ->with('error', 'Item realisasi yang dipilih tidak sesuai dengan Cost Request ini.');
        }

        // CR yang sudah cair (PAID) wajib melampirkan bukti per item di tiap
        // baris Rincian Settlement; lampiran global di sini hanya opsional.
        $missingAtt = [];
        $no         = 0;
        foreach ((array) $request->input('realizations', []) as $i => $row) {
            if (! $this->normalizeMoney($row['amount'] ?? null) && blank($row['request_detail_id'] ?? null)) {
                continue;
            }
            $no++;

            // Baris dengan Settle 0 tidak wajib berlampiran.
            if (! $this->normalizeMoney($row['amount'] ?? null)) {
                continue;
            }

            $hasFile = collect((array) $request->file("realizations.$i.attachments", []))
                ->contains(fn ($f) => $f && $f->isValid());

            // Lampiran dari submit sebelumnya (mis. settlement yang sempat
            // ditolak) tetap dihitung, selama tidak dicentang hapus.
            if (! $hasFile) {
                $detailId = (int) ($row['request_detail_id'] ?? 0);
                $hapusIds = array_filter((array) $request->input('delete_realization_attachments', []));

                $hasFile = \App\Models\RealizationDetailAttachment::whereIn(
                        'realization_id',
                        $cr->realizations()->where('request_detail_id', $detailId)->pluck('id')
                    )
                    ->whereNotIn('id', $hapusIds ?: [0])
                    ->exists();
            }

            if (! $hasFile) $missingAtt[] = $no;
        }

        if ($missingAtt) {
            return back()->withInput()->with(
                'error',
                'Setiap baris Rincian Settlement wajib punya "Lampiran Item". Baris yang belum berlampiran: no. '
                . implode(', ', $missingAtt) . '.'
            );
        }

        DB::transaction(function () use ($request, $cr) {
            /*
            | Lampiran global settlement TIDAK lagi dihapus borongan — kalau
            | settlement pernah ditolak, bukti lama harus tetap ada. Yang
            | dihapus hanya yang dicentang user.
            */
            $hapusGlobal = array_filter((array) $request->input('delete_realization_global_attachments', []));
            if ($hapusGlobal) {
                $cr->realizationAttachments()->whereIn('id', $hapusGlobal)->get()
                    ->each(function ($att) {
                        Storage::disk('secure')->delete($att->path);
                        $att->delete();
                    });
            }

            $this->syncRealizations($cr, $request);
            $this->storeRealizationAttachments($cr, $request);

            $cr->update([
                'status'      => 'REALIZED',
                'realized_at' => now(),
            ]);

            $cr->addLog('PAID', 'REALIZED', 'realization_submitted',
                'Total realisasi: ' . number_format($cr->fresh()->total_realization, 0, ',', '.')
            );
        });

        NotifyService::send(
            $this->financeUsers(),
            'Realisasi CR menunggu verifikasi',
            Auth::user()->name . ' mengajukan realisasi Cost Request "' . $cr->description . '" (' . $cr->code . ').',
            route('budget-request.index'),
            'workflow'
        );

        // Redirect eksplisit ke daftar CR (bukan back()) — hindari bounce ke Referer
        // yang bisa menunjuk ke URL POST submit-realization.
        return redirect()->route('budget-request.index')
            ->with('success', 'Realisasi berhasil diajukan. Status CR menjadi REALIZED.');
    }

    /*
    |--------------------------------------------------------------------------
    | SETTLEMENT — Cash Advance
    |--------------------------------------------------------------------------
    | Seluruh alurnya dipindah ke Concerns\HandlesCrSettlement:
    |   REALIZED (Settle) -> SETTLE_CHECKED (Head) -> SETTLE_VERIFIED (Finance)
    |   -> SETTLE_APPROVED / SECOND_APPROVED (cabang Expense Claim) -> CLOSED.
    |
    | Reject di tiap tahap mengembalikan CR ke PAID (manager mengajukan ulang
    | Settlement). Tidak ada aturan yang berubah saat pemindahan — lihat
    | catatan di berkas trait-nya soal alasan pemisahan.
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | SECOND PAYMENT — dibuat otomatis saat Settlement > nilai pengajuan.
    |
    | Pembayarannya TIDAK lagi ditandai dari sini/To-Do List. Sebelumnya cukup
    | satu klik "Mark Paid" yang hanya mengubah kolom status — uangnya keluar
    | tanpa baris Treasury, tanpa bukti transfer, dan tanpa jejak di riwayat CR.
    | Sekarang Second Payment ikut batch Treasury seperti pengeluaran lain:
    |   Treasury pilih ke batch  -> log 'second_payment_batched'
    |   CEO approve batch        -> log 'second_payment_ceo_approved'
    |   Treasury Mark Paid batch -> status PAID + log 'second_payment_paid'
    | Lihat DailyTransactionController::storeCashOut()/approve()/markPaid().
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | CHECK NOB — AJAX
    |--------------------------------------------------------------------------
    */

    public function checkNob(Request $request)
    {
        $bp     = BudgetPlan::find($request->budget_plan_id);
        $amount = (int) $request->amount;
        $crId   = $request->cr_id ? (int) $request->cr_id : null;

        if (!$bp) return response()->json(['is_nob' => false, 'remaining' => 0]);

        $isNob     = $this->isNOB($bp, $amount, $crId);
        $remaining = $bp->remainingAmount();

        return response()->json([
            'is_nob'            => $isNob,
            'remaining'         => $remaining,
            'remaining_formatted' => number_format($remaining, 0, ',', '.'),
            'budget_amount'     => $bp->amount,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT — daftar Cost Request ke Excel
    |--------------------------------------------------------------------------
    */

    /**
     * API publik (token statis, bukan session) untuk konsumsi eksternal
     * seperti Excel Power Query. Contoh:
     *   GET /api/public/budget-request?token=...&date_from=2026-05-01&date_to=2026-08-03
     * Token diverifikasi terhadap EXPORT_API_TOKEN di .env — siapapun yang
     * pegang token ini otomatis dapat akses ke SEMUA Cost Request (tidak
     * dibatasi access group/role, karena endpoint ini tidak punya sesi user).
     */
    private function checkExportApiToken(Request $request): bool
    {
        $expected = config('services.export_api.token');

        if (blank($expected)) {
            return false;
        }

        /*
        | Token dibaca dari header lebih dulu (Authorization: Bearer <token>
        | atau X-Export-Token). Query string dipertahankan sebagai jalur
        | cadangan karena konsumen yang sudah berjalan (Excel Power Query)
        | memakainya — tetapi dicatat sebagai peringatan, sebab token di URL
        | ikut tersimpan di access log server, riwayat peramban, dan header
        | Referer. Rencananya jalur itu dimatikan setelah konsumen dipindah.
        */
        $provided = $request->bearerToken()
            ?: $request->header('X-Export-Token')
            ?: $request->query('token');

        if (blank($provided)) {
            return false;
        }

        // hash_equals: perbandingan waktu-tetap. '===' pada string membocorkan
        // posisi karakter pertama yang berbeda lewat selisih waktu eksekusi.
        $valid = hash_equals((string) $expected, (string) $provided);

        if ($valid && $request->query('token')) {
            Log::warning('Export API dipanggil dengan token di query string (usang)', [
                'ip'   => $request->ip(),
                'path' => $request->path(),
            ]);
        }

        if (! $valid) {
            Log::warning('Export API ditolak: token tidak cocok', [
                'ip'   => $request->ip(),
                'path' => $request->path(),
            ]);
        }

        return $valid;
    }

    /**
     * Rentang tanggal untuk API publik — SELALU terbatas.
     *
     * Sebelumnya date_from/date_to hanya diterapkan kalau dikirim, sehingga
     * permintaan tanpa parameter menarik SELURUH riwayat Cost Request beserta
     * delapan relasinya ke memori PHP sekaligus. Pada puluhan ribu baris itu
     * lambat; pada ratusan ribu ia menghabiskan memory_limit dan berhenti
     * sebagai 500. Karena endpoint ini dipanggil Excel Power Query yang bisa
     * me-refresh otomatis, satu workbook yang salah setel cukup untuk
     * menjatuhkan aplikasi — dan bagi pemegang token itu juga jalur DoS.
     *
     * Pola bawaannya diambil dari crDateRange() yang sudah dipakai halaman UI:
     * tanpa parameter = bulan berjalan. Lebar rentang dibatasi
     * API_MAX_RENTANG_HARI supaya permintaan yang meminta bertahun-tahun
     * ditolak dengan pesan yang jelas, bukan dijawab dengan timeout.
     *
     * @return array{0: string, 1: string}|\Illuminate\Http\JsonResponse
     */
    private function apiDateRange(Request $request)
    {
        [$dateFrom, $dateTo] = $this->crDateRange($request);

        try {
            $dari  = \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay();
            $hingga = \Illuminate\Support\Carbon::parse($dateTo)->endOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Format date_from/date_to tidak sah. Gunakan YYYY-MM-DD.',
            ], 422);
        }

        if ($hingga->lessThan($dari)) {
            return response()->json([
                'message' => 'date_to tidak boleh lebih awal dari date_from.',
            ], 422);
        }

        if ($dari->diffInDays($hingga) > self::API_MAX_RENTANG_HARI) {
            return response()->json([
                'message' => 'Rentang tanggal maksimal ' . self::API_MAX_RENTANG_HARI
                    . ' hari. Persempit date_from/date_to, lalu gabungkan hasilnya di sisi konsumen.',
            ], 422);
        }

        return [$dari->format('Y-m-d H:i:s'), $hingga->format('Y-m-d H:i:s')];
    }

    /** Query dasar BudgetRequest untuk API publik, sudah difilter rentang tanggal. */
    private function apiBaseQuery(Request $request, array $with, string $dari, string $hingga)
    {
        // Perbandingan langsung (bukan whereDate) supaya indeks created_at terpakai.
        return BudgetRequest::with($with)
            ->where('created_at', '>=', $dari)
            ->where('created_at', '<=', $hingga)
            ->latest()
            /*
            | Batas keras sebagai jaring terakhir. Rentang tanggal sudah
            | membatasi, tapi satu bulan yang sangat sibuk tetap tidak boleh
            | bisa menjatuhkan proses PHP — lebih baik jawabannya terpotong dan
            | diketahui daripada permintaannya mati tanpa jawaban.
            */
            ->limit(self::API_MAX_BARIS);
    }

    public function apiExportData(Request $request)
    {
        if (! $this->checkExportApiToken($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $rentang = $this->apiDateRange($request);
        if ($rentang instanceof \Illuminate\Http\JsonResponse) {
            return $rentang;
        }

        $budgetRequests = $this->apiBaseQuery($request, [
            'type', 'cluster', 'budgetPlan.project', 'budgetPlan.costCenter',
            'applicant', 'beneficiary', 'statusLogs', 'realizations',
        ], $rentang[0], $rentang[1])->get();

        $data = $budgetRequests->map(function ($cr) {
            $isNob = (bool) $cr->is_nob;
            $desc  = $isNob ? ltrim(preg_replace('/^\[NOB\]\s*/i', '', $cr->description)) : $cr->description;

            $sumber = $cr->budgetPlan?->isProjectBudget()
                ? ($cr->budgetPlan->project?->name ?? '-')
                : ($cr->budgetPlan?->costCenter?->name ?? '-');

            $realisasi = $cr->total_realization;
            $paidLog   = $cr->statusLogs->firstWhere('action_type', 'paid');

            return [
                'kode'           => $cr->code,
                'tanggal'        => $cr->created_at?->format('Y-m-d H:i:s'),
                'deskripsi'      => $desc,
                'tipe'           => $cr->type?->name ?? '-',
                'sumber_dana'    => $sumber,
                'beneficiary'    => $cr->beneficiary?->nickname ?? '-',
                'pemohon'        => strtoupper($cr->applicant?->username ?? '-'),
                'request'        => (int) $cr->amount,
                'realisasi'      => $realisasi,
                'refund'         => $cr->refundAmount(),
                'status'         => $cr->status,
                'nob'            => $isNob ? 'YA' : '-',
                'transaction_id' => $paidLog?->notes ?? '-',
            ];
        });

        return response()->json($data);
    }

    /**
     * API publik — Detail rincian tiap Cost Request (mirror Sheet "Detail" di export Excel).
     *   GET /api/public/budget-request/detail?token=...&date_from=...&date_to=...
     */
    public function apiExportDetailData(Request $request)
    {
        if (! $this->checkExportApiToken($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $rentang = $this->apiDateRange($request);
        if ($rentang instanceof \Illuminate\Http\JsonResponse) {
            return $rentang;
        }

        $budgetRequests = $this->apiBaseQuery($request, ['details'], $rentang[0], $rentang[1])->get();

        $data = collect();
        foreach ($budgetRequests as $cr) {
            foreach ($cr->details as $d) {
                if ($d->status === 'TAKEOUT') continue;
                $data->push([
                    'kode'      => $cr->code,
                    'deskripsi' => $d->description,
                    'notes'     => $d->notes,
                    'satuan'    => (int) $d->amount,
                    'qty'       => $d->qty,
                    'total'     => (int) $d->total,
                ]);
            }
        }

        return response()->json($data->values());
    }

    /**
     * API publik — Detail realisasi tiap Cost Request (mirror Sheet "Detail Realisasi" di export Excel).
     *   GET /api/public/budget-request/realisasi?token=...&date_from=...&date_to=...
     */
    public function apiExportRealizationData(Request $request)
    {
        if (! $this->checkExportApiToken($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $rentang = $this->apiDateRange($request);
        if ($rentang instanceof \Illuminate\Http\JsonResponse) {
            return $rentang;
        }

        $budgetRequests = $this->apiBaseQuery($request, ['realizations'], $rentang[0], $rentang[1])->get();

        $data = collect();
        foreach ($budgetRequests as $cr) {
            foreach ($cr->realizations as $r) {
                $data->push([
                    'kode'         => $cr->code,
                    'deskripsi'    => $r->description,
                    'notes'        => $r->notes,
                    'actual_used'  => (int) $r->amount,
                    'qty'          => $r->qty,
                    'total'        => (int) $r->total,
                ]);
            }
        }

        return response()->json($data->values());
    }

    public function export(Request $request)
    {
        $query = BudgetRequest::with([
            'type', 'cluster', 'budgetPlan.project', 'budgetPlan.costCenter',
            'applicant', 'beneficiary', 'details.account', 'statusLogs', 'realizations',
        ]);

        $this->scopeByRole($query);

        // Export mengikuti hasil filter yang sedang aktif di tabel (search,
        // dropdown PO/Tipe/Beneficiary/Manager/Status/NOB, klik stepper status)
        // — browser mengirim ID baris yang lolos filter tersebut. Tanpa ids
        // (mis. diakses langsung), fallback ke rentang tanggal seperti biasa.
        // Export dari tab Cash Advance / Cost Payment hanya mengambil CR tab tsb.
        // (tab 'db' / kosong = semua).
        $tab = $request->input('tab');
        if ($tab === 'ca') {
            $query->cashAdvance();
        } elseif ($tab === 'cp') {
            $query->costPayment();
        }

        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        } else {
            // Perbandingan langsung (bukan whereDate) supaya indeks terpakai.
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
            }
            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }
        }

        $budgetRequests = $query->latest()->get();

        $spreadsheet = new Spreadsheet();

        /*
        |----------------------------------------------------------------
        | SHEET 1 — HEADER (satu baris per Cost Request)
        |----------------------------------------------------------------
        */
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Header');

        /*
        | Kolom Header mengikuti KOLOM AKTIF tabel di layar (dikirim JS lewat cols[],
        | urutan sesuai tabel). Tanpa cols (akses langsung) -> semua kolom lengkap.
        */
        $columnDefs = [
            'code'      => ['Kode',         16, fn ($cr) => $cr->code],
            'desc'      => ['Deskripsi',    40, fn ($cr) => $cr->is_nob ? ltrim(preg_replace('/^\[NOB\]\s*/i', '', $cr->description)) : $cr->description],
            'jenis'     => ['Jenis',        14, fn ($cr) => $cr->isCashAdvance() ? 'Cash Advance' : 'Cost Payment'],
            'tanggal'   => ['Tanggal',      16, fn ($cr) => $cr->created_at?->format('d-m-Y H:i')],
            'paid_at'   => ['Tanggal Paid', 16, fn ($cr) => $cr->paid_at?->format('d-m-Y H:i') ?? '-'],
            'pengaju'   => ['Requestor',    16, fn ($cr) => strtoupper($cr->applicant?->username ?? '-')],
            'penerima'  => ['Beneficiary',  20, fn ($cr) => $cr->beneficiary?->nickname ?? '-'],
            'request'   => ['Request',      16, fn ($cr) => (int) $cr->amount],
            'realisasi' => ['Settlement',   16, fn ($cr) => (int) $cr->total_realization],
            'refund'    => ['Refund',       16, fn ($cr) => (int) $cr->refundAmount()],
            // Cost: Cash Advance = nilai Settlement, Cost Payment = nilai Request.
            'cost'      => ['Cost',         16, fn ($cr) => $cr->isCashAdvance() ? (int) $cr->total_realization : (int) $cr->amount],
            'status'    => ['Status',       14, fn ($cr) => $cr->status],
            // Hanya untuk export tanpa cols (bentuk lengkap lama):
            'type'      => ['Tipe',         16, fn ($cr) => $cr->type?->name ?? '-'],
            'sumber'    => ['Sumber Dana',  28, fn ($cr) => $cr->budgetPlan?->isProjectBudget()
                ? ($cr->budgetPlan->project?->name ?? '-')
                : ($cr->budgetPlan?->costCenter?->name ?? '-')],
            'nob'       => ['NOB',           8, fn ($cr) => $cr->is_nob ? 'YA' : '-'],
            'txid'      => ['Transaction ID', 22, fn ($cr) => $cr->statusLogs->firstWhere('action_type', 'paid')?->notes ?? '-'],
        ];

        $legacyOrder = ['code', 'tanggal', 'paid_at', 'desc', 'type', 'sumber', 'penerima', 'pengaju', 'request', 'realisasi', 'refund', 'status', 'nob', 'txid'];
        $requested   = collect((array) $request->input('cols', []))->filter(fn ($k) => isset($columnDefs[$k]))->values()->all();
        $activeCols  = $requested ?: $legacyOrder;

        foreach ($activeCols as $i => $key) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $columnDefs[$key][0]);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getColumnDimension($col)->setWidth($columnDefs[$key][1]);
        }

        $row = 2;
        foreach ($budgetRequests as $cr) {
            foreach ($activeCols as $i => $key) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue($col . $row, $columnDefs[$key][2]($cr));
            }
            $row++;
        }

        /*
        |----------------------------------------------------------------
        | SHEET 2 — DETAIL (satu baris per item rincian CR)
        |----------------------------------------------------------------
        */
        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Detail');

        $detailHeaders = ['Kode', 'Deskripsi', 'Notes', 'Satuan', 'Qty', 'Total'];
        foreach ($detailHeaders as $i => $label) {
            $col = chr(65 + $i);
            $detailSheet->setCellValue($col . '1', $label);
            $detailSheet->getStyle($col . '1')->getFont()->setBold(true);
            $detailSheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $detailSheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $dRow = 2;
        foreach ($budgetRequests as $cr) {
            foreach ($cr->details as $d) {
                if ($d->status === 'TAKEOUT') continue;
                $detailSheet->setCellValue('A' . $dRow, $cr->code);
                $detailSheet->setCellValue('B' . $dRow, $d->description);
                $detailSheet->setCellValue('C' . $dRow, $d->notes);
                $detailSheet->setCellValue('D' . $dRow, (int) $d->amount);
                $detailSheet->setCellValue('E' . $dRow, $d->qty);
                $detailSheet->setCellValue('F' . $dRow, (int) $d->total);
                $dRow++;
            }
        }

        foreach (['A'=>16,'B'=>40,'C'=>28,'D'=>16,'E'=>8,'F'=>16] as $col => $width) {
            $detailSheet->getColumnDimension($col)->setWidth($width);
        }

        /*
        |----------------------------------------------------------------
        | SHEET 3 — DETAIL REALISASI (satu baris per item realisasi CR)
        |----------------------------------------------------------------
        */
        $realSheet = $spreadsheet->createSheet();
        $realSheet->setTitle('Detail Realisasi');

        $realHeaders = ['Kode', 'Deskripsi', 'Notes', 'Actual Used', 'Qty', 'Total'];
        foreach ($realHeaders as $i => $label) {
            $col = chr(65 + $i);
            $realSheet->setCellValue($col . '1', $label);
            $realSheet->getStyle($col . '1')->getFont()->setBold(true);
            $realSheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $realSheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $rRow = 2;
        foreach ($budgetRequests as $cr) {
            foreach ($cr->realizations as $r) {
                $realSheet->setCellValue('A' . $rRow, $cr->code);
                $realSheet->setCellValue('B' . $rRow, $r->description);
                $realSheet->setCellValue('C' . $rRow, $r->notes);
                $realSheet->setCellValue('D' . $rRow, (int) $r->amount);
                $realSheet->setCellValue('E' . $rRow, $r->qty);
                $realSheet->setCellValue('F' . $rRow, (int) $r->total);
                $rRow++;
            }
        }

        foreach (['A'=>16,'B'=>40,'C'=>28,'D'=>16,'E'=>8,'F'=>16] as $col => $width) {
            $realSheet->getColumnDimension($col)->setWidth($width);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'cost-request-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — DRAFT only
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $cr = $this->findVisibleCr($id, ['attachments', 'details.attachments']);

        if ($cr->status !== 'DRAFT') {
            return back()
                ->with('error', 'Hanya CR berstatus DRAFT yang dapat dihapus.');
        }

        DB::transaction(function () use ($cr) {
            foreach ($cr->attachments as $att) {
                Storage::disk('secure')->delete($att->path);
            }
            foreach ($cr->details as $d) {
                foreach ($d->attachments as $att) {
                    Storage::disk('secure')->delete($att->path);
                }
            }
            $cr->delete();
        });

        return back()
            ->with('success', 'Cost Request berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | TEMPLATE & IMPORT EXCEL — Rincian Pengajuan (create/edit)
    |--------------------------------------------------------------------------
    | Sub item budget dicocokkan berdasarkan teks "{Account} - {Deskripsi}"
    | (sama seperti yang tampil di dropdown Sub Item Budget), bukan ID —
    | supaya template tetap gampang dibaca/diisi manual oleh user.
    |--------------------------------------------------------------------------
    */

    /** Label Sub Item Budget yang dipakai untuk pencocokan template <-> data (stabil, tanpa nilai remaining yang berubah-ubah). */
    private function subItemLabel(\App\Models\BudgetPlanDetail $bd): string
    {
        return trim(($bd->account?->name ?? '-') . ' - ' . ($bd->deskripsi ?? '-'));
    }

    public function downloadDetailTemplate(Request $request)
    {
        $request->validate(['budget_plan_id' => 'required|exists:budget_plans,id']);

        $budgetPlan = BudgetPlan::with('project', 'costCenter')->findOrFail($request->budget_plan_id);
        $subItems   = \App\Models\BudgetPlanDetail::where('budget_plan_id', $budgetPlan->id)
            ->with('account')->orderBy('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rincian Pengajuan');

        $headers = ['Sub Item Budget', 'Deskripsi / Catatan', 'Harga Satuan', 'Qty'];
        foreach ($headers as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $first = $subItems->first();
        $sheet->setCellValue('A2', $first ? $this->subItemLabel($first) : 'ACCOUNT - DESKRIPSI SUB ITEM');
        $sheet->setCellValue('B2', 'Contoh catatan pengajuan');
        $sheet->setCellValue('C2', 100000);
        $sheet->setCellValue('D2', 1);

        foreach (['A' => 45, 'B' => 35, 'C' => 16, 'D' => 8] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Sheet referensi: daftar Sub Item Budget valid untuk dropdown kolom A.
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');
        $refSheet->setCellValue('A1', 'Daftar Sub Item Budget Valid');
        $refSheet->getStyle('A1')->getFont()->setBold(true);
        foreach ($subItems->values() as $i => $bd) {
            $refSheet->setCellValue('A' . ($i + 2), $this->subItemLabel($bd));
        }
        $refSheet->getColumnDimension('A')->setWidth(45);

        $subItemCount = max($subItems->count(), 1);
        for ($row = 2; $row <= 200; $row++) {
            $validation = $sheet->getCell('A' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_WARNING);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(false);
            $validation->setPromptTitle('Pilih Sub Item Budget');
            $validation->setPrompt('Pilih dari daftar di sheet Referensi.');
            $validation->setFormula1("Referensi!\$A\$2:\$A\$" . ($subItemCount + 1));
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template-rincian-cr-' . $budgetPlan->code . '-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * POST /budget-request/import-preview
     * Body: file (xlsx), budget_plan_id
     * Tidak menyimpan apa pun — hanya parsing untuk diisi ulang oleh JS ke
     * tabel Rincian Pengajuan di form create/edit.
     */
    public function importDetailPreview(Request $request)
    {
        $request->validate([
            'file'            => 'required|file|mimes:xlsx,xls|max:5120',
            'budget_plan_id'  => 'required|exists:budget_plans,id',
        ]);

        $subItems = \App\Models\BudgetPlanDetail::where('budget_plan_id', $request->budget_plan_id)
            ->with('account')->get()
            ->keyBy(fn ($bd) => strtoupper($this->subItemLabel($bd)));

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Rincian Pengajuan') ?? $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json([
                'rows' => [], 'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()],
            ], 422);
        }

        $result = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // header

            [$subItemLabel, $notes, $amount, $qty] = array_pad($row, 4, null);

            if (blank($subItemLabel) && blank($notes) && blank($amount) && blank($qty)) continue;

            $rowNum = $i + 1;
            $key    = strtoupper(trim((string) $subItemLabel));
            $bd     = $subItems->get($key);

            if (!$bd) {
                $errors[] = "Baris {$rowNum}: Sub Item Budget \"{$subItemLabel}\" tidak ditemukan di Budget Plan ini.";
                continue;
            }

            $amountNum = is_numeric($amount) ? (int) round((float) $amount) : (int) $this->normalizeMoney((string) $amount);
            $qtyNum    = is_numeric($qty) ? (int) $qty : 1;
            if ($qtyNum <= 0) $qtyNum = 1;

            $result[] = [
                'row'              => $rowNum,
                'budget_detail_id' => $bd->id,
                'sub_item_label'   => $this->subItemLabel($bd),
                'notes'            => $notes,
                'amount'           => $amountNum,
                'qty'              => $qtyNum,
                'total'            => $amountNum * $qtyNum,
            ];
        }

        if (empty($result) && empty($errors)) {
            $errors[] = 'Tidak ada baris data yang terbaca dari file. Pastikan mengisi mulai baris ke-2.';
        }

        return response()->json(['rows' => $result, 'errors' => $errors]);
    }

    /*
    |--------------------------------------------------------------------------
    | TEMPLATE & IMPORT EXCEL — Rincian Settlement (realize.blade)
    |--------------------------------------------------------------------------
    | Template berisi baris item CR apa adanya (tidak bisa tambah/kurang
    | baris — settlement selalu 1 baris per item pengajuan). User cukup edit
    | kolom "Actual Used", lalu import lagi; dicocokkan lewat Request Detail ID.
    |--------------------------------------------------------------------------
    */

    public function downloadSettlementTemplate($id)
    {
        $cr = $this->findVisibleCr($id, ['details']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rincian Settlement');

        $headers = ['No', 'Request Detail ID', 'Description', 'Requested', 'Actual Used'];
        foreach ($headers as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($cr->details->where('status', '!=', 'TAKEOUT')->values() as $i => $d) {
            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValueExplicit('B' . $row, $d->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $row, $d->description);
            $sheet->setCellValue('D' . $row, (int) $d->total);
            $sheet->setCellValue('E' . $row, (int) $d->total);
            $row++;
        }

        // Kolom B ("Request Detail ID") jangan diubah manual — dipakai
        // mencocokkan baris saat file ini di-import kembali.
        $sheet->getComment('B1')->getText()
            ->createTextRun('Jangan ubah kolom ini — dipakai mencocokkan baris saat import.');

        foreach (['A' => 6, 'B' => 16, 'C' => 40, 'D' => 16, 'E' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'template-settlement-' . $cr->code . '-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function importSettlementPreview(Request $request, $id)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);

        $cr = $this->findVisibleCr($id, ['details']);
        $validIds = $cr->details->where('status', '!=', 'TAKEOUT')->pluck('id')->map(fn ($v) => (int) $v);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Rincian Settlement') ?? $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json([
                'rows' => [], 'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()],
            ], 422);
        }

        $result = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // header

            [, $detailId, $desc, , $actualUsed] = array_pad($row, 5, null);
            if (blank($detailId) && blank($actualUsed)) continue;

            $rowNum    = $i + 1;
            $detailId  = (int) $detailId;

            if (!$validIds->contains($detailId)) {
                $errors[] = "Baris {$rowNum}: Request Detail ID \"{$detailId}\" tidak sesuai dengan CR ini (jangan ubah kolom ini — hanya kolom Actual Used).";
                continue;
            }

            $amountNum = is_numeric($actualUsed) ? (int) round((float) $actualUsed) : (int) $this->normalizeMoney((string) $actualUsed);

            $result[] = [
                'row'               => $rowNum,
                'request_detail_id' => $detailId,
                'description'       => $desc,
                'amount'            => $amountNum,
            ];
        }

        if (empty($result) && empty($errors)) {
            $errors[] = 'Tidak ada baris data yang terbaca dari file. Pastikan tidak mengubah struktur kolom template.';
        }

        return response()->json(['rows' => $result, 'errors' => $errors]);
    }
}