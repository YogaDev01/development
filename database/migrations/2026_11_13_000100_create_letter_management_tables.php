<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Letter Management (HRIS) — tahap 1 (2026-11-13).
 *
 * Aditif: enam tabel baru + menu 'letter-management'. Tidak menyentuh tabel
 * yang sudah ada. Relasi ke customers/employees/teams/departments/users
 * memakai FK RESTRICT (master tidak boleh terhapus selama dipakai surat).
 * Menu hanya didaftarkan; pemberiannya ke role lewat modal Menu Access
 * (superadmin lolos otomatis).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('letter_categories')) {
            Schema::create('letter_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('letters')) {
            Schema::create('letters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('letter_categories')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('type', 10);                       // internal | external
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('letter_no', 60)->unique();
                $table->string('reference_no', 100)->nullable();
                $table->string('status', 15)->default('draft')->index();
                $table->string('sign_mode', 10)->nullable();      // digital | wet
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
                $table->foreign('team_id')->references('id')->on('teams')->restrictOnDelete();
                $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('letter_details')) {
            Schema::create('letter_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('letter_id')->unique()->constrained('letters')->cascadeOnDelete();
                $table->text('header')->nullable();
                $table->longText('body')->nullable();
                $table->json('meta')->nullable();                 // [[label, isi], ...]
                $table->json('items')->nullable();                // [{desc, price, qty, unit}, ...] (rupiah integer)
                $table->decimal('tax', 5, 2)->default(0);         // persen
                $table->text('note')->nullable();
                $table->string('closing', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('letter_attachments')) {
            Schema::create('letter_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('letter_id')->constrained('letters')->cascadeOnDelete();
                $table->string('path', 255);                      // disk 'secure'
                $table->string('original_name', 255);
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('kind', 15)->default('lampiran');  // lampiran | balasan
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('letter_signatures')) {
            Schema::create('letter_signatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('letter_id')->constrained('letters')->cascadeOnDelete();
                $table->foreignId('signer_id')->constrained('users')->restrictOnDelete();
                $table->string('sign_as', 100);
                $table->timestamp('signed_at')->nullable();
                $table->string('signed_by', 10)->nullable();      // digital | wet
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('letter_logs')) {
            Schema::create('letter_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('letter_id')->constrained('letters')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 30);
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Kategori awal sesuai mockup.
        $awal = [
            'BAST' => 'Berita Acara Serah Terima', 'SPH' => 'Surat Penawaran Harga',
            'PO' => 'Purchase Order', 'SK' => 'Surat Keputusan', 'SPK' => 'Surat Perintah Kerja',
        ];
        foreach ($awal as $code => $name) {
            DB::table('letter_categories')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('menu_items')
            && ! DB::table('menu_items')->where('key', 'letter-management')->exists()) {
            DB::table('menu_items')->insert([
                'key'        => 'letter-management',
                'label'      => 'Letter Management',
                'icon'       => 'fas fa-envelope-open-text',
                'route'      => '/letter',
                'section'    => 'hr',
                'sort_order' => 70,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Hanya aman bila belum ada surat: tabel berisi dokumen tidak dihapus otomatis. */
    public function down(): void
    {
        if (Schema::hasTable('letters') && DB::table('letters')->exists()) {
            throw new RuntimeException('Rollback dibatalkan: tabel letters sudah berisi data.');
        }

        if (Schema::hasTable('menu_items')) {
            $id = DB::table('menu_items')->where('key', 'letter-management')->value('id');
            if ($id) {
                DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                DB::table('menu_items')->where('id', $id)->delete();
            }
        }

        foreach (['letter_logs', 'letter_signatures', 'letter_attachments', 'letter_details', 'letters', 'letter_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
