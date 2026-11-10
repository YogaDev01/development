<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Expense (Fase 3, 2026-11-10) — status Rejected, pembebanan multi-periode, dan
 * alokasi Fixed Cost / Overhead ke PO (rencana: docs/plans/2026-11-10-expense-module.md §10).
 *
 * cost_registers
 *   ~ status ENUM + REJECTED (CEO tolak; FM revisi ke Draft atau batalkan)
 *                 + SETTLE_REJECTED (CEO tolak settlement; FM perbaiki & ajukan ulang).
 *   + period_start, period_end (tanggal 1 bulan), period_method (equal|manual).
 *     Kolom `period` lama tetap = bulan awal.
 * cost_register_periods       pembebanan per bulan (integer rupiah, > 0, jumlah = nilai Expense).
 * cost_register_details / cost_register_settlement_details
 *   + allocation ENUM('po','annual','selected','cc') — cara rincian dibebankan:
 *       po       : satu PO (project_id) — wajib untuk akun Variable Cost.
 *       annual   : seluruh PO yang dibuat pada allocation_year (Fixed Cost/Overhead).
 *       selected : PO terpilih (tabel pivot) (Fixed Cost/Overhead).
 *       cc       : Cost Center (cost_center_id) — tidak masuk biaya PO.
 *     annual & selected dibagi proporsional projects.value, DIHITUNG ULANG saat dibaca
 *     (App\Support\ExpenseAllocation) — tidak ada nilai per PO yang disimpan.
 *   + allocation_year SMALLINT.
 * cost_register_detail_projects / cost_register_settlement_detail_projects — pivot 'selected'.
 *
 * Backfill (hanya baris Expense baru, header type NULL): allocation = 'po' bila project_id
 * terisi, 'cc' bila cost_center_id terisi. Data Cost Register lama tidak disentuh
 * (allocation tetap NULL = ikut PO header). Jumlah baris dicatat ke log.
 *
 * down(): menolak berjalan bila sudah ada data yang memakai fitur baru.
 */
