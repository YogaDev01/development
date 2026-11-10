<?php

namespace App\Support;

use App\Models\CostRegisterDetail;
use App\Models\CostRegisterSettlementDetail;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu sumber pembagian biaya Expense ke PO (keputusan 2026-11-10).
 *
 * Sumber baris (tidak tumpang tindih):
 *   - rincian pengajuan Expense yang dihitung  (CostRegisterDetail::scopeCounted)
 *   - rincian realisasi Cash Advance disetujui (CostRegisterSettlementDetail::scopeCounted)
 *
 * Cara pembebanan per baris (allocation):
 *   po       seluruh nilai ke satu PO.
 *   selected PO terpilih yang berstatus disetujui (Project::EXPENSE_ALLOCATION_STATUSES)
 *            dan bernilai > 0; dibagi  nilai × (Nilai PO / Total Nilai PO terpilih).
 *   annual   seluruh PO disetujui yang DIBUAT (created_at) pada allocation_year dan
 *            bernilai > 0; dibagi  nilai × (Nilai PO / Total Nilai PO tahun itu).
 *   cc       Cost Center — tidak masuk PO mana pun.
 * Nilai PO = projects.value. Pembagian DIHITUNG ULANG setiap dibaca (tidak disimpan):
 * PO baru / perubahan nilai / status PO langsung menggeser porsi.
 *
 * Pembulatan rupiah memakai metode sisa terbesar, jadi jumlah porsi seluruh PO selalu
 * TEPAT sama dengan nilai baris (tidak ada rupiah hilang/berlebih). Bila tidak ada PO
 * penerima (mis. tidak ada PO disetujui di tahun itu), baris itu tidak masuk PO mana pun
 * form Expense menolak pilihan seperti itu saat disimpan (lihat CostRegisterController).
 */
final class ExpenseAllocation
{
    public const CATEGORIES = ['Variable Cost', 'Fixed Cost', 'Overhead'];

