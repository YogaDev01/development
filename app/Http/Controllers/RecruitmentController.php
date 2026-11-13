<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateLog;
use App\Models\Employee;
use App\Models\ManpowerRequest;
use App\Models\ManpowerRequestDetail;
use App\Models\ManpowerRequestLog;
use App\Models\Recruitment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recruitment (HRIS) — tahap 2b. Proses rekrutmen per rincian kebutuhan MP.
 *
 * Hanya HR_ROLES (superadmin/hr) — sama dengan approver Manpower Request.
 * Hire membuat baris employees baru (lihat hire()).
 */
class RecruitmentController extends Controller
{
    private function authorizeHr(): void
    {
        abort_unless(Auth::user()->hasRole(ManpowerRequest::HR_ROLES), 403, 'Hanya HR yang dapat mengelola recruitment.');
    }

    public function index(Request $request)
    {
        $this->authorizeHr();

        $all = Recruitment::with(['detail.request', 'candidate'])->latest('id')->get()
            ->reject(fn ($r) => $r->status === 'RETURNED');

        $stage = $request->query('stage');
        $rows = $stage
            ? $all->filter(fn ($r) => $stage === 'CLOSE' ? in_array($r->status, ['REJECTED', 'CANCELED'], true) : ($r->isActive() || $r->status === 'APPROVED') && $r->stage === $stage)
            : $all;

        // Pilihan untuk "Recruitment Baru": kandidat tersedia + rincian terbuka dari MP APPROVED.
        $openDetails = ManpowerRequestDetail::with('request')
            ->whereIn('status', ['OPEN', 'ON_PROCESS'])
            ->whereHas('request', fn ($q) => $q->where('status', 'APPROVED'))
            ->get();
        $available = Candidate::with('recruitments')->whereNull('hired_employee_id')->orderBy('name')->get()
            ->filter(fn ($c) => ! $c->activeRecruitment())->values();

        return view('recruitment.index', [
            'rows' => $rows->values(), 'all' => $all, 'stage' => $stage,
            'openDetails' => $openDetails, 'available' => $available,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'candidate_id' => 'required|exists:candidates,id',
            'detail_id'    => 'required|exists:manpower_request_details,id',
            'note'         => 'nullable|string|max:500',
        ]);

        $rec = DB::transaction(function () use ($data) {
            $cand   = Candidate::lockForUpdate()->findOrFail($data['candidate_id']);
            $detail = ManpowerRequestDetail::lockForUpdate()->with('request')->findOrFail($data['detail_id']);

            if ($cand->hired_employee_id || $cand->recruitments()->where('status', 'ON_PROCESS')->exists()) {
                throw ValidationException::withMessages(['candidate_id' => 'Kandidat sedang diproses atau sudah onboard.']);
            }
            if (! in_array($detail->status, ['OPEN', 'ON_PROCESS'], true) || $detail->request->status !== 'APPROVED') {
                throw ValidationException::withMessages(['detail_id' => 'Kebutuhan ini tidak terbuka (pengajuan belum disetujui / sudah terisi / dibatalkan).']);
            }

            $rec = Recruitment::create([
                'detail_id' => $detail->id, 'candidate_id' => $cand->id,
                'stage' => 'SCREENING', 'status' => 'ON_PROCESS', 'scheduled' => now()->toDateString(),
            ]);
            if ($detail->status === 'OPEN') {
                $detail->update(['status' => 'ON_PROCESS']);
            }
            $note = trim((string) ($data['note'] ?? ''));
            $this->candLog($cand, "Recruitment dibuat — kebutuhan {$detail->position} ({$detail->request->request_no}), tahap SCREENING" . ($note ? ": {$note}" : ''));
            $this->mpEvent($detail->request, "Recruitment dimulai — {$detail->position} · {$cand->name} ({$cand->code})", $note ?: null);

            return $rec;
        });

        return redirect()->route('recruitment.index')->with('success', 'Recruitment dibuat — ' . $rec->candidate->name . ' masuk SCREENING.');
    }

    /** next | reject | cancel */
    public function move(Request $request, Recruitment $recruitment, string $act)
    {
        $this->authorizeHr();
        abort_unless(in_array($act, ['next', 'reject', 'cancel'], true), 404);

        $note = trim((string) $request->input('note'));
        if ($act !== 'next' && $note === '') {
            throw ValidationException::withMessages(['note' => $act === 'reject' ? 'Alasan penolakan wajib diisi.' : 'Alasan pembatalan wajib diisi.']);
        }

        $msg = DB::transaction(function () use ($recruitment, $act, $note) {
            $r = Recruitment::lockForUpdate()->with(['detail.request', 'candidate'])->findOrFail($recruitment->id);
            abort_unless($r->isActive(), 409, 'Proses ini sudah tidak aktif.');
            abort_if($r->detail->status === 'DONE', 409, 'Kebutuhan sudah terisi.');

            if ($act === 'next') {
                return $this->advance($r, $note);
            }

            $r->update(['status' => $act === 'reject' ? 'REJECTED' : 'CANCELED', 'decided_by' => Auth::id(), 'decided_at' => now()->toDateString()]);
            if (! Recruitment::where('detail_id', $r->detail_id)->where('status', 'ON_PROCESS')->exists()) {
                $r->detail->update(['status' => 'OPEN']);
            }
            $verb = $act === 'reject' ? 'Ditolak' : 'Proses dibatalkan';
            $this->candLog($r->candidate, "{$verb} di tahap {$r->stage} — kebutuhan {$r->detail->position} ({$r->detail->request->request_no})" . ($note ? ": {$note}" : ''));
            $this->candLog($r->candidate, 'Kembali ke database kandidat');
            $this->mpEvent($r->detail->request, ($act === 'reject' ? 'Kandidat ditolak' : 'Kandidat mundur') . " di tahap {$r->stage} — {$r->detail->position} dibuka lagi", $note);

            return $r->candidate->name . ($act === 'reject' ? ' ditolak' : ' batal') . ' — kebutuhan terbuka lagi.';
        });

        return back()->with('success', $msg);
    }

