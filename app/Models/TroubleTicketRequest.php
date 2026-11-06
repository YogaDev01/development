<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pengajuan biaya sebuah Trouble Ticket (pra-Cost Request).
 *
 * Terlepas dari Plan Visit: satu tiket boleh punya banyak pengajuan, termasuk
 * beberapa yang SUBMITTED bersamaan. Cost Request baru lahir (berstatus DRAFT)
 * saat supervisor meng-approve; sebelum itu pengajuan tidak menyentuh
 * BudgetConsumption maupun pagu Budget Plan. Uang = integer rupiah.
 *
 * Revisi menambah baris item BARU (revision + 1); baris lama tidak diubah atau
 * dihapus, jadi riwayat perubahan selalu bisa ditelusuri.
 */
class TroubleTicketRequest extends Model
{
    use SoftDeletes;

    public const DRAFT     = 'DRAFT';
    public const SUBMITTED = 'SUBMITTED';
    public const APPROVED  = 'APPROVED';
    public const REJECTED  = 'REJECTED';
    public const CANCELLED = 'CANCELLED';

    public const STATUSES = [self::DRAFT, self::SUBMITTED, self::APPROVED, self::REJECTED, self::CANCELLED];

    /** Status yang masih boleh dikerjakan teknisi (disimpan ulang / diajukan). */
    public const EDITABLE_BY_REQUESTER = [self::DRAFT, self::REJECTED];

    /** Status yang masih boleh dibatalkan. */
    public const CANCELLABLE = [self::DRAFT, self::SUBMITTED, self::REJECTED];

    /** Review settlement oleh atasan teknisi, sebelum CR menjadi REALIZED. */
    public const REVIEW_PENDING  = 'PENDING';
    public const REVIEW_APPROVED = 'APPROVED';
    public const REVIEW_REJECTED = 'REJECTED';

    protected $fillable = [
        'trouble_ticket_id', 'budget_plan_id', 'cluster_id', 'request_type_id', 'beneficiary_id',
        'description', 'total', 'status', 'revision', 'requested_by', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'review_notes', 'cancelled_by', 'cancelled_at', 'cancel_reason',
        'budget_request_id', 'request_number', 'settlement_review_status', 'settlement_reviewed_by',
        'settlement_reviewed_at', 'settlement_review_notes',
    ];

    protected $casts = [
        'total'        => 'integer',
        'revision'     => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'settlement_reviewed_at' => 'datetime',
    ];

    /** Kolom nomor belum tentu ada (migrasi menyusul di host). */
    public static function supportsNumber(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('trouble_ticket_requests', 'request_number');
    }

    /** Kolom review settlement belum tentu ada (migrasi menyusul di host). */
    public static function supportsSettlementReview(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('trouble_ticket_requests', 'settlement_review_status');
    }

    /** CR ini sedang direview atasan, jadi settlement-nya terkunci? */
    public static function settlementInReview(int $costRequestId): bool
    {
        return self::supportsSettlementReview()
            && self::where('budget_request_id', $costRequestId)
                ->where('settlement_review_status', self::REVIEW_PENDING)->exists();
    }

    /**
     * Settlement CR ini wajib dikirim dari halaman tiket? Berlaku untuk pengajuan
     * bernomor, yaitu yang lahir sesudah rilis review atasan. CR lama tetap
     * memakai jalur Cost Request supaya yang sedang berjalan tidak tersangkut.
     */
    public static function settlementViaTicket(int $costRequestId): bool
    {
        return self::supportsNumber() && self::supportsSettlementReview()
            && self::where('budget_request_id', $costRequestId)->whereNotNull('request_number')->exists();
    }

    /** Nomor untuk layar: pengajuan lama belum punya nomor. */
    public function getDisplayNumberAttribute(): string
    {
        return $this->request_number ?: '#' . $this->id;
    }

    /**
     * CR hasil pengajuan ini sudah gugur (dibatalkan atau dihapus)? Pengajuannya
     * tetap tercatat APPROVED; kebutuhan dananya diajukan ulang sebagai pengajuan baru.
     */
    public function costRequestGone(): bool
    {
        if ($this->budget_request_id === null) {
            return false;
        }

        $cr = $this->budgetRequest;

        return $cr === null || $cr->trashed() || $cr->status === 'CANCELLED';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TroubleTicket::class, 'trouble_ticket_id');
    }

    public function budgetPlan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Cost Request hasil approve (null sebelum disetujui). */
    public function budgetRequest(): BelongsTo
    {
        // withTrashed: CR draft boleh dihapus, dan pengajuan tetap perlu tahu itu.
        return $this->belongsTo(BudgetRequest::class)->withTrashed();
    }

    /** SEMUA baris dari semua revisi (riwayat). */
    public function items(): HasMany
    {
        return $this->hasMany(TroubleTicketRequestItem::class, 'trouble_ticket_request_id')->orderBy('id');
    }

    /** Baris revisi yang berlaku sekarang. */
    public function currentItems(): HasMany
    {
        return $this->items()->where('revision', $this->revision);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TroubleTicketRequestAttachment::class, 'trouble_ticket_request_id')->orderBy('id');
    }

    /** Request Type ini dihitung Cash Advance? (nama jenis, seperti BudgetRequest::isCashAdvance()). */
    public function isCashAdvance(): bool
    {
        return in_array((int) $this->request_type_id, BudgetRequest::cashAdvanceTypeIds(), true);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::DRAFT     => 'secondary',
            self::SUBMITTED => 'warning',
            self::APPROVED  => 'success',
            self::REJECTED  => 'danger',
            self::CANCELLED => 'dark',
            default         => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::DRAFT     => 'Draft',
            self::SUBMITTED => 'Menunggu review',
            self::APPROVED  => 'Disetujui',
            self::REJECTED  => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
            default         => (string) $this->status,
        };
    }
}
