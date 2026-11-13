<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Letter;
use App\Models\LetterLog;
use App\Models\LetterSignature;
use App\Models\ManpowerRequest;
use App\Models\ManpowerRequestDetail;
use App\Models\Recruitment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Halaman ringkasan app HRIS: Todo List, Dashboard, Summary.
 *
 * Gerbang route: menu:hris-overview. Isi tiap bagian hanya dihitung & dikirim
 * bila user punya menu modulnya (letter-management / manpower-request /
 * recruitment / candidate-pool), supaya tidak ada data modul yang bocor lewat
 * halaman ringkasan. Hanya membaca data; tidak mengubah apa pun.
 */
class HrisController extends Controller
{
    private function can(string $menu): bool
    {
        $u = Auth::user();

        return $u->hasRole('superadmin') || (bool) $u->role?->hasMenu($menu);
    }

    private function isHr(): bool
    {
        return Auth::user()->hasRole(ManpowerRequest::HR_ROLES);
    }

    /** Bagian modul yang boleh dilihat user ini. */
    private function access(): array
    {
        return [
            'letter'      => $this->can('letter-management'),
            'manpower'    => $this->can('manpower-request'),
            'recruitment' => $this->can('recruitment') && $this->isHr(),
            'candidate'   => $this->can('candidate-pool') && $this->isHr(),
        ];
    }

    /* ----------------------------------------------------------- TODO LIST */

    public function todo()
    {
        $me = Auth::user();
        $a  = $this->access();
        $tasks = [];

        if ($a['letter']) {
            $letters = app(LetterController::class);

            $waiting = Letter::with(['category', 'creator'])->where('status', 'submitted')->get()
                ->filter(fn ($l) => $letters->canApprove($l));
            foreach ($waiting as $l) {
                $tasks[] = ['group' => 'Surat menunggu approval Anda', 'title' => $l->letter_no,
                    'sub' => $l->category->name . ' · dari ' . ($l->creator->name ?? '-'), 'since' => $l->updated_at,
                    'url' => route('letter.show', $l), 'badge' => 'warning'];
            }

            $sign = LetterSignature::with('letter.category')->where('signer_id', $me->id)->whereNull('signed_at')
                ->whereHas('letter', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))->get();
            foreach ($sign as $s) {
                $tasks[] = ['group' => 'Surat menunggu tanda tangan Anda', 'title' => $s->letter->letter_no,
                    'sub' => $s->letter->category->name . ' · sebagai ' . $s->sign_as, 'since' => $s->created_at,
                    'url' => route('letter.show', [$s->letter, 'tab' => 'ttd']), 'badge' => 'info'];
            }

            $mine = Letter::with('category')->where('user_id', $me->id)->whereIn('status', ['draft', 'rejected', 'approved', 'sent'])->get();
            foreach ($mine as $l) {
                $tasks[] = ['group' => 'Surat Anda yang perlu ditindaklanjuti', 'title' => $l->letter_no,
                    'sub' => $l->category->name . ' · ' . Letter::STATUSES[$l->status][0], 'since' => $l->updated_at,
                    'url' => route('letter.show', $l), 'badge' => Letter::STATUSES[$l->status][1]];
            }
        }

        if ($a['manpower']) {
            if ($this->isHr()) {
                foreach (ManpowerRequest::with('requester')->where('status', 'SUBMITTED')->get() as $m) {
                    $tasks[] = ['group' => 'Pengajuan manpower menunggu persetujuan', 'title' => $m->request_no,
                        'sub' => 'dari ' . ($m->requester->name ?? '-') . ' · dibutuhkan ' . ($m->needed_date?->format('d M Y') ?? '-'),
                        'since' => $m->updated_at, 'url' => route('manpower.show', $m), 'badge' => 'warning'];
                }
            }
            foreach (ManpowerRequest::where('requester_id', $me->id)->whereIn('status', ['DRAFT', 'REJECTED'])->get() as $m) {
                $tasks[] = ['group' => 'Pengajuan manpower Anda perlu dilengkapi', 'title' => $m->request_no,
                    'sub' => $m->status === 'REJECTED' ? 'Ditolak — perbaiki lalu ajukan ulang' : 'Draft belum diajukan',
                    'since' => $m->updated_at, 'url' => route('manpower.show', $m), 'badge' => $m->status === 'REJECTED' ? 'danger' : 'secondary'];
            }
        }

        if ($a['recruitment']) {
            foreach (Recruitment::with(['candidate', 'detail.request'])->where('status', 'ON_PROCESS')->get() as $r) {
                $tasks[] = ['group' => 'Proses recruitment berjalan', 'title' => $r->candidate->name,
                    'sub' => $r->detail->position . ' (' . $r->detail->request->request_no . ') · tahap ' . $r->stage,
                    'since' => $r->updated_at, 'url' => route('recruitment.index', ['stage' => $r->stage]), 'badge' => 'primary'];
            }
        }

