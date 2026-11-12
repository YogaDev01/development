<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\BudgetRequestRealizationAttachment;
use App\Models\RequestDetail;
use App\Models\RequestLogNote;
use App\Models\RequestAttachment;
use App\Models\RequestType;
use App\Models\BudgetPlan;
use App\Models\Cluster;
use App\Models\Beneficiary;
use App\Models\Account;
use App\Models\DailyTransactionItem;
use App\Models\CostCenter;
use App\Models\TroubleTicket;
use App\Models\User;
use App\Services\NotifyService;
use App\Services\RoleDirectory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class BudgetRequestController extends Controller
{
    use \App\Http\Controllers\Concerns\BuildsCrWorklist;
    use \App\Http\Controllers\Concerns\TransitionsStatus;
    // Visibilitas Budget Plan (scopeVisibleTo) — dipakai checkNob (temuan F-4).
    use \App\Http\Controllers\Concerns\ScopesBudgetPlanVisibility;
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

        if ($query->exists()) {
            return true;
        }

        /* Teknisi tidak punya access group proyek, jadi scopeByRole() tidak
           pernah meloloskan CR apa pun untuknya. Satu kekecualian sempit
           (20 Okt 2026): CR yang lahir dari plan visit TIKET YANG DITUGASKAN
           KEPADANYA — itulah yang ia settle dari halaman tiketnya. */
        return $this->isTicketTechnicianOfCr(Auth::user(), $cr);
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
        return \App\Support\CashAdvanceQuota::usage($beneficiaryId, $excludeCrId);
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
        return \App\Support\CashAdvanceQuota::error($beneficiaryId, $amount, $excludeCrId);
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

        // Sub-item yang dipakai form dimuat sekali, lalu "Used"-nya (dasar
        // NOB per baris di bawah) di-prime sekaligus — bukan satu set query
        // costLines() per baris.
        $bdIds = collect($rows)->pluck('budget_detail_id')->filter()->map(fn ($i) => (int) $i)->unique()->values();
        $subItems = $bdIds->isNotEmpty()
            ? \App\Models\BudgetPlanDetail::with('account')->whereIn('id', $bdIds)->get()->keyBy('id')
            : collect();
        \App\Models\BudgetPlanDetail::primeCostLines($subItems);

        foreach ($rows as $key => $row) {
            $amount = $this->normalizeMoney($row['amount'] ?? null);
            $qty    = (int) ($row['qty'] ?? 1);
            $total  = $amount * $qty;

            $budgetDetailId = $row['budget_detail_id'] ?? null;
            if (!$amount && blank($budgetDetailId)) continue;

            $budgetDetail = $budgetDetailId
                ? $subItems->get((int) $budgetDetailId)
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

    /**
     * Label satu baris rincian CR untuk tampilan: nama Chart of Account dibuang
     * dari depan deskripsi (karena sudah jadi judul grup), lalu catatan barisnya
     * ditempelkan - mis. "Tim penarikan kabel — REJECT".
     */
    /**
     * Buang AWALAN label (nama akun, atau Sub COA/deskripsi budget detail) dari
     * sebuah teks deskripsi, kalau teksnya memang diawali label itu diikuti
     * "-" / em dash — pola yang dulu dipakai saat input manual CR ("Akun -
     * detail sebenarnya"). Dicoba case-INSENSITIVE dan satu per satu sesuai
     * urutan $labels; berhenti begitu satu label cocok.
     *
     * Tanpa ini, baris yang catatannya kosong dan deskripsinya hanya mengulang
     * nama label sendiri (mis. "Dana Tidak Terduga - Dana Tidak Terduga")
     * tampil berulang dua kali di layar — sekali sebagai judul grup/Sub COA,
     * sekali lagi sebagai "teks" di baris Request/Settle.
     */
    private function stripLeadingLabel(string $desc, ?string ...$labels): string
    {
        $desc = trim($desc);

        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label === '') continue;

            $stripped = preg_replace('/^' . preg_quote($label, '/') . '\s*[-\x{2013}\x{2014}]\s*/iu', '', $desc);
            if ($stripped !== null && $stripped !== $desc) {
                $desc = trim($stripped);
                break;
            }
        }

        /*
         | Sisa teks yang PERSIS sama dengan salah satu label (akun/Sub COA)
         | berarti deskripsinya memang cuma mengulang nama itu sendiri —
         | tidak ada keterangan nyata di baliknya. Kembalikan string kosong
         | supaya pemanggil tidak menampilkannya lagi (informasi CoA/Sub COA
         | sudah ada di judul grup/header, tidak perlu diulang di deskripsi).
         */
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label !== '' && mb_strtolower($desc) === mb_strtolower($label)) {
                return '';
            }
        }

        return $desc;
    }

    private function itemLabel(RequestDetail $detail): string
    {
        $akun = trim((string) ($detail->account?->name ?? ''));
        $subCoa = trim((string) ($detail->budgetDetail?->deskripsi ?? ''));

        // Pemisah yang dulu dipakai saat input manual bisa berbasis nama akun
        // ATAU Sub COA — dicoba keduanya, bukan cuma akun. Sisa yang ternyata
        // cuma mengulang salah satu nama itu (tanpa keterangan nyata) ikut
        // dibuang oleh stripLeadingLabel() — fallback-nya "-", BUKAN nama akun,
        // supaya tidak mengulang informasi yang sudah ada di judul grup/header.
        $desc = $this->stripLeadingLabel((string) $detail->description, $akun, $subCoa);
        $note = trim((string) $detail->notes);

        if ($desc === '') {
            return $note !== '' ? $note : '-';
        }

        return $note !== '' ? $desc . ' — ' . $note : $desc;
    }

    /**
     * Sub COA satu baris rincian untuk tampilan: deskripsi tanpa nama akun
     * (judul grup) DAN tanpa catatan barisnya — catatan ditampilkan terpisah.
     * Dipakai saat baris tidak tertaut budget plan detail (budgetDetail null).
     */
    private function subCoaLabel(RequestDetail $detail): string
    {
        $akun = trim((string) ($detail->account?->name ?? ''));
        $desc = $this->stripLeadingLabel((string) $detail->description, $akun);

        return $desc !== '' ? $desc : ($akun !== '' ? $akun : '-');
    }

    /**
     * Keterangan rincian PENGAJUAN untuk baris "Request:" di tab Settlement —
     * teks bebas yang ditulis pengaju, BUKAN kolom description-nya.
     *
     * description auto-terisi "{Akun} — {Sub COA}" saat baris tertaut budget
     * detail (lihat syncDetails()); itu cuma mengulang info yang sudah ada di
     * judul grup/item, jadi notes-lah yang dipakai duluan kalau ada. description
     * tetap jadi fallback (habis dibuang awalan labelnya) untuk baris lama yang
     * tidak tertaut budget detail, di mana ia memang bisa memuat teks bebas.
     */
    private function requestNoteLabel(?RequestDetail $rd, ?string $rawDescriptionFallback): string
    {
        if ($rd) {
            $note = trim((string) $rd->notes);
            if ($note !== '') return $note;

            $desc = $this->stripLeadingLabel(
                (string) $rd->description,
                $rd->account?->name,
                $rd->budgetDetail?->deskripsi
            );
            return $desc !== '' ? $desc : '-';
        }

        return $this->stripLeadingLabel((string) $rawDescriptionFallback) ?: '-';
    }

    /**
     * Baris form settlement yang kosong (Settle 0 DAN tanpa item asal) —
     * dilewati saat menyimpan maupun memvalidasi, sama seperti sebelumnya.
     */
    private function isEmptyRealizationRow(array $row): bool
    {
        return ! $this->normalizeMoney($row['amount'] ?? null) && blank($row['request_detail_id'] ?? null);
    }

    /**
     * Pasangkan tiap baris form settlement dengan baris realisasi yang SUDAH
     * tersimpan (Settlement Custom COA, 25 Okt 2026):
     *   1. lewat realizations[i][id] — hanya id milik CR ini yang diakui;
     *   2. HANYA untuk form lama yang belum mengirim id sama sekali (tidak
     *      satu baris pun membawa kunci `id`, mis. tab peramban yang terbuka
     *      sebelum rilis): jatuh ke baris pertama yang belum terpakai dengan
     *      request_detail_id yang sama — persis cara lampiran dulu dipetakan.
     *      Form baru selalu mengirim kunci `id` (kosong untuk baris baru),
     *      jadi baris hasil Pecah/import tidak pernah mengambil alih id baris
     *      yang dihapus user beserta lampiran & e-sign-nya.
     * Baris tanpa pasangan = baris baru.
     *
     * @return array<int|string, BudgetRequestRealization|null> indeks form -> baris tersimpan
     */
    private function matchExistingRealizations(array $rows, \Illuminate\Support\Collection $existing): array
    {
        $byId     = $existing->keyBy('id');
        $claimed  = [];
        $map      = [];
        $formLama = collect($rows)->every(fn ($r) => ! array_key_exists('id', (array) $r));

        foreach ($rows as $i => $row) {
            $row = (array) $row;
            $id  = (int) ($row['id'] ?? 0);
            if ($id && $byId->has($id) && ! isset($claimed[$id])) {
                $map[$i]      = $byId->get($id);
                $claimed[$id] = true;
            }
        }

        foreach ($rows as $i => $row) {
            if (array_key_exists($i, $map)) {
                continue;
            }

            $row      = (array) $row;
            $detailId = (int) ($row['request_detail_id'] ?? 0);
            $hit      = null;

            if ($formLama && $detailId) {
                $hit = $existing->first(fn ($r) => ! isset($claimed[$r->id]) && (int) $r->request_detail_id === $detailId);
            }

            if ($hit) {
                $claimed[$hit->id] = true;
            }

            $map[$i] = $hit;
        }

        return $map;
    }

    /**
     * Simpan baris settlement (Settlement Custom COA, 25 Okt 2026).
     *
     * Baris di-UPSERT per realizations[i][id] (pola syncDetails()), bukan
     * dibuat ulang tiap submit. Akibatnya lampiran per baris tetap menempel
     * pada barisnya sendiri walau settlement ditolak lalu diajukan ulang, dan
     * walau satu item dipecah jadi beberapa baris — tidak perlu lagi
     * memindah lampiran lewat request_detail_id.
     *
     * Tiap baris boleh memilih Sub COA (sub-item Budget Plan CR ini, sudah
     * divalidasi di validateRealizationInput()). account_id & description
     * diturunkan dari sub-item itu ("{Akun} — {Sub COA}", sama seperti
     * syncDetails()). Baris tanpa Sub COA terpilih memakai sub-item item
     * asalnya; item asal lama yang tidak tertaut sub-item tetap memakai
     * akun & deskripsi item asal seperti sebelumnya.
     *
     * Yang dihapus: lampiran yang dicentang hapus (delete_realization_attachments[])
     * dan baris tersimpan yang tidak dikirim lagi (beserta berkas lampirannya,
     * sama seperti perilaku sebelumnya untuk item yang tidak disubmit ulang).
     *
     * @return array<int|string, BudgetRequestRealization> indeks form -> baris tersimpan
     */
    private function syncRealizations(BudgetRequest $cr, Request $request): array
    {
        $hapusIds = array_map('strval', array_filter((array) $request->input('delete_realization_attachments', [])));
        $rows     = (array) $request->input('realizations', []);
        $existing = $cr->realizations()->with('attachments')->get();
        $match    = $this->matchExistingRealizations($rows, $existing);
        $details  = $cr->details()->get()->keyBy('id');
        $subItems = $cr->budget_plan_id
            ? \App\Models\BudgetPlanDetail::where('budget_plan_id', $cr->budget_plan_id)->with('account')->get()->keyBy('id')
            : collect();
        $hasBdCol = BudgetRequestRealization::hasBudgetDetailColumn();

        // Lampiran yang dicentang hapus — hanya yang memang milik baris CR ini.
        foreach ($existing as $old) {
            foreach ($old->attachments as $att) {
                if (in_array((string) $att->id, $hapusIds, true)) {
                    Storage::disk('secure')->delete($att->path);
                    $att->delete();
                }
            }
        }

        $kept = [];
        $map  = [];

        foreach ($rows as $i => $row) {
            $row = (array) $row;
            if ($this->isEmptyRealizationRow($row)) continue;

            $amount   = $this->normalizeMoney($row['amount'] ?? null);
            $detailId = (int) ($row['request_detail_id'] ?? 0);
            $detail   = $detailId ? $details->get($detailId) : null;

            $bdId = (int) ($row['budget_detail_id'] ?? 0)
                ?: ($detail?->budget_detail_id ? (int) $detail->budget_detail_id : 0);
            $bd   = $bdId ? $subItems->get($bdId) : null;

            $qty     = max(1, (int) ($row['qty'] ?? 1));
            $payload = [
                'request_detail_id' => $detail->id ?? null,
                'description'       => $bd
                    ? trim(($bd->account?->name ?? '-') . ($bd->deskripsi ? ' — ' . $bd->deskripsi : ''))
                    : ($detail->description ?? null),
                'account_id'        => $bd ? $bd->account_id : ($detail->account_id ?? null),
                // Kolom Harga (amount) x Qty = Settle (total). Form tiket tanpa qty = 1,
                // jadi total = amount seperti sebelumnya.
                'amount'            => $amount,
                'qty'               => $qty,
                'total'             => $amount * $qty,
                'notes'             => $row['notes'] ?? null,
                'change_note'       => $row['change_note'] ?? null,
            ];

            if ($hasBdCol) {
                $payload['budget_detail_id'] = $bd->id ?? null;
            }

            // Hanya dikirim form tiket; form Cost Request tidak menimpanya.
            if (BudgetRequestRealization::hasTransactionColumns() && array_key_exists('transaction_date', $row)) {
                $payload['transaction_date'] = ($row['transaction_date'] ?? null) ?: null;
                $payload['vendor_name']      = trim((string) ($row['vendor_name'] ?? '')) ?: null;
            }

            $realization = $match[$i] ?? null;
            if ($realization) {
                $realization->update($payload);
            } else {
                $realization = $cr->realizations()->create($payload);
            }

            $kept[]  = $realization->id;
            $map[$i] = $realization;

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

        // Baris yang tidak dikirim lagi: berkasnya dibuang, barisnya dihapus
        // (FK realization_detail_attachments ON DELETE CASCADE ikut menghapus
        // baris lampirannya). Tanda tangan penerima dana baris itu
        // ("ttd-r<id>", lampiran global CR) ikut dibuang — tidak ada lagi
        // baris yang memilikinya.
        $dibuang = [];
        foreach ($existing as $old) {
            if (in_array($old->id, $kept, true)) continue;

            foreach ($old->attachments as $att) {
                if (! in_array((string) $att->id, $hapusIds, true)) {
                    Storage::disk('secure')->delete($att->path);
                }
            }
            $dibuang[] = $this->signatureName($cr, $old->id);
            $old->delete();
        }

        if ($dibuang) {
            $cr->realizationAttachments()->whereIn('original_name', $dibuang)->get()
                ->each(function ($att) {
                    Storage::disk('secure')->delete($att->path);
                    $att->delete();
                });
        }

        // Relasi & cache costUnits() yang mungkin sudah termuat pada instance
        // ini sudah basi.
        $cr->unsetRelation('realizations');
        $cr->forgetCostUnits();

        return $map;
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

        // Sama dengan daftar PO & CR: Access Group individu ∪ team ACTIVE.
        $projectIds = $user->accessibleProjectIds();

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
    | Rumus NOB (isNob / nobOverage) dan pemeriksaan kepemilikan sub-item
    | (ownsDetails) kini ada di model BudgetPlan, satu sumber dengan pengajuan
    | Trouble Ticket.
    */

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    /** Batas baris yang dirender ke satu tab (DataTables memproses semua baris di browser). */
    private const TAB_MAX_ROWS = 1000;

    /**
     * @return array{0:string,1:string} [dateFrom, dateTo] — default 12 bulan terakhir.
     *
     * Default sebelumnya awal bulan s/d hari ini, sehingga tiap tanggal 1 daftar
     * CR tampak kosong: semua pengajuan bulan-bulan sebelumnya jatuh di luar rentang.
     */
    private function crDateRange(Request $request): array
    {
        /*
         | Deep-link dari Summary Task (?all=1): tampilkan SELURUH riwayat tanpa
         | batas bulan, supaya jumlah yang diklik di Summary benar-benar muncul.
         | Rentangnya dibuat mengikuti data NYATA — dari CR terawal yang boleh
         | dilihat user s/d hari ini — bukan tanggal ekstrem (2000–2100) yang
         | tampak janggal di datepicker.
         */
        if ($request->boolean('all') && ! $request->filled('date_from') && ! $request->filled('date_to')) {
            $q = BudgetRequest::query();
            $this->scopeByRole($q);
            $earliest = $q->min('created_at');

            return [
                $earliest ? substr($earliest, 0, 10) : now()->startOfMonth()->format('Y-m-d'),
                now()->format('Y-m-d'),
            ];
        }

        return [
            $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->format('Y-m-d'),
            $request->filled('date_to')   ? $request->date_to   : now()->endOfMonth()->format('Y-m-d'),
        ];
    }

    /**
     * Query dasar daftar CR: rentang tanggal + scoping role. Rentang tanggal
     * memakai perbandingan langsung pada kolom, bukan whereDate() — fungsi di
     * sisi kolom membuat MySQL tidak bisa memakai indeks (scan seluruh tabel).
     */
    private function crRangeStart(string $value): string
    {
        return strlen($value) > 10 ? substr($value, 0, 19) : $value . ' 00:00:00';
    }

    private function crRangeEnd(string $value): string
    {
        return strlen($value) > 10 ? substr($value, 0, 19) : $value . ' 23:59:59';
    }

    private function crBaseQuery(string $dateFrom, string $dateTo)
    {
        $query = BudgetRequest::query()
            ->where('created_at', '>=', $this->crRangeStart($dateFrom))
            ->where('created_at', '<=', $this->crRangeEnd($dateTo));

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
     * Halaman index hanya memuat KERANGKA satu tabel gabungan (semua tipe CR)
     * + angka stepper awal. Baris tabel TIDAK dirender di sini — dimuat lewat
     * tab('all') setelah halaman tampil, supaya halaman awal ringan. Angka
     * stepper berikutnya dihitung ulang di browser mengikuti filter aktif.
     */
    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->crDateRange($request);

        $counts = $this->crStatusCounts($this->crBaseQuery($dateFrom, $dateTo));

        return view('budget-request.index', [
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'counts'   => $counts,
        ]);
    }

    /**
     * Isi tabel gabungan (HTML fragment) — dipanggil AJAX. Dulu ada tiga tab
     * (ca / cp / db); sekarang satu tabel 'all' dengan kolom & filter Tipe.
     */
    public function tab(Request $request, string $tab)
    {
        abort_unless($tab === 'all', 404);

        [$dateFrom, $dateTo] = $this->crDateRange($request);
        $query = $this->crBaseQuery($dateFrom, $dateTo);

        $total     = (clone $query)->count();
        $maxRows   = self::TAB_MAX_ROWS;
        $truncated = $total > $maxRows;

        // 'realizations' untuk Settlement/Refund/Expense Claim/Cost (CR non-CA
        // tidak punya baris realisasi, jadi muatannya kosong).
        $query->with(['type', 'budgetPlan.project.manager', 'applicant', 'details', 'beneficiary.pic', 'realizations'])
            // Close Date = kapan Second Payment (pembayaran ke-2) dibayar.
            ->withMax(['secondPayments as second_paid_at' => fn ($q) => $q->where('status', 'PAID')], 'paid_at');

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
            'details.*.notes'            => 'required|string|max:255',
            'details.*.amount'           => 'required',
            'details.*.qty'              => 'required|integer|min:1',
            'details.*.attachments'      => 'nullable|array',
            'details.*.attachments.*'    => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        if ($msg = $this->attachmentRuleError($request, (int) $request->type_id)) {
            return back()->withInput()->with('error', $msg);
        }

        if ($this->typeIsCashAdvance((int) $request->type_id)) {
            $beneficiary = Beneficiary::find($request->beneficiary_id);
            if ($beneficiary && $beneficiary->category !== 'INTERNAL') {
                return back()->withInput()->with('error', 'Cash Advance hanya untuk beneficiary kategori Internal.');
            }

            // Limit Cash Advance per PIC (lewat beneficiary) — blokir kalau habis.
            $msg = $this->cashAdvanceQuotaError(
                (int) $request->beneficiary_id,
                $this->requestedDetailTotal($request)
            );
            if ($msg) return back()->withInput()->with('error', $msg);
        }

        $budgetPlan = BudgetPlan::with(['project', 'costCenter'])->findOrFail($request->budget_plan_id);

        if (!$budgetPlan->ownsDetails(collect((array) $request->input('details', []))->pluck('budget_detail_id'))) {
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
        $isNob     = $budgetPlan->isNob($totalAmount);
        $nobAmount = $budgetPlan->nobOverage($totalAmount);
        $description = strtoupper(trim($request->description));

        /*
        | Kode CR: {prefix tipe}-{MM}{YY}{sumber(4)}{seq(4)}
        |   - prefix: CA/PA/PC/PR/RB menurut Tipe Request, selain itu CR
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

        $code = BudgetRequest::generateCode($sourceCode, (int) $request->type_id);

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

    /**
     * Catatan tab Log dalam bentuk yang sama dengan baris riwayat status.
     * Tabelnya baru; bila migrasinya belum jalan di server, detail CR tetap
     * harus terbuka — catatan saja yang kosong.
     */
    private function logNotesFor(BudgetRequest $cr)
    {
        try {
            $notes   = RequestLogNote::where('budget_request_id', $cr->id)->get();
            $avatars = \App\Models\User::avatarUrlMap($notes->pluck('acted_by'));

            return $notes->map(fn ($n) => [
                'avatar'       => $avatars[$n->acted_by] ?? null,
                'kind'         => 'note',
                'action_type'  => 'note',
                'action_label' => 'Catatan',
                'action_color' => 'secondary',
                'status_from'  => null,
                'status_to'    => null,
                'actor'        => $n->actor_name ?? 'System',
                'user_id'      => $n->acted_by,
                'role'         => $n->actor_role,
                'notes'        => $n->notes,
                'date'         => $n->created_at->format('d M Y H:i'),
                'ts'           => $n->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Kirim catatan ke tab Log (gaya chat). Siapa pun yang boleh melihat CR
     * boleh menulis; ditutup begitu CR berstatus CLOSED.
     */
    public function storeLogNote(Request $request, $id)
    {
        $cr = $this->findVisibleCr($id);

        $data = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        if ($cr->status === 'CLOSED') {
            return response()->json(['message' => 'Cost Request sudah Closed; catatan baru tidak dapat ditambahkan.'], 422);
        }

        $user = Auth::user();
        $note = RequestLogNote::create([
            'budget_request_id' => $cr->id,
            'acted_by'          => $user?->id,
            'actor_name'        => $user?->name ?? 'System',
            'actor_role'        => $user?->role?->name,
            'notes'             => trim($data['notes']),
        ]);

        return response()->json([
            'ok'  => true,
            'log' => [
                'avatar'       => $user?->avatar_url,
                'kind'         => 'note',
                'action_type'  => 'note',
                'action_label' => 'Catatan',
                'action_color' => 'secondary',
                'status_from'  => null,
                'status_to'    => null,
                'actor'        => $note->actor_name,
                'user_id'      => $note->acted_by,
                'role'         => $note->actor_role,
                'notes'        => $note->notes,
                'date'         => now()->format('d M Y H:i'),
                'ts'           => now()->toIso8601String(),
            ],
        ]);
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
            'applicant', 'beneficiary.bank', 'beneficiary.pic',
            'details.account', 'details.budgetDetail', 'details.attachments',
            'attachments',
            'realizations.account', 'realizations.requestDetail.account', 'realizations.requestDetail.budgetDetail', 'realizations.attachments',
            'realizations.budgetDetail.account', 'details.budgetDetail.account',
            'realizationAttachments',
            'secondPayments.dailyTransactionItems.dailyTransaction',
            'statusLogs',
        ])->findOrFail($id);

        /*
         | Jalur BACA-SAJA (keputusan 2026-11-06, K7): pelaku yang pernah
         | menindak CR ini (punya baris log status atas namanya) tetap boleh
         | membuka detailnya walau sudah pindah team / kehilangan akses
         | proyek. Aksi apa pun tetap lewat findVisibleCr() — tidak berubah —
         | jadi di sini semua aksi dimatikan dan respons ditandai read_only.
         */
        $bolehLihat = $this->canViewCr($cr);
        $hanyaBaca  = ! $bolehLihat && $cr->wasActedOnBy(Auth::user());
        abort_unless($bolehLihat || $hanyaBaca, 403);

        $bp = $cr->budgetPlan;
        $logAvatars = \App\Models\User::avatarUrlMap($cr->statusLogs->pluck('acted_by'));

        /*
         | Dengan ?with_budget=1 sub-item yang sama termuat dua kali (lewat
         | details.budgetDetail dan budgetPlan.details). Rincian CR diarahkan ke
         | instance milik Budget Plan yang di-prime sekali, supaya costLines()
         | tidak dimuat dua set untuk sub-item yang sama.
         */
        if ($bp && $denganBudget) {
            \App\Models\BudgetPlanDetail::primeCostLines($bp->details);
            $subItemById = $bp->details->keyBy('id');
            foreach ($cr->details as $d) {
                if ($d->budget_detail_id && $subItemById->has($d->budget_detail_id)) {
                    $d->setRelation('budgetDetail', $subItemById->get($d->budget_detail_id));
                }
            }
        }

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
            // Tiket asal CR ini, bila ada; tautannya hanya untuk yang boleh membuka tiket.
            'ticket'           => $this->ticketOfCr($cr),
            // Apakah user yang login boleh takeout/restore item pada tahap CR ini.
            'can_moderate_items' => ! $hanyaBaca && $cr->canModerateItems(Auth::user()),
            // true = dibuka lewat jalur "pernah ditindak" (tanpa aksi apa pun).
            'read_only'          => $hanyaBaca,
            /*
             | Aksi workflow yang BOLEH dilakukan user ini pada CR ini sekarang.
             | Dihitung di server, bukan ditebak di JavaScript: gerbang role &
             | status yang sama persis dengan method aksinya masing-masing —
             | kalau daftar ini dibangun di layar, cepat atau lambat tombolnya
             | muncul untuk orang yang lalu ditolak 403 saat menekannya.
             */
            'workflow_actions' => $hanyaBaca ? [] : $this->workflowActions($cr, Auth::user()),
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
            // Scope of Work untuk baris "Reference": nama Project kalau budget
            // berbasis Project/PO, selain itu deskripsi Budget Plan (budget
            // Cost Center tidak punya Project). Kodenya ada di code_reference.
            'reference_scope'  => $bp
                ? ($bp->isProjectBudget()
                    ? ($bp->project?->name ?? '-')
                    : ($bp->description ?? '-'))
                : '-',
            'applicant'        => $cr->applicant?->name ?? '-',
            'beneficiary'      => $cr->beneficiary?->complete_name ?? '-',
            // Penerima yang ditampilkan di layar detail = PIC pemegang
            // beneficiary (Master PIC). Kalau beneficiary belum punya PIC,
            // jatuh ke nama lengkap beneficiary-nya sendiri supaya tidak kosong.
            'pic'              => $cr->beneficiary?->pic?->pic
                                    ?: ($cr->beneficiary?->complete_name ?? '-'),
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

            /*
             | Riwayat pengajuan PIC yang sama (satu PIC bisa punya banyak
             | beneficiary) di PO yang sama — dipakai panel Info di Todo List
             | supaya approver melihat apa saja yang pernah cair ke PIC ini,
             | disusun jadi pohon COA → Sub COA → item di layar. Sumber baris
             | per CR: Cash Advance dari settlement (fallback pengajuan), non
             | Cash Advance dari pengajuan. Lihat picHistory().
             */
            'pic_history' => $this->picHistory($cr),

            // "Used" sub-item semua baris dimuat sekaligus (BudgetPlanDetail::primeCostLines()).
            'details' => tap($cr->details, fn ($all) => \App\Models\BudgetPlanDetail::primeCostLines($all->pluck('budgetDetail')->filter()))->map(function ($d) {
                $bd        = $d->budgetDetail;
                $budget    = $bd->total ?? 0;
                $used      = $bd?->usedAmount() ?? 0;
                $remaining = $bd?->remainingAmount() ?? 0;

                return [
                    'id'                  => $d->id,
                    'budget_detail_id'    => $d->budget_detail_id,
                    'description'         => $d->description,
                    'account'             => $d->account?->name ?? '-',
                    // Label baris untuk tampilan: nama akun dibuang (sudah jadi
                    // judul grup) lalu digabung dengan catatan barisnya.
                    'item_label'          => $this->itemLabel($d),
                    // Sub COA = deskripsi baris Budget Plan (tanpa nama akun);
                    // dipakai sebagai judul tebal item di drawer. Catatan baris
                    // (notes) tampil sebagai deskripsi di bawahnya.
                    'sub_coa'             => $bd?->deskripsi ?: $this->subCoaLabel($d),
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

            'realizations' => $cr->realizations->map(function ($r) {
                // Sub COA yang BERLAKU (Settlement Custom COA, 25 Okt 2026):
                // pilihan baris settlement, atau sub-item item asalnya untuk
                // baris lama (budget_detail_id NULL).
                $rd       = $r->requestDetail;
                $efektif  = $r->budgetDetail ?? $rd?->budgetDetail;
                $isAdded  = ! $r->request_detail_id;
                $isMoved  = ! $isAdded && $r->effectiveBudgetDetailId($rd)
                    !== ($rd?->budget_detail_id ? (int) $rd->budget_detail_id : null);

                return [
                    'id'                => $r->id,
                    'request_detail_id' => $r->request_detail_id,
                    'budget_detail_id'  => $r->effectiveBudgetDetailId($rd),
                    'is_added'          => $isAdded,
                    'is_moved'          => $isMoved,
                    // Sub COA item asal — ditampilkan sebagai "Pindah dari: ...".
                    'origin_label'      => $isMoved
                        ? ($rd?->budgetDetail?->deskripsi ?: ($rd ? $this->subCoaLabel($rd) : null))
                        : null,
                    'cr_item'           => $r->requestDetail?->description ?? ($r->description ?? '-'),
                    // Keterangan yang diketik pengaju di form Cost Request. Kolom
                    // DESKRIPSI rincian ($rd->description) auto-terisi "{Akun} —
                    // {Sub COA}" begitu baris tertaut budget detail (lihat
                    // syncDetails()) — BUKAN teks bebas, jadi tidak dipakai di
                    // sini kalau ada catatan ($rd->notes), persis seperti yang
                    // ditampilkan tab Request untuk baris yang sama. Kalaupun
                    // deskripsinya dipakai (baris lama tanpa budget detail),
                    // awalan "{Akun} - " / "{Sub COA} - " tetap dibuang — info itu
                    // sudah ada di judul grup & judul tebal item di atasnya.
                    'des_request'       => $isAdded ? '-' : $this->requestNoteLabel($rd, $r->description),
                    // Sub COA (deskripsi budget plan detail dari baris pengajuan yang
                    // ditautkan) — judul tebal item di tab Settlement drawer.
                    'sub_coa'           => $efektif?->deskripsi
                                            ?: ($rd ? $this->subCoaLabel($rd) : null),
                    // Teks baris "Settle:" di drawer — isi kolom "Description Settle"
                    // di form Submit Settlement (disimpan ke kolom notes), dengan
                    // awalan COA/Sub COA dibuang dengan alasan yang sama.
                    'des_settle'        => $this->stripLeadingLabel(
                                                (string) ($r->notes ?: ''),
                                                $r->account?->name,
                                                $efektif?->deskripsi
                                            ) ?: '-',
                    // Nilai Request ITEM ASAL — satu item bisa punya beberapa baris
                    // settlement, jadi angka ini JANGAN dijumlahkan per baris
                    // (pakai settlement_comparison untuk total per COA / Sub COA).
                    'total_cr'          => $rd?->total ?? 0,
                    'total_cr_formatted'=> number_format($rd?->total ?? 0, 0, ',', '.'),
                    'actual'            => $r->total,
                    'actual_formatted'  => number_format($r->total, 0, ',', '.'),
                    'notes'             => $r->notes,
                    // Alasan perubahan dari Description Request ke Description Settle.
                    'change_note'       => $r->change_note,
                    // Kolom lama dipertahankan agar kompatibel bila ada kode lain yang masih memakainya
                    'description'       => $r->description,
                    'account'           => $r->account?->name ?? ($efektif?->account?->name ?? '-'),
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
                ];
            })->values(),
            // Perbandingan Request vs Settlement per (Akun, Sub COA), dihitung
            // di server supaya layar tidak menjumlah total_cr per baris (yang
            // berlipat bila satu item dipecah jadi beberapa baris settlement).
            'settlement_comparison'       => $this->settlementComparison($cr),
            'total_realization'           => $cr->total_realization,
            'total_realization_formatted' => $cr->total_realization_formatted,

            // batch_code: Second Payment dibayar lewat batch Treasury, jadi
            // statusnya tidak cukup PENDING/PAID — pengguna perlu tahu apakah
            // ia sudah diambil Treasury atau masih menunggu dimasukkan batch.
            'second_payments' => $cr->secondPayments->map(function ($p) {
                $activeItem = $p->dailyTransactionItems
                    ->first(fn ($i) => $i->status === 'INCLUDED'
                        && in_array($i->dailyTransaction?->status, array_merge(\App\Models\DailyTransaction::ACTIVE_STATUSES, ['PAID'])));

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

            /*
             | Riwayat status + catatan bebas (request_log_notes), diurutkan
             | terbaru-dulu seperti sebelumnya. 'ts' (ISO 8601) dipakai layar
             | untuk menyusun tanggal tiap tahap dan menghitung total waktu;
             | 'action_type' untuk membedakan 'paid' dari transisi lain.
             */
            'logs' => $cr->statusLogs->map(fn($l) => [
                'avatar'       => $logAvatars[$l->acted_by] ?? null,
                'kind'         => 'status',
                'action_type'  => $l->action_type,
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
                'ts'           => $l->created_at->toIso8601String(),
            ])->concat($this->logNotesFor($cr))
              ->sortByDesc('ts')->values(),
            'can_post_log_note' => ! $hanyaBaca && $cr->status !== 'CLOSED',

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
                'details' => tap($bp->details, fn ($all) => \App\Models\BudgetPlanDetail::primeCostLines($all))->map(function ($d) {
                    $used      = $d->usedAmount();
                    $remaining = $d->remainingAmount();

                    // Riwayat CR per sub-item dari sumber yang sama dengan
                    // "Used" (BudgetPlanDetail::costLines(), per baris settlement
                    // untuk Cash Advance yang settlement-nya sudah diajukan).
                    $crDetails = $d->costLines()
                        ->map(function ($u) {
                            $cr   = $u['budget_request'];
                            $show = \App\Models\BudgetPlanDetail::costLineDisplay($u);
                            return [
                                'cr_id'                => $cr->id,
                                'cr_date_formatted'    => $cr->created_at?->format('d M Y') ?? '-',
                                'beneficiary'          => $cr->beneficiary?->nickname ?? '-',
                                'notes'                => $show['notes'],
                                'unit_price_formatted' => number_format($show['unit_price'], 0, ',', '.'),
                                'qty'                  => $show['qty'],
                                'amount'               => $show['amount'],
                                'amount_formatted'     => number_format($show['amount'], 0, ',', '.'),
                            ];
                        })
                        ->values();

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

    /**
     * Perbandingan Request vs Settlement per (Akun, Sub COA) untuk drawer CR
     * & Todo List (Settlement Custom COA, 25 Okt 2026).
     *
     *   Request    = jumlah item pengajuan (bukan TAKEOUT) di Sub COA itu —
     *                totalnya sama dengan amount CR, tidak terhitung ganda
     *                walau item dipecah jadi beberapa baris settlement;
     *   Settlement = jumlah baris settlement yang Sub COA efektifnya itu —
     *                totalnya sama dengan total_realization;
     *   Variance   = Request - Settlement (positif = sisa/refund).
     *
     * Butuh relasi details.budgetDetail.account, details.account,
     * realizations.budgetDetail.account, realizations.requestDetail.budgetDetail
     * (sudah dimuat showJson()).
     */
    private function settlementComparison(BudgetRequest $cr): array
    {
        $baris = [];

        $kunci = function (?\App\Models\BudgetPlanDetail $bd, ?string $akun, ?string $cadangan) {
            return $bd
                ? ['key' => 'bd:' . $bd->id, 'account' => $bd->account?->name ?? ($akun ?: '-'), 'sub_coa' => $bd->deskripsi ?: '-']
                : ['key' => 'acc:' . ($akun ?: '-') . '|' . ($cadangan ?: '-'), 'account' => $akun ?: '-', 'sub_coa' => $cadangan ?: '-'];
        };

        $tambah = function (array $k, int $request, int $settle) use (&$baris) {
            $baris[$k['key']] ??= ['account' => $k['account'], 'sub_coa' => $k['sub_coa'], 'request' => 0, 'settlement' => 0];
            $baris[$k['key']]['request']    += $request;
            $baris[$k['key']]['settlement'] += $settle;
        };

        foreach ($cr->details as $d) {
            if ($d->isTakenOut()) continue;
            $tambah($kunci($d->budgetDetail, $d->account?->name, $this->subCoaLabel($d)), (int) $d->total, 0);
        }

        foreach ($cr->realizations as $r) {
            $rd = $r->requestDetail;
            $bd = $r->budgetDetail ?? $rd?->budgetDetail;
            $tambah($kunci($bd, $r->account?->name, $rd ? $this->subCoaLabel($rd) : null), 0, (int) $r->total);
        }

        $fmt  = fn ($v) => number_format($v, 0, ',', '.');
        $rows = collect($baris)->values()->map(fn ($b) => $b + [
            'variance'             => $b['request'] - $b['settlement'],
            'request_formatted'    => $fmt($b['request']),
            'settlement_formatted' => $fmt($b['settlement']),
            'variance_formatted'   => $fmt($b['request'] - $b['settlement']),
        ]);

        $request = (int) $rows->sum('request');
        $settle  = (int) $rows->sum('settlement');

        return [
            'rows'                 => $rows->all(),
            'request'              => $request,
            'settlement'           => $settle,
            'variance'             => $request - $settle,
            'request_formatted'    => $fmt($request),
            'settlement_formatted' => $fmt($settle),
            'variance_formatted'   => $fmt($request - $settle),
        ];
    }

    /**
     * Riwayat pengajuan Cost Request untuk PIC yang sama (pemegang beneficiary),
     * DI PO (atau cost center) yang sama dengan CR ini.
     *
     * Satu PIC bisa memegang BANYAK beneficiary, jadi cakupannya PIC — bukan
     * satu beneficiary. Tetap dibatasi sumber anggaran yang sama (PO/cost
     * center) dengan CR yang dibuka.
     *
     * Hanya CR yang sudah dibayar atau setelahnya yang masuk (paid_at terisi —
     * paid_at tetap melekat sepanjang leg settlement, jadi REALIZED/SETTLE/
     * SECOND/CLOSED ikut masuk). CR yang sedang dibuka sendiri dikeluarkan.
     *
     * Hasilnya daftar DATAR baris item lintas CR; penyusunan pohon
     * COA → Sub COA → item dikerjakan di layar (drawer Todo List). Sumber
     * baris per CR mengikuti aturan yang sama dengan View Budget:
     *   - Cash Advance    : dari rincian SETTLEMENT (budget_request_realizations);
     *                       bila settlement masih kosong, jatuh balik ke
     *                       rincian pengajuan.
     *   - Non Cash Advance: selalu dari rincian pengajuan.
     * Nilai per baris = nilai settlement (CA) atau total pengajuan (non-CA /
     * CA tanpa settlement). Item takeout tidak ikut.
     */
    private function picHistory(BudgetRequest $cr): \Illuminate\Support\Collection
    {
        $bp  = $cr->budgetPlan;
        $ben = $cr->beneficiary;
        if (! $bp || ! $ben) {
            return collect(['pic_name' => '-', 'has_pic' => false, 'items' => collect(), 'total' => 0]);
        }

        /*
         | Cakupan PIC: semua beneficiary di bawah PIC yang sama. Kalau
         | beneficiary CR ini belum punya PIC, mundur ke beneficiary itu
         | sendiri supaya riwayatnya tidak melebar ke seluruh master.
         */
        $picName        = $ben->pic?->pic;
        $beneficiaryIds = $ben->pic_id
            ? Beneficiary::where('pic_id', $ben->pic_id)->pluck('id')
            : collect([$ben->id]);

        $history = BudgetRequest::with([
                'type',
                'details.account', 'details.budgetDetail',
                'realizations.account',
                'realizations.requestDetail.account',
                'realizations.requestDetail.budgetDetail',
                'realizations.budgetDetail',
            ])
            ->whereIn('beneficiary_id', $beneficiaryIds)
            ->where('id', '!=', $cr->id)
            ->whereHas('budgetPlan', function ($q) use ($bp) {
                if ($bp->project_id) {
                    $q->where('project_id', $bp->project_id);
                } elseif ($bp->cost_center_id) {
                    $q->where('cost_center_id', $bp->cost_center_id);
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->limit(50)
            ->get();

        $items = collect();

        foreach ($history as $h) {
            $meta = [
                'cr_id'           => $h->id,
                'cr_code'         => $h->code,
                'cr_type'         => $h->type?->name ?? '-',
                'cr_status'       => $h->status,
                'cr_status_label' => BudgetRequest::statusLabelFor($h->status),
                'cr_status_color' => $h->status_color,
                'cr_date'         => $h->paid_at?->format('d M Y') ?? '-',
                'cr_date_raw'     => $h->paid_at?->format('Y-m-d H:i:s'),
            ];

            $useSettlement = $h->isCashAdvance() && $h->realizations->isNotEmpty();

            if ($useSettlement) {
                foreach ($h->realizations as $r) {
                    $rd = $r->requestDetail;
                    $items->push($meta + [
                        'account'         => $r->account?->name ?? ($rd?->account?->name ?? '-'),
                        // Sub COA efektif: pilihan baris settlement, atau
                        // sub-item item asal untuk baris lama.
                        'sub_coa'         => ($r->budgetDetail ?? $rd?->budgetDetail)?->deskripsi ?? '-',
                        'description'     => $r->description ?: ($rd ? $this->itemLabel($rd) : '-'),
                        'value'           => (int) $r->amount,
                        'value_formatted' => number_format((int) $r->amount, 0, ',', '.'),
                    ]);
                }
            } else {
                foreach ($h->details as $d) {
                    if ($d->isTakenOut()) continue;
                    $items->push($meta + [
                        'account'         => $d->account?->name ?? '-',
                        'sub_coa'         => $d->budgetDetail?->deskripsi ?? '-',
                        'description'     => $this->itemLabel($d),
                        'value'           => (int) $d->total,
                        'value_formatted' => number_format((int) $d->total, 0, ',', '.'),
                    ]);
                }
            }
        }

        return collect([
            'pic_name' => $picName ?: ($ben->complete_name ?? '-'),
            // Beneficiary ini belum punya PIC master -> judul panelnya di
            // layar ikut menyesuaikan jadi "Riwayat Pengajuan BENEFICIARY",
            // supaya tidak menyebut sebuah nama beneficiary sebagai "PIC".
            'has_pic'  => (bool) $ben->pic_id,
            'items'    => $items->values(),
            'total'    => (int) $items->sum('value'),
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

        if (!$cr->isEditableBy(Auth::user())) {
            return redirect('/budget-request')
                ->with('error', 'Cost Request hanya dapat diedit saat berstatus DRAFT.');
        }

        $types         = RequestType::whereNotNull('name')->where('name', '!=', '')->orderBy('name')->get();
        $budgetPlans   = $this->getAvailableBudgetPlans();
        $clusters      = Cluster::where('status', 'ACTIVE')->orderBy('name')->get();
        /*
        | Hanya beneficiary ACTIVE yang boleh dipilih — TAPI penerima dana yang
        | sudah dipakai CR ini tetap disertakan walau statusnya sudah INACTIVE.
        | Tanpa itu, membuka form edit diam-diam mengosongkan pilihannya dan
        | penyimpanan berikutnya mengganti penerima dana tanpa disadari.
        */
        $beneficiaries = Beneficiary::where('status', 'ACTIVE')
            ->orWhere('id', $cr->beneficiary_id)
            ->orderBy('complete_name')->get();

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

        if (!$cr->isEditableBy(Auth::user())) {
            return redirect('/budget-request')
                ->with('error', 'Cost Request hanya dapat diedit saat berstatus DRAFT.');
        }
        $editLanjut = ! $cr->isEditable();   // edit Superadmin di luar Draft

        // Snapshot untuk rincian perubahan (nilai lama -> baru) di log CR.
        $snapshot = function (BudgetRequest $x): array {
            $x->loadMissing(['type:id,name', 'beneficiary:id,complete_name', 'cluster:id,name', 'budgetPlan:id,code']);
            return [
                'Tipe'        => (string) ($x->type->name ?? '-'),
                'Budget Plan' => (string) ($x->budgetPlan->code ?? '-'),
                'Cluster'     => (string) ($x->cluster->name ?? '-'),
                'Beneficiary' => (string) ($x->beneficiary->complete_name ?? '-'),
                'Deskripsi'   => (string) $x->description,
                'Catatan'     => (string) ($x->notes ?? ''),
                'Email'       => (string) ($x->email ?? ''),
                'Jumlah item' => (string) $x->details()->count(),
                'Total rincian' => number_format((int) $x->details()->sum('total'), 0, ',', '.'),
            ];
        };
        $sebelum = $editLanjut ? $snapshot($cr) : [];

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
            'details.*.notes'            => 'required|string|max:255',
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

        if ($this->typeIsCashAdvance((int) $request->type_id)) {
            $beneficiary = Beneficiary::find($request->beneficiary_id);
            if ($beneficiary && $beneficiary->category !== 'INTERNAL') {
                return back()->withInput()->with('error', 'Cash Advance hanya untuk beneficiary kategori Internal.');
            }

            // Limit Cash Advance per PIC — CR yang sedang diedit dikecualikan dari
            // hitungan supaya nominal lamanya tidak dihitung dua kali.
            $msg = $this->cashAdvanceQuotaError(
                (int) $request->beneficiary_id,
                $this->requestedDetailTotal($request),
                $cr->id
            );
            if ($msg) return back()->withInput()->with('error', $msg);
        }

        $budgetPlan = BudgetPlan::findOrFail($request->budget_plan_id);

        if (!$budgetPlan->ownsDetails(collect((array) $request->input('details', []))->pluck('budget_detail_id'))) {
            return back()->withInput()
                ->with('error', 'Sub-item budget yang dipilih tidak sesuai dengan Budget Plan ini.');
        }

        $totalAmount = 0;
        foreach ((array) $request->input('details', []) as $row) {
            $amt = $this->normalizeMoney($row['amount'] ?? null);
            $totalAmount += $amt * (int) ($row['qty'] ?? 1);
        }

        $isNob     = $budgetPlan->isNob($totalAmount, $cr->id);
        $nobAmount = $budgetPlan->nobOverage($totalAmount, $cr->id);
        $description = strtoupper(trim($request->description));

        // Prefix kode mengikuti Tipe Request, hanya selama CR belum pernah diajukan.
        $code = (int) $cr->type_id !== (int) $request->type_id && $cr->codePrefixChangeable()
            ? BudgetRequest::codeWithTypePrefix((string) $cr->code, (int) $request->type_id)
            : $cr->code;

        DB::transaction(function () use ($request, $cr, $code, $description, $isNob, $nobAmount) {
            $cr->update([
                'code'           => $code,
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

        if ($editLanjut) {
            $sesudah = $snapshot($cr->fresh());
            $ubah = [];
            foreach ($sebelum as $label => $lama) {
                if ($lama !== $sesudah[$label]) {
                    $ubah[] = $label . ': ' . ($lama !== '' ? $lama : '-') . ' -> ' . ($sesudah[$label] !== '' ? $sesudah[$label] : '-');
                }
            }
            // Status tidak berubah; log mencatat siapa, kapan, dan apa yang diubah.
            // Catatan: batch Treasury & settlement membaca nominal CR secara langsung.
            $cr->addLog($cr->status, $cr->status, 'superadmin_edited', mb_substr(
                '[status ' . $cr->status . '] '
                . ($ubah ? implode('; ', $ubah) : 'Disimpan tanpa perubahan field ringkas (rincian per baris/lampiran mungkin berubah).'),
                0, 2000));
        }

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

        // Lampiran & limit Cash Advance: satu sumber dengan Manager direct approval.
        if ($msg = $cr->submissionError()) {
            return back()->with('error', $msg);
        }

        $cr->markSubmitted();

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

        // Transisi terkunci, sama seperti managerCancel(): tanpa kunci, Revise
        // dan Cancel yang diklik bersamaan bisa menghidupkan lagi CR yang baru
        // saja dibatalkan.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'REJECTED', ['status' => 'DRAFT'], function ($cr) use ($request) {
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

            $cr->recalculateAmount();
            $cr->addLog('REJECTED', 'DRAFT', 'revised', $request->notes);
        });

        if (! $cr) return back()->with('error', 'Status sudah berubah — muat ulang halaman.');

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

    /*
    | Setiap perpindahan di bawah memakai transitionStatus() dengan status saat
    | CR dimuat sebagai status yang diharapkan. Loop bulk bisa berjalan lama
    | (notifikasi per CR); CR yang statusnya diubah orang lain di tengah loop
    | kini DILEWATI, bukan ditimpa - dulu CR yang baru ditolak bisa kembali
    | tersetujui (atau sebaliknya) karena penulis terakhir yang menang.
    */

    /** @return bool true jika CR berhasil ditransisikan (status+role cocok). */
    private function applyReviewApprove(BudgetRequest $cr, User $user): bool
    {
        if ($cr->status === 'SUBMITTED' && $user->hasRole(['superadmin', 'head'])) {
            if (! $this->transitionStatus(BudgetRequest::class, $cr->id, 'SUBMITTED', ['status' => 'CHECKED'],
                fn ($c) => $c->addLog('SUBMITTED', 'CHECKED', 'head_approved', null))) {
                return false;
            }
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
            return (bool) $this->transitionStatus(BudgetRequest::class, $cr->id, 'CHECKED', ['status' => 'VERIFIED', 'approved_at' => now()],
                fn ($c) => $c->addLog('CHECKED', 'VERIFIED', 'finance_approved', null));
        }

        if ($cr->status === 'VERIFIED' && $user->hasRole(['superadmin', 'approver'])) {
            return (bool) $this->transitionStatus(BudgetRequest::class, $cr->id, 'VERIFIED', ['status' => 'APPROVED'],
                fn ($c) => $c->addLog('VERIFIED', 'APPROVED', 'cr_approved', null));
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

        return (bool) $this->transitionStatus(BudgetRequest::class, $cr->id, $fromStatus, ['status' => 'REJECTED'],
            fn ($c) => $c->addLog($fromStatus, 'REJECTED', $actionType, $notes));
    }

    /** Batas jumlah CR per satu kali bulk review (approve/reject). */
    private const BULK_REVIEW_MAX = 50;

    /**
     * Validasi umum bulk review. `expected` (opsional, id => STATUS) dikirim
     * halaman index CR: CR hanya diproses bila statusnya saat dimuat sama
     * dengan status yang dilihat user di layar. Todo List tidak mengirimnya,
     * jadi perilakunya tetap seperti semula.
     */
    private function validateBulkReview(Request $request, array $extra = []): void
    {
        $request->validate($extra + [
            'ids'        => 'required|array|min:1|max:' . self::BULK_REVIEW_MAX,
            'ids.*'      => 'integer',
            'expected'   => 'nullable|array',
            'expected.*' => 'nullable|string|max:30',
        ]);
    }

    /**
     * Jalankan $apply(CR) untuk tiap id yang valid dan kumpulkan alasan CR yang
     * dilewati. Aturan role/status tetap di applyReviewApprove/Reject; alasan
     * di sini hanya untuk pesan ke user (id tak ditemukan dan id tanpa akses
     * sengaja diberi pesan yang sama).
     *
     * @return array{0:int,1:array<int,string>} [jumlah berhasil, daftar alasan skip]
     */
    private function runBulkReview(Request $request, User $user, \Closure $apply): array
    {
        $ids      = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $expected = (array) $request->input('expected', []);
        $visible  = $this->visibleCrs($ids)->keyBy('id');

        $count   = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $cr = $visible->get($id);
            if (! $cr) {
                $skipped[] = '#' . $id . ' (tidak ditemukan atau tidak ada akses)';
                continue;
            }

            $exp = $expected[$id] ?? null;
            if ($exp !== null && $exp !== $cr->status) {
                $skipped[] = $cr->code . ' (status berubah dari ' . $exp . ' menjadi ' . $cr->status . ' — muat ulang)';
                continue;
            }
            if (! $cr->isInApprovalStage()) {
                $skipped[] = $cr->code . ' (status ' . $cr->status . ' bukan tahap approval)';
                continue;
            }
            if (! $user->hasRole(array_merge(['superadmin'], BudgetRequest::STAGE_ROLES[$cr->status]))) {
                $skipped[] = $cr->code . ' (peran Anda tidak berhak pada tahap ' . $cr->status . ')';
                continue;
            }

            if ($apply($cr)) {
                $count++;
            } else {
                $skipped[] = $cr->code . ' (status berubah saat diproses — muat ulang)';
            }
        }

        return [$count, $skipped];
    }

    /** Ringkas daftar CR yang dilewati jadi satu pesan error (maks 5 lalu "…"). */
    private function bulkSkipMessage(array $skipped): string
    {
        $tampil = array_slice($skipped, 0, 5);

        return count($skipped) . ' CR dilewati: ' . implode(', ', $tampil)
            . (count($skipped) > 5 ? ', …' : '') . '.';
    }

    public function reviewBulkApprove(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->validateBulkReview($request);

        // Hanya CR yang memang boleh dilihat user ini - sama dengan aksi
        // satuan (findVisibleCr) dan bulk settlement (visibleCrs()). Dulu id
        // dimuat apa adanya, sehingga Head bisa memproses CR di luar Access
        // Group-nya lewat POST yang dibuat sendiri.
        [$count, $skipped] = $this->runBulkReview($request, $user, fn (BudgetRequest $cr) => $this->applyReviewApprove($cr, $user));

        $redirect = back()->with('success', $count . ' CR berhasil di-approve.');
        return $skipped ? $redirect->with('error', $this->bulkSkipMessage($skipped)) : $redirect;
    }

    public function reviewBulkReject(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->validateBulkReview($request, ['notes' => 'required|string|max:500']);

        [$count, $skipped] = $this->runBulkReview($request, $user, fn (BudgetRequest $cr) => $this->applyReviewReject($cr, $user, $request->notes));

        $redirect = back()->with('success', $count . ' CR berhasil ditolak. Menunggu keputusan Manager (Revise/Cancel).');
        return $skipped ? $redirect->with('error', $this->bulkSkipMessage($skipped)) : $redirect;
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

        try {
        DB::transaction(function () use ($cr, $detail, $user, $stage, $stageLabel, $request) {
            /*
            | Kunci CR, lalu pastikan tahap & hak moderasi masih sama dengan saat
            | dibaca, dan item belum di-takeout orang lain. Tanpa ini dua takeout
            | bersamaan atas dua item terakhir sama-sama melihat "masih ada sisa
            | 1 item", sehingga CR tetap berjalan dengan nilai 0 tanpa
            | auto-Rejected.
            */
            $locked = BudgetRequest::whereKey($cr->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== $stage || ! $locked->canModerateItems($user)
                || $detail->fresh()?->isTakenOut()) {
                throw new \DomainException('Status sudah berubah — muat ulang halaman.');
            }

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
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

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

        try {
        DB::transaction(function () use ($cr, $detail, $user, $stage, $stageLabel) {
            // Kunci & periksa ulang, sama seperti takeoutDetail().
            $locked = BudgetRequest::whereKey($cr->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== $stage || ! $locked->canModerateItems($user)
                || ! $detail->fresh()?->isTakenOut()) {
                throw new \DomainException('Status sudah berubah — muat ulang halaman.');
            }

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
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

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
        // ditolak (REALIZED -> SETTLE_REJECTED) & disubmit ulang, kolom
        // "Description Settle" & "Settle" ke-prefill dari input sebelumnya
        // (bukan reset). 'statusLogs' dipakai menampilkan alasan reject
        // terakhir di form (lihat $rejectReason di bawah). Sub-item Budget
        // Plan dimuat untuk pilihan Sub COA per baris (Settlement Custom COA).
        $budgetRequest = $this->findVisibleCr($id, [
            'type', 'details.account', 'details.budgetDetail.account',
            'realizations.attachments', 'realizationAttachments', 'statusLogs',
        ]);

        if (! in_array($budgetRequest->status, ['PAID', 'SETTLE_REJECTED'], true)) {
            return redirect()->route('budget-request.index')
                ->with('error', 'Hanya CR berstatus PAID atau Settle Rejected yang dapat diajukan realisasi.');
        }

        // Alasan reject terakhir — ditampilkan menonjol di form supaya
        // pengaju tahu persis apa yang harus direvisi sebelum submit ulang.
        // statusLogs() sudah urut terbaru-dulu, jadi firstWhere cukup.
        $rejectReason = $budgetRequest->status === 'SETTLE_REJECTED'
            ? $budgetRequest->statusLogs->firstWhere('action_type', 'settlement_rejected')
            : null;

        // Sejak 4 Nov 2026 form kembali terisi 1:1 dari item pengajuan (bukan
        // TAKEOUT): baris item itu dikunci — tidak bisa dihapus & Sub COA-nya
        // tetap; yang disunting hanya deskripsi, qty dan nilai. Baris tersimpan
        // (draft / ditolak), pecahan, dan baris tambahan ikut tampil.
        $settleRows = $budgetRequest->settlementFormRows();
        $subItems   = $budgetRequest->settlementSubItemOptions();

        return view('budget-request.realize', compact('budgetRequest', 'rejectReason', 'settleRows', 'subItems'));
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT REALISASI — PAID → REALIZED (manager)
    |--------------------------------------------------------------------------
    */

    /**
     * Validasi input form Settlement, dipakai sama-sama oleh submit (PAID/
     * SETTLE_REJECTED -> REALIZED) dan simpan draft (status tidak berubah).
     * Mengembalikan response error siap-kirim kalau tidak valid, atau null
     * kalau lolos — supaya kedua pemanggil menolak dengan aturan yang sama.
     *
     * Settlement Custom COA (25 Okt 2026) — tiap baris boleh:
     *   - menunjuk item asal (request_detail_id, item non-TAKEOUT milik CR
     *     ini) atau kosong = baris tambahan;
     *   - memilih Sub COA (budget_detail_id) yang WAJIB milik Budget Plan CR
     *     ini. Kosong = Sub COA item asalnya; baris tambahan wajib memilih.
     * Pelanggaran dua aturan itu ditolak juga saat simpan draft, sebelum ada
     * apa pun yang tersimpan.
     */
    private function validateRealizationInput(Request $request, BudgetRequest $cr, bool $strict = true)
    {
        $request->validate([
            'realizations'                     => 'required|array|min:1',
            // Baris tersimpan yang di-update (lihat syncRealizations()).
            'realizations.*.id'                => 'nullable|integer',
            // Kosong = baris tambahan tanpa item asal.
            'realizations.*.request_detail_id' => 'nullable|integer',
            'realizations.*.budget_detail_id'  => 'nullable|integer',
            // Settle boleh 0 (mis. uang dikembalikan utuh); kosong dianggap 0.
            'realizations.*.amount'            => 'nullable',
            // Sejalan dengan maxlength input di form settlement.
            'realizations.*.notes'             => 'nullable|string|max:255',
            'realizations.*.change_note'       => 'nullable|string|max:500',
            // Qty settle (informasi; nilai uangnya tetap dari kolom Settle).
            'realizations.*.qty'               => 'nullable|integer|min:1|max:999',
            'realizations.*.attachments'       => 'nullable|array',
            'realizations.*.attachments.*'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'realization_attachments'          => 'nullable|array',
            'realization_attachments.*'        => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            // Lampiran lama yang dicentang hapus oleh user.
            'delete_realization_attachments'        => 'nullable|array',
            'delete_realization_global_attachments' => 'nullable|array',
            // Keterangan opsional dari pengaju — ikut ditulis ke Log CR.
            'settlement_notes'                      => 'nullable|string|max:1000',
            // Bukti transfer pengembalian dana (total Settle < nilai pengajuan).
            'refund_proofs'                         => 'nullable|array',
            'refund_proofs.*'                       => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $rows         = (array) $request->input('realizations', []);
        $validDetails = $cr->details()->where('status', '!=', 'TAKEOUT')->get()->keyBy('id');
        $submittedIds = collect($rows)->pluck('request_detail_id')->filter()->map(fn ($i) => (int) $i);

        $asing = $submittedIds->diff($validDetails->keys()->map(fn ($i) => (int) $i));
        if ($asing->isNotEmpty()) {
            /*
            | Baris yatim (item asalnya sudah di-takeout) ikut terkirim apa
            | adanya dari form — lihat BudgetRequest::settlementFormRows().
            | Pesannya disebut terang supaya user tahu baris mana yang dihapus.
            */
            $takeoutIds = $cr->details()->where('status', 'TAKEOUT')->whereIn('id', $asing->all())
                ->pluck('id')->map(fn ($i) => (int) $i)->all();

            if ($takeoutIds) {
                $noBaris = [];
                $no      = 0;
                foreach ($rows as $row) {
                    $no++;
                    if (in_array((int) (((array) $row)['request_detail_id'] ?? 0), $takeoutIds, true)) {
                        $noBaris[] = $no;
                    }
                }

                return back()->withInput()->with(
                    'error',
                    'Item asal baris no. ' . implode(', ', $noBaris) . ' sudah di-takeout dan tidak bisa disettle. Hapus baris tersebut lalu simpan lagi.'
                );
            }

            return back()->withInput()
                ->with('error', 'Item realisasi yang dipilih tidak sesuai dengan Cost Request ini.');
        }

        // Sub COA hanya dari sub-item Budget Plan CR ini sendiri.
        $bdIds = collect($rows)->pluck('budget_detail_id')->filter()->map(fn ($i) => (int) $i);
        $bp    = $cr->budgetPlan;
        if ($bdIds->isNotEmpty() && (! $bp || ! $bp->ownsDetails($bdIds))) {
            return back()->withInput()
                ->with('error', 'Sub COA yang dipilih bukan milik Budget Plan Cost Request ini.');
        }

        $no         = 0;
        $tanpaSub   = [];
        $pindahSub  = [];
        $fleksibel  = false;
        foreach ($rows as $row) {
            $row = (array) $row;
            // Nomor = urutan baris di form (baris kosong ikut dihitung), sama
            // dengan nomor yang dipakai pesan di layar.
            $no++;
            if ($this->isEmptyRealizationRow($row)) continue;

            if (blank($row['request_detail_id'] ?? null) && blank($row['budget_detail_id'] ?? null)) {
                $tanpaSub[] = $no;
            }

            // Baris tambahan, atau Sub COA berbeda dari item asalnya.
            $asal = $validDetails->get((int) ($row['request_detail_id'] ?? 0));

            // Sub COA baris item pengajuan (termasuk pecahannya) dikunci ke
            // Sub COA item asal (4 Nov 2026) — hanya baris tambahan yang memilih.
            if ($asal && filled($row['budget_detail_id'] ?? null)
                    && (int) $row['budget_detail_id'] !== (int) $asal->budget_detail_id) {
                $pindahSub[] = $no;
            }

            if (! $asal || (filled($row['budget_detail_id'] ?? null)
                    && (int) $row['budget_detail_id'] !== (int) $asal->budget_detail_id)) {
                $fleksibel = true;
            }
        }

        /*
        | Kode terunggah lebih dulu daripada migrasi 2026_10_25_000200: kolom
        | budget_detail_id belum ada, jadi pilihan Sub COA tidak bisa disimpan.
        | Ditolak terang-terangan daripada tersimpan diam-diam di Sub COA asal.
        */
        if ($fleksibel && ! BudgetRequestRealization::hasBudgetDetailColumn()) {
            return back()->withInput()->with(
                'error',
                'Pindah Sub COA / baris tambahan belum bisa disimpan: migrasi database belum dijalankan. Hubungi admin.'
            );
        }

        if ($tanpaSub) {
            return back()->withInput()->with(
                'error',
                'Baris tambahan wajib memilih Sub COA. Baris yang belum dipilih: no. ' . implode(', ', $tanpaSub) . '.'
            );
        }

        if ($pindahSub) {
            return back()->withInput()->with(
                'error',
                'Sub COA baris item pengajuan tidak dapat diganti. Baris: no. ' . implode(', ', $pindahSub) . '.'
            );
        }

        // Simpan draft ($strict = false) sengaja melewati pemeriksaan
        // kelengkapan: draft memang boleh setengah jadi dan dilengkapi nanti
        // sebelum ditekan "Submit Settlement".
        if (! $strict) {
            return null;
        }

        /*
        | Aturan submit (4 Nov 2026):
        |   - setiap item pengajuan (bukan TAKEOUT) wajib tetap punya baris —
        |     baris item pengajuan tidak bisa dihapus dari form;
        |   - baris bernilai Settle > 0 wajib punya Lampiran Item (berkas baru
        |     atau lampiran tersimpan baris itu yang tidak dicentang hapus).
        |     Note per baris tetap opsional.
        | Keterangan & Lampiran Settlement (global) tidak lagi wajib, termasuk
        | saat total Settle melebihi nilai pengajuan.
        */
        $adaBaris = collect($rows)->pluck('request_detail_id')->filter()->map(fn ($i) => (int) $i)->all();
        $hilang   = $validDetails->reject(fn ($d) => in_array((int) $d->id, $adaBaris, true));
        if ($hilang->isNotEmpty()) {
            return back()->withInput()->with(
                'error',
                'Baris item pengajuan tidak boleh dihapus: ' . $hilang->map(fn ($d) => $this->itemLabel($d))->implode('; ') . '.'
            );
        }

        $hapusIds = array_map('strval', array_filter((array) $request->input('delete_realization_attachments', [])));
        $match    = $this->matchExistingRealizations($rows, $cr->realizations()->with('attachments')->get());
        $tanpaLampiran = [];
        $no = 0;
        foreach ($rows as $i => $row) {
            $row = (array) $row;
            $no++;
            if ($this->isEmptyRealizationRow($row) || $this->normalizeMoney($row['amount'] ?? null) <= 0) continue;

            $adaBerkas = collect((array) $request->file("realizations.$i.attachments", []))
                ->contains(fn ($f) => $f && $f->isValid());
            $saved = $match[$i] ?? null;
            if (! $adaBerkas && $saved) {
                $adaBerkas = $saved->attachments->contains(fn ($a) => ! in_array((string) $a->id, $hapusIds, true));
            }

            if (! $adaBerkas) {
                $tanpaLampiran[] = $no;
            }
        }

        if ($tanpaLampiran) {
            return back()->withInput()->with(
                'error',
                '"Lampiran Item" wajib untuk baris bernilai Settle > 0. Baris yang belum dilampiri: no. ' . implode(', ', $tanpaLampiran) . '.'
            );
        }

        return null;
    }

    /** Prefix nama lampiran global CR untuk bukti transfer pengembalian dana. */
    private const REFUND_PROOF_PREFIX = 'bukti-transfer-';

    /**
     * Nama berkas tanda tangan penerima dana untuk satu baris settlement.
     * Sejak Settlement Custom COA (25 Okt 2026) dikunci ke ID BARIS
     * REALISASI ("ttd-r<id>-<kode CR>.png"), bukan nomor urut baris di form —
     * nomor urut bergeser begitu baris dipecah/ditambah/dihapus, id baris tidak.
     */
    private function signatureName(BudgetRequest $cr, int $realizationId): string
    {
        return 'ttd-r' . $realizationId . '-' . $cr->code . '.png';
    }

    /**
     * Nomor baris tanda tangan format LAMA ("ttd-<nomor baris>-<kode CR>.png")
     * per item asal: [request_detail_id => nomor]. Nomor baris dulu = urutan
     * item non-TAKEOUT CR (urut id) di modal settlement tiket. Dipakai agar
     * tanda tangan yang tersimpan sebelum rilis ini tetap diakui.
     *
     * @return array<int, int>
     */
    private function legacySignatureNumbers(BudgetRequest $cr): array
    {
        return $cr->details()->where('status', '!=', 'TAKEOUT')->orderBy('id')->pluck('id')
            ->values()
            ->mapWithKeys(fn ($id, $pos) => [(int) $id => $pos + 1])
            ->all();
    }

    /**
     * Bukti wajib per baris settlement (keputusan 20 Okt 2026):
     *
     * Sejak 6 Nov 2026 bukti baris cukup salah satu: nota atau kwitansi digital.
     * Aturan lama di bawah ini dicatat untuk riwayat:
     *   a. Baris bernilai > 0 yang SUDAH ditandatangani penerima dana wajib
     *      punya lampiran — tanda tangan tanpa bukti belanja tidak bisa
     *      dipertanggungjawabkan.
     *   b. Baris yang VARIANCE-nya tidak nol (Settle beda dari Request) wajib
     *      punya lampiran DAN tanda tangan — selisih uang harus berbukti dan
     *      diakui penerimanya.
     *
     * Sejak Settlement Custom COA (25 Okt 2026) variance dihitung per ITEM
     * ASAL (jumlah seluruh baris item itu dibanding Request-nya) dan berlaku
     * untuk setiap baris item tersebut; baris tambahan (Request 0) bervariance
     * sebesar nilainya. Untuk data satu baris per item hasilnya sama dengan
     * aturan per baris sebelumnya.
     *
     * Item asal (bukan TAKEOUT) yang barisnya dihapus semua dihitung Settle 0,
     * sama seperti bila barisnya diisi 0 — aturan b tidak bisa dilewati
     * dengan menghapus baris. Karena tidak ada baris untuk menampung bukti,
     * simpanan ditolak; form yang dimuat ulang memunculkan lagi baris item itu.
     *
     * Berlaku juga saat menyimpan DRAFT: draft memang boleh setengah jadi,
     * tapi tidak untuk baris yang sudah menyentuh dua hal di atas. Lampiran
     * dan tanda tangan dari simpanan sebelumnya pada baris yang SAMA ikut
     * dihitung selama tidak dicentang hapus.
     */
    private function validateSettlementEvidence(Request $request, BudgetRequest $cr)
    {
        $rows           = (array) $request->input('realizations', []);
        $signatures     = (array) $request->input('signatures', []);
        $hapusIds       = array_map('strval', array_filter((array) $request->input('delete_realization_attachments', [])));
        $existing       = $cr->realizations()->with('attachments')->get();
        $match          = $this->matchExistingRealizations($rows, $existing);
        $details        = $cr->details()->with(['account', 'budgetDetail'])->get()->keyBy('id');
        $ttdTersimpan   = $cr->realizationAttachments()->where('original_name', 'like', 'ttd-%')->pluck('original_name')->all();
        $nomorLama      = $this->legacySignatureNumbers($cr);
        $kurangLampiran = [];

        $settlePerItem = [];
        // Baris pertama (urutan form) tiap item asal — satu-satunya baris yang
        // mewarisi tanda tangan format lama, sama dengan storeSettlementSignatures().
        $barisPertama = [];
        foreach ($rows as $i => $row) {
            $row = (array) $row;
            if ($this->isEmptyRealizationRow($row) || blank($row['request_detail_id'] ?? null)) continue;
            $settlePerItem[(int) $row['request_detail_id']] = ($settlePerItem[(int) $row['request_detail_id']] ?? 0)
                + $this->normalizeMoney($row['amount'] ?? null);
            $barisPertama[(int) $row['request_detail_id']] ??= $i;
        }

        // Item ikut yang tidak punya baris sama sekali = Settle 0.
        $tanpaBaris = $details
            ->reject(fn ($d) => $d->isTakenOut() || array_key_exists((int) $d->id, $settlePerItem) || (int) $d->total === 0)
            ->map(fn ($d) => $this->itemLabel($d))
            ->values()
            ->all();

        $no = 0;
        foreach ($rows as $i => $row) {
            $row = (array) $row;
            // Nomor = urutan baris di form (baris kosong ikut dihitung), sama
            // dengan nomor yang dipakai pesan di layar.
            $no++;
            if ($this->isEmptyRealizationRow($row)) continue;

            $detailId = (int) ($row['request_detail_id'] ?? 0);
            $amount   = $this->normalizeMoney($row['amount'] ?? null);
            $detail   = $detailId ? $details->get($detailId) : null;

            $adaSelisih = $detail
                ? ($settlePerItem[$detailId] ?? 0) !== (int) $detail->total
                : $amount !== 0;

            // Tanda tangan baru dari form, atau yang sudah tersimpan untuk
            // baris yang sama (format baru per id baris, atau format lama per
            // nomor urut item asal).
            $saved = $match[$i] ?? null;
            $ttd   = str_starts_with((string) ($signatures[$i] ?? ''), 'data:image/png;base64,')
                || ($saved && in_array($this->signatureName($cr, $saved->id), $ttdTersimpan, true))
                || ($detail && isset($nomorLama[$detail->id])
                    && ($barisPertama[$detail->id] ?? null) === $i
                    && in_array('ttd-' . $nomorLama[$detail->id] . '-' . $cr->code . '.png', $ttdTersimpan, true));

            if (! $adaSelisih && ! ($amount && $ttd)) {
                continue;
            }

            $adaBerkas = collect((array) $request->file("realizations.$i.attachments", []))
                ->contains(fn ($f) => $f && $f->isValid());

            if (! $adaBerkas && $saved) {
                $adaBerkas = $saved->attachments
                    ->contains(fn ($a) => ! in_array((string) $a->id, $hapusIds, true));
            }

            // Bukti baris cukup salah satu: nota atau kwitansi digital. Tidak semua
            // transaksi punya penerima yang bisa menandatangani (tol, minimarket).
            if (! $adaBerkas && ! $ttd) {
                $kurangLampiran[] = $no;
            }
        }

        $pesan = [];

        if ($kurangLampiran) {
            $pesan[] = 'Nota atau E-sign belum diisi pada baris no. ' . implode(', ', $kurangLampiran) . '.';
        }

        if ($tanpaBaris) {
            $pesan[] = 'Item berikut tidak punya baris settlement dan dihitung Settle 0, sehingga wajib Nota atau E-sign: '
                . implode('; ', $tanpaBaris) . '. Baris item tersebut ditampilkan lagi di form — isi Settle-nya atau lengkapi buktinya.';
        }

        return $pesan
            ? back()->withInput()->with('error', implode(' ', $pesan))
            : null;
    }

    /**
     * Simpan data form Settlement (realisasi + lampiran) ke database. Status
     * CR TIDAK disentuh di sini — pemanggil (submit atau simpan draft) yang
     * memutuskan transisinya.
     *
     * @return array<int|string, BudgetRequestRealization> indeks form -> baris tersimpan
     */
    private function syncRealizationData(Request $request, BudgetRequest $cr): array
    {
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

        $map = $this->syncRealizations($cr, $request);
        $this->storeRealizationAttachments($cr, $request);

        /*
        | Bukti transfer pengembalian dana (4 Nov 2026) — hanya diterima bila
        | total Settle di bawah nilai pengajuan (ada sisa dana yang dikembalikan).
        | Disimpan sebagai lampiran global CR berawalan REFUND_PROOF_PREFIX,
        | seperti tanda tangan "ttd-", tanpa tabel/kolom baru. Opsional.
        */
        $totalSettle = collect($map)->sum(fn ($r) => (int) $r->total);
        if ($totalSettle < (int) $cr->amount) {
            foreach ((array) $request->file('refund_proofs', []) as $file) {
                if (! $file || ! $file->isValid()) continue;
                $path = $file->store('cost_request_realizations', 'secure');
                $cr->realizationAttachments()->create([
                    'original_name' => mb_substr(self::REFUND_PROOF_PREFIX . $file->getClientOriginalName(), 0, 255),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                    'uploaded_by'   => Auth::id(),
                ]);
            }
        }

        // Keterangan pengaju disimpan di datanya (bukan hanya Log CR).
        if (BudgetRequest::hasSettlementNotesColumn()) {
            $cr->update(['settlement_notes' => trim((string) $request->input('settlement_notes')) ?: null]);
        }

        return $map;
    }

    public function submitRealization(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $cr = $this->findVisibleCr($id);

        /* Sejak 20 Okt 2026 TEKNISI boleh mengajukan settlement — tetapi HANYA
           untuk CR yang lahir dari plan visit tiket yang ditugaskan kepadanya,
           bukan CR mana pun. Di luar itu gerbang perannya tidak berubah. */
        if (! $user->hasRole(['superadmin', 'manager', 'supervisor'])
            && ! $this->isTicketTechnicianOfCr($user, $cr)) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengajukan realisasi CR ini.');
        }

        if (! in_array($cr->status, ['PAID', 'SETTLE_REJECTED'], true)) {
            return back()->with('error', 'Hanya CR berstatus PAID atau Settle Rejected yang dapat diajukan realisasi.');
        }

        // Pengajuan dana baru menempuh review atasan di halaman tiket dulu.
        if (\App\Models\TroubleTicketRequest::settlementViaTicket($cr->id)) {
            return back()->with('error', 'Settlement CR ini dikirim dari halaman Trouble Ticket, lewat review atasan teknisi.');
        }

        if ($error = $this->validateRealizationInput($request, $cr)) {
            return $error;
        }

        if (\App\Models\TroubleTicketRequest::settlementInReview($cr->id)) {
            return back()->with('error', 'Settlement sedang direview atasan dan tidak dapat diajukan dari sini.');
        }

        DB::transaction(function () use ($request, $cr) {
            $map = $this->syncRealizationData($request, $cr);
            app(\App\Services\SettlementReceiptService::class)->sync($request, $cr->refresh(), $map);

            /*
            | Periksa ulang SETELAH sync: validasi di atas menghitung salinan PDF
            | kwitansi sebagai Lampiran Item, padahal sync() menggugurkan dan
            | melepas salinan itu bila nilai barisnya diubah. Tanpa cek ini CR
            | bisa REALIZED dengan baris Settle > 0 tanpa lampiran. Exception
            | membatalkan seluruh transaksi.
            */
            $tanpaLampiran = $cr->realizations()->where('total', '>', 0)->whereDoesntHave('attachments')
                ->pluck('description')->map(fn ($d) => '"' . $d . '"')->implode(', ');
            if ($tanpaLampiran !== '') {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(back()->withInput()->with(
                    'error',
                    '"Lampiran Item" wajib untuk baris bernilai Settle > 0. Kwitansi digital gugur karena nilai baris diubah; lampirkan nota untuk baris: ' . $tanpaLampiran . '.'
                ));
            }

            $dariStatus = $cr->status;
            $cr->update([
                'status'      => 'REALIZED',
                'realized_at' => now(),
            ]);

            /*
            | Catatan log menggabungkan angka dan keterangan pengaju supaya
            | approver membaca keduanya dalam satu baris riwayat — bukan cuma
            | total, yang tidak pernah menjelaskan KENAPA settlement-nya begitu.
            */
            $catatan = 'Total realisasi: ' . number_format($cr->fresh()->total_realization, 0, ',', '.');

            if ($ket = trim((string) $request->input('settlement_notes'))) {
                $catatan .= ' — Keterangan: ' . $ket;
            }

            $cr->addLog($dariStatus, 'REALIZED', 'realization_submitted', $catatan);
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

    /**
     * User ini pemilik settlement CR yang lahir dari tiket?
     *
     * Dipakai sebagai PELEBARAN SEMPIT gerbang settlement: pemilik adalah pemohon
     * pengajuan dana, karena dialah yang memegang uangnya, walau tiketnya sudah
     * dialihkan. CR data lama tanpa pengajuan mengikuti teknisi tiket.
     */
    private function isTicketTechnicianOfCr(User $user, BudgetRequest $cr): bool
    {
        return app(\App\Services\TroubleTicketWorkflow::class)->isSettlementOwner($user, $cr);
    }

    /** Ringkasan tiket asal CR untuk drawer; null bila CR bukan dari tiket. */
    private function ticketOfCr(BudgetRequest $cr): ?array
    {
        $ticket = TroubleTicket::forCostRequest($cr->id)->first(['id', 'ticket_number']);

        if (! $ticket) {
            return null;
        }

        $fr = \App\Models\TroubleTicketRequest::where('budget_request_id', $cr->id)->first();

        return [
            'number'  => $ticket->ticket_number,
            'request' => $fr?->display_number,
            'url'     => TroubleTicket::visibleTo(Auth::user())->whereKey($ticket->id)->exists()
                ? route('trouble-ticket.show', $ticket) . '?tab=pengajuan'
                : null,
        ];
    }

    /**
     * Menyimpan tanda tangan penerima dana per baris settlement.
     *
     * Dikirim sebagai data URL PNG pada `signatures[<indeks baris form>]`.
     * Tiap coretan disimpan ke disk `secure` dan dicatat sebagai LAMPIRAN
     * GLOBAL CR (bukan lampiran item) — keputusan 20 Okt 2026, supaya bukti
     * serah-terima terkumpul di satu tempat dan tidak tercampur dengan
     * nota/bukti belanja per item.
     *
     * Sejak Settlement Custom COA (25 Okt 2026) nama berkasnya dikunci ke ID
     * baris realisasi hasil syncRealizations() ($map: indeks form -> baris),
     * lihat signatureName(): baris yang dipecah/ditambah/dihapus tidak lagi
     * menggeser tanda tangan ke baris lain. Tanda tangan format lama (nomor
     * urut item) dinamai ulang sekali ke baris pertama item asalnya.
     *
     * Tanda tangan baris yang sama DIGANTI bila dikirim ulang, jadi menyimpan
     * draft berkali-kali tidak menumpuk berkas.
     *
     * @param  array<int|string, BudgetRequestRealization>  $map
     */
    private function storeSettlementSignatures(Request $request, BudgetRequest $cr, array $map): void
    {
        // Format lama "ttd-<n>-<kode>.png" -> "ttd-r<id baris>-<kode>.png".
        $itemPerNomor = array_flip($this->legacySignatureNumbers($cr));
        $cr->realizationAttachments()->where('original_name', 'like', 'ttd-%-' . $cr->code . '.png')->get()
            ->each(function ($att) use ($cr, $map, $itemPerNomor) {
                if (! preg_match('/^ttd-(\d+)-/', (string) $att->original_name, $m)) {
                    return;
                }

                $detailId = $itemPerNomor[(int) $m[1]] ?? null;
                $baris    = $detailId
                    ? collect($map)->first(fn ($r) => (int) $r->request_detail_id === (int) $detailId)
                    : null;

                if ($baris && ! $cr->realizationAttachments()->where('original_name', $this->signatureName($cr, $baris->id))->exists()) {
                    $att->update(['original_name' => $this->signatureName($cr, $baris->id)]);
                }
            });

        foreach ((array) $request->input('signatures', []) as $i => $raw) {
            $raw = (string) $raw;

            if (! str_starts_with($raw, 'data:image/png;base64,')) {
                continue;
            }

            // Baris form yang tidak tersimpan (kosong) tidak punya tanda tangan.
            $baris = $map[$i] ?? null;
            if (! $baris) {
                continue;
            }

            $png = base64_decode(substr($raw, strlen('data:image/png;base64,')), true);

            if ($png === false) {
                continue;
            }

            $nama = $this->signatureName($cr, $baris->id);

            // Tanda tangan lama untuk baris ini dibuang lebih dulu.
            $cr->realizationAttachments()->where('original_name', $nama)->get()
                ->each(function ($att) {
                    Storage::disk('secure')->delete($att->path);
                    $att->delete();
                });

            $path = 'settlement_signatures/' . Str::random(40) . '.png';
            Storage::disk('secure')->put($path, $png);

            $cr->realizationAttachments()->create([
                'original_name' => $nama,
                'stored_name'   => basename($path),
                'path'          => $path,
                'mime_type'     => 'image/png',
                'size'          => strlen($png),
                'uploaded_by'   => Auth::id(),
            ]);
        }
    }

    /** CR ini lahir dari plan visit sebuah Trouble Ticket? */
    private function crBerasalDariTiket(BudgetRequest $cr): bool
    {
        if (! Schema::hasColumn('trouble_tickets', 'budget_request_id')) {
            return false;
        }

        return TroubleTicket::forCostRequest($cr->id)->exists();
    }

    /**
     * Simpan draft Settlement TANPA mengajukan — status CR tetap PAID atau
     * SETTLE_REJECTED, pengaju bisa melanjutkan mengisi nanti lalu baru
     * menekan "Ajukan" (submitRealization). Draft boleh belum lengkap:
     * Lampiran Item wajib (baris Settle > 0) baru ditegakkan saat Submit,
     * bukan saat menyimpan.
     */
    public function saveRealizationDraft(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $cr = $this->findVisibleCr($id);

        /* Teknisi boleh menyimpan draft settlement — hanya untuk CR yang lahir
           dari plan visit tiket yang ditugaskan kepadanya (20 Okt 2026).
           Status CR tidak berubah, jadi tidak ada tahap alur uang yang
           dilewati; pengajuan resminya tetap dari modul Cost Request. */
        if (! $user->hasRole(['superadmin', 'manager', 'supervisor'])
            && ! $this->isTicketTechnicianOfCr($user, $cr)) {
            abort(403, 'Anda tidak memiliki wewenang untuk menyimpan realisasi CR ini.');
        }

        if (! in_array($cr->status, ['PAID', 'SETTLE_REJECTED'], true)) {
            return back()->with('error', 'Hanya CR berstatus PAID atau Settle Rejected yang dapat disimpan.');
        }

        if (\App\Models\TroubleTicketRequest::settlementInReview($cr->id)) {
            return back()->with('error', 'Settlement sedang direview atasan dan tidak dapat diubah.');
        }

        // CR dari pengajuan dana baru: hanya pemilik dana yang mengisi settlement,
        // supaya nilai baris tidak berubah tanpa sepengetahuan pemegang uang.
        if (\App\Models\TroubleTicketRequest::settlementViaTicket($cr->id)
            && ! $user->hasRole('superadmin') && ! $this->isTicketTechnicianOfCr($user, $cr)) {
            return back()->with('error', 'Settlement CR ini diisi pemohon dana dari halaman Trouble Ticket.');
        }

        $request->validate([
            'realizations.*.transaction_date' => 'nullable|date',
            'realizations.*.vendor_name'      => 'nullable|string|max:150',
            'recipient_names.*'               => 'nullable|string|max:150',
        ]);
        app(\App\Services\SettlementReceiptService::class)->assertReady($request);

        if ($error = $this->validateRealizationInput($request, $cr, false)) {
            return $error;
        }

        // Bukti per baris (lampiran + e-sign) hanya untuk draft dari modal tiket,
        // yang punya kolom tanda tangan. Form Settlement di Cost Request tidak
        // mewajibkannya lagi (29 Okt 2026).
        if ($request->boolean('from_ticket') && ($error = $this->validateSettlementEvidence($request, $cr))) {
            return $error;
        }

        DB::transaction(function () use ($request, $cr) {
            // Dikunci: Kirim Settlement yang bersamaan menunggu, lalu dicek ulang di sini.
            \App\Models\TroubleTicketRequest::where('budget_request_id', $cr->id)->lockForUpdate()->first();
            if (\App\Models\TroubleTicketRequest::settlementInReview($cr->id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['realizations' => 'Settlement sedang direview atasan dan tidak dapat diubah.']);
            }

            $map = $this->syncRealizationData($request, $cr);
            $this->storeSettlementSignatures($request, $cr, $map);
            app(\App\Services\SettlementReceiptService::class)->sync($request, $cr->refresh(), $map);
        });

        // Permintaan dari modal tiket cukup kembali ke halaman asalnya.
        if ($request->boolean('from_ticket')) {
            return back()->with('success', 'Draft Settlement tersimpan. Status CR belum berubah.');
        }

        // Kembali ke daftar Cost Request biasa (tanpa membuka drawer detail) —
        // draft bisa dilanjutkan lagi kapan saja lewat aksi Settlement.
        return redirect()->route('budget-request.index')
            ->with('success', 'Draft Settlement tersimpan. Status CR belum berubah — tekan "Ajukan" saat sudah siap.');
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
        // Temuan F-4 (2026-11-07): hanya Budget Plan yang terlihat oleh user
        // (aturan sama dengan daftar & detail Budget Plan). Yang tidak terlihat
        // dijawab seperti Budget Plan tidak ada — nilai & sisanya tidak dibuka.
        $bpQuery = BudgetPlan::whereKey((int) $request->budget_plan_id);
        $this->scopeVisibleTo($bpQuery);
        $bp     = $bpQuery->first();
        $amount = (int) $request->amount;
        $crId   = $request->cr_id ? (int) $request->cr_id : null;

        if (!$bp) return response()->json(['is_nob' => false, 'remaining' => 0]);

        $isNob     = $bp->isNob($amount, $crId);
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

    /**
     * Item batch Treasury yang membayar pengajuan awal CR ini (Second Payment
     * dikecualikan). Dipakai bersama oleh kolom export Transaction ID & Account
     * supaya keduanya selalu merujuk batch yang sama.
     */
    private function payingTransactionItem(BudgetRequest $cr): ?DailyTransactionItem
    {
        return $cr->dailyTransactionItems
            ->whereNull('second_payment_id')
            ->where('status', 'INCLUDED')
            ->sortByDesc('id')
            ->first();
    }

    public function export(Request $request)
    {
        $query = BudgetRequest::with([
            'type', 'cluster', 'budgetPlan.project', 'budgetPlan.costCenter',
            'applicant', 'beneficiary.pic', 'details.account', 'statusLogs', 'realizations',
            'dailyTransactionItems.dailyTransaction.sourceFund', 'secondPayments',
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
            'tanggal'   => ['Tanggal',      16, fn ($cr) => $cr->created_at?->format('d-m-Y H:i')],
            'paid_at'   => ['Tanggal Paid', 16, fn ($cr) => $cr->paid_at?->format('d-m-Y H:i') ?? '-'],
            'pengaju'   => ['Requestor',    16, fn ($cr) => strtoupper($cr->applicant?->username ?? '-')],
            'penerima'  => ['Beneficiary',  20, fn ($cr) => $cr->beneficiary?->nickname ?? '-'],
            'request'   => ['Request',      16, fn ($cr) => (int) $cr->amount],
            'realisasi' => ['Settlement',   16, fn ($cr) => (int) $cr->total_realization],
            'refund'    => ['Refund',       16, fn ($cr) => (int) $cr->refundAmount()],
            'claim'     => ['Expense Claim', 16, fn ($cr) => (int) $cr->expenseClaimAmount()],
            'pic'       => ['PIC',          20, fn ($cr) => $cr->beneficiary?->pic?->pic ?? '-'],
            /*
            | Cost = biaya yang benar-benar diakui:
            |   Cash Advance -> nilai Settlement bila settlement sudah diajukan
            |                   (termasuk Settle 0); belum diajukan -> Request.
            |   Selain CA    -> selalu nilai Request.
            | Sumber tunggal: BudgetRequest::realizedCostValue().
            */
            'cost'      => ['Cost',         16, fn ($cr) => $cr->realizedCostValue()],
            'status'    => ['Status',       14, fn ($cr) => $cr->status],
            // Close Date = tanggal Second Payment (pembayaran ke-2) dibayar; '-' bila tidak ada.
            'close_date' => ['Close Date',  16, fn ($cr) => $cr->secondPayments->where('status', 'PAID')
                ->sortByDesc('paid_at')->first()?->paid_at?->format('d-m-Y H:i') ?? '-'],
            // Hanya untuk export tanpa cols (bentuk lengkap lama):
            'type'      => ['Tipe',         16, fn ($cr) => $cr->type?->name ?? '-'],
            'sumber'    => ['Sumber Dana',  28, fn ($cr) => $cr->budgetPlan?->isProjectBudget()
                ? ($cr->budgetPlan->project?->name ?? '-')
                : ($cr->budgetPlan?->costCenter?->name ?? '-')],
            'nob'       => ['NOB',           8, fn ($cr) => $cr->is_nob ? 'YA' : '-'],
            /*
            | Transaction ID = kode batch Treasury yang membayar CR ini
            | (daily_transactions.code, mis. 202609280010009). Dulu diambil
            | dari catatan log 'paid', padahal kolom itu hanya terisi kalau
            | Treasury kebetulan mengetik sesuatu — hasilnya hampir selalu
            | kosong. Baris Second Payment dikecualikan: yang dicari adalah
            | pembayaran pengajuan awalnya.
            |
            | Account = No. Rekening proses pembayaran (Treasury > Source Fund >
            | No Account), bukan Chart of Account rincian — diambil dari batch
            | Treasury yang sama dengan Transaction ID di atas.
            */
            'account'   => ['Account',      28, fn ($cr) => $this->payingTransactionItem($cr)?->dailyTransaction?->sourceFund?->no_account ?? '-'],
            'costtype'  => ['Cost Type',    16, fn ($cr) => $cr->budgetPlan?->isProjectBudget()
                ? 'Variable Cost'
                : (self::ACCOUNT_CATEGORY[strtoupper($cr->budgetPlan?->costCenter?->type ?? '')] ?? 'Overhead')],
            'txid'      => ['Transaction ID', 22, function ($cr) {
                $item = $this->payingTransactionItem($cr);

                return $item?->dailyTransaction?->code
                    ?? ($cr->statusLogs->firstWhere('action_type', 'paid')?->notes ?: '-');
            }],
        ];

        /*
        | Export SELALU memuat seluruh kolom, tidak lagi mengikuti kolom yang
        | kebetulan tampil di layar. Menyembunyikan kolom di tabel tujuannya
        | merapikan pandangan, bukan memangkas isi berkas — dan file inilah
        | yang dipakai untuk rekap di luar aplikasi, jadi kolom yang hilang
        | tidak bisa dipulihkan penerimanya.
        */
        $activeCols = [
            'code', 'tanggal', 'paid_at', 'desc', 'type', 'sumber',
            'penerima', 'pic', 'pengaju',
            'request', 'realisasi', 'refund', 'claim', 'cost',
            'status', 'close_date', 'nob', 'txid', 'account', 'costtype',
        ];

        foreach ($activeCols as $i => $key) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . '1', $columnDefs[$key][0]);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getColumnDimension($col)->setWidth($columnDefs[$key][1]);
            // Transaction ID & No. Rekening berupa angka panjang (mis.
            // 202609280010009) — kalau dibiarkan bertipe numerik, Excel
            // menampilkannya sebagai notasi ilmiah (2,02607E+14). Kedua kolom
            // diformat teks sejak header.
            if (in_array($key, ['txid', 'account'], true)) {
                $sheet->getStyle($col . ':' . $col)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            }
        }

        $row = 2;
        foreach ($budgetRequests as $cr) {
            foreach ($activeCols as $i => $key) {
                $col   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $value = $columnDefs[$key][2]($cr);
                if (in_array($key, ['txid', 'account'], true)) {
                    $sheet->setCellValueExplicit($col . $row, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($col . $row, $value);
                }
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
    | Sejak Settlement Custom COA (25 Okt 2026) template berisi baris form
    | settlement apa adanya (prefill 1:1 per item, atau baris draft yang sudah
    | dipecah) + kolom Budget Detail ID (Sub COA). Baris boleh ditambah:
    |   - Request Detail ID diisi  -> baris milik item asal itu (pecahan);
    |   - Request Detail ID kosong -> baris tambahan, Budget Detail ID wajib.
    | Budget Detail ID harus sub-item Budget Plan CR ini (daftar di sheet
    | Referensi); ID dari Budget Plan lain ditolak.
    |--------------------------------------------------------------------------
    */

    public function downloadSettlementTemplate($id)
    {
        $cr = $this->findVisibleCr($id, ['details', 'realizations']);

        $subItems = $cr->budget_plan_id
            ? \App\Models\BudgetPlanDetail::where('budget_plan_id', $cr->budget_plan_id)->with('account')->orderBy('id')->get()->keyBy('id')
            : collect();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rincian Settlement');

        $headers = ['No', 'Request Detail ID', 'Budget Detail ID', 'Sub COA', 'Description', 'Requested', 'Actual Used'];
        foreach ($headers as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row  = 2;
        $seen = [];
        foreach ($cr->settlementFormRows()->values() as $i => $r) {
            $d  = $r['detail'];
            $bd = $r['budget_detail_id'] ? $subItems->get($r['budget_detail_id']) : null;

            $sheet->setCellValue('A' . $row, $i + 1);
            if ($d) {
                $sheet->setCellValueExplicit('B' . $row, $d->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            if ($r['budget_detail_id']) {
                $sheet->setCellValueExplicit('C' . $row, $r['budget_detail_id'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->setCellValue('D' . $row, $bd ? $this->subItemLabel($bd) : '-');
            $sheet->setCellValue('E' . $row, $d?->description ?? 'Baris tambahan');
            // Requested hanya di baris pertama tiap item supaya tidak terbaca ganda.
            $sheet->setCellValue('F' . $row, ($d && ! isset($seen[$d->id])) ? (int) $d->total : 0);
            $sheet->setCellValue('G' . $row, (int) $r['amount']);
            if ($d) {
                $seen[$d->id] = true;
            }
            $row++;
        }

        $sheet->getComment('B1')->getText()
            ->createTextRun('ID item pengajuan asal. Kosongkan untuk baris tambahan. Jangan ubah ID yang sudah ada.');
        $sheet->getComment('C1')->getText()
            ->createTextRun('ID Sub COA dari sheet Referensi. Wajib untuk baris tambahan; kosong = Sub COA item asal.');

        foreach (['A' => 6, 'B' => 16, 'C' => 16, 'D' => 40, 'E' => 40, 'F' => 16, 'G' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Sheet referensi: daftar Sub COA (sub-item Budget Plan CR ini).
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');
        $refSheet->setCellValue('A1', 'Budget Detail ID');
        $refSheet->setCellValue('B1', 'Sub COA');
        $refSheet->getStyle('A1:B1')->getFont()->setBold(true);
        foreach ($subItems->values() as $i => $bd) {
            $refSheet->setCellValueExplicit('A' . ($i + 2), $bd->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $refSheet->setCellValue('B' . ($i + 2), $this->subItemLabel($bd));
        }
        $refSheet->getColumnDimension('A')->setWidth(18);
        $refSheet->getColumnDimension('B')->setWidth(50);

        $spreadsheet->setActiveSheetIndex(0);

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
        $validDetails = $cr->details->where('status', '!=', 'TAKEOUT')->keyBy('id');
        $subItems     = $cr->budget_plan_id
            ? \App\Models\BudgetPlanDetail::where('budget_plan_id', $cr->budget_plan_id)->with('account')->get()->keyBy('id')
            : collect();

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Rincian Settlement') ?? $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json([
                'rows' => [], 'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()],
            ], 422);
        }

        /*
        | Kolom dicari lewat judulnya, bukan posisi — template lama (tanpa
        | Budget Detail ID: No, Request Detail ID, Description, Requested,
        | Actual Used) tetap bisa diimpor.
        */
        $judul   = array_map(fn ($h) => strtoupper(trim((string) $h)), $rows[0] ?? []);
        $kolom   = function (string $nama, ?int $bawaan) use ($judul) {
            $k = array_search($nama, $judul, true);

            return $k !== false ? $k : $bawaan;
        };
        $cDetail = $kolom('REQUEST DETAIL ID', 1);
        $cSub    = $kolom('BUDGET DETAIL ID', null);
        $cDesc   = $kolom('DESCRIPTION', 2);
        $cActual = $kolom('ACTUAL USED', 4);

        $result = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // header

            $detailId   = $row[$cDetail] ?? null;
            $subId      = $cSub !== null ? ($row[$cSub] ?? null) : null;
            $desc       = $row[$cDesc] ?? null;
            $actualUsed = $row[$cActual] ?? null;
            if (blank($detailId) && blank($subId) && blank($actualUsed)) continue;

            $rowNum   = $i + 1;
            $detailId = blank($detailId) ? null : (int) $detailId;
            $subId    = blank($subId) ? null : (int) $subId;

            if ($detailId !== null && ! $validDetails->has($detailId)) {
                $errors[] = "Baris {$rowNum}: Request Detail ID \"{$detailId}\" tidak sesuai dengan CR ini (jangan ubah ID yang sudah ada).";
                continue;
            }

            if ($subId !== null && ! $subItems->has($subId)) {
                $errors[] = "Baris {$rowNum}: Budget Detail ID \"{$subId}\" bukan Sub COA Budget Plan CR ini (lihat sheet Referensi).";
                continue;
            }

            if ($detailId === null && $subId === null) {
                $errors[] = "Baris {$rowNum}: baris tambahan (tanpa Request Detail ID) wajib mengisi Budget Detail ID.";
                continue;
            }

            if ($subId === null) {
                $asal  = $validDetails->get($detailId);
                $subId = $asal?->budget_detail_id ? (int) $asal->budget_detail_id : null;
            }
            $bd = $subId ? $subItems->get($subId) : null;

            $amountNum = is_numeric($actualUsed) ? (int) round((float) $actualUsed) : (int) $this->normalizeMoney((string) $actualUsed);

            $result[] = [
                'row'               => $rowNum,
                'request_detail_id' => $detailId,
                'budget_detail_id'  => $subId,
                'sub_coa_label'     => $bd ? $this->subItemLabel($bd) : null,
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
