<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Site;
use App\Models\SiteSla;
use App\Models\TroubleTicket;
use App\Services\OrgHierarchy;
use App\Support\AccessRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman ringkasan app Operations: Todo List, Dashboard, Summary.
 *
 * Gerbang route: menu:operations-overview. Data Trouble Ticket hanya dihitung
 * bila user punya menu 'trouble-ticket', dan SELALU lewat TroubleTicket::visibleTo()
 * (rantai site → PIC → team) — sama dengan daftar tiket, tidak ada aturan
 * visibilitas baru. Hanya membaca. Project Monitoring belum dimasukkan.
 */
class OperationsController extends Controller
{
    private function canTickets(): bool
    {
        $u = Auth::user();

        return $u->hasRole('superadmin') || (bool) $u->role?->hasMenu('trouble-ticket');
    }

    private function visible()
    {
        return TroubleTicket::visibleTo(Auth::user());
    }

    /* ----------------------------------------------------------- TODO LIST */

    public function todo()
    {
        $user = Auth::user();
        $tasks = [];

        if ($this->canTickets()) {
            $row = fn ($t, $group, $badge, $sub = null) => ['group' => $group, 'title' => $t->ticket_number,
                'sub' => trim(($t->site->sitename ?? '-') . ' · ' . ($t->problem->name ?? $t->issue ?? '') . ($sub ? ' · ' . $sub : '')),
                'since' => $t->updated_at, 'url' => route('trouble-ticket.show', $t), 'badge' => $badge];

            // Tiket yang ditugaskan ke user dan belum final.
            foreach (TroubleTicket::open()->where('assigned_to', $user->id)->with(['site:id,sitename', 'problem:id,name'])->get() as $t) {
                $tasks[] = $row($t, 'Tiket yang Anda kerjakan', 'primary', $t->status);
            }

            // Progress Done menunggu persetujuan close: hanya tiket bawahan (cermin canApproveClose()).
            if ($user->hasRole(AccessRoles::TROUBLE_TICKET_SUPERVISE)) {
                $bawahan = app(OrgHierarchy::class)->subordinateIds((int) $user->id)->all();
                foreach ($this->visible()->where('status', 'PROGRESS_DONE')->where(fn ($q) => $user->hasRole('superadmin') ? $q : $q->whereIn('assigned_to', $bawahan))
                    ->with(['site:id,sitename', 'problem:id,name'])->get() as $t) {
                    $tasks[] = $row($t, 'Menunggu persetujuan close Anda', 'warning');
                }
            }

            // Melewati SLA di tiket yang bisa dilihat user.
            foreach ($this->visible()->overdue()->with(['site:id,sitename', 'problem:id,name'])->limit(100)->get() as $t) {
                $tasks[] = $row($t, 'Melewati SLA', 'danger', $t->status);
            }
        }

        return view('operations.todo', [
            'groups' => collect($tasks)->sortBy('since')->groupBy('group'),
            'total' => count($tasks),
            'canTickets' => $this->canTickets(),
        ]);
    }

    /* ----------------------------------------------------------- DASHBOARD */

    public function dashboard()
    {
        $d = ['canTickets' => $this->canTickets()];

        if ($d['canTickets']) {
            $agg = $this->visible()->toBase()
                ->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
            $d['byStatus'] = $agg;
            $d['openTotal'] = TroubleTicket::STATUSES ? collect($agg)->except(TroubleTicket::FINAL_STATUSES)->sum() : 0;
            $d['closedMonth'] = $this->visible()->where('closed_at', '>=', now()->startOfMonth())->count();
            $d['overdue'] = $this->visible()->overdue()->count();
            $d['bySeverity'] = $this->visible()->open()->toBase()
                ->selectRaw('severity, COUNT(*) n')->groupBy('severity')->pluck('n', 'severity');
            $d['financeOpen'] = $this->visible()->financeOpen()->count();
        }

        return view('operations.dashboard', $d);
    }

    /* ----------------------------------------------------------- SUMMARY */

