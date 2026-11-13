<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;

/**
 * Halaman ringkasan app Projects: Todo List, Dashboard, Summary.
 *
 * Gerbang route: menu:projects-overview. Proyek yang dihitung HANYA yang boleh
 * diakses user — memakai ProjectMonitoringController::accessibleProjectIds()
 * yang sama dengan halaman Project Monitoring (tanpa aturan akses baru).
 * Hanya membaca. Angka uang hanya menjumlahkan kolom projects.value (nilai
 * PO tersimpan), tidak menghitung ulang pembayaran/tagihan.
 */
class ProjectsController extends Controller
{
    private function projects()
    {
        $ids = app(ProjectMonitoringController::class)->accessibleProjectIds();

        return Project::with(['customer:id,name', 'manager:id,name', 'details'])
            ->when(is_array($ids), fn ($q) => $q->whereIn('id', $ids));
    }

    /** Progres tertimbang milestone (0–100): bobot milestone DONE / total bobot. */
    private function progress(Project $p): int
    {
        $total = $p->details->sum('weight');
        if ($total <= 0) {
            return $p->details->isEmpty() ? 0 : (int) round($p->details->where('status', 'DONE')->count() / $p->details->count() * 100);
        }

        return (int) round($p->details->where('status', 'DONE')->sum('weight') / $total * 100);
    }

    private function overdueDetails(Project $p)
    {
        return $p->details->filter(fn ($d) => $d->status !== 'DONE' && $d->plan_end && $d->plan_end->isPast() && ! $d->plan_end->isToday());
    }

    /* ----------------------------------------------------------- TODO LIST */

    public function todo()
    {
        $me = Auth::id();
        $tasks = [];

        foreach ($this->projects()->where('manager_id', $me)->whereNotIn('status', ['CLOSED', 'PAID'])->get() as $p) {
            $url = route('project-monitoring.show', $p->id);

            foreach ($this->overdueDetails($p) as $d) {
                $tasks[] = ['group' => 'Milestone terlambat', 'title' => $p->name,
                    'sub' => ($d->cluster->name ?? 'Milestone') . ' · target ' . $d->plan_end->format('d M Y'),
                    'since' => $d->plan_end, 'url' => $url, 'badge' => 'danger'];
            }
            $ongoing = $p->details->where('status', 'ON_GOING')->count();
            if ($ongoing) {
                $tasks[] = ['group' => 'Proyek Anda sedang berjalan', 'title' => $p->name,
                    'sub' => $ongoing . ' milestone on going · progres ' . $this->progress($p) . '%',
                    'since' => $p->updated_at, 'url' => $url, 'badge' => 'primary'];
            }
            if (in_array($p->status, ['EARLY_START', 'WAITING_PO'], true)) {
                $tasks[] = ['group' => 'Proyek menunggu PO', 'title' => $p->name,
                    'sub' => ($p->customer->name ?? '-') . ' · ' . str_replace('_', ' ', $p->status),
                    'since' => $p->updated_at, 'url' => $url, 'badge' => 'warning'];
            }
        }

        return view('projects.todo', ['groups' => collect($tasks)->sortBy('since')->groupBy('group'), 'total' => count($tasks)]);
    }

    /* ----------------------------------------------------------- DASHBOARD */

    public function dashboard()
    {
        $all = $this->projects()->get();
        $active = $all->whereIn('status', Project::OPERATIONAL_STATUSES);

        return view('projects.dashboard', [
            'total'        => $all->count(),
            'byStatus'     => $all->countBy('status'),
            'activeCount'  => $active->count(),
            'activeValue'  => (int) $active->sum('value'),
            'overdue'      => $all->whereNotIn('status', ['CLOSED', 'PAID'])->sum(fn ($p) => $this->overdueDetails($p)->count()),
            'noManager'    => $all->whereNotIn('status', ['CLOSED', 'PAID', 'DRAFT'])->whereNull('manager_id')->count(),
            'avgProgress'  => $active->isEmpty() ? null : (int) round($active->avg(fn ($p) => $this->progress($p))),
        ]);
    }

    /* ----------------------------------------------------------- SUMMARY */

    public function summary()
    {
        $all = $this->projects()->get();
        $live = $all->whereNotIn('status', ['DRAFT', 'CLOSED', 'PAID']);

        $from = now()->startOfMonth()->subMonths(5);
        $perMonth = $all->where('created_at', '>=', $from)->countBy(fn ($p) => $p->created_at->format('Y-m'));

        $overdueRows = $live->flatMap(fn ($p) => $this->overdueDetails($p)->map(fn ($d) => [
            'project' => $p->name, 'id' => $p->id, 'milestone' => $d->cluster->name ?? 'Milestone',
            'due' => $d->plan_end, 'days' => $d->plan_end->diffInDays(now(), true),
        ]))->sortByDesc('days')->take(10)->values();

        return view('projects.summary', [
            'months' => collect(range(0, 5))->mapWithKeys(fn ($i) => [
                ($k = $from->copy()->addMonths($i))->format('Y-m') => ['label' => $k->format('M Y'), 'n' => $perMonth[$k->format('Y-m')] ?? 0],
            ]),
            'byCustomer' => $all->groupBy(fn ($p) => $p->customer->name ?? '(tanpa customer)')->map(fn ($g) => [
                'n' => $g->count(), 'value' => (int) $g->sum('value'),
            ])->sortByDesc('value')->take(8),
            'byType' => $all->groupBy(fn ($p) => $p->type ?: '(tidak diisi)')->map->count()->sortDesc(),
            'progressRows' => $live->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'manager' => $p->manager->name ?? '-', 'progress' => $this->progress($p),
                'status' => $p->status, 'overdue' => $this->overdueDetails($p)->count(),
            ])->sortBy('progress')->take(15)->values(),
            'overdueRows' => $overdueRows,
        ]);
    }
}
