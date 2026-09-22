<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMonitoringLog extends Model
{
    protected $fillable = [
        'project_id',
        'cluster_id',
        'project_detail_id',
        'budget_plan_id',
        'user_id',
        'type',
        'message',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function projectDetail()
    {
        return $this->belongsTo(ProjectDetail::class);
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(ProjectMonitoringLogAttachment::class, 'log_id');
    }

    /**
     * Pesan yang sudah di-escape aman untuk HTML, dengan mention "@nama"
     * ditebalkan (format sederhana ala WhatsApp, tanpa kurung siku).
     * Notifikasi ke user yang di-mention belum diimplementasikan (modul
     * notif menyusul).
     */
    public function getMessageHtmlAttribute(): string
    {
        $escaped = e($this->message ?? '');
        return preg_replace('/@([A-Za-z0-9_]+)/', '<strong class="mention">@$1</strong>', $escaped);
    }
}
