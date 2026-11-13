<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kandidat / talent pool (HRIS). */
class Candidate extends Model
{
    public const GENDERS  = ['L' => 'LAKI-LAKI', 'P' => 'PEREMPUAN'];
    public const MARITAL  = ['BELUM MENIKAH', 'MENIKAH', 'CERAI'];
    public const EDUCATION = ['SMA / SMK', 'D3', 'S1', 'S2'];
    public const SOURCES  = ['JOBSTREET', 'REFERRAL', 'WALKIN', 'LINKEDIN', 'INTERNAL'];

    protected $fillable = [
        'code', 'name', 'address', 'phone', 'birth_place', 'birth_date', 'gender', 'marital_status',
        'education', 'position', 'grade', 'source', 'hired_employee_id', 'created_by',
    ];

    protected $casts = ['birth_date' => 'date'];

    public function documents() { return $this->hasMany(CandidateDocument::class); }
    public function recruitments() { return $this->hasMany(Recruitment::class); }
    public function logs() { return $this->hasMany(CandidateLog::class)->latest('created_at')->latest('id'); }
    public function hiredEmployee() { return $this->belongsTo(Employee::class, 'hired_employee_id'); }

    public function activeRecruitment(): ?Recruitment
    {
        return $this->recruitments->firstWhere('status', 'ON_PROCESS');
    }

    /** TERSEDIA | DIPROSES | ONBOARD */
    public function availability(): string
    {
        if ($this->hired_employee_id) {
            return 'ONBOARD';
        }

        return $this->activeRecruitment() ? 'DIPROSES' : 'TERSEDIA';
    }
}