return new class extends Migration
{
    private const LAMA = ['DRAFT', 'VERIFIED', 'APPROVED', 'QUEUED', 'PAID', 'SETTLE_SUBMITTED', 'SETTLE_APPROVED', 'CLOSED', 'CANCELLED'];
    private const BARU = ['DRAFT', 'VERIFIED', 'REJECTED', 'APPROVED', 'QUEUED', 'PAID', 'SETTLE_SUBMITTED', 'SETTLE_REJECTED', 'SETTLE_APPROVED', 'CLOSED', 'CANCELLED'];

    /** Nama index eksplisit per tabel (nama otomatis Laravel > 64 karakter untuk tabel settlement). */
    private const INDEX_ALOKASI = [
        'cost_register_details' => 'crd_allocation_index',
        'cost_register_settlement_details' => 'crsd_allocation_index',
    ];

    private function enum(array $nilai): string
    {
        return "ENUM('" . implode("','", $nilai) . "')";
    }

    /** Index alokasi bisa bernama eksplisit atau memakai nama otomatis dari versi awal migrasi ini. */
    private function namaIndexAlokasi(string $tabel, string $namaIndex): array
    {
        return [$namaIndex, $tabel . '_allocation_allocation_year_index'];
    }

    private function punyaIndexAlokasi(string $tabel, string $namaIndex): bool
    {
        foreach ($this->namaIndexAlokasi($tabel, $namaIndex) as $kandidat) {
            if (Schema::hasIndex($tabel, $kandidat)) {
                return true;
            }
        }

        return false;
    }

    public function up(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(self::BARU) . " NOT NULL DEFAULT 'DRAFT'");

        Schema::table('cost_registers', function (Blueprint $table) {
            if (! Schema::hasColumn('cost_registers', 'period_start')) {
                $table->date('period_start')->nullable()->after('period');
                $table->date('period_end')->nullable()->after('period_start');
                $table->enum('period_method', ['equal', 'manual'])->nullable()->after('period_end');
            }
        });

        if (! Schema::hasTable('cost_register_periods')) {
            Schema::create('cost_register_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cost_register_id')->constrained('cost_registers')->restrictOnDelete();
                $table->date('period');
                $table->unsignedBigInteger('amount');
                $table->timestamps();

                $table->unique(['cost_register_id', 'period']);
            });
        }

        foreach (self::INDEX_ALOKASI as $tabel => $namaIndex) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                if (! Schema::hasColumn($tabel, 'allocation')) {
                    $table->enum('allocation', ['po', 'annual', 'selected', 'cc'])->nullable()->after('cost_center_id');
                }
                if (! Schema::hasColumn($tabel, 'allocation_year')) {
                    $table->unsignedSmallInteger('allocation_year')->nullable()->after('allocation');
                }
            });

            // Nama index ditulis eksplisit: nama otomatis Laravel untuk
            // cost_register_settlement_details mencapai 65 karakter, melewati batas
            // identifier MySQL (64) sehingga pembuatan index gagal.
            if (! $this->punyaIndexAlokasi($tabel, $namaIndex)) {
                Schema::table($tabel, function (Blueprint $table) use ($namaIndex) {
                    $table->index(['allocation', 'allocation_year'], $namaIndex);
                });
            }
        }

        if (! Schema::hasTable('cost_register_detail_projects')) {
            Schema::create('cost_register_detail_projects', function (Blueprint $table) {
                $table->id();
                // Rincian dihapus/diganti hanya saat Expense masih Draft -> pivot ikut terhapus.
                $table->foreignId('cost_register_detail_id')->constrained('cost_register_details')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->unique(['cost_register_detail_id', 'project_id'], 'crdp_detail_project_unique');
            });
        }

        if (! Schema::hasTable('cost_register_settlement_detail_projects')) {
            Schema::create('cost_register_settlement_detail_projects', function (Blueprint $table) {
                $table->id();
                // Realisasi diganti saat diajukan ulang -> pivot ikut terhapus.
                $table->foreignId('cost_register_settlement_detail_id')->constrained('cost_register_settlement_details', 'id', 'crsdp_detail_fk')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects', 'id', 'crsdp_project_fk')->restrictOnDelete();
                $table->unique(['cost_register_settlement_detail_id', 'project_id'], 'crsdp_detail_project_unique');
            });
        }

        // Backfill rincian Expense baru (header tanpa type).
        $hasil = [];
        foreach (array_keys(self::INDEX_ALOKASI) as $tabel) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'allocation')) {
                continue;
            }

            $po = DB::table($tabel)->whereNull('allocation')->whereNotNull('project_id')
                ->whereIn('cost_register_id', DB::table('cost_registers')->whereNull('type')->select('id'))
                ->update(['allocation' => 'po']);
            $cc = DB::table($tabel)->whereNull('allocation')->whereNotNull('cost_center_id')
                ->whereIn('cost_register_id', DB::table('cost_registers')->whereNull('type')->select('id'))
                ->update(['allocation' => 'cc']);
            $hasil[] = "{$tabel}: po={$po}, cc={$cc}";
        }
        Log::info('Expense Fase 3 backfill allocation — ' . implode('; ', $hasil));
    }

    public function down(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        $status = DB::table('cost_registers')->whereIn('status', ['REJECTED', 'SETTLE_REJECTED'])->count();
        $periode = Schema::hasTable('cost_register_periods') ? DB::table('cost_register_periods')->count() : 0;
        // Kolom allocation bisa sudah tidak ada bila rollback sebelumnya berhenti di tengah.
        $alokasi = 0;
        foreach (array_keys(self::INDEX_ALOKASI) as $tabel) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, 'allocation')) {
                $alokasi += DB::table($tabel)->whereIn('allocation', ['annual', 'selected'])->count();
            }
        }
        if ($status > 0 || $periode > 0 || $alokasi > 0) {
            throw new \RuntimeException(
                "Rollback Expense Fase 3 dibatalkan: {$status} Expense berstatus REJECTED/SETTLE_REJECTED, "
                . "{$periode} baris periode, {$alokasi} rincian alokasi tahunan/PO terpilih. Data akan hilang."
            );
        }

        Schema::dropIfExists('cost_register_settlement_detail_projects');
        Schema::dropIfExists('cost_register_detail_projects');

        foreach (self::INDEX_ALOKASI as $tabel => $namaIndex) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            foreach ($this->namaIndexAlokasi($tabel, $namaIndex) as $kandidat) {
                if (Schema::hasIndex($tabel, $kandidat)) {
                    Schema::table($tabel, function (Blueprint $table) use ($kandidat) {
                        $table->dropIndex($kandidat);
                    });
                }
            }

            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $kolom = array_values(array_filter(
                    ['allocation', 'allocation_year'],
                    fn (string $k) => Schema::hasColumn($tabel, $k)
                ));
                if ($kolom !== []) {
                    $table->dropColumn($kolom);
                }
            });
        }

        Schema::dropIfExists('cost_register_periods');

        Schema::table('cost_registers', function (Blueprint $table) {
            $kolom = array_values(array_filter(
                ['period_start', 'period_end', 'period_method'],
                fn (string $k) => Schema::hasColumn('cost_registers', $k)
            ));
            if ($kolom !== []) {
                $table->dropColumn($kolom);
            }
        });

        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(self::LAMA) . " NOT NULL DEFAULT 'DRAFT'");
    }
};
