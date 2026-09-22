<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Best-effort: isi projects.manager_id dari PIC internal ber-jabatan
     * "Project Manager" yang sudah tertaut ke PO, dicocokkan ke users lewat
     * email (fallback: nama persis). Tidak fatal kalau gagal — PO tanpa
     * kecocokan dibiarkan null dan diisi manual lewat form Edit PO.
     */
    public function up(): void
    {
        try {
            $rows = DB::table('projects')
                ->join('project_pic_links', 'project_pic_links.project_id', '=', 'projects.id')
                ->join('master_pics', 'master_pics.id', '=', 'project_pic_links.master_pic_id')
                ->whereNull('projects.manager_id')
                ->where('master_pics.type', 'internal')
                ->where('master_pics.position', 'like', '%project manager%')
                ->orderBy('projects.id')
                ->get(['projects.id as project_id', 'master_pics.email', 'master_pics.pic']);

            $seen = [];
            foreach ($rows as $r) {
                if (isset($seen[$r->project_id])) {
                    continue;
                }
                $seen[$r->project_id] = true;

                $userId = null;
                if ($r->email) {
                    $userId = DB::table('users')->where('email', $r->email)->value('id');
                }
                if (! $userId && $r->pic) {
                    $userId = DB::table('users')->whereRaw('LOWER(name) = ?', [strtolower(trim($r->pic))])->value('id');
                }

                if ($userId) {
                    DB::table('projects')->where('id', $r->project_id)->update(['manager_id' => $userId]);
                }
            }
        } catch (\Throwable $e) {
            // diabaikan — backfill opsional
        }
    }

    public function down(): void
    {
        // tidak reversible (data best-effort)
    }
};
