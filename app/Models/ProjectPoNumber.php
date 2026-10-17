<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPoNumber extends Model
{
    protected $fillable = [
        'project_id',
        'po_number',
        'nominal',
        'release_date',
        'status',
        'input_by',
        'no_bast',
        'tanggal_bast',
        'nilai_bast',
        'bast_notes',
        'bast_submitted_by',
        'bast_submitted_at',
    ];

    protected $casts = [
        'nominal'           => 'integer',
        'release_date'      => 'date',
        'tanggal_bast'      => 'date',
        'nilai_bast'        => 'integer',
        'bast_submitted_at' => 'datetime',
    ];

    /**
     * RELEASED -> PARTIAL -> BAST_SUBMITTED -> INVOICED -> PAID
     * PARTIAL = sudah ada BAST yang di-upload tapi belum ditandai lengkap
     * (1 No. PO bisa punya 1-4 dokumen BAST) — baru jadi BAST_SUBMITTED
     * setelah user eksplisit konfirmasi via tombol "Tandai BAST Lengkap".
     */
    public const STATUSES = ['RELEASED', 'PARTIAL', 'BAST_SUBMITTED', 'INVOICED', 'PAID'];

    /**
     * PO header `value` = SUM(nominal) semua No. PO — sinkron otomatis di
     * satu titik ini (bukan diulang di tiap controller) supaya tidak ada
     * jalur create/update/delete yang lupa memanggilnya.
     */
    protected static function booted(): void
    {
        static::saved(fn (ProjectPoNumber $po) => $po->project?->syncValueFromPoNumbers());
        static::deleted(fn (ProjectPoNumber $po) => $po->project?->syncValueFromPoNumbers());
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_po_number_id')->orderBy('id');
    }

    public function bastAttachments()
    {
        return $this->attachments()->where('type', 'po_number_bast');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function bastSubmittedBy()
    {
        return $this->belongsTo(User::class, 'bast_submitted_by');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'project_po_number_id');
    }

    /*
    |--------------------------------------------------------------------------
    | KALKULASI
    |--------------------------------------------------------------------------
    */

    /**
     * Sisa nominal No. PO ini yang belum ditagihkan (exclude invoice tertentu,
     * mis. diri sendiri saat edit). Dibandingkan dari billed_amount (nominal
     * SETELAH persentase penagihan), bukan subtotal — karena satu No. PO bisa
     * ditagih bertahap (Termin 1, 2, 3, ...) dengan baris detail yang merujuk
     * ke scope penuh yang sama tiap kali; subtotal saja tidak mencerminkan
     * berapa yang benar-benar sudah tertagih.
     */
    public function remainingValue(?int $excludeInvoiceId = null): int
    {
        $query = $this->invoices()->where('status', '!=', 'CANCELLED');
        if ($excludeInvoiceId) {
            $query->where('id', '!=', $excludeInvoiceId);
        }
        $invoiced = (int) $query->sum('billed_amount');

        return (int) $this->nominal - $invoiced;
    }

    public function getRemainingValueFormattedAttribute(): string
    {
        return number_format($this->remainingValue(), 0, ',', '.');
    }

    /**
     * Sinkron status "sudah ditagih penuh?" No. PO ini berdasarkan remainingValue().
     * Satu No. PO bisa punya beberapa invoice (Termin 1, 2, 3, ...) — statusnya
     * baru pindah ke INVOICED kalau seluruh nominal sudah tertagih, dan turun
     * lagi ke BAST_SUBMITTED kalau ada invoice yang dibatalkan/dihapus sehingga
     * masih ada sisa. Tidak menyentuh status di luar tahap penagihan (RELEASED/PAID).
     */
    public function syncInvoiceStatus(): void
    {
        if (!in_array($this->status, ['PARTIAL', 'BAST_SUBMITTED', 'INVOICED'], true)) {
            return;
        }

        $newStatus = $this->remainingValue() <= 0 ? 'INVOICED' : 'BAST_SUBMITTED';

        if ($newStatus !== $this->status) {
            $this->update(['status' => $newStatus]);
        }

        // Tagihan berubah (termasuk penagihan sebagian yang tidak mengubah status No. PO)
        // -> status header PO ikut dihitung ulang. poNumbers dibuang dulu supaya
        // agregat dibaca dari status terbaru, bukan relasi yang sudah termuat.
        $project = $this->project;
        $project?->unsetRelation('poNumbers');
        $project?->syncStatusFromPoNumbers();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getNominalFormattedAttribute(): string
    {
        return number_format($this->nominal, 0, ',', '.');
    }

    public function getNilaiBastFormattedAttribute(): ?string
    {
        return $this->nilai_bast === null ? null : number_format($this->nilai_bast, 0, ',', '.');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'RELEASED'       => 'secondary',
            'PARTIAL'        => 'info',
            'BAST_SUBMITTED' => 'primary',
            'INVOICED'       => 'warning',
            'PAID'           => 'success',
            default          => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'RELEASED'       => 'Released',
            'PARTIAL'        => 'Partial',
            'BAST_SUBMITTED' => 'BAST',
            'INVOICED'       => 'Invoiced',
            'PAID'           => 'Paid',
            default          => $this->status,
        };
    }
}
