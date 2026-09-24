<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastDetail extends Model
{
    protected $fillable = [
        'forecast_id',
        'description',
        // PO melekat di BARIS, bukan header — satu forecast (satu rentang waktu)
        // bisa memuat rencana untuk beberapa PO, atau tanpa PO sama sekali.
        'project_id',
        // Arah rencana: satu forecast bisa memuat penerimaan dan pengeluaran.
        'type',
        // Rekening melekat di baris, bukan di header — satu forecast bisa
        // menyentuh beberapa rekening.
        'source_fund_id',
        'amount',
        'actual_amount',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'actual_amount' => 'integer',
    ];

    public const TYPES = ['IN' => 'Masuk', 'OUT' => 'Keluar'];

    protected static function booted(): void
    {
        /*
        | Menjaga header tetap sinkron untuk perubahan satu baris dari mana pun.
        |
        | Saat menulis SEKUMPULAN baris sekaligus, hook ini justru menghitung
        | total di atas data yang masih separuh jadi — pemanggilnya mematikan
        | event selama loop lalu memanggil syncAmounts() sekali di akhir.
        | Lihat ForecastController::syncDetails().
        */
        static::saved(fn (ForecastDetail $d) => $d->forecast?->syncAmounts());
        static::deleted(fn (ForecastDetail $d) => $d->forecast?->syncAmounts());
    }

    public function forecast()
    {
        return $this->belongsTo(Forecast::class);
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Realisasi baris ini — beberapa kejadian, bukan satu angka.
     * Terbaru di atas: yang paling sering dicari adalah entri terakhir.
     */
    public function actualEntries()
    {
        return $this->hasMany(ForecastActualEntry::class)->latest('id');
    }

    /**
     * Hitung ulang actual_amount baris dari entri-entrinya.
     *
     * Kolomnya tetap ada (dan tetap menyimpan besaran tanpa tanda) supaya
     * Forecast::syncAmounts() dan seluruh tampilan lama tidak perlu tahu dari
     * mana angkanya berasal; yang berubah hanya siapa yang berhak mengisinya.
     */
    public function syncActual(): void
    {
        $this->update([
            'actual_amount' => (int) $this->actualEntries()->sum("amount"),
        ]);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return $this->type === 'IN' ? 'success' : 'danger';
    }

    /** Nilai bertanda — dipakai saat menjumlah rencana bersih. */
    public function getSignedAmountAttribute(): int
    {
        return $this->type === 'IN' ? $this->amount : -$this->amount;
    }

    /** Realisasi bertanda — alasannya sama dengan getSignedAmountAttribute(). */
    public function getSignedActualAmountAttribute(): int
    {
        return $this->type === 'IN' ? $this->actual_amount : -$this->actual_amount;
    }

    /**
     * RENCANA + REALISASI, keduanya bertanda menurut arah barisnya.
     *
     * Bentuk ini diminta langsung oleh pengguna, dan dua sifat itulah yang
     * dicarinya: angkanya BERTAMBAH setiap realisasi diisi (rumus selisih
     * justru menyusut), dan tandanya tetap mengikuti arah baris — baris Masuk
     * positif, baris Keluar negatif — sama seperti kolom Amount dan Actual di
     * barisnya sendiri.
     *
     *   MASUK  rencana  100, realisasi    0 ->  +100
     *   MASUK  rencana  100, realisasi  0,1 ->  +100,1
     *   KELUAR rencana  -50, realisasi    0 ->   -50
     *   KELUAR rencana  -50, realisasi  -30 ->   -80
     *
     * Perlu dicatat: ini bukan variance dalam arti akuntansi (selisih rencana
     * vs realisasi), melainkan jumlah keduanya. Kolomnya dibiarkan bernama
     * Variance sesuai permintaan.
     */
    public function getVarianceAttribute(): int
    {
        return $this->signed_amount + $this->signed_actual_amount;
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->signed_amount, 0, ',', '.');
    }

    public function getActualAmountFormattedAttribute(): string
    {
        return number_format($this->signed_actual_amount, 0, ',', '.');
    }
}
