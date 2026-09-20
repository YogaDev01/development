<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Persentase penagihan dari subtotal (mis. Termin 50%). NULL = tagih penuh (100%).
            $table->decimal('billing_percentage', 5, 2)->nullable()->after('tax_rate');
            // Nominal setelah persentase diterapkan (dasar perhitungan pajak & total tagihan).
            $table->unsignedBigInteger('billed_amount')->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_percentage', 'billed_amount']);
        });
    }
};
