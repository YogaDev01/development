<?php

namespace Database\Seeders;

use App\Models\TroubleTicket;
use App\Models\TroubleTicketMessage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| DUMMY SITE + TROUBLE TICKET
|--------------------------------------------------------------------------
| Mengisi dua modul sekaligus karena Trouble Ticket tidak bisa hidup tanpa
| rantai di depannya:
|
|   1. master Severity (S1..S4), Problem, dan target SLA per severity,
|   2. user + PIC teknisi/supervisor dan satu Team ACTIVE — dipakai aturan
|      visibility tiket (site -> site_pics -> master_pics -> user -> team)
|      dan penentuan leader yang boleh approve/reject plan,
|   3. 10 site dummy dengan wilayah asli, data jaringan, penjaga site, dan
|      PIC (1 Supervisor + 1 Teknisi) per site,
|   4. 12 trouble ticket yang menyebar di lima status SLAmeet
|      (OPEN / APPROVED / ON_PLAN / IN_PROGRESS / CLOSED) beserta timestamp
|      SLA, baris assignment, dan timeline event yang konsisten.
|
| Timestamp ditulis langsung (bukan lewat TroubleTicketWorkflow) supaya
| riwayatnya bisa dibuat mundur beberapa hari; isi kolom dan event timeline
| dibuat sama dengan yang ditulis workflow asli.
|
| Penomoran tiket memakai TroubleTicket::generateNumber() supaya penghitung
| document_sequences ikut maju. Semua baris ditandai [DUMMY].
|
| Jalankan: php artisan db:seed --class=DummySiteTroubleTicketSeeder
| Idempoten: site dipatok pada TID-nya, tiket dilewati kalau site itu sudah
| punya tiket dengan issue yang sama.
*/
class DummySiteTroubleTicketSeeder extends Seeder
{
    /** 10 site dummy: TID, nama, tipe, power, program, gateway, hub, spotbeam, polarisasi. */
    private const SITES = [
        ['AO16709279673301N', 'KANTOR DESA SUKAMAJU', 'PEMERINTAH', 'PLN', 'AKSES_INTERNET', 'Pontianak', 'H58', '25', 'R'],
        ['AO16709279673302N', 'SD NEGERI 3 CIBEUREUM', 'PENDIDIKAN', 'PLN', 'AKSES_INTERNET', 'Pontianak', 'H58', '26', 'L'],
        ['AO16709279673303N', 'TOWER BTS SAMPANG 01', 'TOWER', 'HYBRID', 'TOWER_POWER', 'Manado', 'H61', '31', 'R'],
        ['AO16709279673304N', 'PUSKESMAS RANTAU JAYA', 'PEMERINTAH', 'SPS', 'AKSES_INTERNET', 'Jayapura', 'H72', '44', 'L'],
        ['AO16709279673305N', 'SMP NEGERI 1 TALIABU', 'PENDIDIKAN', 'SPS', 'AKSES_INTERNET', 'Manado', 'H61', '33', 'R'],
        ['AO16709279673306N', 'TOWER BTS MERAUKE 04', 'TOWER', 'HYBRID', 'TOWER_POWER', 'Jayapura', 'H72', '46', 'R'],
        ['AO16709279673307N', 'KANTOR CAMAT LONG APARI', 'PEMERINTAH', 'PLN', 'AKSES_INTERNET', 'Pontianak', 'H59', '27', 'L'],
        ['AO16709279673308N', 'SD NEGERI WAIGEO SELATAN', 'PENDIDIKAN', 'SPS', 'AKSES_INTERNET', 'Jayapura', 'H73', '48', 'R'],
        ['AO16709279673309N', 'TOWER BTS SUMBA TIMUR 02', 'TOWER', 'PLN', 'TOWER_POWER', 'Manado', 'H62', '35', 'L'],
        ['AO16709279673310N', 'KANTOR DESA WANGI WANGI', 'PEMERINTAH', 'HYBRID', 'AKSES_INTERNET', 'Manado', 'H62', '36', 'R'],
    ];

