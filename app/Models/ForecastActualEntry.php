<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu kejadian realisasi atas satu baris rencana.
 *
 * Realisasi tidak lagi berupa satu angka yang ditimpa: selama forecast ACTIVE
 * angkanya boleh ditambah berkali-kali, dan setiap penambahan wajib membawa
 * keterangan — lihat ForecastController::storeActual().
 */
class ForecastActualEntry extends Model
{
    protected $fillable = [
        'forecast_detail_id',
        'amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function detail()
    {
        return $this->belongsTo(ForecastDetail::class, 'forecast_detail_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Nilai bertanda — cerminan arah baris induknya.
     *
     * Realisasi atas baris KELUAR adalah uang yang pergi, jadi di layar pun
     * ia tampil negatif; menampilkannya positif membuat pengeluaran terbaca
     * seolah menambah kas.
     */
    public function getSignedAmountAttribute(): int
    {
        return $this->detail?->type === 'OUT' ? -$this->amount : $this->amount;
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->signed_amount, 0, ',', '.');
    }
}
