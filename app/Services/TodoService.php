<?php

namespace App\Services;

use App\Models\User;
use App\Models\BudgetRequest;
use App\Models\BudgetPlan;
use App\Models\Project;

/**
 * Menghitung daftar "To Do" untuk user saat ini — LIVE query terhadap status
 * record saat ini (bukan tabel tersendiri), jadi otomatis hilang dari daftar
 * begitu record-nya sudah diproses (status berubah). Sumber: Cost Request,
 * Budget Plan, Purchase Order, dan status Project Monitoring.
 */
class TodoService
{
    private const CAP_PER_CATEGORY = 10;

    public static function build(User $user): array
    {
        $role = strtolower($user->role->name ?? '');
        $items = [];

        // Cost Request menunggu approval Head
        if (in_array($role, ['superadmin', 'head'])) {
            $items = array_merge($items, self::crItems(
                BudgetRequest::where('status', 'SUBMITTED'),
                'Menunggu approval Anda',
                'cr-submitted',
                'fa-file-invoice-dollar',
                'warning'
            ));
        }

        // Cost Request menunggu verifikasi Finance
        if (in_array($role, ['superadmin', 'finance', 'finance_manager'])) {
            $items = array_merge($items, self::crItems(
                BudgetRequest::where('status', 'APPROVED'),
                'Menunggu verifikasi Anda',
                'cr-approved',
                'fa-file-invoice-dollar',
                'info'
            ));

            $items = array_merge($items, self::crItems(
                BudgetRequest::where('status', 'REALIZED'),
                'Realisasi menunggu verifikasi Anda',
                'cr-realized',
                'fa-receipt',
                'info'
            ));
        }

        // Cost Request sudah dibayar — milik sendiri, perlu Realisasi
        $crPaidQuery = BudgetRequest::where('status', 'PAID');
        if ($role !== 'superadmin') {
            $crPaidQuery->where('applicant_id', $user->id);
        }
        $items = array_merge($items, self::crItems(
            $crPaidQuery,
            'Sudah dibayar — buat Realisasi',
            'cr-paid',
            'fa-money-check-alt',
            'success'
        ));

        // Budget Plan — 4 tahap sama seperti Cost Request: Manager submit ->
        // Head check -> Finance verify -> CEO approve (Active).
        if (in_array($role, ['superadmin', 'head'])) {
            $items = array_merge($items, self::budgetPlanItems(
                BudgetPlan::where('status', 'SUBMITTED'),
                'Menunggu approval Anda',
                'bp-submitted',
                'warning'
            ));
        }

        if (in_array($role, ['superadmin', 'finance', 'finance_manager'])) {
            $items = array_merge($items, self::budgetPlanItems(
                BudgetPlan::where('status', 'CHECKED'),
                'Menunggu verifikasi Anda',
                'bp-checked',
                'info'
            ));

            $items = array_merge($items, self::budgetPlanItems(
                BudgetPlan::where('status', 'CLOSING'),
                'Closing menunggu verifikasi Anda',
                'bp-closing',
                'info'
            ));
        }

        if (in_array($role, ['superadmin', 'approver'])) {
            $items = array_merge($items, self::budgetPlanItems(
                BudgetPlan::where('status', 'VERIFIED'),
                'Menunggu approval Anda',
                'bp-verified',
                'primary'
            ));
        }

        // Purchase Order menunggu approval / verifikasi Finance
        if (in_array($role, ['superadmin', 'finance', 'finance_manager'])) {
            $items = array_merge($items, self::projectItems(
                Project::where('status', 'SUBMITTED'),
                'Menunggu approval Anda',
                'po-submitted',
                fn ($p) => route('project.index') . '?open=' . $p->id,
                'warning'
            ));

            $items = array_merge($items, self::projectItems(
                Project::where('status', 'CLOSING'),
                'Closing menunggu verifikasi Anda',
                'po-closing',
                fn ($p) => route('project.index') . '?open=' . $p->id,
                'info'
            ));
        }

        // Purchase Order Waiting PO dengan No. PO sudah terisi — siap diaktifkan
        if (in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            $items = array_merge($items, self::projectItems(
                Project::where('status', 'WAITING_PO')->whereHas('poNumbers'),
                'Siap diaktifkan',
                'po-waiting-activate',
                fn ($p) => route('project.activate-form', $p->id),
                'success'
            ));
        }

        // Project Monitoring — milestone selesai tapi dokumen belum di-upload
        if (in_array($role, ['superadmin', 'head'])) {
            $items = array_merge($items, self::monitoringItems(
                Project::where('status', 'WAITING_PO')->where('monitoring_po_received_status', 'DONE'),
                'PO Received selesai — upload No. PO',
                'mon-waiting-po',
                'warning'
            ));

            $items = array_merge($items, self::monitoringItems(
                Project::whereIn('status', array_merge(['WAITING_PO', 'ACTIVE'], Project::PARTIAL_STATUSES))->where('monitoring_bast_status', 'DONE'),
                'BAST selesai — submit BAST',
                'mon-waiting-bast',
                'warning'
            ));
        }

        // Payment selesai tapi PO belum diajukan Closing
        if (in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            $items = array_merge($items, self::monitoringItems(
                Project::whereIn('status', array_merge(['WAITING_PO', 'ACTIVE'], Project::PARTIAL_STATUSES))->where('monitoring_payment_status', 'DONE'),
                'Payment selesai — ajukan Closing',
                'mon-waiting-closed',
                'success'
            ));
        }

        return $items;
    }

    private static function crItems($query, string $message, string $category, string $icon, string $color): array
    {
        return $query->orderByDesc('created_at')->limit(self::CAP_PER_CATEGORY)->get()
            ->map(fn ($cr) => [
                'category' => $category,
                'icon'     => $icon,
                'color'    => $color,
                'title'    => $cr->code . ' — ' . ($cr->description ?: '-'),
                'message'  => $message,
                'url'      => route('budget-request.index') . '?open=' . $cr->id,
            ])->all();
    }

    private static function budgetPlanItems($query, string $message, string $category, string $color): array
    {
        return $query->orderByDesc('created_at')->limit(self::CAP_PER_CATEGORY)->get()
            ->map(fn ($bp) => [
                'category' => $category,
                'icon'     => 'fa-wallet',
                'color'    => $color,
                'title'    => $bp->code . ' — ' . ($bp->description ?: '-'),
                'message'  => $message,
                'url'      => route('budget-plan.index') . '?open=' . $bp->id,
            ])->all();
    }

    private static function projectItems($query, string $message, string $category, callable $urlResolver, string $color): array
    {
        return $query->orderByDesc('created_at')->limit(self::CAP_PER_CATEGORY)->get()
            ->map(fn ($p) => [
                'category' => $category,
                'icon'     => 'fa-file-contract',
                'color'    => $color,
                'title'    => $p->code . ' — ' . $p->name,
                'message'  => $message,
                'url'      => $urlResolver($p),
            ])->all();
    }

    private static function monitoringItems($query, string $message, string $category, string $color): array
    {
        return $query->orderByDesc('id')->limit(self::CAP_PER_CATEGORY)->get()
            ->map(fn ($p) => [
                'category' => $category,
                'icon'     => 'fa-binoculars',
                'color'    => $color,
                'title'    => $p->code . ' — ' . $p->name,
                'message'  => $message,
                'url'      => route('project-monitoring.show', $p->id),
            ])->all();
    }
}
