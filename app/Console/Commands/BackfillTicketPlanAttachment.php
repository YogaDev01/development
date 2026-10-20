<?php

namespace App\Console\Commands;

use App\Models\TroubleTicket;
use App\Services\TicketDetailImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Melampirkan ringkasan detail tiket (PNG) ke Cost Request yang SUDAH terbit
 * dari approval plan tetapi belum punya lampiran global.
 *
 * Perlu karena lampiran otomatis baru ada sejak 20 Okt 2026: CR yang
 * di-approve sebelum itu lahir tanpa lampiran, sementara tipe Cash Advance
 * mewajibkannya, sehingga Finance tidak bisa menyimpan ulang CR-nya.
 *
 * Hanya MENAMBAH lampiran. Tidak menyentuh status, nilai, rincian, maupun
 * kolom lain CR; CR yang lampirannya sudah ada dilewati.
 *
 * Dry run kecuali --apply.
 *
 *   php artisan tt:backfill-plan-attachment
 *   php artisan tt:backfill-plan-attachment --cr=CR-102600200001 --apply
 */
class BackfillTicketPlanAttachment extends Command
{
    protected $signature = 'tt:backfill-plan-attachment
        {--cr= : Batasi ke satu kode Cost Request}
        {--apply : Tulis lampiran (tanpa opsi ini hanya dry run)}
        {--replace : Ganti lampiran detail tiket yang sudah dibuat command/approve sebelumnya}';

    protected $description = 'Lampirkan detail tiket ke CR hasil approval plan yang lampirannya masih kosong';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $kode = $this->option('cr');

        $tickets = TroubleTicket::query()
            ->whereNotNull('budget_request_id')
            ->with(['site', 'customer:id,name', 'creator:id,name', 'technician:id,name', 'closer:id,name',
                'budgetPlan:id,code', 'rcaRef', 'subRcaRef', 'budgetRequest'])
            ->when($kode, fn ($q) => $q->whereHas('budgetRequest', fn ($b) => $b->where('code', $kode)))
            ->get();

        if ($tickets->isEmpty()) {
            $this->warn('Tidak ada tiket dengan Cost Request hasil approval plan' . ($kode ? ' untuk ' . $kode : '') . '.');

            return self::SUCCESS;
        }

        $ditulis = 0;

        foreach ($tickets as $t) {
            $cr = $t->budgetRequest;

            if (! $cr) {
                $this->warn("{$t->ticket_number}: Cost Request-nya tidak ditemukan lagi — dilewati.");
                continue;
            }

            /* Hanya lampiran buatan sendiri yang boleh diganti — dikenali dari
               awalan namanya. Lampiran yang diunggah manusia tidak disentuh. */
            $lama = $cr->attachments()->where('original_name', 'like', 'detail-tiket-%')->get();
            $lain = $cr->attachments()->where('original_name', 'not like', 'detail-tiket-%')->exists();

            if ($lain || ($lama->isNotEmpty() && ! $this->option('replace'))) {
                $this->line("{$t->ticket_number} → {$cr->code}: sudah punya lampiran, dilewati"
                    . ($lama->isNotEmpty() && ! $lain ? ' (pakai --replace untuk menggantinya)' : '') . '.');
                continue;
            }

            $baris = $t->planCosts()->orderBy('id')->get();

            if ($baris->isEmpty()) {
                $this->warn("{$t->ticket_number} → {$cr->code}: tidak punya baris biaya plan — dilewati.");
                continue;
            }

            if (! $apply) {
                $this->info("[dry run] {$t->ticket_number} → {$cr->code}: akan dilampiri detail tiket.");
                $ditulis++;
                continue;
            }

            foreach ($lama as $att) {
                Storage::disk('secure')->delete($att->path);
                $att->delete();
            }

            $png = TicketDetailImage::render($t, $baris);
            $path = 'cost_request_attachments/' . Str::random(40) . '.png';
            Storage::disk('secure')->put($path, $png);

            $cr->attachments()->create([
                'original_name' => 'detail-tiket-' . str_replace(['/', '\\', ' '], '-', $t->ticket_number) . '.png',
                'stored_name'   => basename($path),
                'path'          => $path,
                'mime_type'     => 'image/png',
                'size'          => strlen($png),
                // Pelakunya = penyetuju plan; tidak ada sesi login saat command jalan.
                'uploaded_by'   => $t->plan_approved_by,
            ]);

            $this->info("{$t->ticket_number} → {$cr->code}: lampiran dibuat (" . strlen($png) . " byte).");
            $ditulis++;
        }

        $this->newLine();
        $this->info($apply ? "Selesai. {$ditulis} lampiran dibuat." : "Dry run. {$ditulis} CR akan dilampiri — ulangi dengan --apply.");

        return self::SUCCESS;
    }
}
