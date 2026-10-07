<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Audit trail aktivitas bisnis user lintas module.
 *
 * Immutable: dibuat lewat UserActivityLog::record(), tidak pernah di-update
 * atau dihapus dari UI (tidak ada route CRUD). Actor selalu diambil dari
 * authenticated user/session — tidak pernah dari input request.
 *
 * Meniru pola RequestStatusLog/AiAuditLog: $timestamps=false, hanya created_at.
 */
class UserActivityLog extends Model
{
    protected $table = 'user_activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_role',
        'module',
        'action',
        'description',
        'notes',
        'subject_type',
        'subject_id',
        'reference',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | KONVENSI MODULE & ACTION
    |
    | Kode (key) dalam English snake_case untuk mesin/filter; label Indonesia
    | untuk tampilan. Sengaja TIDAK memaksakan setiap action ada di setiap
    | module — pemanggil memakai yang sesuai business process-nya.
    |--------------------------------------------------------------------------
    */

    public const MODULES = [
        'auth' => 'Autentikasi',
        'profile' => 'Profil',
        'cost_request' => 'Cost Request',
        'treasury' => 'Treasury',
        'budget_plan' => 'Budget Plan',
        'purchase_order' => 'Purchase Order',
        'invoice' => 'Invoice',
        'cash_receipt' => 'Cash Receipt',
        'cash_management' => 'Cash Management',
        'closing' => 'Closing',
        'payroll' => 'Payroll',
        'forecast' => 'Forecast',
        'cost_register' => 'Cost Register',
        'delivery_note' => 'Delivery Note',
        'todo_list' => 'Todo List',
        'user_access' => 'User & Access',
        'master_data' => 'Master Data',
    ];

    public const ACTIONS = [
        'login' => 'Login',
        'logout' => 'Logout',
        'view' => 'Lihat',
        'create' => 'Buat',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'submit' => 'Ajukan',
        'check' => 'Periksa',
        'verify' => 'Verifikasi',
        'approve' => 'Setujui',
        'reject' => 'Tolak',
        'revise' => 'Revisi',
        'cancel' => 'Batalkan',
        'process' => 'Proses',
        'pay' => 'Bayar',
        'settle' => 'Settle',
        'close' => 'Tutup',
        'takeout' => 'Takeout',
        'restore' => 'Kembalikan',
        'export' => 'Export',
        'download' => 'Download',
        'upload' => 'Upload',
        'import' => 'Import',
    ];

    /*
    |--------------------------------------------------------------------------
    | PENCATATAN
    |--------------------------------------------------------------------------
    */

    /**
     * Catat satu aktivitas. Gagal-aman: kalau pencatatan error, aktivitas
     * bisnis TIDAK ikut gagal — error hanya ditulis ke log aplikasi.
     *
     * @param  string  $module  salah satu key MODULES
     * @param  string  $action  salah satu key ACTIONS
     * @param  string  $description  human-readable (Bahasa Indonesia)
     * @param  Model|null  $subject  objek terkait (reference auto dari code/number)
     * @param  string|null  $notes  catatan opsional
     * @param  string|null  $reference  override nomor dokumen (kalau $subject tak punya)
     */
    public static function record(
        string $module,
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $notes = null,
        ?string $reference = null
    ): ?self {
        try {
            $user = Auth::user();

            $subjectType = $subject ? get_class($subject) : null;
            $subjectId = $subject?->getKey();

            if ($reference === null && $subject !== null) {
                $reference = self::guessReference($subject);
            }

            return self::create([
                'user_id' => $user?->getKey(),
                'actor_name' => $user?->name,
                'actor_role' => $user?->role?->name,
                'module' => $module,
                'action' => $action,
                'description' => mb_substr($description, 0, 500),
                'notes' => $notes !== null ? mb_substr($notes, 0, 500) : null,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'reference' => $reference !== null ? mb_substr($reference, 0, 100) : null,
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat UserActivityLog', [
                'module' => $module,
                'action' => $action,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Tebak nomor dokumen dari atribut umum (code/number/...). Aman kalau
     * tidak ada — kembalikan null.
     */
    protected static function guessReference(Model $subject): ?string
    {
        foreach (['code', 'number', 'invoice_number', 'po_number', 'batch_number'] as $attr) {
            $value = $subject->getAttribute($attr);
            if (! empty($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | FEED / FILTER
    |
    | Query terindeks + paginasi server-side. Tidak pernah mengambil seluruh
    | log lalu memfilter di PHP (lihat index pada migration).
    |--------------------------------------------------------------------------
    */

    /**
     * Feed aktivitas satu user, dengan filter opsional:
     * module, action, date_from (Y-m-d), date_to (Y-m-d), q (keyword).
     * Paginasi memakai page-name 'alog' agar tidak bentrok dengan tab lain.
     */
    public static function feedFor(int $userId, array $filters = [], int $perPage = 25)
    {
        $query = self::where('user_id', $userId);

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['q'])) {
            $keyword = trim($filters['q']);
            $query->where(function ($q) use ($keyword) {
                $q->where('description', 'like', "%{$keyword}%")
                    ->orWhere('reference', 'like', "%{$keyword}%")
                    ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'alog');
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getModuleLabelAttribute(): string
    {
        return self::MODULES[$this->module] ?? $this->module;
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'create', 'submit', 'upload', 'import' => 'info',
            'approve', 'verify', 'pay', 'settle', 'close',
            'process', 'restore' => 'success',
            'reject', 'cancel', 'delete', 'takeout' => 'danger',
            'revise', 'check' => 'warning',
            'login' => 'primary',
            'logout' => 'secondary',
            default => 'secondary',
        };
    }
}
