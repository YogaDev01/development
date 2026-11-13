<?php

namespace Database\Seeders;

use App\Models\TroubleTicket;
use App\Models\TroubleTicketMessage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| DUMMY 50 TIKET UNTUK ANALISA MODUL TROUBLE TICKET
|--------------------------------------------------------------------------
| 50 tiket menyebar di semua tahap (OPEN, REMOTE, PLAN, TRIP, IN_PROGRESS,
| PROGRESS_DONE, CLOSED) dengan kondisi berbeda: dalam/lewat SLA, aging
| 0..20 hari (severity Rendah..Kritis), perjalanan dibatalkan, close ditolak
| lalu diajukan ulang, ditutup langsung dari Plan, Remote berbalik jadi
| kunjungan, tiket tanpa SLA policy, dll.
|
| Kolom & event timeline meniru TroubleTicketWorkflow; timestamp ditulis
| langsung supaya riwayat bisa mundur beberapa hari. TIDAK membuat
| pengajuan dana / Cost Request (urusan keuangan tidak disentuh).
|
| Prasyarat: site ACTIVE yang punya PIC teknisi internal ACTIVE dengan akun
| user (mis. hasil DummySiteTroubleTicketSeeder).
| Semua baris ditandai external_ref 'DUMMY-ANL-xxx' dan deskripsi [DUMMY].
| Idempoten: external_ref yang sudah ada dilewati.
|
| Jalankan (HANYA di DB lokal yang targetnya sudah dipastikan):
|   php artisan db:seed --class=DummyAnalisaTroubleTicketSeeder
*/
class DummyAnalisaTroubleTicketSeeder extends Seeder
{
    /**
     * Skenario: [kode, catatan]. Kode dipakai di buildTicket().
     */
    private const SKENARIO = [
        ['open_baru', 'OPEN baru masuk, SLA Response masih aman'],
        ['open_telat', 'OPEN belum direspons, SLA Response terlewati'],
        ['open_tanpa_sla', 'OPEN tanpa SLA policy (target belum diatur)'],
        ['remote_baru', 'REMOTE baru direspons'],
        ['remote_lama', 'REMOTE berhari-hari belum selesai'],
        ['remote_close_ditolak', 'REMOTE setelah pengajuan close ditolak'],
        ['plan_besok', 'PLAN kunjungan dijadwalkan ke depan'],
        ['plan_lewat_jadwal', 'PLAN tanggal kunjungan sudah lewat'],
        ['plan_reschedule', 'PLAN setelah perjalanan dibatalkan (reschedule)'],
        ['trip_jalan', 'TRIP teknisi sedang di perjalanan, SLA Travel aman'],
        ['trip_telat', 'TRIP perjalanan melewati SLA Travel'],
        ['trip_tiba', 'TRIP sudah tiba di site, progres belum dimulai'],
        ['progress', 'IN_PROGRESS pengerjaan di site, SLA Resolution aman'],
        ['progress_telat', 'IN_PROGRESS lewat SLA Resolution'],
        ['progress_close_ditolak', 'IN_PROGRESS setelah close ditolak supervisor'],
        ['done_remote', 'PROGRESS_DONE jalur remote, menunggu approval'],
        ['done_visit', 'PROGRESS_DONE jalur kunjungan, menunggu approval'],
        ['closed_remote', 'CLOSED via remote'],
        ['closed_visit', 'CLOSED via kunjungan, dalam SLA'],
        ['closed_visit_telat', 'CLOSED via kunjungan, lewat SLA Resolution'],
        ['closed_dari_plan', 'CLOSED langsung dari PLAN (beres sebelum kunjungan)'],
        ['closed_resubmit', 'CLOSED setelah sekali close ditolak'],
    ];

    /** [problem, issue, device, flag alarm] */
    private const KASUS = [
        ['Link Down', 'Site down total', 'Modem', 'LOS'],
        ['Degradasi Performa', 'Trafik turun di jam sibuk', 'Router', null],
        ['Power Problem', 'Baterai drop, genset mati', 'Battery', 'LOW VOLTAGE'],
        ['Konfigurasi', 'VLAN pelanggan tidak jalan', 'Switch', null],
        ['Perangkat Rusak', 'ODU tidak merespons', 'ODU', 'LOS'],
        ['Link Down', 'Putus setelah pemadaman listrik PLN', 'IFL', 'LOS'],
        ['Cuaca / Force Majeure', 'Sinyal hilang saat hujan deras', 'Antena', 'RAIN FADE'],
        ['Degradasi Performa', 'Latensi tinggi dilaporkan pelanggan', 'Router', null],
        ['Power Problem', 'Solar charge controller alarm', 'SCC', 'ALARM POWER'],
        ['Perangkat Rusak', 'Adaptor modem terbakar', 'Adaptor', 'LOS'],
        ['Link Down', 'Kabel IFL digigit tikus', 'IFL', 'LOS'],
    ];

