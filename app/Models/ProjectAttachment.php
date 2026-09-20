<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAttachment extends Model
{
    protected $fillable = [
        'project_id',
        'project_po_number_id',
        'type',
        'title',
        'file_path',
        'file_name',
        'file_type',
        'input_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function poNumber()
    {
        return $this->belongsTo(ProjectPoNumber::class, 'project_po_number_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->file_type, 'image/');
    }

    public function isPdf(): bool
    {
        return (string) $this->file_type === 'application/pdf';
    }
}