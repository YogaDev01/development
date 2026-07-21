<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename contact_person → term_of_payment
        DB::statement("ALTER TABLE projects CHANGE contact_person term_of_payment VARCHAR(100) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE projects CHANGE term_of_payment contact_person VARCHAR(255) NULL DEFAULT NULL");
    }
};