<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\SettlementReceipt;
use App\Models\TroubleTicket;
use App\Support\DocumentSequence;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kwitansi digital per baris settlement: penerima uang menandatangani di layar
 * HP teknisi, sistem membuat PDF-nya. Ini kwitansi biasa dalam bentuk digital
 * (keputusan 6 Nov 2026), bukan tanda tangan elektronik tersertifikasi.
 *
 * PDF disimpan dua kali: arsip tetap di settlement_receipts/, dan salinan sebagai
 * lampiran baris supaya Finance melihatnya di detail CR dengan hak akses yang
 * sudah ada. Salinan boleh ikut hilang saat baris disunting; arsip tidak.
 */
class SettlementReceiptService
{
    /**
     * Dipanggil sesudah draft settlement tersimpan. Baris yang nilainya berubah
     * menggugurkan kwitansinya, lalu baris bertanda tangan baru dibuatkan kwitansi.
     *
     * @param  array<int|string, BudgetRequestRealization>  $map  indeks form -> baris tersimpan
     */
    public function sync(Request $request, BudgetRequest $cr, array $map): void
    {
        if (! SettlementReceipt::available()) {
            return;
        }

        // Berlaku di SEMUA jalur simpan: kwitansi tidak boleh tetap berlaku
        // setelah nilai barisnya diubah dari form Cost Request.
        $baris = collect($map)->keyBy('id');

        SettlementReceipt::where('budget_request_id', $cr->id)->where('status', SettlementReceipt::VALID)->get()
            ->each(function (SettlementReceipt $k) use ($baris) {
                $row = $baris->get($k->realization_id);
                if (! $row || (int) $row->total !== (int) $k->amount) {
                    $this->gugurkan($k, $row);
                }
            });

        if (! $request->boolean('from_ticket')) {
            return;
        }

        $nama = (array) $request->input('recipient_names', []);

        foreach ((array) $request->input('signatures', []) as $i => $raw) {
            $raw = (string) $raw;
            $row = $map[$i] ?? null;

            if (! $row || ! str_starts_with($raw, 'data:image/png;base64,')) {
                continue;
            }

            $png = base64_decode(substr($raw, strlen('data:image/png;base64,')), true);
            if ($png === false) {
                continue;
            }

            $penerima = trim((string) ($nama[$i] ?? ''));
            if ($penerima === '') {
                throw ValidationException::withMessages(['recipient_names' => 'Nama penerima wajib diisi pada baris yang ditandatangani.']);
            }

            $this->terbitkan($cr, $row, $penerima, $png);
        }
    }

    /**
     * Diperiksa SEBELUM transaksi: gagal di dalam transaksi sesudah lampiran
     * dihapus akan menghilangkan berkas yang barisnya dipulihkan rollback.
     */
    public function assertReady(Request $request): void
    {
        if (! SettlementReceipt::available() || ! $request->boolean('from_ticket')) {
            return;
        }

        $nama = (array) $request->input('recipient_names', []);

        foreach ((array) $request->input('signatures', []) as $i => $raw) {
            if (! str_starts_with((string) $raw, 'data:image/png;base64,')) {
                continue;
            }
            if (trim((string) ($nama[$i] ?? '')) === '') {
                throw ValidationException::withMessages(['recipient_names' => 'Nama penerima wajib diisi pada baris yang ditandatangani.']);
            }
            if (! class_exists(Dompdf::class)) {
                throw ValidationException::withMessages(['signatures' => 'Pembuat PDF belum terpasang di server. Hubungi admin.']);
            }
        }
    }

    private function terbitkan(BudgetRequest $cr, BudgetRequestRealization $row, string $penerima, string $png): void
    {
        $lama = SettlementReceipt::where('realization_id', $row->id)->where('status', SettlementReceipt::VALID)->first();
        if ($lama) {
            $this->gugurkan($lama, $row);
        }

        $urut  = DocumentSequence::next('settlement-receipt', fn () => SettlementReceipt::count());
        $nomor = 'KW-' . now()->format('my') . '-' . str_pad((string) $urut, 5, '0', STR_PAD_LEFT);
        $ticket = TroubleTicket::forCostRequest($cr->id)->first();

        $data = [
            'nomor'    => $nomor,
            'ticket'   => $ticket?->ticket_number,
            'cr'       => $cr->code,
            'penerima' => $penerima,
            'jumlah'   => (int) $row->total,
            'keperluan' => $row->notes ?: $row->description,
            'kategori' => $row->description,
            'vendor'   => $row->vendor_name,
            'tanggal'  => $row->transaction_date ?? now(),
            'teknisi'  => Auth::user()?->name,
            'waktu'    => now(),
            'bukti'    => $row->attachments()->pluck('original_name')->all(),
            'ttd'      => 'data:image/png;base64,' . base64_encode($png),
        ];

        $pdf = $this->render($data);

        $disk = Storage::disk('secure');
        $dasar = 'settlement_receipts/' . Str::random(40);
        $disk->put($dasar . '.png', $png);
        $disk->put($dasar . '.pdf', $pdf);

        SettlementReceipt::create([
            'receipt_number' => $nomor, 'budget_request_id' => $cr->id, 'trouble_ticket_id' => $ticket?->id,
            'realization_id' => $row->id, 'request_detail_id' => $row->request_detail_id,
            'recipient_name' => $penerima, 'amount' => (int) $row->total, 'purpose' => mb_substr((string) $data['keperluan'], 0, 500),
            'transaction_date' => $data['tanggal'], 'signature_path' => $dasar . '.png', 'pdf_path' => $dasar . '.pdf',
            'signed_at' => $data['waktu'], 'captured_by' => Auth::id(), 'status' => SettlementReceipt::VALID,
        ]);

        // Salinan sebagai lampiran baris: bukti di detail CR dan syarat kirim settlement.
        $salinan = 'cost_request_realization_details/' . Str::random(40) . '.pdf';
        $disk->put($salinan, $pdf);
        $row->attachments()->create([
            'original_name' => $this->namaLampiran($nomor), 'stored_name' => basename($salinan), 'path' => $salinan,
            'mime_type' => 'application/pdf', 'size' => strlen($pdf), 'uploaded_by' => Auth::id(),
        ]);
    }

    /** Kwitansi tidak lagi berlaku: arsipnya tetap, salinannya di baris dilepas. */
    private function gugurkan(SettlementReceipt $k, ?BudgetRequestRealization $row): void
    {
        $k->update(['status' => SettlementReceipt::SUPERSEDED]);

        $row?->attachments()->where('original_name', $this->namaLampiran($k->receipt_number))->get()
            ->each(function ($att) {
                $path = $att->path;
                $att->delete();
                // Berkas dihapus setelah transaksi selesai, supaya rollback tidak menyisakan baris tanpa berkas.
                DB::afterCommit(fn () => Storage::disk('secure')->delete($path));
            });
    }

    private function namaLampiran(string $nomor): string
    {
        return 'kwitansi-' . $nomor . '.pdf';
    }

    private function render(array $data): string
    {
        if (! class_exists(Dompdf::class)) {
            throw ValidationException::withMessages(['signatures' => 'Pembuat PDF belum terpasang di server. Hubungi admin.']);
        }

        $dompdf = new Dompdf();
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->loadHtml(view('trouble-ticket.receipt', $data)->render());
        $dompdf->render();

        return $dompdf->output();
    }
}
