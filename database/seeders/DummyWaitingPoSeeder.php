<?php

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\ProjectPoNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dua PO dummy dengan lebih dari satu No. PO: 2 No. PO untuk yang pertama, 3
 * untuk yang kedua. Dipakai untuk menguji tampilan kode multi No. PO.
 *
 * Status ACTIVE, bukan WAITING_PO: begitu semua No. PO terbit, PO-nya sudah
 * lewat tahap menunggu. Contract sengaja dibuat persis sebesar SUM(nominal)
 * No. PO supaya kolom Waiting PO di Summary Progress nol - kolom itu berisi
 * sisa kontrak yang No. PO-nya belum terbit, bukan status PO-nya.
 *
 * Berbeda dengan DummyChainSeeder yang menulis lewat query builder, di sini
 * sengaja lewat Eloquent supaya hook ProjectPoNumber::saved() ikut jalan dan
 * projects.value tersinkron dari SUM(nominal) seperti di aplikasi.
 */
class DummyWaitingPoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Cluster dipakai bersama semua milestone dummy - tidak ada cluster
        // khusus per PO, sama seperti data dummy yang sudah ada.
        $clusterId = (int) (Cluster::where('status', 'ACTIVE')->value('id') ?? 0);

        $rows = [
            [
                'name'        => 'Pengadaan Perangkat Transmisi Microwave Ring Lombok',
                'customer_id' => 12,   // PT PARAMITRA INTIMEGA
                'overhead'    => 9000000,
                'fixed_cost'  => 6000000,
                'financing'   => 'NL',
                'pos'         => [140000000, 185000000],
            ],
            [
                'name'        => 'Pembangunan Jaringan FO Backbone Ruas Kediri - Blitar',
                'customer_id' => 14,   // PT DWIPANTARA MEDIA TELEMATIKA
                'overhead'    => 14000000,
                'fixed_cost'  => 8500000,
                'financing'   => 'IL',
                'pos'         => [180000000, 210000000, 165000000],
            ],
        ];

        foreach ($rows as $row) {
            $project = Project::create([
                'code'            => Project::generateNextCode(),
                'name'            => $row['name'],
                'customer_id'     => $row['customer_id'],
                'manager_id'      => 5,
                'input_by'        => 1,
                'status'          => 'ACTIVE',
                'financing'       => $row['financing'],
                'contract'        => array_sum($row['pos']),
                'overhead'        => $row['overhead'],
                'fixed_cost'      => $row['fixed_cost'],
                'po_quantity'     => count($row['pos']),
                'term_of_payment' => '30 hari setelah invoice diterima',
                'notes'           => '[DUMMY] Data dummy untuk development.',
                'submitted_at'    => $now->copy()->subDays(12),
                'approved_at'     => $now->copy()->subDays(10),
                'approved_by'     => 1,

                // Semua No. PO sudah terbit & diterima; BAST dst. belum jalan.
                'monitoring_po_received_status' => 'DONE',
                'monitoring_bast_status'        => 'NOT_YET',
                'monitoring_invoice_status'     => 'NOT_YET',
                'monitoring_payment_status'     => 'NOT_YET',
            ]);

            foreach ($row['pos'] as $i => $nominal) {
                ProjectPoNumber::create([
                    'project_id'   => $project->id,
                    'po_number'    => 'PO/' . $project->code . '/' . str_pad($i + 1, 2, '0', STR_PAD_LEFT) . '/2026',
                    'nominal'      => $nominal,
                    'release_date' => $now->copy()->subDays(8 - $i),
                    'status'       => 'RELEASED',
                    'input_by'     => 1,
                ]);
            }

            /*
            | Milestone Project Monitoring. Kolom Progress % di Daftar Purchase
            | Order = SUM(weight milestone DONE) / SUM(weight) - bukan turunan
            | status PO. Tanpa baris ini PO-nya tampil 0% walau No. PO-nya sudah
            | terbit, jadi milestone-nya ikut dibuat: Survey & PO Received sudah
            | DONE (bobot 20), implementasi masih jalan.
            */
            $milestones = [
                [1,  10, 'DONE',        -30, -23],  // Survey
                [3,  10, 'DONE',        -23, -16],  // PO Received
                [2,  50, 'ON_GOING',    -16,  14],  // Implementasi
                [4,  10, 'NOT_STARTED',  14,  21],  // BAST Pekerjaan
                [6,  10, 'NOT_STARTED',  21,  28],  // Invoice
                [7,  10, 'NOT_STARTED',  28,  35],  // Payment
            ];

            foreach ($milestones as [$stageId, $weight, $status, $startOffset, $endOffset]) {
                $done = $status === 'DONE';

                ProjectDetail::create([
                    'project_id'        => $project->id,
                    'cluster_id'        => $clusterId,
                    'stage_id'          => $stageId,
                    'weight'            => $weight,
                    'status'            => $status,
                    'plan_start'        => $now->copy()->addDays($startOffset),
                    'plan_end'          => $now->copy()->addDays($endOffset),
                    'actual_start'      => $status === 'NOT_STARTED' ? null : $now->copy()->addDays($startOffset),
                    'actual_end'        => $done ? $now->copy()->addDays($endOffset) : null,
                    'submitted_done_by' => $done ? 1 : null,
                    'submitted_done_at' => $done ? $now->copy()->addDays($endOffset) : null,
                ]);
            }

            $this->command?->info("PO {$project->code} dibuat dengan " . count($row['pos']) . ' No. PO.');
        }
    }
}
