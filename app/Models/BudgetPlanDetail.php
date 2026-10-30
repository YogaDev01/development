<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlanDetail extends Model
{
    protected $fillable = [
        'budget_plan_id',
        'type',
        'category',
        'account_id',
        'deskripsi',
        'amount',
        'qty',
        'unit',
        'total',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'qty'    => 'decimal:2',
        'total'  => 'integer',
    ];

    public const TYPES = [
        'main',
        'additional',
    ];

    public const CATEGORIES = [
        'Variable Cost',
        'Fixed Cost',
        'Overhead',
    ];

    public const UNITS = [
        'bulan', 'hari', 'kg', 'lembar', 'liter', 'lot', 'lumpsum',
        'meter', 'meter kubik', 'meter persegi', 'minggu', 'orang',
        'package', 'pcs', 'roll', 'sax', 'set', 'site', 'team',
        'ton', 'trip', 'unit',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->total, 0, ',', '.');
    }

    /** Cache in-memory per instance — lihat catatan di usedAmount(). */
    private ?int $usedAmountCache = null;

    /**
     * Nilai yang sudah terpakai, dihitung dari Cost Request berstatus PAID/REALIZED/CLOSED
     * yang menunjuk ke baris detail ini: pakai nilai realisasi bila sudah ada,
     * jika belum ada realisasi pakai nilai pengajuan (request).
     *
     * Dipanggil per SUB-ITEM di halaman breakdown Chart of Account (Todo List,
     * Daily Transaction, Cost Request, dst) — bisa puluhan kali per satu page
     * load. Di-cache per instance supaya remainingAmount() (yang memanggil
     * usedAmount() lagi) tidak menjalankan query yang sama dua kali. Dulu di
     * sini juga ada Schema::hasColumn() yang menjalankan query ke
     * information_schema pada SETIAP pemanggilan — dihapus karena kolom
     * budget_detail_id sudah permanen ada sejak migration lama, jadi
     * pengecekan itu cuma query terbuang yang ikut menyumbang slow query.
     */
    public function usedAmount(): int
    {
        if ($this->usedAmountCache !== null) {
            return $this->usedAmountCache;
        }

        /*
         | Sejak Settlement Custom COA (25 Okt 2026) nilainya dari
         | BudgetRequest::costUnits() — satu sumber per (CR, sub-item): CA yang
         | settlement-nya sudah diajukan memakai baris settlement yang Sub COA
         | efektifnya sub-item ini (termasuk baris yang dipindah ke sini, tidak
         | termasuk yang dipindah keluar); selainnya nilai pengajuan.
         */
        return $this->usedAmountCache = (int) $this->costLines()->sum('value');
    }

    /** Cache in-memory per instance — lihat costLines(). */
    private ?\Illuminate\Support\Collection $costLinesCache = null;

    /**
     * Unit biaya (BudgetRequest::costUnits()) milik sub-item ini dari CR yang
     * sudah cair (scopeDisbursed), masing-masing ditambah kunci
     * 'budget_request' (model CR-nya). Sumber "Used" sekaligus daftar riwayat
     * CR di balik sub-item ini — keduanya pasti sejalan.
     */
    public function costLines(): \Illuminate\Support\Collection
    {
        if ($this->costLinesCache !== null) {
            return $this->costLinesCache;
        }

        self::primeCostLines(collect([$this]));

        return $this->costLinesCache;
    }

    /**
     * Isi tampilan satu unit costLines() untuk daftar riwayat CR di balik
     * sub-item (tab Budget CR, modal PO, Daily Transaction). Unit biasa
     * tampil seperti dulu (catatan, harga & qty item pengajuan); unit dari
     * baris settlement yang dipindah ke sini / baris tambahan memakai
     * catatan baris settlement-nya dan harga = nilainya (qty 1).
     *
     * @param  array<string, mixed>  $unit
     * @return array{notes: string, unit_price: int, qty: float, amount: int, is_added: bool, is_moved: bool}
     */
    public static function costLineDisplay(array $unit): array
    {
        $rd      = $unit['request_detail'];
        $pindah  = $unit['is_added'] || $unit['is_moved'] || ! $rd;
        $catatan = $unit['realizations']->pluck('notes')->filter()->implode('; ');

        return [
            'notes'      => $pindah ? ($catatan ?: ($rd?->notes ?: '-')) : ($rd->notes ?: '-'),
            'unit_price' => $pindah ? (int) $unit['value'] : (int) $rd->amount,
            'qty'        => $pindah ? 1.0 : (float) $rd->qty,
            'amount'     => (int) $unit['value'],
            'is_added'   => (bool) $unit['is_added'],
            'is_moved'   => (bool) $unit['is_moved'],
        ];
    }

    /**
     * Muat costLines() untuk banyak sub-item sekaligus (satu set query untuk
     * seluruh koleksi, bukan per baris) — dipakai halaman yang menelusuri
     * semua sub-item sebuah Budget Plan.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $details
     */
    public static function primeCostLines(\Illuminate\Support\Collection $details): void
    {
        $details = $details->filter(fn ($d) => $d && $d->id && $d->costLinesCache === null);
        if ($details->isEmpty()) {
            return;
        }

        $ids = $details->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $crs = BudgetRequest::disbursed()
            ->where(function ($q) use ($ids) {
                // Item takeout tidak pernah menyumbang (sama seperti costRequestDetails()).
                $q->whereHas('details', fn ($d) => $d->whereIn('request_detail.budget_detail_id', $ids)->where('request_detail.status', '!=', 'TAKEOUT'));

                // Baris settlement yang dipindah/ditambahkan ke sub-item ini.
                if (BudgetRequestRealization::hasBudgetDetailColumn()) {
                    $q->orWhereHas('realizations', fn ($r) => $r->whereIn('budget_request_realizations.budget_detail_id', $ids));
                }
            })
            ->with(['type', 'beneficiary:id,nickname', 'details', 'realizations'])
            ->orderBy('id')
            ->get();

        $perDetail = [];
        foreach ($crs as $cr) {
            foreach ($cr->costUnits() as $unit) {
                if ($unit['budget_detail_id'] && in_array($unit['budget_detail_id'], $ids, true)) {
                    $perDetail[$unit['budget_detail_id']][] = $unit + ['budget_request' => $cr];
                }
            }
        }

        foreach ($details as $d) {
            $d->costLinesCache = collect($perDetail[(int) $d->id] ?? []);
        }
    }

    /**
     * Variance (Budget - Used). Sengaja TIDAK dibatasi ke 0 supaya kondisi NOB
     * (terpakai melebihi budget) tampil sebagai variance negatif yang jujur.
     */
    public function remainingAmount(): int
    {
        return ($this->total ?? 0) - $this->usedAmount();
    }

    /**
     * Relasi ke detail Cost Request yang menunjuk ke baris budget ini
     * (dipakai setelah Cost Request dimodifikasi untuk pick sub-item budget).
     * Item yang di-takeout salah satu approval TIDAK dihitung — tidak menyumbang
     * ke "Used" maupun daftar riwayat CR pada breakdown Chart of Account.
     */
    public function costRequestDetails()
    {
        return $this->hasMany(RequestDetail::class, 'budget_detail_id')
            ->where('request_detail.status', '!=', 'TAKEOUT');
    }
}