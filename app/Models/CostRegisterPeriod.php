<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pembebanan Expense per bulan (period = tanggal 1 bulan). Jumlah seluruh baris =
 * nilai Expense (nominal pengajuan; untuk Cash Advance disesuaikan ke realisasi saat
 * settlement disetujui). Setiap baris > 0. Uang = integer rupiah.
 */
class CostRegisterPeriod extends Model
{
    protected $fillable = ['cost_register_id', 'period', 'amount'];

    protected $casts = [
        'period' => 'date',
        'amount' => 'integer',
    ];

    public function costRegister()
    {
        return $this->belongsTo(CostRegister::class);
    }
}
