<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Jejak audit Accounting. Hanya ditulis, tidak pernah diubah/dihapus. */
class AccountingAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'entity_type', 'entity_id', 'action', 'from_status', 'to_status',
        'old_values', 'new_values', 'reason', 'source_ref', 'user_id',
    ];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $entityType,
        int $entityId,
        string $action,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $sourceRef = null,
    ): self {
        return static::create([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'action'      => $action,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'old_values'  => $old,
            'new_values'  => $new,
            'reason'      => $reason,
            'source_ref'  => $sourceRef,
            'user_id'     => Auth::id(),
        ]);
    }
}
