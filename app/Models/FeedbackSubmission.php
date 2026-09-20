<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'title',
        'message',
        'criticism_text',
        'suggestion_text',
        'willing_to_discuss',
        'whatsapp_number',
        'status',
        'reviewed_by',
        'reviewed_at',
        'response_text',
        'responded_by',
        'responded_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'willing_to_discuss' => 'boolean',
        'reviewed_at'        => 'datetime',
        'responded_at'       => 'datetime',
        'closed_at'          => 'datetime',
    ];

    public const CATEGORIES = ['SUGGESTION', 'CRITICISM', 'CONCERN', 'IDEA', 'OTHER'];
    public const STATUSES   = ['SUBMITTED', 'REVIEWED', 'RESPONDED', 'CLOSED'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function submitter()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function respondedBy()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'SUGGESTION' => 'Suggestion',
            'CRITICISM'  => 'Criticism',
            'CONCERN'    => 'Concern',
            'IDEA'       => 'Idea',
            'OTHER'      => 'Other',
            default      => $this->category,
        };
    }

    public function getCategoryColorAttribute(): string
    {
        return match ($this->category) {
            'SUGGESTION' => 'info',
            'CRITICISM'  => 'danger',
            'CONCERN'    => 'warning',
            'IDEA'       => 'success',
            'OTHER'      => 'secondary',
            default      => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'SUBMITTED' => 'Submitted',
            'REVIEWED'  => 'Reviewed',
            'RESPONDED' => 'Responded',
            'CLOSED'    => 'Closed',
            default     => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'SUBMITTED' => 'secondary',
            'REVIEWED'  => 'info',
            'RESPONDED' => 'primary',
            'CLOSED'    => 'success',
            default     => 'secondary',
        };
    }
}
