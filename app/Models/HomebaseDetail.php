<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomebaseDetail extends Model
{
    protected $table = 'homebase_detail';

    protected $fillable = ['hb_id', 'pic_id'];

    public function homebase()
    {
        return $this->belongsTo(Homebase::class, 'hb_id');
    }

    public function pic()
    {
        return $this->belongsTo(MasterPic::class, 'pic_id');
    }
}
