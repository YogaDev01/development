<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Surat (Letter Management, HRIS). Status & alur diatur di sini supaya
 * controller, view, dan test memakai satu sumber.
 */
class Letter extends Model
{
    public const STATUSES = [
        'draft'     => ['Draft', 'secondary'],
        'submitted' => ['Submitted', 'warning'],
        'approved'  => ['Approved', 'info'],
        'sent'      => ['Sent', 'primary'],
        'complete'  => ['Complete', 'success'],
        'rejected'  => ['Rejected', 'danger'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    public const FLOW = [
        'external' => ['draft', 'submitted', 'approved', 'sent', 'complete'],
        'internal' => ['draft', 'submitted', 'approved', 'complete'],
    ];

    public const ACTION_LABELS = [
        'created' => 'Dibuat', 'updated' => 'Diperbarui', 'submitted' => 'Diajukan', 'approved' => 'Disetujui',
        'sent' => 'Dikirim', 'complete' => 'Diselesaikan', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan',
        'redraft' => 'Dikembalikan ke draft', 'signed' => 'Ditandatangani', 'unsigned' => 'TTD dibatalkan',
        'sign_added' => 'Penandatangan ditambahkan', 'sign_removed' => 'Penandatangan dihapus',
        'attach_added' => 'Lampiran ditambahkan', 'attach_removed' => 'Lampiran dihapus',
        'detail_updated' => 'Isi surat diperbarui',
    ];

    protected $fillable = [
        'category_id', 'user_id', 'type', 'customer_id', 'employee_id', 'team_id', 'department_id',
        'letter_no', 'reference_no', 'status', 'sign_mode',
    ];

    public function category() { return $this->belongsTo(LetterCategory::class, 'category_id'); }
    public function creator() { return $this->belongsTo(User::class, 'user_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function detail() { return $this->hasOne(LetterDetail::class); }
    public function attachments() { return $this->hasMany(LetterAttachment::class); }
    public function signatures() { return $this->hasMany(LetterSignature::class); }
    public function logs() { return $this->hasMany(LetterLog::class)->latest('created_at')->latest('id'); }

    /**
     * Aksi alur yang boleh dilakukan pada status sekarang.
     * Satu sumber untuk tombol di view dan validasi di controller.
     *
     * @return string[]
     */
    public function allowedActions(): array
    {
        return match ($this->status) {
            'draft'     => ['submit', 'edit', 'cancel', 'delete'],
            'submitted' => ['approve', 'reject', 'cancel'],
            'approved'  => $this->type === 'external' ? ['sent', 'cancel'] : ['complete', 'cancel'],
            'sent'      => ['complete'],
            'rejected'  => ['redraft', 'delete'],
            'cancelled' => ['redraft', 'delete'],
            default     => [],
        };
    }

    public function canEditContent(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function recipientName(): string
    {
        if ($this->type === 'external') {
            return $this->customer->name ?? '-';
        }

        return $this->employee->name ?? $this->team->name ?? $this->department->name ?? '-';
    }
}