    private function advance(Recruitment $r, string $note): string
    {
        $next = $r->nextStage();
        abort_unless($next, 409, 'Sudah di tahap akhir.');

        if ($next === 'ONBOARD') {
            return $this->hire($r, $note);
        }
        if ($next === 'KONTRAK' && $r->stage === 'INTERVIEW USER' && ! preg_match('/teknisi/i', $r->detail->position)) {
            $this->candLog($r->candidate, 'Posisi non-teknisi — tahap TRAINING dilewati, langsung ke KONTRAK');
        }
        $r->update(['stage' => $next, 'scheduled' => now()->toDateString()]);
        $this->candLog($r->candidate, "Maju ke tahap {$next}" . ($next === 'KONTRAK' ? ' — kontrak kerja diteken' : '') . ($note ? " — {$note}" : ''));

        return "Tahap → {$next}";
    }

    /**
     * ONBOARD: buat karyawan baru dari data kandidat.
     *
     * KEPUTUSAN (perlu konfirmasi): karyawan dibuat dengan status INACTIVE dan
     * gaji 0 agar tidak otomatis ikut payroll sebelum HR melengkapi data
     * (NIK, rekening, gaji) dan mengaktifkannya di Master Employee. Nomor
     * induk (employee_number) dibiarkan kosong untuk diisi HR.
     */
    private function hire(Recruitment $r, string $note): string
    {
        $c = $r->candidate;
        $d = $r->detail;

        $emp = Employee::create([
            'name' => $c->name, 'birth_place' => $c->birth_place, 'birth_date' => $c->birth_date,
            'gender' => $c->gender, 'address' => $c->address, 'phone' => $c->phone,
            'status' => 'INACTIVE', 'default_salary' => 0, 'input_by' => Auth::id(),
            'notes' => "Dibuat dari recruitment {$c->code} ({$d->request->request_no})",
        ]);

        $r->update(['stage' => 'ONBOARD', 'status' => 'APPROVED', 'decided_by' => Auth::id(), 'decided_at' => now()->toDateString()]);
        $c->update(['hired_employee_id' => $emp->id]);
        $d->update(['filled_employee_id' => $emp->id, 'status' => 'DONE']);

        $code = 'EMP-' . str_pad((string) $emp->id, 4, '0', STR_PAD_LEFT);
        $this->candLog($c, 'Maju ke tahap ONBOARD — mulai bekerja' . ($note ? ": {$note}" : ''));
        $this->candLog($c, "ID karyawan {$code} terbit atas nama {$c->name} (status INACTIVE sampai HR melengkapi data)");
        $this->candLog($c, "Kebutuhan {$d->position} ({$d->request->request_no}) ditutup — status DONE");

        // Kandidat lain yang masih memproses kebutuhan yang sama otomatis kembali ke talent pool.
        $others = Recruitment::where('detail_id', $d->id)->where('status', 'ON_PROCESS')->where('id', '!=', $r->id)->with('candidate')->get();
        foreach ($others as $o) {
            $o->update(['status' => 'RETURNED', 'decided_by' => Auth::id(), 'decided_at' => now()->toDateString()]);
            $this->candLog($o->candidate, "Kebutuhan {$d->position} ({$d->request->request_no}) sudah terisi kandidat lain — kembali ke Talent Pool");
        }

        $mp = $d->request->load('details');
        $this->mpEvent($mp, "Kebutuhan {$d->position} → DONE · {$c->name} ({$c->code})", "ID karyawan {$code} terbit. Status pengajuan sekarang {$mp->displayStatus()}.");

        return "ID karyawan {$code} terbit untuk {$c->name}" . ($others->count() ? " · {$others->count()} kandidat lain ditutup" : '') . '.';
    }

    private function candLog(Candidate $c, string $text): void
    {
        CandidateLog::create(['candidate_id' => $c->id, 'user_id' => Auth::id(), 'description' => $text]);
    }

    private function mpEvent(ManpowerRequest $mp, string $event, ?string $note = null): void
    {
        ManpowerRequestLog::create([
            'manpower_request_id' => $mp->id, 'user_id' => Auth::id(), 'is_system' => true, 'event' => $event, 'text' => $note,
        ]);
    }
}