    public function summary(Request $request)
    {
        $d = ['canTickets' => $this->canTickets()];

        // Rekap SLA Site (diinput di menu 'site-sla'): site yang punya nilai di tahun
        // terpilih × 12 bulan. Dilewati bila migrasi site_slas belum dijalankan di host.
        $d['slaReady'] = Schema::hasTable('site_sla_periods');
        $d['slaYear'] = (int) $request->input('sla_year', now()->year);
        $d['slaFilters'] = ['customer_id' => $request->integer('sla_customer') ?: null];
        $d['slaMatrix'] = $d['slaReady'] ? SiteSla::matrix($d['slaYear'], $d['slaFilters']['customer_id']) : [];
        $d['slaSites'] = $d['slaMatrix'] ? Site::query()
            ->with(['customer:id,name,initial', 'provinsi:id,name', 'kota:id,name', 'technician:id,name', 'picLinks.pic'])
            ->whereIn('id', array_keys($d['slaMatrix']))->orderBy('customer_id')->orderBy('tid')->get() : collect();
        $d['slaCustomers'] = Customer::orderBy('name')->get(['id', 'name']);
        $d['canInputSla'] = Auth::user()->hasRole('superadmin') || (bool) Auth::user()->role?->hasMenu('site-sla');

        if ($d['canTickets']) {
            $from = now()->startOfMonth()->subMonths(5);
            $perMonth = $this->visible()->where('trouble_tickets.created_at', '>=', $from)->get(['trouble_tickets.created_at'])
                ->countBy(fn ($t) => $t->created_at->format('Y-m'));
            $d['months'] = collect(range(0, 5))->mapWithKeys(fn ($i) => [
                ($k = $from->copy()->addMonths($i))->format('Y-m') => ['label' => $k->format('M Y'), 'n' => $perMonth[$k->format('Y-m')] ?? 0],
            ]);

            // Tiket closed 90 hari terakhir: rata-rata resolusi & kepatuhan SLA resolusi.
            $closed = $this->visible()->whereNotNull('closed_at')->where('closed_at', '>=', now()->subDays(90))
                ->get(['trouble_tickets.id', 'resolution_seconds', 'sla_resolution_minutes', 'response_seconds', 'sla_response_minutes']);
            $res = $closed->whereNotNull('resolution_seconds');
            $d['closed90'] = $closed->count();
            $d['avgResolutionHours'] = $res->isEmpty() ? null : round($res->avg('resolution_seconds') / 3600, 1);
            $withSla = $res->whereNotNull('sla_resolution_minutes');
            $d['slaResolutionRate'] = $withSla->isEmpty() ? null : round($withSla->filter(fn ($t) => $t->resolution_seconds <= $t->sla_resolution_minutes * 60)->count() / $withSla->count() * 100, 1);
            $resp = $closed->whereNotNull('response_seconds')->whereNotNull('sla_response_minutes');
            $d['slaResponseRate'] = $resp->isEmpty() ? null : round($resp->filter(fn ($t) => $t->response_seconds <= $t->sla_response_minutes * 60)->count() / $resp->count() * 100, 1);

            $d['topProblems'] = $this->visible()->leftJoin('trouble_ticket_problems as p', 'p.id', '=', 'trouble_tickets.problem_id')
                ->toBase()->selectRaw("COALESCE(p.name, 'Tidak diisi') as name, COUNT(*) n")->groupBy('name')->orderByDesc('n')->limit(8)->get();
            $d['topSites'] = $this->visible()->join('sites as s', 's.id', '=', 'trouble_tickets.site_id')
                ->toBase()->selectRaw('s.sitename as name, COUNT(*) n')->groupBy('s.sitename')->orderByDesc('n')->limit(8)->get();
            $d['workload'] = $this->visible()->open()->whereNotNull('assigned_to')->join('users as u', 'u.id', '=', 'trouble_tickets.assigned_to')
                ->toBase()->selectRaw('u.name, COUNT(*) n')->groupBy('u.name')->orderByDesc('n')->limit(10)->get();
        }

        return view('operations.summary', $d);
    }
}
