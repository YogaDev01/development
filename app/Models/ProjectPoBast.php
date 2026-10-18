<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu dokumen/submission BAST milik sebuah No. PO (project_po_numbers = header).
 * Kolom agregat lama di project_po_numbers tetap dipertahankan untuk laporan.
 */
class ProjectPoBast extends Model
{
    // Penghapusan BAST ditandai (deleted_by/delete_reason), bukan dilenyapkan.
    use SoftDeletes;

    protected $fillable = [
        'project_po_number_id',
        'no_bast',
        'tanggal_bast',
        'nilai_bast',
        'notes',
        'submitted_by',
        'deleted_by',
        'delete_reason',
        'origin',
        'split_from_bast_id',
        'split_run_id',
    ];

    /** Asal baris: BACKFILL (hasil migrasi), SPLIT_INVOICE / SPLIT_SISA (hasil pemecahan); NULL = submit normal. */
    public const ORIGIN_BACKFILL      = 'BACKFILL';
    public const ORIGIN_SPLIT_INVOICE = 'SPLIT_INVOICE';
    public const ORIGIN_SPLIT_SISA    = 'SPLIT_SISA';

    protected $casts = [
        'tanggal_bast' => 'date',
        'nilai_bast'   => 'integer',
    ];

    public function poNumber()
    {
        return $this->belongsTo(ProjectPoNumber::class, 'project_po_number_id');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_po_bast_id')->orderBy('id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** BAST induk (sintetis) tempat pecahan ini berasal; ikut dibaca walau induknya soft-deleted. */
    public function parent()
    {
        return $this->belongsTo(self::class, 'split_from_bast_id')->withTrashed();
    }

    public function splits()
    {
        return $this->hasMany(self::class, 'split_from_bast_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'project_po_bast_id');
    }

    /** Invoice yang masih berlaku (CANCELLED tidak dihitung). */
    public function activeInvoices()
    {
        return $this->invoices()->where('status', '!=', 'CANCELLED');
    }

    public function isBilled(): bool
    {
        return $this->activeInvoices()->exists();
    }
}