    private const REPORTER = ['NOC Monitoring', 'Helpdesk Customer', 'Penjaga Site', 'Monitoring Tools'];

    public function run(): void
    {
        $actor = DB::table('users as u')->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->orderBy('u.id')->first(['u.id', 'u.name', 'r.name as role']);
        if (! $actor) {
            $this->command?->warn('Tidak ada user; seeder dilewati.');

            return;
        }

        $kandidat = DB::table('sites as s')
            ->join('site_pics as sp', 'sp.site_id', '=', 's.id')
            ->join('master_pics as mp', 'mp.id', '=', 'sp.pic_id')
            ->join('users as u', 'u.id', '=', 'mp.user_id')
            ->where('s.status', 'ACTIVE')
            ->where('mp.type', 'internal')
            ->where('mp.status', 'ACTIVE')
            ->where('u.status', 'ACTIVE')
            ->select('s.id as site_id', 's.tid', 's.sitename', 's.customer_id',
                'mp.id as pic_id', 'u.id as user_id', 'u.name as user_name')
            ->orderBy('s.id')
            ->get()
            ->unique('site_id')
            ->values();

        if ($kandidat->isEmpty()) {
            $this->command?->warn('Belum ada site dengan PIC teknisi aktif; jalankan DummySiteTroubleTicketSeeder dulu.');

            return;
        }

        $problems = DB::table('trouble_ticket_problems')->pluck('id', 'name');
        $rcas = DB::table('trouble_ticket_rcas as r')
            ->leftJoin('trouble_ticket_sub_rcas as s', 's.rca_id', '=', 'r.id')
            ->where('r.status', 'ACTIVE')
            ->select('r.id as rca_id', 'r.name as rca', 's.id as sub_id', 's.name as sub')
            ->orderBy('r.id')->get();
        $dibuat = 0;

        for ($i = 0; $i < 50; $i++) {
            $ref = 'DUMMY-ANL-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            if (DB::table('trouble_tickets')->where('external_ref', $ref)->exists()) {
                continue;
            }

            [$kode, $catatan] = self::SKENARIO[$i % count(self::SKENARIO)];
            [$problem, $issue, $device, $alarm] = self::KASUS[$i % count(self::KASUS)];
            $site = $kandidat[$i % $kandidat->count()];
            $problemId = $problems[$problem] ?? null;

            $policy = $kode === 'open_tanpa_sla' ? null : DB::table('trouble_ticket_sla_policies')
                ->where('status', 'ACTIVE')
                ->where(fn ($q) => $q->where('problem_id', $problemId)->orWhereNull('problem_id'))
                ->orderByRaw('problem_id IS NULL')
                ->first();

            $rca = $rcas->isEmpty() ? null : $rcas[$i % $rcas->count()];

            [$row, $events, $closure] = $this->buildTicket($kode, $i, $site, $actor, $policy, $rca);

            $row += [
                'ticket_number' => TroubleTicket::generateNumber($row['created_at']),
                'external_ref' => $ref,
                'site_id' => $site->site_id,
                'customer_id' => $site->customer_id,
                'problem_id' => $problemId,
                'flag_alarm' => $alarm,
                'issue' => $issue,
                'device' => $device,
                'description' => "[DUMMY] {$catatan}. {$issue} di {$site->tid} — {$site->sitename}.",
                'reporter_name' => self::REPORTER[$i % count(self::REPORTER)],
                'reported_at' => $row['created_at'],
                'created_by' => $actor->id,
                'assigned_to' => $site->user_id,
                'pic_id' => $site->pic_id,
                'assigned_at' => $row['created_at'],
                'sla_policy_id' => $policy?->id,
                'sla_response_minutes' => $policy?->response_minutes,
                'sla_travel_minutes' => $policy?->travel_minutes,
                'sla_resolution_minutes' => $policy?->resolution_minutes,
            ];

            DB::transaction(function () use ($row, $events, $closure, $actor) {
                $ticketId = DB::table('trouble_tickets')->insertGetId($row);

                DB::table('trouble_ticket_assignments')->insert([
                    'trouble_ticket_id' => $ticketId,
                    'assigned_to' => $row['assigned_to'],
                    'assigned_by' => $actor->id,
                    'assigned_at' => $row['assigned_at'],
                    'responded_at' => $row['acknowledged_at'] ?? null,
                    'created_at' => $row['assigned_at'],
                ]);

                foreach ($events as [$event, $userId, $nama, $role, $body, $meta, $at]) {
                    DB::table('trouble_ticket_messages')->insert([
                        'trouble_ticket_id' => $ticketId,
                        'user_id' => $userId,
                        'actor_name' => $nama,
                        'actor_role' => $role,
                        'type' => TroubleTicketMessage::TYPE_EVENT,
                        'event' => $event,
                        'body' => $body,
                        'metadata' => $meta ? json_encode($meta) : null,
                        'created_at' => $at,
                    ]);
                }

                foreach ($closure as $n => $c) {
                    DB::table('trouble_ticket_closure_reviews')->insert($c + [
                        'trouble_ticket_id' => $ticketId,
                        'attempt_no' => $n + 1,
                        'created_at' => $c['requested_at'],
                        'updated_at' => $c['reviewed_at'] ?? $c['requested_at'],
                    ]);
                }
            });

            $dibuat++;
        }

