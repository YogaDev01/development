<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'initial',
        'address',
        'npwp',
        'customer_type_id',
        'input_by',
        'status',
    ];

    /**
     * Auto-suggest initial 3 huruf dari nama customer.
     * "PT KHARISMA PUTRA ADITAMA" -> "KPA" (skip badan hukum seperti PT/CV/dst).
     */
    public static function suggestInitial(string $name): string
    {
        $skip = ['PT', 'CV', 'UD', 'PD', 'TBK', 'PERSERO'];

        $words = collect(preg_split('/\s+/', trim($name)))
            ->filter(fn ($w) => $w !== '')
            ->reject(fn ($w) => in_array(strtoupper(trim($w, '.,()')), $skip))
            ->values();

        if ($words->isEmpty()) {
            $words = collect(preg_split('/\s+/', trim($name)))->filter();
        }

        $initial = $words->map(fn ($w) => strtoupper(substr($w, 0, 1)))->implode('');

        // Kalau kata signifikan cuma 1-2, tambal dari huruf berikutnya kata itu
        if (strlen($initial) < 3 && $words->isNotEmpty()) {
            $first = strtoupper(preg_replace('/[^A-Za-z]/', '', $words->first()));
            $initial = substr($first, 0, 3);
        }

        return substr(str_pad($initial, 3, 'X'), 0, 3);
    }

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Project::class, 'customer_id', 'po_id');
    }

    /**
     * PIC customer, diambil dari Master PIC (type=customer) lewat pivot
     * table customer_pic_links. Menggantikan tabel lama customer_details.
     */
    public function pics()
    {
        return $this->belongsToMany(MasterPic::class, 'customer_pic_links', 'customer_id', 'master_pic_id')
            ->withTimestamps();
    }

    /**
     * Lampiran customer (tabel customer_attachments).
     */
    public function attachments()
    {
        return $this->hasMany(CustomerAttachment::class);
    }
}