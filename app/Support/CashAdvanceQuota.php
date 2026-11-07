<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\BudgetRequest;

/**
 * Limit Cash Advance per PIC: satu sumber perhitungan, dipakai form Cost Request
 * dan Manager direct approval di Trouble Ticket. Aturannya: lihat komentar
 * "LIMIT CASH ADVANCE PER PIC" di BudgetRequest.
 */
class CashAdvanceQuota
{
    /**
     * Pemakaian limit CA milik PIC pemilik $beneficiaryId. Null kalau
     * beneficiary tidak ada, belum punya PIC, atau PIC-nya tidak mengisi
     * token maupun nominal (berarti tidak ada batas yang berlaku).
     */
    public static function usage(?int $beneficiaryId, ?int $excludeCrId = null): ?array
    {
        $ben = $beneficiaryId ? Beneficiary::with('pic')->find($beneficiaryId) : null;
        if (! $ben || ! $ben->pic_id) return null;

        // Batas dari Master PIC. Token hanya dipakai kalau isinya angka;
        // kosong/0 = aspek itu tanpa batas DI SINI. Sejak 2026-11-07 Cash
        // Advance sendiri mensyaratkan Token > 0 (eligibilityError()), jadi
        // "tanpa batas jumlah" hanya tersisa untuk tampilan kuota.
        $pic         = $ben->pic;
        $limitCount  = is_numeric($pic?->token) ? (int) $pic->token : null;
        $limitAmount = $pic?->nominal !== null ? (int) $pic->nominal : null;
        if (! $limitCount)  $limitCount  = null;
        if (! $limitAmount) $limitAmount = null;
        if ($limitCount === null && $limitAmount === null) return null;

        $names = BudgetRequest::CASH_ADVANCE_TYPE_NAMES;

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

        /*
        | Nominal: seluruh CR yang belum lepas — termasuk yang masih diproses
        | (Draft/Submitted/dst) supaya pipeline-nya tetap memesan pagu.
        |
        | Lepasnya SAMA dengan slot jumlah pengajuan: begitu approval settlement
        | tuntas di Second Approve. CR yang sudah lewat tahap itu lalu masuk
        | batch Treasury (statusnya kembali QUEUED untuk membayar Expense Claim)
        | juga tidak dihitung lagi — makanya dicek lewat jejak lognya, bukan
        | status saat ini.
        */
        $usedAmount = (int) $base()
            ->whereNotIn('budget_request.status', BudgetRequest::CA_QUOTA_RELEASED_STATUSES)
            ->where(function ($q) {
                // Lepas di Second Approve, dan tetap lepas saat klaimnya
                // menunggu dibayar Treasury (status kembali QUEUED).
                $q->where('budget_request.status', '!=', 'SECOND_APPROVED')
                  ->where(function ($w) {
                      $w->where('budget_request.status', '!=', 'QUEUED')
                        ->orWhereNotExists(function ($sq) {
                            $sq->selectRaw(1)
                               ->from('request_status_logs')
                               ->whereColumn('request_status_logs.budget_request_id', 'budget_request.id')
                               ->where('request_status_logs.status_to', 'SECOND_APPROVED');
                        });
                  });
            })
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

    /**
     * Syarat beneficiary Cash Advance (keputusan pemilik 2026-11-07, temuan F-5):
     * CA hanya untuk beneficiary kategori INTERNAL yang punya PIC, dan PIC itu
     * WAJIB punya Token (limit jumlah CA) berupa angka > 0. Token kosong/0 =
     * CA diblokir — sebelumnya berarti tanpa batas. Nominal tetap opsional
     * (kosong/0 = tanpa batas nominal, seperti sebelumnya).
     *
     * Berlaku di simpan/ubah draft dan submit (termasuk Manager direct approval
     * Trouble Ticket) karena semuanya lewat error(). CA yang sudah lewat submit
     * tidak disentuh. usage() TIDAK berubah, jadi peringatan & tampilan kuota
     * di form tetap sama.
     */
    public static function eligibilityError(?int $beneficiaryId): ?string
    {
        $ben = $beneficiaryId ? Beneficiary::with('pic')->find($beneficiaryId) : null;

        if (! $ben) {
            return 'Cash Advance wajib memilih beneficiary kategori Internal.';
        }

        if ($ben->category !== 'INTERNAL') {
            return 'Cash Advance hanya untuk beneficiary kategori Internal.';
        }

        if (! $ben->pic_id || ! $ben->pic) {
            return 'Cash Advance hanya untuk beneficiary yang memiliki PIC. Lengkapi PIC beneficiary "'
                . ($ben->nickname ?: $ben->complete_name) . '" di Master Beneficiary.';
        }

        $token = $ben->pic->token;
        if (! is_numeric($token) || (int) $token < 1) {
            return 'Token (limit jumlah Cash Advance) PIC "' . ($ben->pic->pic ?? '-') . '" belum diisi atau bukan angka lebih dari 0. '
                . 'Isi Token di Master PIC sebelum mengajukan Cash Advance.';
        }

        return null;
    }

    /** Limit CA terlampaui? Mengembalikan pesan error, atau null kalau lolos. */
    public static function error(?int $beneficiaryId, int $amount, ?int $excludeCrId = null): ?string
    {
        if ($msg = self::eligibilityError($beneficiaryId)) {
            return $msg;
        }

        $q = self::usage($beneficiaryId, $excludeCrId);
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
}