        return view('hris.todo', [
            'groups' => collect($tasks)->sortBy('since')->groupBy('group'),
            'total'  => count($tasks),
            'access' => $a,
        ]);
    }

    /* ----------------------------------------------------------- DASHBOARD */

    public function dashboard()
    {
        $a = $this->access();
        $d = ['access' => $a];

        if ($a['letter']) {
            $d['letterByStatus'] = Letter::select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n', 'status');
        }
        if ($a['manpower']) {
            $mps = ManpowerRequest::with('details')->get();
            $d['mpByStatus'] = $mps->countBy(fn ($m) => $m->displayStatus());
            $active = ManpowerRequestDetail::whereHas('request', fn ($q) => $q->where('status', 'APPROVED'))->where('status', '!=', 'CANCELED');
            $d['headcountTotal'] = (clone $active)->count();
            $d['headcountFilled'] = (clone $active)->where('status', 'DONE')->count();
        }
        if ($a['recruitment']) {
            $d['pipeline'] = Recruitment::where('status', 'ON_PROCESS')->select('stage', DB::raw('count(*) n'))->groupBy('stage')->pluck('n', 'stage');
        }
        if ($a['candidate']) {
            $d['candTotal'] = Candidate::count();
            $d['candHired'] = Candidate::whereNotNull('hired_employee_id')->count();
            $d['candBusy'] = Candidate::whereNull('hired_employee_id')->whereHas('recruitments', fn ($q) => $q->where('status', 'ON_PROCESS'))->count();
        }

        return view('hris.dashboard', $d);
    }

    /* ----------------------------------------------------------- SUMMARY */

    public function summary()
    {
        $a = $this->access();
        $d = ['access' => $a];

        if ($a['letter']) {
            // Surat per bulan (6 bulan terakhir) & per kategori.
            $from = now()->startOfMonth()->subMonths(5);
            $perMonth = Letter::where('created_at', '>=', $from)->get(['created_at'])
                ->countBy(fn ($l) => $l->created_at->format('Y-m'));
            $d['letterMonths'] = collect(range(0, 5))->mapWithKeys(fn ($i) => [
                ($k = $from->copy()->addMonths($i))->format('Y-m') => ['label' => $k->format('M Y'), 'n' => $perMonth[$k->format('Y-m')] ?? 0],
            ]);
            $d['letterByCategory'] = Letter::join('letter_categories', 'letter_categories.id', '=', 'letters.category_id')
                ->select('letter_categories.code', 'letter_categories.name', DB::raw('count(*) n'))
                ->groupBy('letter_categories.code', 'letter_categories.name')->orderByDesc('n')->get();

            // Rata-rata lama approval: log 'submitted' pertama → 'approved' pertama per surat.
            $logs = LetterLog::whereIn('action', ['submitted', 'approved'])->orderBy('created_at')->get(['letter_id', 'action', 'created_at'])->groupBy('letter_id');
            $durs = $logs->map(function ($g) {
                $s = $g->firstWhere('action', 'submitted');
                $ap = $g->firstWhere('action', 'approved');

                return $s && $ap && $ap->created_at >= $s->created_at ? $s->created_at->diffInHours($ap->created_at, true) : null;
            })->filter(fn ($v) => $v !== null);
            $d['letterAvgApprovalHours'] = $durs->isEmpty() ? null : round($durs->avg(), 1);
            $d['letterRejectRate'] = ($t = Letter::count()) ? round(Letter::where('status', 'rejected')->count() / $t * 100, 1) : 0;
        }

        if ($a['manpower']) {
            $details = ManpowerRequestDetail::with('request')->whereHas('request', fn ($q) => $q->where('status', 'APPROVED'))->get();
            $d['mpByPosition'] = $details->where('status', '!=', 'CANCELED')->groupBy('position')->map(fn ($g) => [
                'total' => $g->count(), 'done' => $g->where('status', 'DONE')->count(),
            ])->sortByDesc('total');
            $done = $details->where('status', 'DONE')->filter(fn ($x) => $x->request->approved_at);
            $d['mpAvgDaysToFill'] = $done->isEmpty() ? null : round($done->avg(fn ($x) => $x->request->approved_at->diffInDays($x->updated_at, true)), 1);
            $d['mpNewVsReplace'] = $details->where('status', '!=', 'CANCELED')->countBy('type');
        }

        if ($a['recruitment']) {
            $all = Recruitment::where('status', '!=', 'RETURNED')->get();
            $d['recByStatus'] = $all->countBy('status');
            $d['recDecided'] = $all->whereIn('status', ['APPROVED', 'REJECTED', 'CANCELED'])->count();
            $d['recHireRate'] = $d['recDecided'] ? round($all->where('status', 'APPROVED')->count() / $d['recDecided'] * 100, 1) : null;
        }

        if ($a['candidate']) {
            $d['bySource'] = Candidate::select('source', DB::raw('count(*) n'), DB::raw('sum(hired_employee_id is not null) hired'))
                ->groupBy('source')->orderByDesc('n')->get();
        }

        return view('hris.summary', $d);
    }
}
