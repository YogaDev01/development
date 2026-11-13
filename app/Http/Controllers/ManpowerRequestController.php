<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ManpowerRequest;
use App\Models\ManpowerRequestDetail;
use App\Models\ManpowerRequestLog;
use App\Models\Project;
use App\Support\DocumentSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manpower Request (HRIS) — tahap 2a: pengajuan kebutuhan pegawai.
 *
 * Gerbang: route menu:manpower-request. Visibilitas: HR_ROLES melihat semua
 * pengajuan, user lain hanya miliknya sendiri. Approve/reject hanya HR_ROLES
 * (ASUMSI, perlu konfirmasi). Recruitment & Kandidat = tahap 2b.
 */
class ManpowerRequestController extends Controller
{
    private function isHr(): bool
    {
        return Auth::user()->hasRole(ManpowerRequest::HR_ROLES);
    }

    /** Pengajuan yang boleh dilihat user ini; selain itu 404 (tidak membocorkan keberadaan). */
    private function visible(ManpowerRequest $mp): ManpowerRequest
    {
        abort_unless($this->isHr() || (int) $mp->requester_id === (int) Auth::id(), 404);

        return $mp;
    }

    public function index(Request $request)
    {
        $q = ManpowerRequest::with(['requester', 'department', 'project', 'details']);
        if (! $this->isHr()) {
            $q->where('requester_id', Auth::id());
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('request_no', 'like', "%{$term}%")
                ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$term}%")));
        }

        // Status tampilan diturunkan (PROCESS/DONE...), jadi disaring setelah query.
        $all    = $q->latest('id')->get();
        $counts = $all->countBy(fn ($m) => $m->displayStatus());
        $status = $request->query('status');
        $rows   = $status ? $all->filter(fn ($m) => $m->displayStatus() === $status)->values() : $all;

