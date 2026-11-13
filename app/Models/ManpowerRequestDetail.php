<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManpowerRequestDetail extends Model
{
    protected $fillable = [
        'manpower_request_id', 'position', 'grade', 'type', 'replaced_employee_id', 'status', 'filled_employee_id',
    ];

    public function request() { return $this->belongsTo(ManpowerRequest::class, 'manpower_request_id'); }
    public function replacedEmployee() { return $this->belongsTo(Employee::class, 'replaced_employee_id'); }
    public function filledEmployee() { return $this->belongsTo(Employee::class, 'filled_employee_id'); }
}
