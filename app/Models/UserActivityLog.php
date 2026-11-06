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
        'changes',
        'subject_type',
        'subject_id',
        'reference',
        'ip_address',
        'user_agent',
        'session_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'changes'    => 'array',
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
        'trouble_ticket' => 'Trouble Ticket',
    ];

    public const ACTIONS = [
        'login' => 'Login',
        'logout' => 'Logout',
        'login_failed' => 'Login Gagal',
        'lockout' => 'Login Diblokir',
        'revoke_session' => 'Cabut Sesi',
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

    /**
     * Aksi yang hanya relevan di konsol Keamanan: tidak pernah ber-actor user
     * itu sendiri (login gagal tanpa sesi, pencabutan sesi oleh superadmin),
     * jadi tidak ditawarkan di filter Activity Log Profil.
     */
    public const SECURITY_ONLY_ACTIONS = ['login_failed', 'lockout', 'revoke_session'];

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
     * @param  string|null  $notes  catatan opsional (non-sensitif)
     * @param  string|null  $reference  override nomor dokumen (kalau $subject tak punya)
     * @param  array|null  $changes  rincian perubahan nilai dari->menjadi (CREDENTIAL),
     *                                tiap elemen: ['label'=>, 'from'=>, 'to'=>]
     */
    public static function record(
        string $module,
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $notes = null,
        ?string $reference = null,
        ?array $changes = null
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
                'changes' => ! empty($changes) ? array_values($changes) : null,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'reference' => $reference !== null ? mb_substr($reference, 0, 100) : null,
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ] + self::securityContext());
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
     * Kolom perangkat & sesi (Fase 1 Security). Hanya diisi bila migrasi
     * 2026_11_07_000500 sudah dijalankan, supaya urutan upload kode -> migrate
     * tidak memunculkan "Unknown column" di jalur bisnis mana pun.
     *
     * Id sesi TIDAK disimpan mentah — hanya sha256-nya, cukup untuk
     * menghubungkan jejak audit dengan baris di konsol Sesi Aktif.
     */
    protected static function securityContext(): array
    {
        if (! self::supportsSecurityColumns()) {
            return [];
        }

        $request = request();
        $session = $request?->hasSession() ? $request->session() : null;
        $agent   = $request?->userAgent();

        return [
            'user_agent'   => $agent !== null && $agent !== '' ? mb_substr($agent, 0, 255) : null,
            'session_hash' => $session ? hash('sha256', $session->getId()) : null,
        ];
    }

    public static function supportsSecurityColumns(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('user_activity_logs', 'user_agent');
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

    /**
     * Bangun rincian perubahan (dari -> menjadi) dengan membandingkan snapshot
     * nilai LAMA ($before, mis. $model->getOriginal() yang dipotret SEBELUM
     * update) dengan nilai BARU pada $after.
     *
     * $map memetakan kolom => label tampilan, atau kolom => ['label'=>...,
     * 'money'=>true] untuk memformat nominal rupiah (1234567 -> "1.234.567").
     * Hanya kolom yang benar-benar berubah yang dikembalikan.
     *
     * Hasil cocok langsung untuk argumen $changes pada record().
     *
     * @return array<int, array{label:string, from:string, to:string}>
     */
    public static function changeSet(array $before, Model $after, array $map): array
    {
        $result = [];

        foreach ($map as $field => $config) {
            $label = is_array($config) ? ($config['label'] ?? $field) : $config;
            $isMoney = is_array($config) && ! empty($config['money']);

            $old = $before[$field] ?? null;
            $new = $after->getAttribute($field);

            if ((string) $old === (string) $new) {
                continue;
            }

            $result[] = [
                'label' => $label,
                'from'  => self::formatValue($old, $isMoney),
                'to'    => self::formatValue($new, $isMoney),
            ];
        }

        return $result;
    }

    /** Format satu nilai untuk tampilan rincian perubahan. */
    protected static function formatValue($value, bool $isMoney): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($isMoney) {
            return number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
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

        self::applyFilters($query, $filters);

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'alog');
    }

    /**
     * Feed audit SELURUH user untuk konsol keamanan (superadmin). Filter sama
     * dengan feedFor() ditambah user_id dan ip. Paginasi server-side.
     */
    public static function feedAll(array $filters = [], int $perPage = 50)
    {
        $query = self::query();

        if (! empty($filters['user_id'])) {
            $userId = (int) $filters['user_id'];

            // Login gagal/diblokir tidak punya actor (user_id NULL) dan
            // pencabutan sesi tercatat atas nama superadmin; akun yang dituju
            // ada di subject. Untuk jejak autentikasi, filter User mencakup
            // keduanya agar "siapa yang mencoba masuk ke akun X" terjawab.
            if (($filters['module'] ?? null) === 'auth') {
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->orWhere(fn ($s) => $s->where('subject_type', User::class)->where('subject_id', $userId));
                });
            } else {
                $query->where('user_id', $userId);
            }
        }

        if (! empty($filters['ip'])) {
            $query->where('ip_address', trim($filters['ip']));
        }

        self::applyFilters($query, $filters);

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'alog');
    }

    /** Filter bersama: module, action, date_from, date_to, q. */
    protected static function applyFilters($query, array $filters): void
    {
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
            'reject', 'cancel', 'delete', 'takeout',
            'login_failed', 'lockout' => 'danger',
            'revoke_session' => 'warning',
            'revise', 'check' => 'warning',
            'login' => 'primary',
            'logout' => 'secondary',
            default => 'secondary',
        };
    }
}
