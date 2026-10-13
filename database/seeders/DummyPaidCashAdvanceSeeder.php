<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\Cluster;
use App\Models\RequestDetail;
use App\Models\RequestType;
use Illuminate\Database\Seeder;

/**
 * Satu Cash Advance dummy berstatus PAID, nilai 2.000.000, deskripsi
 * "Deskripsi Request" — dipakai menguji tampilan detail CR (tab Request dan,
 * setelah settlement diisi, tab Settlement).
 *
 * Status PAID berarti CR ini siap di-settle: tab Settlement baru muncul
 * setelah ada data realisasi, jadi seeder ini TIDAK mengisi realisasi.
 *
 * Baris ditandai notes = 'DUMMY_PAID_CA' supaya mudah dibersihkan.
 *
 * Jalankan:  php artisan db:seed --class=DummyPaidCashAdvanceSeeder
 * Hapus:     delete from request_detail where budget_request_id in
 *              (select id from budget_request where notes = 'DUMMY_PAID_CA');
 *            delete from budget_request where notes = 'DUMMY_PAID_CA';
 */
class DummyPaidCashAdvanceSeeder extends Seeder
{
    private const PENANDA = 'DUMMY_PAID_CA';
    private const DESKRIPSI = 'Deskripsi Request';
    private const NOMINAL = 2_000_000;

    public function run(): void
    {
        $sudahAda = BudgetRequest::where('notes', self::PENANDA)->count();

        if ($sudahAda > 0) {
            $this->command?->warn("Sudah ada {$sudahAda} CR dummy bertanda " . self::PENANDA . ' — dilewati.');
            $this->command?->line("  delete from budget_request where notes = '" . self::PENANDA . "';");
            return;
        }

        $tipe = RequestType::whereRaw('LOWER(name) = ?', ['cash advance'])->first();

        if (! $tipe) {
            $this->command?->error("Tipe 'Cash Advance' tidak ada di tabel request_type — dibatalkan.");
            return;
        }

        // Budget plan yang sudah punya rincian: Sub Item Budget di tab Request
        // diambil dari budget_plan_detail.deskripsi, jadi tanpa itu kolomnya '-'.
        $plan = BudgetPlan::with(['project', 'details'])
            ->whereHas('details')
            ->whereHas('project.manager')
            ->latest('id')
            ->first();

        if (! $plan) {
            $this->command?->error('Tidak ada Budget Plan yang punya rincian + manager project — dibatalkan.');
            return;
        }

        $penerima = Beneficiary::where('status', 'ACTIVE')->orderBy('id')->first() ?? Beneficiary::orderBy('id')->first();

        if (! $penerima) {
            $this->command?->error('Tabel beneficiary kosong — dibatalkan.');
            return;
        }

        $cluster = Cluster::where('project_id', $plan->project_id)
            ->where('status', 'ACTIVE')
            ->value('id');

        $cr = BudgetRequest::create([
            'code'           => BudgetRequest::generateCode((string) ($plan->project->code ?? '0000')),
            'description'    => self::DESKRIPSI,
            'amount'         => self::NOMINAL,
            'notes'          => self::PENANDA,
            'applicant_id'   => $plan->project->manager_id,
            'beneficiary_id' => $penerima->id,
            'type_id'        => $tipe->id,
            'cluster_id'     => $cluster,
            'budget_plan_id' => $plan->id,
            'status'         => 'PAID',
            'submitted_at'   => now()->subDays(10),
            'approved_at'    => now()->subDays(7),
            'paid_at'        => now()->subDays(5),
        ]);

        $bd = $plan->details->first();

        RequestDetail::create([
            'budget_request_id' => $cr->id,
            'budget_detail_id'  => $bd->id,
            'account_id'        => $bd->account_id,
            'description'       => self::DESKRIPSI,
            'amount'            => self::NOMINAL,
            'qty'               => 1,
            'total'             => self::NOMINAL,
        ]);

        $this->command?->newLine();
        $this->command?->info("CR {$cr->code} dibuat — Cash Advance, PAID, Rp " . number_format(self::NOMINAL, 0, ',', '.') . '.');
        $this->command?->line("  Budget Plan: {$plan->id} (project {$plan->project->code}), Beneficiary: {$penerima->nickname}");
    }
}