        $this->command?->info("Tiket dummy analisa: {$dibuat} dibuat.");
    }

    /**
     * Susun kolom tiket + event timeline + riwayat closure review untuk satu
     * skenario. Waktu dihitung mundur dari sekarang.
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function buildTicket(string $kode, int $i, object $site, object $actor, ?object $policy, ?object $rca): array
    {
        $tech = [$site->user_id, $site->user_name, 'teknisi'];
        $sup = [$actor->id, $actor->name, $actor->role ?? 'superadmin'];
        $respMin = $policy?->response_minutes ?? 30;
        $travMin = $policy?->travel_minutes ?? 120;
        $resoMin = $policy?->resolution_minutes ?? 240;

        // Umur tiket (hari) bervariasi supaya severity aging Rendah..Kritis muncul.
        // Minimal 2 hari untuk skenario berjalan panjang supaya tidak ada
        // timestamp di masa depan (jadwal kunjungan = H+1 dari plan).
        $umur = max(2, [0, 1, 2, 4, 6, 9, 13, 20][$i % 8]);
        $t0 = match ($kode) {
            'open_baru', 'trip_jalan', 'remote_baru' => now()->subMinutes(max(1, intdiv($respMin, 3))),
            'open_telat' => now()->subMinutes($respMin * 3 + 15),
            default => now()->subDays($umur)->subHours(2 + $i % 5),
        };

        $row = ['status' => 'OPEN', 'created_at' => $t0, 'updated_at' => $t0];
        $ev = [
            ['created', $actor->id, $actor->name, $sup[2], null, ['site' => $site->tid], $t0],
            ['assigned', $actor->id, $actor->name, $sup[2], null, ['to_id' => $tech[0], 'to_name' => $tech[1]], $t0],
        ];
        $closure = [];

        if (in_array($kode, ['open_baru', 'open_telat', 'open_tanpa_sla'], true)) {
            return [$row, $ev, $closure];
        }

        // Respons teknisi -> REMOTE (jalur standar workflow).
        $ack = $t0->copy()->addMinutes(max(1, intdiv($respMin, 2)) + $i % 7);
        $row = array_merge($row, [
            'acknowledged_at' => $ack, 'acknowledged_by' => $tech[0],
            'response_seconds' => $ack->getTimestamp() - $t0->getTimestamp(),
            'status' => 'REMOTE', 'visit_decision' => TroubleTicket::NON_VISIT,
            'visit_decided_by' => $tech[0], 'visit_decided_at' => $ack,
            'progress_started_at' => $ack, 'approval' => null,
        ]);
        $ev[] = ['responded_remote', $tech[0], $tech[1], $tech[2], null, null, $ack];
        $row['updated_at'] = $ack;

        $tutup = function (Carbon $at, bool $approve, ?Carbon $reviewAt = null) use (&$row, &$ev, &$closure, $tech, $sup, $rca) {
            $ringkas = 'Perangkat normal kembali, link up, trafik stabil. [DUMMY]';
            $row = array_merge($row, [
                'status' => 'PROGRESS_DONE', 'approval' => 'PENDING',
                'rca_id' => $rca?->rca_id, 'sub_rca_id' => $rca?->sub_id,
                'rca' => $rca?->rca ?? 'HARDWARE', 'sub_rca' => $rca?->sub,
                'resolution_summary' => $ringkas,
                'resolved_at' => $at, 'progress_done_at' => $at, 'closed_by' => $tech[0],
                'updated_at' => $at,
            ]);
            $row['progress_started_at'] ??= $at;
            $ev[] = ['progress_done', $tech[0], $tech[1], $tech[2], $ringkas, ['rca' => $row['rca'], 'sub_rca' => $row['sub_rca']], $at];
            $c = ['requested_by' => $tech[0], 'requested_at' => $at, 'resolution_summary' => $ringkas, 'review_status' => 'PENDING'];

            if ($reviewAt) {
                if ($approve) {
                    $row = array_merge($row, [
                        'status' => 'CLOSED', 'approval' => 'APPROVED',
                        'approved_at' => $reviewAt, 'approved_by' => $sup[0], 'closed_at' => $reviewAt,
                        'resolution_seconds' => max(0, $reviewAt->getTimestamp() - $row['progress_started_at']->getTimestamp()),
                        'updated_at' => $reviewAt,
                    ]);
                    $ev[] = ['closed', $sup[0], $sup[1], $sup[2], 'Disetujui. [DUMMY]', ['visit_decision' => $row['visit_decision']], $reviewAt];
                    $c = array_merge($c, ['review_status' => 'APPROVED', 'reviewed_by' => $sup[0], 'reviewed_at' => $reviewAt]);
                } else {
                    $catatan = 'Evidence foto kurang jelas, mohon lengkapi. [DUMMY]';
                    $row = array_merge($row, [
                        'status' => $row['visit_decision'] === TroubleTicket::NON_VISIT ? 'REMOTE' : 'IN_PROGRESS',
                        'approval' => 'REJECTED', 'resolved_at' => null, 'progress_done_at' => null,
                        'closed_by' => null, 'updated_at' => $reviewAt,
                    ]);
                    $ev[] = ['close_rejected', $sup[0], $sup[1], $sup[2], $catatan, null, $reviewAt];
                    $c = array_merge($c, ['review_status' => 'REJECTED', 'reviewed_by' => $sup[0], 'reviewed_at' => $reviewAt, 'review_notes' => $catatan]);
                }
            }
            $closure[] = $c;
        };

        // --- Jalur REMOTE ---
        switch ($kode) {
            case 'remote_baru':
            case 'remote_lama':
                return [$row, $ev, $closure];
            case 'remote_close_ditolak':
                $at = $ack->copy()->addHours(3);
                $tutup($at, false, $at->copy()->addHour());

                return [$row, $ev, $closure];
            case 'done_remote':
                $tutup($ack->copy()->addMinutes(intdiv($resoMin, 2)), false);

                return [$row, $ev, $closure];
            case 'closed_remote':
                $at = $ack->copy()->addMinutes(intdiv($resoMin, 2));
                $tutup($at, true, $at->copy()->addMinutes(30));

                return [$row, $ev, $closure];
        }

        // --- Jalur kunjungan: REMOTE -> PLAN ---
        $plan = $ack->copy()->addMinutes(30);
        $visit = match ($kode) {
            'plan_besok' => now()->addDays(1 + $i % 3),
            'plan_lewat_jadwal' => now()->subDays(1),
            default => $plan->copy()->addDay(),
        };
        $alasan = 'Perlu pengecekan fisik perangkat di site. [DUMMY]';
        $row = array_merge($row, [
            'status' => 'PLAN', 'visit_decision' => TroubleTicket::VISIT,
            'visit_decided_by' => $tech[0], 'visit_decided_at' => $plan,
            'visit_decision_reason' => $alasan, 'visit_site' => $visit->toDateString(),
            'target_online' => $visit->copy()->addDay()->toDateString(),
            'progress_started_at' => null, 'updated_at' => $plan,
        ]);
        $ev[] = ['plan_created', $tech[0], $tech[1], $tech[2], $alasan, ['visit_site' => $row['visit_site'], 'target_online' => $row['target_online']], $plan];

        if (in_array($kode, ['plan_besok', 'plan_lewat_jadwal'], true)) {
            return [$row, $ev, $closure];
        }
        if ($kode === 'closed_dari_plan') {
            $at = $plan->copy()->addHours(5);
            $tutup($at, true, $at->copy()->addHour());

            return [$row, $ev, $closure];
        }

        // PLAN -> TRIP
        $berangkat = match ($kode) {
            'trip_jalan' => now()->subMinutes(max(1, intdiv($travMin, 3))),
            'trip_telat' => now()->subMinutes($travMin * 2),
            default => Carbon::parse($row['visit_site'])->setTime(8, 0),
        };
        if ($berangkat->lt($plan)) {
            $berangkat = $plan->copy()->addMinutes(20);
        }
        $row = array_merge($row, ['status' => 'TRIP', 'travel_started_at' => $berangkat, 'updated_at' => $berangkat]);
        $ev[] = ['travel_started', $tech[0], $tech[1], $tech[2], null, null, $berangkat];

        if ($kode === 'plan_reschedule') {
            $batal = $berangkat->copy()->addMinutes(40);
            $why = 'Jalan menuju site longsor, kunjungan dijadwalkan ulang. [DUMMY]';
            $row = array_merge($row, [
                'status' => 'PLAN', 'travel_started_at' => null, 'travel_seconds' => null,
                'travel_cancelled_at' => $batal, 'travel_cancel_reason' => $why,
                'visit_site' => now()->addDays(2)->toDateString(),
                'target_online' => now()->addDays(3)->toDateString(), 'updated_at' => $batal,
            ]);
            $ev[] = ['travel_cancelled', $tech[0], $tech[1], $tech[2], $why, null, $batal];

            return [$row, $ev, $closure];
        }
        if (in_array($kode, ['trip_jalan', 'trip_telat'], true)) {
            return [$row, $ev, $closure];
        }

        // TRIP -> tiba
        $tiba = $berangkat->copy()->addMinutes(max(10, intdiv($travMin, 2)));
        $row = array_merge($row, [
            'travel_arrived_at' => $tiba,
            'travel_seconds' => $tiba->getTimestamp() - $berangkat->getTimestamp(), 'updated_at' => $tiba,
        ]);
        $ev[] = ['arrived_on_site', $tech[0], $tech[1], $tech[2], null, null, $tiba];
        if ($kode === 'trip_tiba') {
            return [$row, $ev, $closure];
        }

        // -> IN_PROGRESS
        $mulai = match ($kode) {
            'progress' => now()->subMinutes(max(5, intdiv($resoMin, 4))),
            'progress_telat' => now()->subMinutes($resoMin * 2),
            default => $tiba->copy()->addMinutes(15),
        };
        if ($mulai->lt($tiba)) {
            $mulai = $tiba->copy()->addMinutes(15);
        }
        $row = array_merge($row, ['status' => 'IN_PROGRESS', 'progress_started_at' => $mulai, 'updated_at' => $mulai]);
        $ev[] = ['progress_started', $tech[0], $tech[1], $tech[2], null, null, $mulai];

        switch ($kode) {
            case 'progress':
            case 'progress_telat':
                break;
            case 'progress_close_ditolak':
                $at = $mulai->copy()->addHours(2);
                $tutup($at, false, $at->copy()->addHour());
                break;
            case 'done_visit':
                $tutup($mulai->copy()->addMinutes(intdiv($resoMin, 2)), false);
                break;
            case 'closed_visit':
                $at = $mulai->copy()->addMinutes(intdiv($resoMin, 2));
                $tutup($at, true, $at->copy()->addMinutes(20));
                break;
            case 'closed_visit_telat':
                $at = $mulai->copy()->addMinutes($resoMin * 3);
                $tutup($at, true, $at->copy()->addHours(2));
                break;
            case 'closed_resubmit':
                $at = $mulai->copy()->addHours(2);
                $tutup($at, false, $at->copy()->addHour());
                $at2 = $at->copy()->addHours(3);
                $tutup($at2, true, $at2->copy()->addMinutes(45));
                break;
        }

        return [$row, $ev, $closure];
    }
}
