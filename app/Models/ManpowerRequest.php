<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan kebutuhan pegawai (HRIS). Status tersimpan: DRAFT, SUBMITTED,
 * REJECTED, APPROVED, CANCEL. Setelah APPROVED, status tampilan diturunkan
 * dari status rincian (displayStatus): PROCESS / PARTIALLY DONE / DONE.
 */
class ManpowerRequest extends Model
{
    public const POSITIONS = ['NOC Officer', 'Supervisor Operation', 'CSO', 'Teknisi Lapangan', 'Admin Project'];
    public const GRADES    = ['FRESH GRADUATE', 'EXPERIENCED', 'SENIOR'];

    /** Label => warna badge Bootstrap. */
    public const STATUS_BADGES = [
        'DRAFT' => 'secondary', 'SUBMITTED' => 'warning', 'REJECTED' => 'danger', 'APPROVED' => 'info',
        'PROCESS' => 'primary', 'PARTIALLY DONE' => 'primary', 'DONE' => 'success', 'CANCEL' => 'dark',
    ];

    /** Role yang menyetujui/menolak & melihat semua pengajuan (ASUMSI: perlu konfirmasi). */
    public const HR_ROLES = ['superadmin', 'hr'];

    protected $fillable = [
        'request_no', 'requester_id', 'department_id', 'project_id', 'request_date', 'needed_date',
        'status', 'note', 'approved_by', 'approved_at', 'approval_note',
    ];

    protected $casts = ['request_date' => 'date', 'needed_date' => 'date', 'approved_at' => 'date'];

    public function requester() { return $this->belongsTo(User::class, 'requester_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function department() { return $this->belongsTo(Department::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function details() { return $this->hasMany(ManpowerRequestDetail::class); }
    public function logs() { return $this->hasMany(ManpowerRequestLog::class)->oldest('created_at')->oldest('id'); }

    /** Status untuk ditampilkan; diturunkan dari rincian saat sudah APPROVED. */
    public function displayStatus(): string
    {
        if ($this->status !== 'APPROVED') {
            return $this->status;
        }

        $ds = $this->details->where('status', '!=', 'CANCELED');
        if ($ds->isEmpty()) {
            return 'APPROVED';
        }
        $done = $ds->where('status', 'DONE')->count();
        if ($done === $ds->count()) {
            return 'DONE';
        }
        if ($done) {
            return 'PARTIALLY DONE';
        }

        return $ds->where('status', 'ON_PROCESS')->isNotEmpty() ? 'PROCESS' : 'APPROVED';
    }

    /**
     * Aksi alur yang sah pada status tersimpan sekarang (satu sumber untuk
     * tombol & validasi). submit juga berlaku untuk REJECTED (ajukan ulang).
     *
     * @return string[]
     */
    public function allowedActions(): array
    {
        return match ($this->status) {
            'DRAFT', 'REJECTED' => ['submit', 'cancel'],
            'SUBMITTED'         => ['approve', 'reject'],
            'APPROVED'          => in_array($this->displayStatus(), ['APPROVED', 'PROCESS'], true) ? ['cancel'] : [],
            default             => [],
        };
    }

    /**
     * Pemohon (requester_id) tidak boleh menyetujui/menolak pengajuannya
     * sendiri, kecuali Superadmin (keputusan pemilik 2026-11-13).
     */
    public function isSelfApprovalBlockedFor(?User $user): bool
    {
        if (! $user || $user->hasRole(['superadmin'])) {
            return false;
        }

        return $this->requester_id !== null && (int) $this->requester_id === (int) $user->id;
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['DRAFT', 'REJECTED'], true);
    }
}
