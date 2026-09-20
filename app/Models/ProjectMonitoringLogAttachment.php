<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMonitoringLogAttachment extends Model
{
    protected $fillable = [
        'log_id',
        'file_path',
        'file_name',
        'file_type',
        'input_by',
    ];

    public function log()
    {
        return $this->belongsTo(ProjectMonitoringLog::class, 'log_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->file_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->file_type, 'video/');
    }

    public function isPdf(): bool
    {
        return (string) $this->file_type === 'application/pdf';
    }
}