    /**
     * Biaya Expense per PO & kategori untuk daftar PO.
     *
     * @param  array<int>  $projectIds
     * @return array<int, array<string,int>>  [project_id => ['Variable Cost' => .., 'Fixed Cost' => .., 'Overhead' => ..]]
     */
    public static function forProjects(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));
        $hasil = [];
        foreach ($projectIds as $pid) {
            $hasil[$pid] = array_fill_keys(self::CATEGORIES, 0);
        }

        foreach (self::linesFor($projectIds) as $baris) {
            $kategori = $baris['line']->cost_category;
            if (! in_array($kategori, self::CATEGORIES, true)) {
                continue;
            }
            foreach ($baris['shares'] as $pid => $nilai) {
                if (isset($hasil[$pid])) {
                    $hasil[$pid][$kategori] += $nilai;
                }
            }
        }

        return $hasil;
    }

    /**
     * Baris Expense yang membebani satu PO beserta porsinya (untuk laporan PO).
     *
     * @return Collection<int, array{line: \Illuminate\Database\Eloquent\Model, share: int}>
     */
    public static function linesForProject(int $projectId): Collection
    {
        return self::linesFor([$projectId])
            ->filter(fn ($b) => ($b['shares'][$projectId] ?? 0) > 0)
            ->map(fn ($b) => ['line' => $b['line'], 'share' => $b['shares'][$projectId]])
            ->values();
    }

    /**
     * PO penerima pembagian tahunan: PO disetujui, dibuat pada $year, nilai > 0.
     *
     * @return array<int,int>  [project_id => value]
     */
    public static function annualPool(int $year): array
    {
        return self::annualPools([$year])[$year] ?? [];
    }

    /**
     * PO penerima dari daftar terpilih: hanya yang disetujui dan nilai > 0.
     *
     * @param  iterable<Project>  $projects
     * @return array<int,int>
     */
    public static function selectedPool(iterable $projects): array
    {
        $pool = [];
        foreach ($projects as $p) {
            if (in_array($p->status, Project::EXPENSE_ALLOCATION_STATUSES, true) && (int) $p->value > 0) {
                $pool[(int) $p->id] = (int) $p->value;
            }
        }

        return $pool;
    }

    /**
     * Bagi $total ke bobot $weights secara proporsional, sisa terbesar.
     *
     * @param  array<int,int>  $weights  [id => bobot > 0]
     * @return array<int,int>            [id => porsi], jumlah = $total ; [] bila bobot kosong
     */
    public static function distribute(int $total, array $weights): array
    {
        $weights = array_filter($weights, fn ($w) => $w > 0);
        $jumlah = array_sum($weights);
        if ($total <= 0 || $jumlah <= 0) {
            return [];
        }

        $hasil = []; $pecahan = [];
        foreach ($weights as $id => $w) {
            $mentah = $total * ($w / $jumlah);
            $hasil[$id] = (int) floor($mentah);
            $pecahan[$id] = $mentah - $hasil[$id];
        }

        $sisa = $total - array_sum($hasil);
        // Urutan stabil: pecahan terbesar dulu, seri -> id terkecil.
        uksort($pecahan, fn ($a, $b) => ($pecahan[$b] <=> $pecahan[$a]) ?: ($a <=> $b));
        foreach (array_keys($pecahan) as $id) {
            if ($sisa <= 0) {
                break;
            }
            $hasil[$id]++;
            $sisa--;
        }

        return $hasil;
    }

    /**
     * Baris yang (berpotensi) membebani salah satu PO di $projectIds, lengkap dengan
     * porsi ke SEMUA PO penerimanya.
     *
     * @return Collection<int, array{line: \Illuminate\Database\Eloquent\Model, shares: array<int,int>}>
     */
    private static function linesFor(array $projectIds): Collection
    {
        if (! $projectIds) {
            return collect();
        }

        // Tahun pembuatan PO target yang memenuhi syarat -> baris 'annual' yang relevan.
        $tahun = Project::whereIn('id', $projectIds)
            ->whereIn('status', Project::EXPENSE_ALLOCATION_STATUSES)
            ->selectRaw('DISTINCT YEAR(created_at) as th')
            ->pluck('th')->map(fn ($v) => (int) $v)->all();

        $lines = collect();
        foreach ([[CostRegisterDetail::class, 'cost_register_details'], [CostRegisterSettlementDetail::class, 'cost_register_settlement_details']] as [$model, $tabel]) {
            $lines = $lines->concat($model::query()
                ->counted()
                ->where(function ($q) use ($tabel, $projectIds, $tahun) {
                    $q->where(fn ($q2) => $q2->where("$tabel.allocation", 'po')->whereIn("$tabel.project_id", $projectIds))
                      ->orWhere(fn ($q2) => $q2->where("$tabel.allocation", 'selected')
                          ->whereHas('projects', fn ($q3) => $q3->whereIn('projects.id', $projectIds)))
                      ->orWhere(fn ($q2) => $q2->where("$tabel.allocation", 'annual')->whereIn("$tabel.allocation_year", $tahun ?: [0]));
                })
                ->with(['costRegister', 'account', 'project', 'projects:projects.id,projects.code,projects.value,projects.status'])
                ->get());
        }

        $pools = self::annualPools($lines->where('allocation', 'annual')->pluck('allocation_year')->unique()->all());

        return $lines->map(function ($line) use ($pools) {
            $total = (int) $line->total;
            $shares = match ($line->allocation) {
                'po'       => $line->project_id ? [(int) $line->project_id => $total] : [],
                'selected' => self::distribute($total, self::selectedPool($line->projects)),
                'annual'   => self::distribute($total, $pools[(int) $line->allocation_year] ?? []),
                default    => [],
            };

            return ['line' => $line, 'shares' => $shares];
        })->values();
    }

    /** @return array<int, array<int,int>>  [tahun => [project_id => value]] */
    private static function annualPools(array $years): array
    {
        $years = array_values(array_filter(array_map('intval', $years)));
        if (! $years) {
            return [];
        }

        $pools = array_fill_keys($years, []);
        Project::whereIn('status', Project::EXPENSE_ALLOCATION_STATUSES)
            ->where('value', '>', 0)
            ->whereIn(DB::raw('YEAR(created_at)'), $years)
            ->get(['id', 'value', 'created_at'])
            ->each(function ($p) use (&$pools) {
                $pools[(int) $p->created_at->format('Y')][(int) $p->id] = (int) $p->value;
            });

        return $pools;
    }
}
