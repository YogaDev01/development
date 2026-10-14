<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dokumen site; berkasnya di disk `secure`, dilayani SecureFileController. */
class SiteDocument extends Model
{
    protected $table = 'site_documents';

    protected $fillable = ['site_id', 'title', 'file_path', 'file_name', 'file_type', 'input_by'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->file_type, 'image/');
    }
}
