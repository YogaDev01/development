<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ProjectDetail;
use App\Models\User;
use Carbon\Carbon;

/**
 * Data untuk Dashboard: Perlu Tindakan (To Do), Pengingat (invoice jatuh
 * tempo + milestone mendekati/lewat target), dan Aktivitas Tim (notifikasi).
 * Semua sumber di-reuse dari service/tabel yang sudah ada — tidak ada
 * nominal uang yang ditampilkan di sini.
 */
class DashboardService
{
    /** Role yang punya visibilitas Invoice. */
    private const INVOICE_VISIBLE_ROLES = ['superadmin', 'approver', 'finance', 'finance_manager'];

    /** Role yang melihat seluruh Project tanpa dibatasi Access Group (samakan dengan ProjectMonitoringController). */
    private const PRIVILEGED_PROJECT_ROLES = ['superadmin', 'admin', 'approver', 'finance', 'finance_manager', 'cost_control', 'treasury', 'collection', 'accounting'];

    private const REMINDER_WINDOW_DAYS = 7;

    private const LIST_LIMIT = 20;

    public static function needAction(User $user): array
    {
        return array_slice(TodoService::build($user), 0, self::LIST_LIMIT);
    }

    public static function reminders(User $user): array
    {
        $role = strtolower($user->role->name ?? '');
        $items = [];

        if (in_array($role, self::INVOICE_VISIBLE_ROLES, true)) {
            $items = array_merge($items, self::invoiceReminders());
        }

        $items = array_merge($items, self::milestoneReminders($user, $role));

        usort($items, fn ($a, $b) => $a['sort_date'] <=> $b['sort_date']);

        return array_slice($items, 0, self::LIST_LIMIT);
    }

    public static function activityFeed(User $user): array
    {
        return $user->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($n) => [
                'category'   => $n->data['category'] ?? 'general',
                'message'    => $n->data['message'] ?? ($n->data['title'] ?? '-'),
                'url'        => $n->data['url'] ?? null,
                'created_at' => $n->created_at->diffForHumans(),
            ])->all();
    }

    private static function invoiceReminders(): array
    {
        $today = Carbon::today();

        return Invoice::whereNotIn('status', ['DRAFT', 'PAID', 'CANCELLED'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(self::REMINDER_WINDOW_DAYS))
            ->with('po.customer')
            ->orderBy('due_date')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(function ($invoice) use ($today) {
                $dueDate = Carbon::parse($invoice->due_date);
                $diff = (int) $today->diffInDays($dueDate, false);

                return [
                    'type'      => 'invoice',
                    'icon'      => 'fa-file-invoice',
                    'color'     => self::urgencyColor($diff),
                    'title'     => $invoice->code,
                    'subtitle'  => $invoice->po->customer->name ?? '-',
                    'badge'     => self::urgencyBadge($diff),
                    'url'       => route('invoice-cash-receipt.index'),
                    'sort_date' => $dueDate->timestamp,
                ];
            })->all();
    }

    private static function milestoneReminders(User $user, string $role): array
    {
        $today = Carbon::today();

        $query = ProjectDetail::where('status', '!=', 'DONE')
            ->whereNotNull('plan_end')
            ->whereDate('plan_end', '<=', $today->copy()->addDays(self::REMINDER_WINDOW_DAYS))
            ->with(['project', 'stage']);

        if (!in_array($role, self::PRIVILEGED_PROJECT_ROLES, true)) {
            $projectIds = $user->accessibleProjectIds()->all();
            $query->whereIn('project_id', $projectIds);
        }

        return $query->orderBy('plan_end')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->filter(fn ($detail) => $detail->project)
            ->map(function ($detail) use ($today) {
                $planEnd = Carbon::parse($detail->plan_end);
                $diff = (int) $today->diffInDays($planEnd, false);

                return [
                    'type'      => 'milestone',
                    'icon'      => 'fa-binoculars',
                    'color'     => self::urgencyColor($diff),
                    'title'     => $detail->stage->name ?? 'Milestone',
                    'subtitle'  => $detail->project->code . ' — ' . $detail->project->name,
                    'badge'     => self::urgencyBadge($diff),
                    'url'       => route('project-monitoring.show', $detail->project_id),
                    'sort_date' => $planEnd->timestamp,
                ];
            })->all();
    }

    private static function urgencyColor(int $diffDays): string
    {
        if ($diffDays < 0) {
            return 'danger';
        }
        if ($diffDays <= 2) {
            return 'warning';
        }
        return 'info';
    }

    private static function urgencyBadge(int $diffDays): string
    {
        if ($diffDays < 0) {
            return 'Terlambat ' . abs($diffDays) . ' hari';
        }
        if ($diffDays === 0) {
            return 'Jatuh tempo hari ini';
        }
        return 'H-' . $diffDays;
    }
}
