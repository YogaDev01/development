<?php

namespace App\Http\Controllers;

use App\Models\CostRegister;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CostRegisterController extends Controller
{
    use \App\Http\Controllers\Concerns\CapsLargeLists;

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $query = CostRegister::with(['project.customer', 'attachments', 'inputBy', 'beneficiary']);

        /*
        | Ringkasan dihitung AGREGAT, bukan dari koleksi baris.
        |
        | Sebelumnya seluruh isi cost_registers ditarik ke memori lalu
        | dihitung/dijumlahkan per tipe & status di atas koleksi itu. Dua
        | masalah: (1) biayanya naik terus seiring catatan bertambah, dan (2)
        | begitu daftar barisnya dibatasi (di bawah), angka ringkasannya akan
        | ikut terpotong dan salah TANPA ada tanda apa pun bahwa ia salah.
        |
        | Pola yang sama dipakai ProjectController::index() dengan alasan yang
        | sama persis.
        */
        $perStatus = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) as jumlah, COALESCE(SUM(amount),0) as total')
            ->groupBy('status')
            ->get();

        $perTipe = (clone $query)->toBase()
            ->selectRaw('type, COALESCE(SUM(amount),0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $summary = [
            'total'    => (int) $perStatus->sum('jumlah'),
            'fixed'    => (int) ($perTipe['FIXED_COST'] ?? 0),
            'overhead' => (int) ($perTipe['OVERHEAD'] ?? 0),
            // MN-09 — posisi alur Non-CR menuju Treasury.
            'draft'     => (int) ($perStatus->firstWhere('status', 'DRAFT')->jumlah ?? 0),
            'submitted' => (int) ($perStatus->firstWhere('status', 'SUBMITTED')->jumlah ?? 0),
            'queued'    => (int) ($perStatus->firstWhere('status', 'QUEUED')->jumlah ?? 0),
            'paid'      => (int) ($perStatus->firstWhere('status', 'PAID')->total ?? 0),
        ];

        // Baris dibatasi (Concerns\CapsLargeLists): daftar ini dirender penuh ke
        // DOM lalu diurus DataTables di peramban, jadi ribuan baris membekukan
        // halaman sebelum menampilkan apa pun.
        [$registers, $truncated, $total] = $this->cappedRows($query, 'id');
        $maxRows = $this->maxRows;

        return view('cost-register.index', compact('registers', 'summary', 'truncated', 'total', 'maxRows'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE / STORE
    |--------------------------------------------------------------------------
    | Dua mode:
    | - single  : satu nominal untuk satu PO (Fixed Cost atau Overhead).
    | - batch   : satu pool Overhead dibagi ke beberapa PO sekaligus (rata
    |             atau nominal manual per PO), ditandai batch_code yang sama.
    */

    public function create()
    {
        $projects = Project::with('customer:id,name')
            ->get(['id', 'code', 'name', 'customer_id'])
            ->sortBy(fn ($p) => strtolower(($p->customer->name ?? '') . '-' . $p->code))
            ->values();

        // MN-09 — tujuan transfer & rekening sumber, supaya Cost Register bisa
        // benar-benar dibayarkan lewat Treasury.
        $beneficiaries = \App\Models\Beneficiary::where('status', 'ACTIVE')
            ->orderBy('nickname')->get(['id', 'nickname', 'complete_name']);

        $sourceFunds = \App\Models\SourceFund::where('status', 'ACTIVE')
            ->orderBy('name')->get(['id', 'name']);

        return view('cost-register.create', compact('projects', 'beneficiaries', 'sourceFunds'));
    }

    public function store(Request $request)
    {
        $mode = $request->input('mode', 'single');

        if ($mode === 'batch') {
            return $this->storeBatch($request);
        }

        $request->validate([
            'project_id'   => 'required|exists:projects,id',
            'type'         => 'required|in:' . implode(',', CostRegister::TYPES),
            'amount'       => 'required',
            'period'       => 'nullable|date',
            'notes'        => 'required|string|max:2000',
            'beneficiary_id' => 'nullable|exists:beneficiaries,id',
            'source_fund_id' => 'nullable|exists:source_funds,id',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $amount = $this->normalizeMoney($request->amount);

        $register = DB::transaction(function () use ($request, $amount) {
            $cr = CostRegister::create([
                'project_id' => $request->project_id,
                'type'       => $request->type,
                'amount'     => $amount,
                'period'     => $request->period,
                'notes'          => $request->notes,
                'beneficiary_id' => $request->beneficiary_id,
                'source_fund_id' => $request->source_fund_id,
                // Selalu mulai dari DRAFT; pengajuan ke Treasury adalah aksi
                // terpisah yang eksplisit (MN-09).
                'status'         => 'DRAFT',
                'input_by'       => Auth::id(),
            ]);

            $this->storeAttachments($cr, $request);

            return $cr;
        });

        return redirect(route('cost-register.index'))
            ->with('success', 'Cost Register berhasil disimpan untuk ' . ($register->project->code ?? '-') . '.');
    }

    private function storeBatch(Request $request)
    {
        $request->validate([
            'project_ids'      => 'required|array|min:1',
            'project_ids.*'    => 'exists:projects,id',
            'total_amount'     => 'required',
            'distribution'     => 'required|in:equal,manual',
            'manual_amount'    => 'nullable|array',
            'period'           => 'nullable|date',
            'notes'            => 'required|string|max:2000',
            // MN-09 — satu alokasi batch berasal dari SATU pengeluaran ke satu
            // pihak, lalu dibagi ke beberapa PO. Jadi Beneficiary & rekeningnya
            // ada di level batch, bukan per baris: kalau ditanya per PO, user
            // akan mengisi nama yang sama berulang kali dan cepat atau lambat
            // salah satunya beda.
            'beneficiary_id'   => 'nullable|exists:beneficiaries,id',
            'source_fund_id'   => 'nullable|exists:source_funds,id',
            'attachments.*'    => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $projectIds  = $request->project_ids;
        $totalAmount = $this->normalizeMoney($request->total_amount);
        $batchCode   = 'OH-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));

        $amounts = [];
        if ($request->distribution === 'equal') {
            $count = count($projectIds);
            $base  = intdiv($totalAmount, $count);
            $remainder = $totalAmount - ($base * $count);
            foreach ($projectIds as $i => $pid) {
                // Sisa pembagian (kalau tidak habis dibagi rata) ditambahkan ke baris pertama.
                $amounts[$pid] = $base + ($i === 0 ? $remainder : 0);
            }
        } else {
            $manual = $request->input('manual_amount', []);
            $sum = 0;
            foreach ($projectIds as $pid) {
                $amounts[$pid] = $this->normalizeMoney($manual[$pid] ?? 0);
                $sum += $amounts[$pid];
            }
            if ($sum !== $totalAmount) {
                return back()->withInput()->with('error',
                    'Total nominal manual per PO (' . number_format($sum, 0, ',', '.') . ') harus sama dengan Total Overhead (' . number_format($totalAmount, 0, ',', '.') . ').'
                );
            }
        }

        DB::transaction(function () use ($request, $projectIds, $amounts, $batchCode) {
            foreach ($projectIds as $pid) {
                $cr = CostRegister::create([
                    'project_id'     => $pid,
                    'type'           => 'OVERHEAD',
                    'amount'         => $amounts[$pid],
                    'period'         => $request->period,
                    'notes'          => $request->notes,
                    'batch_code'     => $batchCode,
                    // Beneficiary & rekening diturunkan dari level batch ke
                    // setiap barisnya, supaya tiap baris bisa langsung diajukan
                    // ke Treasury tanpa harus diedit satu per satu dulu.
                    'beneficiary_id' => $request->beneficiary_id ?: null,
                    'source_fund_id' => $request->source_fund_id ?: null,
                    'status'         => 'DRAFT',
                    'input_by'       => Auth::id(),
                ]);

                $this->storeAttachments($cr, $request);
            }
        });

        $pesan = 'Alokasi Overhead berhasil disimpan ke ' . count($projectIds) . ' PO (batch ' . $batchCode . ').';

        if (!$request->beneficiary_id) {
            $pesan .= ' Beneficiary belum diisi — lengkapi dulu sebelum baris ini bisa diajukan ke Treasury.';
        }

        return redirect(route('cost-register.index'))->with('success', $pesan);
    }

    private function storeAttachments(CostRegister $cr, Request $request): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if (!$file->isValid()) continue;

            $path = $file->store('cost_register_attachments', 'secure');
            $cr->attachments()->create([
                'title'       => $file->getClientOriginalName(),
                'file_path'   => $path,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function normalizeMoney($value): int
    {
        if (blank($value)) return 0;
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $cr = CostRegister::findOrFail($id);

        // MN-09 — yang sudah masuk antrean/terbayar tidak boleh dihapus:
        // Treasury dan laporan biaya PO sudah merujuk baris ini.
        if (!$cr->isEditable()) {
            return back()->with('error',
                'Cost Register berstatus ' . $cr->status . ' tidak bisa dihapus.');
        }

        $cr->delete(); // attachments dihapus cascade lewat FK

        return back()->with('success', 'Cost Register berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | MN-09 — ALUR NON-CR MENUJU TREASURY
    |--------------------------------------------------------------------------
    | DRAFT -> SUBMITTED : siap dimasukkan Treasury ke batch pembayaran.
    | Status QUEUED & PAID ditetapkan oleh Treasury, bukan dari sini.
    */

    public function submit($id)
    {
        $cr = CostRegister::findOrFail($id);

        if (!$cr->isDraft()) {
            return back()->with('error', 'Hanya Cost Register berstatus DRAFT yang bisa diajukan.');
        }

        if (!$cr->beneficiary_id) {
            return back()->with('error', 'Isi dulu Beneficiary — Treasury perlu tahu tujuan transfernya.');
        }

        $cr->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);

        return back()->with('success', 'Cost Register diajukan ke Treasury.');
    }

    public function cancel(Request $request, $id)
    {
        $cr = CostRegister::findOrFail($id);

        if (!$cr->isEditable()) {
            return back()->with('error',
                'Cost Register berstatus ' . $cr->status . ' tidak bisa dibatalkan dari sini.');
        }

        $cr->update(['status' => 'CANCELLED']);

        return back()->with('success', 'Cost Register dibatalkan.');
    }
}
