<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan performa — audit slow query (lihat Server & Database Capacity
 * Summary, 31 Agu 2026): 4 tabel yang sering di-filter/di-cek by status di
 * hampir semua modul (Todo List, Treasury/Daily Transaction, Cost Request
 * dropdown, sinkronisasi status Invoice) ternyata TIDAK punya index di kolom
 * status-nya sejak awal dibuat — jadi tiap query "WHERE status = ..." full
 * table scan. Idempotent: aman dijalankan ulang kalau index-nya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('daily_transactions', 'status');
        $this->addIndexIfMissing('daily_transaction_items', 'status');
        $this->addIndexIfMissing('budget_plans', 'status');
        $this->addIndexIfMissing('project_po_numbers', 'status');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('daily_transactions', 'status');
        $this->dropIndexIfExists('daily_transaction_items', 'status');
        $this->dropIndexIfExists('budget_plans', 'status');
        $this->dropIndexIfExists('project_po_numbers', 'status');
    }

    private function addIndexIfMissing(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }
        if (Schema::hasIndex($table, [$column])) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->index($column);
        });
    }

    private function dropIndexIfExists(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasIndex($table, [$column])) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->dropIndex([$column]);
        });
    }
};
