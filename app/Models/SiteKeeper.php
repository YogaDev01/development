<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Penjaga site; diisi bebas lewat form site, boleh lebih dari satu. */
class SiteKeeper extends Model
{
    protected $table = 'site_keepers';

    protected $fillable = ['site_id', 'name', 'phone'];

    /**
     * Akun user dengan nama yang sama. Site Keeper disimpan sebagai teks
     * (dipilih dari daftar nama user), jadi kontaknya dibaca lewat pencocokan
     * nama — bukan foreign key. Boleh kosong bila namanya tidak lagi terdaftar.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'name', 'name');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
