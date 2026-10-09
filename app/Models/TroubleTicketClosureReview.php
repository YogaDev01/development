<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pengajuan Closed beserta keputusannya. Setiap pengajuan baris baru
 * (attempt_no naik) — penolakan lama tidak pernah ditimpa. Satu PENDING per
 * tiket dijaga unique index pending_ticket_id.
 */
class TroubleTicketClosureReview extends Model
{
    protected $table = 'trouble_ticket_closure_reviews';

    protected $fillable = [
        'trouble_ticket_id', 'attempt_no', 'requested_by', 'requested_at',
        'rca', 'sub_rca', 'action', 'resolution_summary',
        'review_status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'attempt_no'   => 'integer',
        'requested_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TroubleTicket::class, 'trouble_ticket_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
