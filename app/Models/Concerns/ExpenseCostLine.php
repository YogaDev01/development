<?php

namespace App\Models\Concerns;

/**
 * Perilaku bersama rincian pengajuan Expense (CostRegisterDetail) dan rincian
 * realisasi Expense Cash Advance (CostRegisterSettlementDetail): cara pembebanan
 * (allocation) dan label tujuannya. Perhitungan nominal per PO ada di
 * App\Support\ExpenseAllocation (satu sumber).
 *
 * allocation:
 *   po       satu PO (project_id). Wajib untuk akun Variable Cost.
 *   annual   seluruh PO yang dibuat pada allocation_year, proporsional projects.value.
 *   selected PO terpilih (relasi projects()), proporsional projects.value.
 *   cc       Cost Center (cost_center_id) — tidak masuk biaya PO.
 *   NULL     rincian Cost Register lama: ikut PO header (cost_registers.project_id).
 */
trait ExpenseCostLine
{
    public const ALLOCATIONS = ['po', 'annual', 'selected', 'cc'];

    /** Label tujuan untuk tampilan (drawer, ekspor, laporan). Relasi sebaiknya di-eager-load. */
    public function getAllocationLabelAttribute(): string
    {
        return match ($this->allocation) {
            'po'       => 'PO ' . ($this->project->code ?? '-'),
            'annual'   => 'Semua PO tahun ' . $this->allocation_year . ' (proporsional nilai PO)',
            'selected' => $this->projects->count() . ' PO terpilih (proporsional nilai PO): '
                . $this->projects->pluck('code')->implode(', '),
            'cc'       => 'CC ' . ($this->costCenter->code ?? '-') . ' — ' . ($this->costCenter->name ?? '-'),
            // Data lama: rincian tanpa tujuan sendiri, ikut PO header.
            default    => $this->project
                ? 'PO ' . $this->project->code
                : ($this->costCenter ? 'CC ' . $this->costCenter->code : ('PO ' . ($this->costRegister?->project?->code ?? '-'))),
        };
    }

    /**
     * Nilai untuk form (atribut data-target): po:ID, cc:ID, annual:YYYY, atau 'selected'
     * (daftar PO-nya ada di selectedProjectIds()).
     */
    public function formTarget(): string
    {
        return match ($this->allocation) {
            'annual'   => 'annual:' . $this->allocation_year,
            'selected' => 'selected',
            'cc'       => 'cc:' . $this->cost_center_id,
            default    => $this->project_id ? 'po:' . $this->project_id : ($this->cost_center_id ? 'cc:' . $this->cost_center_id : ''),
        };
    }

    public function selectedProjectIds(): array
    {
        return $this->allocation === 'selected'
            ? $this->projects->pluck('id')->map(fn ($v) => (int) $v)->all()
            : [];
    }
}