        return view('manpower.index', ['rows' => $rows, 'counts' => $counts, 'total' => $all->count()]);
    }

    private function formData(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'projects'    => Project::orderBy('name')->get(['id', 'name']),
            'employees'   => Employee::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function create()
    {
        return view('manpower.form', ['mp' => null] + $this->formData() + ['taken' => $this->takenReplaced()]);
    }

    public function edit(ManpowerRequest $manpower)
    {
        $this->visible($manpower);
        abort_unless($manpower->canEdit(), 403, 'Pengajuan hanya dapat diubah saat Draft/Rejected.');

        return view('manpower.form', ['mp' => $manpower->load('details')] + $this->formData() + ['taken' => $this->takenReplaced($manpower->id)]);
    }

    /** Karyawan yang sudah dicakup rincian aktif pengajuan lain (tidak boleh digantikan dua kali). */
    private function takenReplaced(?int $exceptMpId = null): array
    {
        return ManpowerRequestDetail::whereNotNull('replaced_employee_id')
            ->where('status', '!=', 'CANCELED')
            ->whereHas('request', fn ($q) => $q->whereNotIn('status', ['CANCEL'])->when($exceptMpId, fn ($w) => $w->where('id', '!=', $exceptMpId)))
            ->pluck('replaced_employee_id')->all();
    }

    private function validated(Request $request, bool $submit, ?int $exceptMpId): array
    {
        $data = $request->validate([
            'department_id'         => 'required|exists:departments,id',
            'project_id'            => 'nullable|exists:projects,id',
            'needed_date'           => ($submit ? 'required' : 'nullable') . '|date',
            'note'                  => 'nullable|string|max:3000',
            'rows'                  => 'required|array|min:1|max:50',
            'rows.*.position'       => 'required|string|max:100',
            'rows.*.grade'          => 'required|in:' . implode(',', ManpowerRequest::GRADES),
            'rows.*.type'           => 'required|in:NEW,REPLACE',
            'rows.*.replaced_id'    => 'nullable|exists:employees,id',
        ], [
            'rows.required' => 'Tambahkan minimal satu kebutuhan pegawai.',
            'rows.min'      => 'Tambahkan minimal satu kebutuhan pegawai.',
        ]);

        $taken = $this->takenReplaced($exceptMpId);
        $seen  = [];
        foreach ($data['rows'] as $i => &$r) {
            if ($r['type'] !== 'REPLACE') {
                $r['replaced_id'] = null;
                continue;
            }
            if ($submit && empty($r['replaced_id'])) {
                throw ValidationException::withMessages(["rows.$i.replaced_id" => 'Ada baris REPLACE yang belum memilih karyawan yang digantikan.']);
            }
            $emp = $r['replaced_id'] ?? null;
            if ($emp && (in_array((int) $emp, $taken, true) || isset($seen[$emp]))) {
                throw ValidationException::withMessages(["rows.$i.replaced_id" => 'Karyawan yang digantikan sudah tercakup pengajuan lain.']);
            }
            if ($emp) {
                $seen[$emp] = true;
            }
        }
        unset($r);

        return $data;
    }

    public function store(Request $request)
    {
        $submit = $request->input('mode') === 'submit';
        $data   = $this->validated($request, $submit, null);

        $mp = DB::transaction(function () use ($data, $submit) {
            $n  = DocumentSequence::next('manpower-request', fn () => 0);
            $mp = ManpowerRequest::create([
                'request_no'    => 'MP' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'requester_id'  => Auth::id(),
                'department_id' => $data['department_id'],
                'project_id'    => $data['project_id'] ?? null,
                'request_date'  => now()->toDateString(),
                'needed_date'   => $data['needed_date'] ?? null,
                'status'        => $submit ? 'SUBMITTED' : 'DRAFT',
                'note'          => $data['note'] ?? null,
            ]);
            $this->syncDetails($mp, $data['rows']);
            $this->event($mp, $submit
                ? 'Pengajuan diajukan — ' . count($data['rows']) . ' kebutuhan pegawai'
                : 'Disimpan sebagai draft — belum diajukan', $data['note'] ?? null);

            return $mp;
        });

        return redirect()->route('manpower.show', $mp)
            ->with('success', "Pengajuan {$mp->request_no} " . ($submit ? 'dikirim.' : 'disimpan sebagai draft.'));
    }

    public function update(Request $request, ManpowerRequest $manpower)
    {
        $this->visible($manpower);
        abort_unless($manpower->canEdit(), 403, 'Pengajuan hanya dapat diubah saat Draft/Rejected.');

        $submit = $request->input('mode') === 'submit';
        $data   = $this->validated($request, $submit, $manpower->id);

        DB::transaction(function () use ($manpower, $data, $submit) {
            $manpower->update([
                'department_id' => $data['department_id'],
                'project_id'    => $data['project_id'] ?? null,
                'needed_date'   => $data['needed_date'] ?? null,
                'note'          => $data['note'] ?? null,
                'status'        => $submit ? 'SUBMITTED' : $manpower->status,
            ]);
            $this->syncDetails($manpower, $data['rows']);
            $this->event($manpower, $submit ? 'Pengajuan diajukan ulang — ' . count($data['rows']) . ' kebutuhan pegawai' : 'Draft diperbarui');
        });

        return redirect()->route('manpower.show', $manpower)->with('success', $submit ? 'Pengajuan dikirim.' : 'Draft disimpan.');
    }

    private function syncDetails(ManpowerRequest $mp, array $rows): void
    {
        $mp->details()->delete(); // hanya dipanggil saat DRAFT/REJECTED: belum ada proses rekrutmen
        foreach ($rows as $r) {
            $mp->details()->create([
                'position' => $r['position'], 'grade' => $r['grade'], 'type' => $r['type'],
                'replaced_employee_id' => $r['replaced_id'] ?? null, 'status' => 'OPEN',
            ]);
        }
    }

    public function show(ManpowerRequest $manpower)
    {
        $this->visible($manpower);
        $manpower->load(['requester', 'approver', 'department', 'project', 'details.replacedEmployee', 'details.filledEmployee', 'logs.user']);

        return view('manpower.show', ['mp' => $manpower, 'isHr' => $this->isHr()]);
    }

    public function action(Request $request, ManpowerRequest $manpower, string $action)
    {
        $this->visible($manpower);
        abort_unless(in_array($action, $manpower->allowedActions(), true), 403, 'Aksi tidak tersedia pada status ini.');

        if (in_array($action, ['approve', 'reject'], true)) {
            abort_unless($this->isHr(), 403, 'Hanya HR yang dapat menyetujui/menolak pengajuan.');
            if ($manpower->isSelfApprovalBlockedFor(Auth::user())) {
                return back()->with('error', 'Anda tidak dapat menyetujui atau menolak pengajuan Anda sendiri.');
            }
        } else {
            // Ajukan/batalkan: pemilik pengajuan atau HR.
            abort_unless($this->isHr() || (int) $manpower->requester_id === (int) Auth::id(), 403);
        }

        $note = trim((string) $request->input('note'));
        if (in_array($action, ['reject', 'cancel'], true) && $note === '') {
            throw ValidationException::withMessages(['note' => 'Alasan wajib diisi.']);
        }
        if ($action === 'submit') {
            if ($manpower->details()->doesntExist()) {
                throw ValidationException::withMessages(['note' => 'Tambahkan minimal satu kebutuhan pegawai.']);
            }
            if (! $manpower->needed_date) {
                throw ValidationException::withMessages(['note' => 'Tanggal dibutuhkan wajib diisi (edit pengajuan dulu).']);
            }
            if ($manpower->details()->where('type', 'REPLACE')->whereNull('replaced_employee_id')->exists()) {
                throw ValidationException::withMessages(['note' => 'Ada baris REPLACE yang belum memilih karyawan yang digantikan.']);
            }
        }

        $map = ['submit' => 'SUBMITTED', 'approve' => 'APPROVED', 'reject' => 'REJECTED', 'cancel' => 'CANCEL'];

        DB::transaction(function () use ($manpower, $action, $map, $note) {
            // Kunci baris + cek ulang: dua aksi bersamaan tidak boleh lolos dua kali.
            $row = ManpowerRequest::lockForUpdate()->findOrFail($manpower->id);
            abort_unless(in_array($action, $row->allowedActions(), true), 409, 'Status pengajuan sudah berubah.');

            $count = $row->details()->count();
            $attrs = ['status' => $map[$action], 'approval_note' => $note ?: null];
            if ($action === 'approve') {
                $attrs += ['approved_by' => Auth::id(), 'approved_at' => now()->toDateString()];
            }
            $row->update($attrs);

            if ($action === 'cancel') {
                $row->details()->where('status', '!=', 'DONE')->update(['status' => 'CANCELED']);
            }

            $this->event($row, [
                'submit'  => "Pengajuan diajukan — {$count} kebutuhan pegawai",
                'approve' => "Pengajuan disetujui — {$count} headcount",
                'reject'  => 'Pengajuan ditolak — kembali ke tahap draft',
                'cancel'  => 'Pengajuan dibatalkan',
            ][$action], $note ?: null);
        });

        return back()->with('success', "Status {$manpower->request_no}: {$map[$action]}.");
    }

    /** Diskusi pemohon <-> HR. */
    public function chat(Request $request, ManpowerRequest $manpower)
    {
        $this->visible($manpower);
        $text = $request->validate(['text' => 'required|string|max:2000'])['text'];

        ManpowerRequestLog::create([
            'manpower_request_id' => $manpower->id, 'user_id' => Auth::id(), 'is_system' => false, 'text' => $text,
        ]);

        return back();
    }

    private function event(ManpowerRequest $mp, string $event, ?string $note = null): void
    {
        ManpowerRequestLog::create([
            'manpower_request_id' => $mp->id, 'user_id' => Auth::id(), 'is_system' => true,
            'event' => $event, 'text' => $note,
        ]);
    }
}