    private const SEVERITIES = [
        ['S1', 'Critical', 1],
        ['S2', 'Major', 2],
        ['S3', 'Minor', 3],
        ['S4', 'Low', 4],
    ];

    private const PROBLEMS = [
        ['Link Down', true],
        ['Power Problem', true],
        ['Perangkat Rusak', true],
        ['Degradasi Performa', false],
        ['Cuaca / Force Majeure', false],
        ['Konfigurasi', false],
    ];

    /** Target SLA per severity: response, travel, resolution (menit). */
    private const SLA = [
        'S1' => [15, 240, 480],
        'S2' => [30, 480, 1440],
        'S3' => [60, 1440, 2880],
        'S4' => [120, 2880, 5760],
    ];

    public function run(): void
    {
        $actor = DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('r.name', 'superadmin')
            ->orderBy('u.id')
            ->first(['u.id', 'u.name']);

        if (! $actor) {
            $this->command?->warn('Tidak ada user superadmin. Jalankan RoleSeeder / user seeder dulu.');

            return;
        }

        if (DB::table('customers')->count() === 0) {
            $this->command?->warn('Tabel customers kosong — site butuh customer_id.');

            return;
        }

        $this->seedMasters((int) $actor->id);
        $people  = $this->seedPeople();
        $siteIds = $this->seedSites($people);
        $this->seedTickets($siteIds, $actor, $people);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. MASTER SEVERITY / PROBLEM / SLA
    |--------------------------------------------------------------------------
    */

    private function seedMasters(int $actorId): void
    {
        $now = now();

        foreach (self::SEVERITIES as [$code, $name, $level]) {
            DB::table('trouble_ticket_severities')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'level' => $level, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (self::PROBLEMS as [$name, $evidence]) {
            DB::table('trouble_ticket_problems')->updateOrInsert(
                ['name' => $name],
                ['requires_evidence' => $evidence, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (self::SLA as $code => [$response, $travel, $resolution]) {
            DB::table('trouble_ticket_sla_policies')->updateOrInsert(
                ['severity' => $code, 'problem_id' => null],
                [
                    'response_minutes'   => $response,
                    'travel_minutes'     => $travel,
                    'resolution_minutes' => $resolution,
                    'effective_from'     => $now->copy()->startOfYear()->toDateString(),
                    'status'             => 'ACTIVE',
                    'created_by'         => $actorId,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ],
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2. USER + PIC + TEAM
    |--------------------------------------------------------------------------
    | Teknisi harus: punya akun ACTIVE, terdaftar di site_pics site-nya
    | (PIC internal ACTIVE), dan menjadi anggota ACTIVE sebuah team yang
    | punya leader — leader itulah yang boleh approve/reject plan.
    |
    | @return array{teknisi: list<array{user_id:int, pic_id:int, name:string}>, supervisor_pic:int, leader_id:int}
    */

    private function seedPeople(): array
    {
        $now         = now();
        $teknisiRole = DB::table('roles')->where('name', 'teknisi')->value('id');
        $spvRole     = DB::table('roles')->where('name', 'supervisor')->value('id');
        $hash        = Hash::make('password123');

        // Leader team (approver plan).
        $leaderId = $this->ensureUser('LEADER TEAM NOC', 'dummy.leadernoc', $spvRole ?: $teknisiRole, $hash);

        // Tiga teknisi: akun + PIC internal berjabatan Teknisi.
        $teknisi = [];
        foreach ([['AGUNG SETIAWAN', 'dummy.teknisi1'], ['BAYU PRATAMA', 'dummy.teknisi2'], ['CANDRA HIDAYAT', 'dummy.teknisi3']] as [$name, $username]) {
            $userId    = $this->ensureUser($name, $username, $teknisiRole, $hash);
            $teknisi[] = [
                'user_id' => $userId,
                'pic_id'  => $this->ensurePic($name, 'Teknisi', $userId),
                'name'    => $name,
            ];
        }

        // Satu PIC Supervisor site, dipasang di semua site dummy.
        $supervisorPic = $this->ensurePic('LEADER TEAM NOC', 'Supervisor', $leaderId);

        $teamId = DB::table('teams')->where('code', 'NOC-DUMMY')->value('id');
        if ($teamId) {
            DB::table('teams')->where('id', $teamId)
                ->update(['leader_id' => $leaderId, 'status' => 'ACTIVE', 'updated_at' => $now]);
        } else {
            $teamId = DB::table('teams')->insertGetId([
                'code'       => 'NOC-DUMMY',
                'name'       => 'Team NOC [DUMMY]',
                'leader_id'  => $leaderId,
                'status'     => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($teknisi as $t) {
            DB::table('team_members')->updateOrInsert(
                ['team_id' => $teamId, 'user_id' => $t['user_id']],
                [
                    'position'   => 'Teknisi',
                    'level'      => 1,
                    'join_at'    => $now->copy()->subMonths(6)->toDateString(),
                    'status'     => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        return ['teknisi' => $teknisi, 'supervisor_pic' => $supervisorPic, 'leader_id' => $leaderId];
    }

    private function ensureUser(string $name, string $username, ?int $roleId, string $hash): int
    {
        $existing = DB::table('users')->where('username', $username)->value('id');

        if ($existing) {
            DB::table('users')->where('id', $existing)->update(['status' => 'ACTIVE', 'updated_at' => now()]);

            return (int) $existing;
        }

        return (int) DB::table('users')->insertGetId([
            'name'       => $name,
            'username'   => $username,
            'email'      => $username . '@tesys.local',
            'password'   => $hash,
            'role_id'    => $roleId,
            'status'     => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensurePic(string $name, string $position, int $userId): int
    {
        $existing = DB::table('master_pics')
            ->where('type', 'internal')
            ->where('user_id', $userId)
            ->where('position', $position)
            ->value('id');

        if ($existing) {
            DB::table('master_pics')->where('id', $existing)->update(['status' => 'ACTIVE', 'updated_at' => now()]);

            return (int) $existing;
        }

        return (int) DB::table('master_pics')->insertGetId([
            'type'       => 'internal',
            'user_id'    => $userId,
            'pic'        => $name,
            'position'   => $position,
            'phone'      => '08' . random_int(1000000000, 1999999999),
            'status'     => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. SITE
    |--------------------------------------------------------------------------
    | @return list<int> id site dummy, urut sesuai self::SITES
    */

    private function seedSites(array $people): array
    {
        $now       = now();
        $customers = DB::table('customers')->orderBy('id')->pluck('id')->all();
        $wilayah   = $this->pickWilayah(count(self::SITES));
        $keepers   = ['Pak Sukardi', 'Bu Ningsih', 'Pak Rahmat', 'Pak Joni', 'Bu Hartini'];
        $ids       = [];

        foreach (self::SITES as $i => [$tid, $sitename, $type, $power, $program, $gateway, $hub, $spotbeam, $pol]) {
            $w = $wilayah[$i] ?? null;

            $row = [
                'sitename'     => $sitename,
                'type'         => $type,
                'source_power' => $power,
                'program'      => $program,
                // Satu site INACTIVE supaya filter status ada isinya.
                'status'       => $i === 9 ? 'INACTIVE' : 'ACTIVE',
                'latitude'     => round(-8 + ($i * 1.3), 7),
                'longitude'    => round(106 + ($i * 2.7), 7),
                'address'      => $w ? "Desa {$w->desa}, Kec. {$w->kec}, {$w->kota} [DUMMY]" : 'Alamat dummy',
                'customer_id'  => $customers[$i % count($customers)],
                'gateway'      => $gateway,
                'outroute_id'  => 'N3' . strtoupper(substr($gateway, 0, 3)) . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT) . 'DFBQ' . (2100 + $i),
                'hub'          => $hub,
                'spotbeam'     => $spotbeam,
                'polarization' => $pol,
                'desa_id'      => $w?->desa_id,
                'kecamatan_id' => $w?->kecamatan_id,
                'kota_id'      => $w?->kota_id,
                'provinsi_id'  => $w?->provinsi_id,
                'pulau_id'     => $w?->pulau_id,
                'updated_at'   => $now,
            ];

            $siteId = DB::table('sites')->where('tid', $tid)->value('id');

            if ($siteId) {
                DB::table('sites')->where('id', $siteId)->update($row);
            } else {
                $siteId = DB::table('sites')->insertGetId($row + ['tid' => $tid, 'created_at' => $now]);
            }

            $ids[] = (int) $siteId;

            // PIC site: 1 Supervisor (sama untuk semua) + 1 Teknisi bergiliran.
            $teknisiPic = $people['teknisi'][$i % count($people['teknisi'])]['pic_id'];

            foreach ([$people['supervisor_pic'], $teknisiPic] as $picId) {
                DB::table('site_pics')->updateOrInsert(
                    ['site_id' => $siteId, 'pic_id' => $picId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }

            if (DB::table('site_keepers')->where('site_id', $siteId)->doesntExist()) {
                DB::table('site_keepers')->insert([
                    'site_id'    => $siteId,
                    'name'       => $keepers[$i % count($keepers)] . ' [DUMMY]',
                    'phone'      => '08' . random_int(1000000000, 1999999999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command?->info('Site dummy: ' . count($ids) . ' baris siap.');

        return $ids;
    }

    /** Wilayah asli (desa -> kecamatan -> kota -> provinsi -> pulau), acak tapi valid. */
    private function pickWilayah(int $n): array
    {
        return DB::table('desa as d')
            ->join('kecamatan as kc', 'kc.id', '=', 'd.kecamatan_id')
            ->join('kota as k', 'k.id', '=', 'kc.kota_id')
            ->join('provinsi as p', 'p.id', '=', 'k.provinsi_id')
            ->inRandomOrder()
            ->limit($n)
            ->get([
                'd.id as desa_id', 'd.name as desa',
                'kc.id as kecamatan_id', 'kc.name as kec',
                'k.id as kota_id', 'k.name as kota',
                'p.id as provinsi_id', 'p.pulau_id',
            ])->all();
    }

    /*
    |--------------------------------------------------------------------------
    | 4. TROUBLE TICKET
    |--------------------------------------------------------------------------
    | 12 tiket: 2 OPEN, 2 APPROVED (plan menunggu leader), 2 ON_PLAN,
    | 3 IN_PROGRESS, 3 CLOSED.
    */

    private function seedTickets(array $siteIds, object $actor, array $people): void
    {
        // [status, severity, problem, issue, device, flag_alarm, umur hari, keputusan plan]
        $plan = [
            ['OPEN', 'S1', 'Link Down', 'Site down total, tidak ada trafik', 'Modem', 'LOS', 0, null],
            ['OPEN', 'S3', 'Degradasi Performa', 'Browsing lambat sejak pagi', 'Router', null, 1, null],
            ['APPROVED', 'S2', 'Power Problem', 'Tegangan PLN turun, baterai cepat habis', 'Battery', 'LOW VOLTAGE', 1, 'VISIT'],
            ['APPROVED', 'S3', 'Konfigurasi', 'IP publik tidak bisa diakses dari NOC', 'Router', null, 2, 'NON_VISIT'],
            ['ON_PLAN', 'S1', 'Perangkat Rusak', 'ODU mati, perlu penggantian unit', 'ODU', 'LOS', 2, 'VISIT'],
            ['ON_PLAN', 'S4', 'Cuaca / Force Majeure', 'Sinyal turun saat hujan deras', 'Antena', 'RAIN FADE', 3, 'NON_VISIT'],
            ['IN_PROGRESS', 'S1', 'Link Down', 'Link terputus, dugaan kabel IFL putus', 'IFL', 'LOS', 3, 'VISIT'],
            ['IN_PROGRESS', 'S2', 'Power Problem', 'Solar charge controller error', 'SCC', 'ALARM POWER', 4, 'VISIT'],
            ['IN_PROGRESS', 'S3', 'Konfigurasi', 'Perlu ganti konfigurasi VLAN pelanggan', 'Switch', null, 4, 'NON_VISIT'],
            ['CLOSED', 'S1', 'Perangkat Rusak', 'Modem tidak bisa booting', 'Modem', 'LOS', 8, 'VISIT'],
            ['CLOSED', 'S2', 'Link Down', 'Site down akibat konektor longgar', 'IFL', 'LOS', 10, 'VISIT'],
            ['CLOSED', 'S4', 'Degradasi Performa', 'Keluhan latensi tinggi dari pelanggan', 'Router', null, 12, 'NON_VISIT'],
        ];

        $problems   = DB::table('trouble_ticket_problems')->pluck('id', 'name');
        $policies   = DB::table('trouble_ticket_sla_policies')->whereNull('problem_id')->get()->keyBy('severity');
        $leader     = DB::table('users')->where('id', $people['leader_id'])->first(['id', 'name']);
        $leaderRole = DB::table('users as u')->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.id', $people['leader_id'])->value('r.name');
        $rcas       = [['HARDWARE', 'Modem rusak', 'Ganti unit modem'], ['POWER', 'Konektor IFL longgar', 'Pasang ulang konektor'], ['CONFIG', 'Konfigurasi QoS', 'Perbaiki konfigurasi']];
        $reporters  = ['NOC Jakarta', 'Helpdesk Customer', 'Monitoring Tools'];
        $created    = 0;

        foreach ($plan as $i => [$status, $severity, $problem, $issue, $device, $alarm, $ageDays, $visit]) {
            $siteId = $siteIds[$i % count($siteIds)];
            $site   = DB::table('sites')->where('id', $siteId)->first(['id', 'tid', 'sitename', 'customer_id']);
            $tech   = $people['teknisi'][$i % count($people['teknisi'])];

            // Sudah pernah di-seed? Jangan dobel.
            if (DB::table('trouble_tickets')->where('site_id', $siteId)->where('issue', $issue)->exists()) {
                continue;
            }

            $policy = $policies[$severity] ?? null;
            $t0     = now()->subDays($ageDays)->subHours(random_int(1, 6));

            $row = [
                'ticket_number'          => TroubleTicket::generateNumber($t0),
                'external_ref'           => 'SLAMEET-' . str_pad((string) (9000 + $i), 5, '0', STR_PAD_LEFT),
                'site_id'                => $siteId,
                'customer_id'            => $site->customer_id,
                'problem_id'             => $problems[$problem] ?? null,
                'severity'               => $severity,
                'flag_alarm'             => $alarm,
                'issue'                  => $issue,
                'device'                 => $device,
                'description'            => "[DUMMY] {$issue} di {$site->tid} — {$site->sitename}. Dilaporkan via NOC.",
                'reporter_name'          => $reporters[$i % count($reporters)],
                'reported_at'            => $t0,
                'status'                 => $status,
                'created_by'             => $actor->id,
                'assigned_to'            => $tech['user_id'],
                'pic_id'                 => $tech['pic_id'],
                'assigned_at'            => $t0,
                'sla_policy_id'          => $policy?->id,
                'sla_response_minutes'   => $policy?->response_minutes,
                'sla_travel_minutes'     => $policy?->travel_minutes,
                'sla_resolution_minutes' => $policy?->resolution_minutes,
                'created_at'             => $t0,
                'updated_at'             => $t0,
            ];

            // Timeline: [event, user_id, nama, role, body, metadata, waktu]
            $events = [
                ['created', $actor->id, $actor->name, 'superadmin', $issue, ['severity' => $severity, 'site' => $site->tid], $t0],
                ['assigned', $actor->id, $actor->name, 'superadmin', null, ['to_id' => $tech['user_id'], 'to_name' => $tech['name']], $t0],
            ];

            if ($status !== 'OPEN') {
                // Respon teknisi.
                $ack                     = $t0->copy()->addMinutes(random_int(5, 25));
                $row['acknowledged_at']  = $ack;
                $row['acknowledged_by']  = $tech['user_id'];
                $row['response_seconds'] = $ack->getTimestamp() - $t0->getTimestamp();
                $events[]                = ['responded', $tech['user_id'], $tech['name'], 'teknisi', null, null, $ack];

                // Pengajuan plan Visit / Non-Visit.
                $decided                      = $ack->copy()->addMinutes(random_int(10, 40));
                $row['visit_decision']        = $visit;
                $row['visit_decided_by']      = $tech['user_id'];
                $row['visit_decided_at']      = $decided;
                $row['visit_decision_reason'] = $visit === 'VISIT'
                    ? 'Perlu pengecekan fisik perangkat di site. [DUMMY]'
                    : 'Bisa diselesaikan remote dari NOC. [DUMMY]';
                $row['approval']              = 'PENDING';
                $row['target_online']         = $decided->copy()->addDays(2)->toDateString();

                if ($visit === 'VISIT') {
                    $row['visit_site'] = $decided->copy()->addDay()->toDateString();
                }

                $events[] = ['plan_submitted', $tech['user_id'], $tech['name'], 'teknisi', $row['visit_decision_reason'], ['decision' => $visit], $decided];

                // Approval plan oleh leader team.
                if ($status !== 'APPROVED') {
                    $approved                = $decided->copy()->addMinutes(random_int(15, 90));
                    $row['approval']         = 'APPROVED';
                    $row['plan_approved_at'] = $approved;
                    $row['plan_approved_by'] = $leader->id;
                    $events[]                = ['plan_approved', $leader->id, $leader->name, $leaderRole, 'Plan disetujui. [DUMMY]', null, $approved];

                    // Mulai pengerjaan.
                    if ($status !== 'ON_PLAN') {
                        $start                      = $approved->copy()->addMinutes(random_int(30, 240));
                        $row['progress_started_at'] = $start;
                        $events[]                   = ['progress_started', $tech['user_id'], $tech['name'], 'teknisi', null, null, $start];

                        if ($status === 'CLOSED') {
                            $closed  = $start->copy()->addMinutes(random_int(60, 600));
                            $summary = 'Perangkat sudah normal, link up, trafik kembali. [DUMMY]';
                            [$rca, $subRca, $action] = $rcas[$i % count($rcas)];

                            $row['resolved_at']        = $closed;
                            $row['closed_at']          = $closed;
                            $row['closed_by']          = $tech['user_id'];
                            $row['resolution_seconds'] = $closed->getTimestamp() - $start->getTimestamp();
                            $row['rca']                = $rca;
                            $row['sub_rca']            = $subRca;
                            $row['action']             = $action;
                            $row['resolution_summary'] = $summary;
                            $row['updated_at']         = $closed;
                            $events[]                  = ['closed', $tech['user_id'], $tech['name'], 'teknisi', $summary, ['rca' => $rca], $closed];
                        }
                    }
                }
            }

            DB::transaction(function () use ($row, $events, $actor) {
                $ticketId = DB::table('trouble_tickets')->insertGetId($row);

                DB::table('trouble_ticket_assignments')->insert([
                    'trouble_ticket_id' => $ticketId,
                    'assigned_to'       => $row['assigned_to'],
                    'assigned_by'       => $actor->id,
                    'assigned_at'       => $row['assigned_at'],
                    'responded_at'      => $row['acknowledged_at'] ?? null,
                    'created_at'        => $row['assigned_at'],
                ]);

                foreach ($events as [$code, $userId, $userName, $role, $body, $meta, $at]) {
                    DB::table('trouble_ticket_messages')->insert([
                        'trouble_ticket_id' => $ticketId,
                        'user_id'           => $userId,
                        'actor_name'        => $userName,
                        'actor_role'        => $role,
                        'type'              => TroubleTicketMessage::TYPE_EVENT,
                        'event'             => $code,
                        'body'              => $body,
                        'metadata'          => $meta ? json_encode($meta) : null,
                        'created_at'        => $at,
                    ]);
                }
            });

            $created++;
        }

        $this->command?->info("Trouble ticket dummy: {$created} dibuat.");
        $this->command?->info('Login: dummy.teknisi1..3 (teknisi), dummy.leadernoc (leader) — password123');
    }
}
