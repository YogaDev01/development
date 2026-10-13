<?php

namespace Database\Seeders;

use App\Models\TroubleTicket;
use App\Models\TroubleTicketMessage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| DUMMY 10 TIKET TAHAP "TT OPEN"
|--------------------------------------------------------------------------
| Untuk mencoba alur dari awal: tiket baru masuk, belum dipilih jalur
| penanganannya (Remote / Create Plan). Semua tiket berstatus OPEN tanpa
| acknowledged_at, jadi SLA Response masih berjalan.
|
| Memakai site & teknisi yang SUDAH ada (hasil DummySiteTroubleTicketSeeder
| atau data asli): site ACTIVE yang punya PIC internal ACTIVE dengan akun
| user. Tidak membuat site, user, atau master baru dan tidak menyentuh tiket
| yang sudah ada.
|
| Jalankan: php artisan db:seed --class=DummyOpenTroubleTicketSeeder
*/
class DummyOpenTroubleTicketSeeder extends Seeder
{
    /** [problem, initial problem, device, flag alarm] */
    private const KASUS = [
        ['Link Down', 'Site down total sejak subuh', 'Modem', 'LOS'],
        ['Degradasi Performa', 'Trafik turun drastis di jam sibuk', 'Router', null],
        ['Power Problem', 'Genset mati, baterai menipis', 'Battery', 'LOW VOLTAGE'],
        ['Konfigurasi', 'VLAN pelanggan tidak jalan', 'Switch', null],
        ['Perangkat Rusak', 'ODU tidak merespons', 'ODU', 'LOS'],
        ['Link Down', 'Putus setelah pemadaman listrik', 'IFL', 'LOS'],
        ['Cuaca / Force Majeure', 'Sinyal hilang saat hujan deras', 'Antena', 'RAIN FADE'],
        ['Degradasi Performa', 'Latensi tinggi dilaporkan pelanggan', 'Router', null],
        ['Power Problem', 'Solar charge controller alarm', 'SCC', 'ALARM POWER'],
        ['Konfigurasi', 'Perlu penyesuaian bandwidth profil', 'Switch', null],
    ];

    public function run(): void
    {
        $actor = DB::table('users')->orderBy('id')->first(['id', 'name']);
        if (! $actor) {
            $this->command?->warn('Tidak ada user; seeder dilewati.');

            return;
        }

        // Site ACTIVE yang punya teknisi (PIC internal ACTIVE dengan akun user).
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
        $dibuat = 0;

        foreach (self::KASUS as $i => [$problem, $issue, $device, $alarm]) {
            $site = $kandidat[$i % $kandidat->count()];

            // Idempoten: lewati kalau site itu sudah punya tiket dengan issue yang sama.
            if (DB::table('trouble_tickets')->where('site_id', $site->site_id)->where('issue', $issue)->exists()) {
                continue;
            }

            $problemId = $problems[$problem] ?? null;
            $policy = DB::table('trouble_ticket_sla_policies')
                ->where('status', 'ACTIVE')
                ->where(fn ($q) => $q->where('problem_id', $problemId)->orWhereNull('problem_id'))
                ->orderByRaw('problem_id IS NULL')
                ->first();

            // Dibuat beberapa jam terakhir supaya aging & SLA Response terlihat hidup.
            $t0 = now()->subHours(random_int(1, 20));

            DB::transaction(function () use ($site, $problemId, $issue, $device, $alarm, $policy, $t0, $actor, $i) {
                $ticketId = DB::table('trouble_tickets')->insertGetId([
                    'ticket_number' => TroubleTicket::generateNumber($t0),
                    'external_ref' => 'DUMMY-OPEN-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'site_id' => $site->site_id,
                    'customer_id' => $site->customer_id,
                    'problem_id' => $problemId,
                    'flag_alarm' => $alarm,
                    'issue' => $issue,
                    'device' => $device,
                    'description' => "[DUMMY] {$issue} di {$site->tid} — {$site->sitename}.",
                    'reporter_name' => 'NOC Monitoring',
                    'reported_at' => $t0,
                    'status' => 'OPEN',
                    'created_by' => $actor->id,
                    'assigned_to' => $site->user_id,
                    'pic_id' => $site->pic_id,
                    'assigned_at' => $t0,
                    'sla_policy_id' => $policy?->id,
                    'sla_response_minutes' => $policy?->response_minutes,
                    'sla_travel_minutes' => $policy?->travel_minutes,
                    'sla_resolution_minutes' => $policy?->resolution_minutes,
                    'created_at' => $t0,
                    'updated_at' => $t0,
                ]);

                DB::table('trouble_ticket_assignments')->insert([
                    'trouble_ticket_id' => $ticketId,
                    'assigned_to' => $site->user_id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => $t0,
                    'created_at' => $t0,
                ]);

                foreach ([
                    ['created', $issue, ['site' => $site->tid]],
                    ['assigned', null, ['to_id' => $site->user_id, 'to_name' => $site->user_name]],
                ] as [$kode, $body, $meta]) {
                    DB::table('trouble_ticket_messages')->insert([
                        'trouble_ticket_id' => $ticketId,
                        'user_id' => $actor->id,
                        'actor_name' => $actor->name,
                        'actor_role' => 'superadmin',
                        'type' => TroubleTicketMessage::TYPE_EVENT,
                        'event' => $kode,
                        'body' => $body,
                        'metadata' => json_encode($meta),
                        'created_at' => $t0,
                    ]);
                }
            });

            $dibuat++;
        }

        $this->command?->info("Tiket dummy tahap TT Open: {$dibuat} dibuat.");
    }
}
