<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tiga team Operation sesuai Struktur Organisasi (berkas "Struktur
 * Organisasi.xlsx" + bagan per project, 19 Okt 2026): PT. Paramitra Intimega,
 * MS MSG & RGR, dan IPT.
 *
 * Susunannya datar per level, persis seperti Team::orgChart() menampilkannya:
 *
 *   level 1 — Manager / Head Manager (leader team)
 *   level 2 — Supervisor (boleh lebih dari satu per team)
 *   level 3 — NOC / CSO / DC / Logistik
 *   level 4 — Teknisi (kolom "position" memuat supervisor & area kerjanya)
 *
 * Anggota ditautkan ke akun `users` yang SUDAH ADA — dicocokkan lewat email,
 * lalu lewat nama bila email kosong/tidak ketemu. Seeder ini TIDAK membuat
 * akun baru: orang yang belum punya akun dilaporkan di layar dan dilewati,
 * supaya tidak ada anggota team yang menunjuk akun karangan.
 *
 * Aman dijalankan ulang: team dicari berdasarkan namanya, anggota berdasarkan
 * pasangan (team, user) — yang sudah ada diperbarui, bukan diduplikasi.
 *
 *   php artisan db:seed --class=TeamStrukturOrganisasiSeeder
 */
class TeamStrukturOrganisasiSeeder extends Seeder
{
    /**
     * [nama, email, jabatan (position), level]
     * Email diambil dari berkas Struktur Organisasi; kosong = cocokkan nama.
     */
    public const STRUKTUR = [
        'PT. PARAMITRA INTIMEGA' => [
            ['M Dzaky A', null, 'Manager Operation', 1],
            ['Fajar', 'fajarhafizhuddin7@gmail.com', 'Supervisor — ACEH-1, ACEH-2, ACEH-3, BANTEN-1', 2],
            ['Sjahnell A', 'sjahnell.business@gmail.com', 'Supervisor — KALTENG-1, JABAR-1B', 2],
            ['Novita', 'vytanovita2@gmail.com', 'NOC', 3],
            ['Tasya', 'tasyahazn@gmail.com', 'CSO', 3],
            ['Manda', 'mandaputri030520@gmail.com', 'DC', 3],
            ['Shima', null, 'DC', 3],
            ['Reina', null, 'DC', 3],
            ['Rizky Saepul', 'rizkiute1468@gmail.com', 'Teknisi ACEH-1 (Fajar)', 4],
            ['Reno Bagas', 'renobagas25.05@gmail.com', 'Teknisi ACEH-1 (Fajar)', 4],
            ['Agung Barokah', 'agungbarokah734@gmail.com', 'Teknisi ACEH-1 (Fajar)', 4],
            ['Ilham Shopyan', 'ilhamsopyan74@gmail.com', 'Teknisi ACEH-3 (Fajar)', 4],
            ['Ihsan', 'ihsanxiitkja@gmail.com', 'Teknisi ACEH-3 (Fajar)', 4],
            ['Ahmad Jaelani', 'ahmadjaelaniii0127@gmail.com', 'Teknisi ACEH-2 (Fajar)', 4],
            ['Dinar Lesmana', 'dinarlesmana885@gmail.com', 'Teknisi ACEH-2 (Fajar)', 4],
            ['Ikhsan', 'fadillahikhsan51@gmail.com', 'Teknisi BANTEN-1 (Fajar)', 4],
            ['Sidik M', 'idikkeren1@gmail.com', 'Teknisi BANTEN-1 (Fajar)', 4],
            ['Anwar Surya', 'anwarsr584@gmail.com', 'Teknisi KALTENG-1 (Sjahnell A)', 4],
            ['Natan Hendra', 'vengerzgatez87@gmail.com', 'Teknisi KALTENG-1 (Sjahnell A)', 4],
            ['Sahrul Firdaus', 'elmyname9@gmail.com', 'Teknisi KALTENG-1 (Sjahnell A)', 4],
            ['Rudiwan', 'Barudgpk6@gmail.com', 'Teknisi KALTENG-1 (Sjahnell A)', 4],
            ['Sendi', 'sendiramdanisonjaya04@gmail.com', 'Teknisi KALTENG-1 (Sjahnell A)', 4],
            ['Shopyan', 'shopyantsauri001@gmail.com', 'Teknisi KALTENG-1 (Sjahnell A)', 4],
            ['Mustofa Kamal', 'mhalkhamal08@gmail.com', 'Teknisi KALTENG-1 (Sjahnell A)', 4],
            ['Rizky Setiawan', 'rs9590374@gmail.com', 'Teknisi JABAR-1B (Sjahnell A)', 4],
            ['Rendi Romadona', 'sosrendi378@gmail.com', 'Teknisi JABAR-1B (Sjahnell A)', 4],
            ['Rizki Fadillah', 'jatinunggal706@gmail.com', 'Teknisi JABAR-1B (Sjahnell A)', 4],
            ['Tatang', 'tatangciceury1975@gmail.com', 'Teknisi JABAR-1B (Sjahnell A)', 4],
            ['Giovany Abdul', null, 'Teknisi JABAR-1B (Sjahnell A)', 4],
            ['Didik Nuralim', null, 'Teknisi JABAR-1B (Sjahnell A)', 4],
        ],
        'MS MSG & RGR' => [
            ['Wilda Ainun Nisa', null, 'OM Manager', 1],
            ['Asep Rohendra', 'hendra@terex.id', 'Supervisor — Maluku, Maluku Utara, Sulawesi', 2],
            ['Aufa Athaya', 'aufaathayya14@gmail.com', 'Supervisor — Kalimantan, Bengkulu, Sumut & Kepri', 2],
            ['Maslahatul Ummah', 'ranmumtazah@gmail.com', 'CSO', 3],
            ['Nazuar Wahya Manik', null, 'Logistic', 3],
            ['Ari Hidayat', 'pawenangarie@gmail.com', 'Teknisi Maluku — Moa (Asep Rohendra)', 4],
            ['Dimas Saputera', 'dimassaputera2307@gmail.com', 'Teknisi Maluku — Moa (Asep Rohendra)', 4],
            ['Tedi Hermawan', 'tediterex@gmail.com', 'Teknisi Maluku — Saumlaki (Asep Rohendra)', 4],
            ['Raihan Adika', 'raihanadikazahara@gmail.com', 'Teknisi Maluku — Dobo (Asep Rohendra)', 4],
            ['Dani Riana', 'daniriana476@gmail.com', 'Teknisi Maluku Utara — Makian (Asep Rohendra)', 4],
            ['Ibnu Fajar', 'fibnujajah@gmail.com', 'Teknisi Maluku Utara — Ternate (Asep Rohendra)', 4],
            ['Shiddiqi Akbar', 'dikyanggara29@gmail.com', 'Teknisi Sulawesi — Kendari (Asep Rohendra)', 4],
            ['M. Farish A. R.', 'muhammadfarishabhir28@gmail.com', 'Teknisi Kalimantan — Kurun (Aufa Athaya)', 4],
            ['Khairul Adzikri', 'khairuladzikri9@gmail.com', 'Teknisi Kalimantan — Serawai (Aufa Athaya)', 4],
            ['Suheri', 'abidrama886@gmail.com', 'Teknisi Kalimantan — Samarinda (Aufa Athaya)', 4],
            ['Awal Farizki', 'awalfarizkik@gmail.com', 'Teknisi Bengkulu — Kota Bengkulu (Aufa Athaya)', 4],
            ['Septian Maulana', 'septianmaulana200904@gmail.com', 'Teknisi Sumut & Kepri — Tebing Tinggi & Ranai (Aufa Athaya)', 4],
        ],
        'IPT' => [
            ['Ahmad Buhori', null, 'Head Manager', 1],
            ['Wafi Nuri', null, 'Supervisor', 2],
            ['Nandika', 'nandikasanjaya5@gmail.com', 'NOC', 3],
            ['Nadiyah', 'nadiyah@terex.com', 'CSO', 3],
            ['Devasya', null, 'DC', 3],
            ['Alnody', null, 'Logistik', 3],
            ['Sep Andian', 'andian290904@gmail.com', 'Teknisi Sumatera — Pekanbaru (Wafi Nuri)', 4],
            ['Kifli Aprizon Faqih Mubarak', 'afrizanmns97@gmail.com', 'Teknisi Kalimantan — Malinau (Wafi Nuri)', 4],
            ['Dani Lesmana', 'danilesmana6996@gmail.com', 'Teknisi Nusra — Kupang (Wafi Nuri)', 4],
            ['Hamdun Djamaludin', 'hamdundjamaludin07@gmail.com', 'Teknisi Maluku — Ternate (Wafi Nuri)', 4],
            ['Rizal Samal', 'rivaisamal1994@gmail.com', 'Teknisi Maluku — Ambon (Wafi Nuri)', 4],
            ['Enggibertus Ahmad Rudyanto', 'roroyrudy@gmail.com', 'Teknisi Papua — Merauke (Wafi Nuri)', 4],
            ['Syaeful Bathaqi', 'syaefulbayhaqi1@gmail.com', 'Teknisi Papua — Mappi (Wafi Nuri)', 4],
            ['Miftakhul Huda', 'mhyuda25@gmail.com', 'Teknisi Papua — Nabire (Wafi Nuri)', 4],
        ],
    ];

    public function run(): void
    {
        foreach (self::STRUKTUR as $namaTeam => $anggota) {
            $belumPunyaAkun = [];

            DB::transaction(function () use ($namaTeam, $anggota, &$belumPunyaAkun) {
                $team = Team::firstOrCreate(
                    ['name' => $namaTeam],
                    ['code' => Team::generateNextCode(), 'status' => 'ACTIVE'],
                );

                foreach ($anggota as [$nama, $email, $jabatan, $level]) {
                    $user = $this->cariUser($nama, $email);

                    if (! $user) {
                        $belumPunyaAkun[] = $nama;

                        continue;
                    }

                    TeamMember::updateOrCreate(
                        ['team_id' => $team->id, 'user_id' => $user->id],
                        [
                            'position' => $jabatan,
                            'level' => $level,
                            'status' => 'ACTIVE',
                        ],
                    );

                    // Leader team = jabatan tertinggi (level 1). Di Trouble
                    // Ticket leader inilah pengawas bawaan tiket anggotanya;
                    // supervisor tetap berwenang lewat role-nya (AccessRoles).
                    if ($level === 1) {
                        $team->update(['leader_id' => $user->id]);
                    }
                }
            });

            $this->command?->info("Team {$namaTeam}: tersusun.");

            if ($belumPunyaAkun) {
                $this->command?->warn('  Belum punya akun users, dilewati: '.implode(', ', $belumPunyaAkun));
            }
        }
    }

    /** Email lebih dipercaya daripada nama; nama dicocokkan tanpa beda huruf besar/kecil. */
    private function cariUser(string $nama, ?string $email): ?User
    {
        if ($email) {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

            if ($user) {
                return $user;
            }
        }

        return User::whereRaw('LOWER(name) = ?', [mb_strtolower($nama)])->first();
    }
}
