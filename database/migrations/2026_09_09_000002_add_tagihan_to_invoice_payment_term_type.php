<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah value 'Tagihan' ke enum payment_term_type — via raw statement
        // karena Laravel tidak punya cara native ubah enum tanpa doctrine/dbal.
        DB::statement("ALTER TABLE invoices MODIFY payment_term_type ENUM(
            'Down Payment', 'Termin', 'Full Payment', 'Final Payment', 'Tagihan'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE invoices SET payment_term_type = 'Termin' WHERE payment_term_type = 'Tagihan'");

        DB::statement("ALTER TABLE invoices MODIFY payment_term_type ENUM(
            'Down Payment', 'Termin', 'Full Payment', 'Final Payment'
        ) NOT NULL");
    }
};
