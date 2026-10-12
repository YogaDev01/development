<?php

namespace Database\Seeders;

use App\Models\TroubleTicketDevice;
use App\Models\TroubleTicketIssue;
use App\Models\TroubleTicketRca;
use App\Models\TroubleTicketSubRca;
use Illuminate\Database\Seeder;

/**
 * Master RCA / Sub-RCA / Initial Problem / Device Trouble Ticket.
 *
 * Sumber: "Daftar RCA Paramitra Iintimega" (Daftar_RCA_Lengkap.xlsx, 77
 * kombinasi) — 5 RCA, 36 Sub-RCA, 55 Issue. Daftar Device diturunkan dari
 * Sub-RCA yang berupa perangkat (keputusan 12 Okt 2026).
 *
 * Idempoten: dijalankan ulang hanya menambah yang belum ada, tidak menghapus
 * atau menonaktifkan baris yang sudah dipakai tiket.
 */
class TroubleTicketRcaSeeder extends Seeder
{
    /** RCA => daftar Sub-RCA miliknya. */
    private const RCA = [
        'POWER' => [
            'GENSET',
            'PERANGKAT HANG',
            'PLN ISSUE',
            'POWER OFF BY PIC SITE',
        ],
        'OTHERS' => [
            'BLOCKING ACCESS TO SITE',
            'PERANGKAT TERDAMPAK BENCANA ALAM',
            'RELOCATION',
            'REPOSISI',
        ],
        'HARDWARE' => [
            'ACCESS POINT',
            'ADAPTOR MODEM',
            'ADAPTOR ROUTER',
            'ANTENA',
            'BOOM ARM',
            'CANISTER',
            'FEED SUPPORT',
            'HARDWARE STOLEN',
            'MODEM',
            'POE INJECTOR',
            'POWER CABLE',
            'POWER CONNECTOR',
            'RACK',
            'ROUTER',
            'STABILIZER',
            'STOP KONTAK',
            'TRANSCEIVER',
        ],
        'TRANSMISSION' => [
            'ANTENNA BERGESER',
            'ANTENNA OBSTACLE',
            'COAX CABLE',
            'CONNECTOR RG 45',
            'CONNECTOR RG 6',
            'LAN CABLE',
        ],
        'CONFIGURATION' => [
            'LINK UNSTABLE / SQF DROP',
            'LOST CONFIGURATION ACCESS POINT',
            'LOST CONFIGURATION MODEM',
            'LOST CONFIGURATION ROUTER',
            'NETWORK LOST',
        ],
    ];

    /** Initial Problem (kolom "Issue" pada daftar RCA). */
    private const ISSUES = [
        'ACCESS POINT HANG',
        'ACCESS POINT RUSAK',
        'ADAPTOR MODEM RUSAK',
        'ADAPTOR ROUTER RUSAK',
        'ANTENA RUSAK',
        'BENCANA ALAM',
        'BENCANA ALAM BANJIR',
        'BENCANA ALAM GEMPA BUMI',
        'BENCANA ALAM GUNUNG MELETUS',
        'BENCANA ALAM TANAH LONGSOR',
        'BOOM ARM RUSAK',
        'CANISTER / BACKFRAME RUSAK',
        'COAX CABLE CUT',
        'CONFIG ISSUE',
        'CONNECTOR RUSAK',
        'DIGUNAKAN JIKA DIPERLUKAN USER',
        'FEED SUPPORT RUSAK',
        'GARDU PLN DOWN',
        'GENSET OFF',
        'GENSET RUSAK',
        'IMPACT ACTIVITY',
        'INSTALLASI TIDAK STANDARD',
        'IP HUB ISSUE',
        'IP TRANSIT ISSUE',
        'LAN CABLE CUT/BROKE',
        'LAN CABLE INTERMITTENT',
        'LIBUR SEKOLAH',
        'MASALAH BAHAN BAKAR MINYAK',
        'MCB TRIP',
        'MODEM HANG',
        'MODEM RUSAK',
        'NEED SUPPORTING DOCUMENT PROCESS',
        'PEMADAMAN BERGILIR',
        'PEMELIHARAN PLN',
        'PERANGKAT HANG',
        'PLTS RUSAK',
        'POE INJECTOR HANG',
        'POE INJECTOR RUSAK',
        'POLE / ANTENNA MIRING',
        'POWER CONNECTOR FAULTY',
        'RACK RUSAK/TIDAK SESUAI',
        'RENOVASI GEDUNG',
        'ROUTER HANG',
        'ROUTER RUSAK',
        'SOURCE POWER ALTERNATIF',
        'STABILIZER RUSAK',
        'STOP KONTAK RUSAK',
        'SUDAH ADA INTERNET MANDIRI',
        'TEGANGAN TIDAK STABIL',
        'TERTUTUP BANGUNAN/POHON',
        'TIDAK ADA PIC /PIC MUTASI DINAS',
        'TOKEN LISTRIK HABIS',
        'TRANSCEIVER RUSAK',
        'VANDALIMS / KEAMANAN',
        'VANDALISM / KEAMANAN',
    ];

    /** Perangkat — diturunkan dari Sub-RCA yang berupa perangkat. */
    private const DEVICES = [
        'ACCESS POINT',
        'ADAPTOR MODEM',
        'ADAPTOR ROUTER',
        'ANTENA',
        'BOOM ARM',
        'CANISTER',
        'COAX CABLE',
        'CONNECTOR RG 6',
        'CONNECTOR RG 45',
        'FEED SUPPORT',
        'GENSET',
        'LAN CABLE',
        'MODEM',
        'POE INJECTOR',
        'POWER CABLE',
        'POWER CONNECTOR',
        'RACK',
        'ROUTER',
        'STABILIZER',
        'STOP KONTAK',
        'TRANSCEIVER',
    ];

    public function run(): void
    {
        foreach (self::RCA as $rca => $subs) {
            $baris = TroubleTicketRca::firstOrCreate(['name' => $rca], ['status' => 'ACTIVE']);

            foreach ($subs as $sub) {
                TroubleTicketSubRca::firstOrCreate(
                    ['rca_id' => $baris->id, 'name' => $sub],
                    ['status' => 'ACTIVE'],
                );
            }
        }

        foreach (self::ISSUES as $issue) {
            TroubleTicketIssue::firstOrCreate(['name' => $issue], ['status' => 'ACTIVE']);
        }

        foreach (self::DEVICES as $device) {
            TroubleTicketDevice::firstOrCreate(['name' => $device], ['status' => 'ACTIVE']);
        }
    }
}
