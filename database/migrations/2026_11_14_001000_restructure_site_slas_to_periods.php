<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SLA Site jadi header + detail: `site_sla_periods` (customer + tahun-bulan) dan
 * `site_slas` (nilai per site per periode). Tabel `site_slas` bentuk lama
 * (site + year + month, migrasi 2026_11_14_000900) diganti nama menjadi
 * `site_slas_v1`, isinya disalin ke struktur baru dengan customer = customer
 * site saat ini. Baris yang site-nya tanpa customer tidak tersalin dan tetap
 * ada di `site_slas_v1` (dicatat di log). `site_slas_v1` tidak dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = Schema::hasTable('site_slas') && Schema::hasColumn('site_slas', 'year');
        if ($legacy) {
            Schema::rename('site_slas', 'site_slas_v1');
            // Nama FK/index ikut nama tabel lama; dilepas agar nama tidak bentrok dengan tabel baru.
            Schema::table('site_slas_v1', function (Blueprint $table) {
                $table->dropForeign('site_slas_site_id_foreign');
                $table->dropForeign('site_slas_updated_by_foreign');
            });
        }

        if (! Schema::hasTable('site_sla_periods')) {
            Schema::create('site_sla_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['customer_id', 'year', 'month']);
                $table->index(['year', 'month']);
            });
        }

        if (! Schema::hasTable('site_slas')) {
            Schema::create('site_slas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_sla_period_id')->constrained('site_sla_periods')->cascadeOnDelete();
                $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
                $table->decimal('sla_percent', 5, 2);
                $table->timestamps();

                $table->unique(['site_sla_period_id', 'site_id']);
            });
        }

        if ($legacy) {
            DB::transaction(function () {
                $rows = DB::table('site_slas_v1 as o')->join('sites as s', 's.id', '=', 'o.site_id')
                    ->whereNotNull('s.customer_id')
                    ->get(['o.site_id', 'o.year', 'o.month', 'o.sla_percent', 'o.updated_by', 's.customer_id']);
                $periods = [];
                foreach ($rows as $r) {
                    $k = "{$r->customer_id}-{$r->year}-{$r->month}";
                    $periods[$k] ??= DB::table('site_sla_periods')->insertGetId([
                        'customer_id' => $r->customer_id, 'year' => $r->year, 'month' => $r->month,
                        'created_by' => $r->updated_by, 'updated_by' => $r->updated_by,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('site_slas')->insert([
                        'site_sla_period_id' => $periods[$k], 'site_id' => $r->site_id, 'sla_percent' => $r->sla_percent,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $total = DB::table('site_slas_v1')->count();
                Log::info('Migrasi SLA Site: site_slas_v1 disalin ke struktur periode.', [
                    'baris_lama' => $total, 'tersalin' => $rows->count(),
                    'tidak_tersalin_tanpa_customer' => $total - $rows->count(), 'periode_dibuat' => count($periods),
                ]);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_slas_v1')) {
            return; // Tidak ada tabel lama yang bisa dikembalikan.
        }
        Schema::dropIfExists('site_slas');
        Schema::dropIfExists('site_sla_periods');
        Schema::rename('site_slas_v1', 'site_slas');
        Schema::table('site_slas', function (Blueprint $table) {
            $table->foreign('site_id')->references('id')->on('sites')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
