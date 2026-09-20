<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sinkronkan ulang projects.value = SUM(project_po_numbers.nominal) untuk
     * SEMUA PO. Sejak PO Value tidak lagi diinput manual, kolom `value`
     * dijaga otomatis oleh Project::syncValueFromPoNumbers() (dipicu
     * ProjectPoNumber::booted()). Tapi data lama — PO yang dibuat sebelum
     * mekanisme ini ada, atau baris No. PO yang pernah dihapus lewat query
     * builder (tidak memicu event Eloquent) — masih menyimpan nilai manual
     * yang basi: ada PO tanpa satu pun No. PO tapi `value`-nya tetap terisi.
     *
     * Migrasi ini menimpa semua nilai basi itu sekali jalan. PO tanpa No. PO
     * dipaksa ke 0, sesuai aturan "PO Value bersumber dari akumulasi No. PO".
     */
    public function up(): void
    {
        try {
            $sums = DB::table('project_po_numbers')
                ->selectRaw('project_id, SUM(nominal) as total')
                ->groupBy('project_id')
                ->pluck('total', 'project_id');

            foreach ($sums as $projectId => $total) {
                DB::table('projects')
                    ->where('id', $projectId)
                    ->where('value', '!=', (int) $total)
                    ->update(['value' => (int) $total]);
            }

            // PO yang sama sekali belum punya No. PO → value wajib 0.
            DB::table('projects')
                ->when($sums->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $sums->keys()->all()))
                ->where(function ($q) {
                    $q->where('value', '!=', 0)->orWhereNull('value');
                })
                ->update(['value' => 0]);
        } catch (\Throwable $e) {
            // diabaikan — backfill opsional, nilai tetap tersinkron sendiri
            // begitu No. PO berikutnya ditambah/diubah/dihapus.
        }
    }

    public function down(): void
    {
        // Tidak reversible — nilai manual lama tidak disimpan di mana pun.
    }
};
