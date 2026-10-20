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

    public function basts()
    {
        return $this->hasMany(ProjectPoBast::class, 'project_po_number_id')->orderBy('tanggal_bast')->orderBy('id');
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
     * Sinkron status No. PO dari BAST aktif (belum di-soft-delete) — satu method
     * untuk semua jalur (submit/hapus BAST, buat/ubah/batal/hapus invoice, bayar):
     *
     *  - tanpa BAST aktif                          -> RELEASED
     *  - ada BAST, belum "Tandai Lengkap"          -> PARTIAL
     *  - sudah ditandai lengkap (BAST_SUBMITTED)   -> BAST_SUBMITTED
     *      semua BAST punya invoice aktif          -> INVOICED
     *      + semua invoice aktif itu PAID          -> PAID
     *
     * "Lengkap" tidak disimpan di kolom tersendiri: ia melekat pada status
     * BAST_SUBMITTED/INVOICED/PAID (hanya tombol Tandai Lengkap yang menaikkannya).
     * Invoice CANCELLED tidak dihitung. No. PO lama yang sama sekali tidak punya
     * baris BAST tetap memakai perhitungan lama (sisa nominal) agar tidak berubah.
     */
    public function syncInvoiceStatus(): void
    {
        if (!$this->usesBastBilling()) {
            $this->syncInvoiceStatusLegacy();

            return;
        }

        $this->refresh();
        $basts   = $this->basts()->get();
        $lengkap = in_array($this->status, ['BAST_SUBMITTED', 'INVOICED', 'PAID'], true);

        // Pembacaan invoice hanya dilakukan bila memang menentukan hasil.
        $semuaTertagih = $lengkap && $basts->isNotEmpty() && $basts->every(fn ($b) => $b->isBilled());
        $semuaPaid     = $semuaTertagih && $basts->every(
            fn ($b) => $b->activeInvoices()->get()->every(fn ($i) => $i->status === 'PAID')
        );

        $baru = self::tentukanStatus($this->status, $basts->count(), $semuaTertagih, $semuaPaid);

        if ($baru !== $this->status) {
            $this->update(['status' => $baru]);
        }

        $this->syncProjectStatus();
    }

    /**
     * Aturan status berbasis BAST sebagai fungsi murni (tanpa baca/tulis DB), dipakai
     * syncInvoiceStatus() dan laporan perkiraan status (invoice:split-synthetic-bast).
     * "Lengkap" diturunkan dari $statusSaatIni (BAST_SUBMITTED/INVOICED/PAID).
     */
    public static function tentukanStatus(string $statusSaatIni, int $jumlahBast, bool $semuaTertagih, bool $semuaPaid): string
    {
        $lengkap = in_array($statusSaatIni, ['BAST_SUBMITTED', 'INVOICED', 'PAID'], true);

        if ($jumlahBast === 0) {
            return 'RELEASED';
        }
        if (!$lengkap) {
            return 'PARTIAL';
        }
        if (!$semuaTertagih) {
            return 'BAST_SUBMITTED';
        }

        return $semuaPaid ? 'PAID' : 'INVOICED';
    }

    /**
     * Ada invoice lama yang masih aktif (bukan CANCELLED, bukan soft-deleted) pada No. PO ini
     * tetapi belum tertaut ke BAST apa pun? BAST pada No. PO seperti itu adalah BAST sintetis
     * hasil backfill (satu baris agregat), jadi belum bisa dijadikan dasar status maupun
     * penagihan baru sampai invoice lamanya ditautkan (invoice:link-bast).
     * $kecualiInvoiceId: abaikan satu invoice (mis. draft lama yang sedang diedit).
     */
    public function punyaInvoiceLamaBelumTertaut(?int $kecualiInvoiceId = null): bool
    {
        return $this->invoices()
            ->tap(fn ($q) => self::batasInvoiceLamaBelumTertaut($q))
            ->when($kecualiInvoiceId, fn ($q) => $q->where('id', '!=', $kecualiInvoiceId))
            ->exists();
    }

    /**
     * Batasan query invoice yang dimaksud punyaInvoiceLamaBelumTertaut(): aktif
     * (bukan CANCELLED; soft-deleted dikecualikan global scope Invoice) dan belum
     * tertaut BAST. Satu sumber definisi, dipakai juga oleh scopeTanpaInvoiceLamaBelumTertaut().
     */
    public static function batasInvoiceLamaBelumTertaut($query)
    {
        return $query->where('status', '!=', 'CANCELLED')->whereNull('project_po_bast_id');
    }

    /** No. PO yang TIDAK punya invoice lama aktif yang belum tertaut BAST. */
    public function scopeTanpaInvoiceLamaBelumTertaut($query)
    {
        return $query->whereDoesntHave('invoices', fn ($q) => self::batasInvoiceLamaBelumTertaut($q));
    }

    /**
     * Status dihitung dari BAST? Hanya bila No. PO pernah punya baris BAST (termasuk yang sudah
     * dihapus) DAN tidak ada invoice lama aktif yang belum tertaut; selain itu alur lama dipakai
     * (status tidak diturunkan dari BAST sintetis).
     */
    public function usesBastBilling(): bool
    {
        if ($this->punyaInvoiceLamaBelumTertaut()) {
            return false;
        }

        return ProjectPoBast::withTrashed()->where('project_po_number_id', $this->id)->exists();
    }

    /** Perhitungan lama (sisa nominal) untuk No. PO tanpa baris BAST. */
    private function syncInvoiceStatusLegacy(): void
    {
        if (!in_array($this->status, ['PARTIAL', 'BAST_SUBMITTED', 'INVOICED'], true)) {
            return;
        }

        $baru = $this->remainingValue() <= 0 ? 'INVOICED' : 'BAST_SUBMITTED';

        if ($baru !== $this->status) {
            $this->update(['status' => $baru]);
        }

        $this->syncProjectStatus();
    }

    private function syncProjectStatus(): void
    {
        // poNumbers dibuang dulu supaya agregat dibaca dari status terbaru,
        // bukan relasi yang sudah termuat.
        $project = $this->project;
        $project?->unsetRelation('poNumbers');
        $project?->syncStatusFromPoNumbers();
    }

    /**
     * Agregat kolom lama (dipakai invoice/laporan) dari BAST aktif: nilai_bast =
     * jumlah, no_bast/tanggal_bast = BAST aktif terakhir atau NULL bila tak ada.
     */
    public function recalculateBastAggregate(): void
    {
        $basts = $this->basts()->get();
        $akhir = $basts->sortBy([['tanggal_bast', 'asc'], ['id', 'asc']])->last();

        $this->update([
            'nilai_bast'   => (int) $basts->sum('nilai_bast'),
            'no_bast'      => $akhir?->no_bast,
            'tanggal_bast' => $akhir?->tanggal_bast,
        ]);
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
