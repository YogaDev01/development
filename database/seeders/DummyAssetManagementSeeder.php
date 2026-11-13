<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetInspection;
use App\Models\AssetLocation;
use App\Models\AssetLog;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceSchedule;
use App\Models\AssetMovement;
use App\Models\AssetOpname;
use App\Models\AssetOpnameItem;
use App\Models\AssetPurchaseRequest;
use App\Models\AssetReceiving;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\AssetNumber;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| DUMMY DATA MODUL ASSET MANAGEMENT
|--------------------------------------------------------------------------
| Mengisi semua halaman /asset dengan data yang konsisten satu sama lain:
|   - 8 kategori, 6 lokasi, ±60 aset (2020–2026) di semua status
|   - ASSIGNED   -> punya BAST serah terima (COMPLETED)
|   - CHECKED_OUT-> punya check-out berjalan (sebagian overdue)
|   - MAINTENANCE-> punya maintenance IN_PROGRESS / WAITING_PARTS
|   - DISPOSED   -> punya disposal APPROVED
|   - riwayat: pengembalian, check-in, transfer approved/pending/rejected,
|     disposal pending, maintenance selesai, jadwal, inspeksi, PR & GR,
|     stock opname selesai + berjalan.
|
| Penanda: aset & dokumen diberi deskripsi/catatan berawalan [DUMMY];
| kategori/lokasi memakai kode tetap (firstOrCreate). Seeder BERHENTI bila
| sudah ada aset [DUMMY] (tidak menggandakan). Karyawan dibuat bila belum ada
| karyawan aktif (notes [DUMMY]). Tidak membuat Cost Request / pembayaran.
|
| Ditolak berjalan di APP_ENV=production.
|
| Jalankan (HANYA di DB lokal yang targetnya sudah dipastikan):
|   php artisan db:seed --class=DummyAssetManagementSeeder
*/
class DummyAssetManagementSeeder extends Seeder
{
    private const TAG = '[DUMMY]';

    private const CATEGORIES = [
        ['ITE', 'IT Equipment', 4, 'STRAIGHT_LINE', 10, '1-5100 Peralatan Komputer', 'Laptop, desktop, server, monitor, printer'],
        ['NET', 'Network', 5, 'STRAIGHT_LINE', 5, '1-5200 Peralatan Jaringan', 'Switch, router, firewall, access point'],
        ['COM', 'Communication', 3, 'STRAIGHT_LINE', 5, '1-5100 Peralatan Komputer', 'Smartphone, HT'],
        ['PWR', 'Power', 8, 'DECLINING_BALANCE', 10, '1-5300 Peralatan Listrik', 'Genset, UPS'],
        ['FUR', 'Office Furniture', 8, 'STRAIGHT_LINE', 0, '1-5400 Inventaris Kantor', 'Meja, kursi, lemari'],
        ['VEH', 'Vehicle', 8, 'DECLINING_BALANCE', 20, '1-5500 Kendaraan', 'Mobil & motor operasional'],
        ['TLS', 'Field Tools', 5, 'STRAIGHT_LINE', 10, '1-5600 Peralatan Lapangan', 'Splicer, OTDR, power meter'],
        ['FAC', 'Facility', 6, 'STRAIGHT_LINE', 5, '1-5400 Inventaris Kantor', 'AC, CCTV'],
    ];

    private const LOCATIONS = [
        ['HO-BDG', 'Head Office – Bandung', 'OFFICE', 'Jl. Soekarno-Hatta, Bandung', 'Dimas Prasetyo'],
        ['BR-JKT', 'Branch Office – Jakarta', 'BRANCH', 'Jl. TB Simatupang, Jakarta Selatan', 'Rina Kartika'],
        ['BR-SBY', 'Branch Office – Surabaya', 'BRANCH', 'Jl. Raya Darmo, Surabaya', 'Agus Salim'],
        ['PRJ-CRB', 'Project Office – Cirebon', 'PROJECT', 'Kawasan Industri Cirebon', 'Hendra Wijaya'],
        ['SITE-LMB', 'Site – BTS Lembang', 'SITE', 'Lembang, Bandung Barat', 'Teknisi Lembang'],
        ['WH-GDB', 'Warehouse – Gedebage', 'WAREHOUSE', 'Gedebage, Bandung', 'Dimas Prasetyo'],
    ];

    /** [nama, kategori, tipe, merek, model, harga, spesifikasi, perorangan?] */
    private const TEMPLATES = [
        ['Laptop ThinkPad T14 Gen 4', 'ITE', 'Laptop', 'Lenovo', 'ThinkPad T14 G4', 21_500_000, ['Processor' => 'Intel Core i7-1365U', 'RAM' => '16 GB', 'Storage' => '512 GB NVMe'], true],
        ['Laptop Latitude 5440', 'ITE', 'Laptop', 'Dell', 'Latitude 5440', 19_800_000, ['Processor' => 'Intel Core i5-1345U', 'RAM' => '16 GB', 'Storage' => '512 GB NVMe'], true],
        ['Desktop OptiPlex 7010', 'ITE', 'Desktop', 'Dell', 'OptiPlex 7010 SFF', 14_200_000, ['Processor' => 'Intel Core i5-13500', 'RAM' => '16 GB'], true],
        ['Monitor 24" P2422H', 'ITE', 'Monitor', 'Dell', 'P2422H', 3_150_000, ['Panel' => '24" IPS FHD'], true],
        ['Printer LaserJet M428fdw', 'ITE', 'Printer', 'HP', 'LaserJet Pro M428fdw', 8_900_000, ['Tipe' => 'Mono Laser MFP'], false],
        ['Server PowerEdge R650', 'ITE', 'Server', 'Dell', 'PowerEdge R650', 168_000_000, ['Processor' => '2x Xeon Silver 4314', 'RAM' => '128 GB ECC'], false],
        ['Smartphone Galaxy A55', 'COM', 'Smartphone', 'Samsung', 'Galaxy A55 5G', 6_200_000, ['RAM' => '8 GB', 'Storage' => '256 GB'], true],
        ['Switch CBS350-24P', 'NET', 'Switch', 'Cisco', 'CBS350-24P-4G', 18_500_000, ['Port' => '24x GE PoE+, 4x SFP'], false],
        ['Router RB4011iGS+', 'NET', 'Router', 'MikroTik', 'RB4011iGS+RM', 4_600_000, ['Firmware' => 'RouterOS 7.14'], false],
        ['UPS Smart-UPS 3000VA', 'PWR', 'UPS', 'APC', 'SMT3000RMI2U', 27_500_000, ['Kapasitas' => '3000 VA / 2700 W'], false],
        ['Genset 20 kVA', 'PWR', 'Generator', 'Cummins', 'C22D5', 245_000_000, ['Kapasitas' => '20 kVA', 'BBM' => 'Solar'], false],
        ['Meja Kerja L-Shape', 'FUR', 'Desk', 'Chitose', 'L-Desk 160', 7_400_000, ['Dimensi' => '160 x 140 x 75 cm'], true],
        ['Kursi Ergonomis Sayl', 'FUR', 'Chair', 'Herman Miller', 'Sayl Work Chair', 9_800_000, ['Material' => 'Mesh'], true],
        ['AC Split 2 PK', 'FAC', 'Air Conditioner', 'Daikin', 'FTKC50TVM4', 11_800_000, ['Kapasitas' => '18.000 BTU'], false],
        ['CCTV IP Camera 4MP', 'FAC', 'CCTV', 'Hikvision', 'DS-2CD2143G2-I', 2_350_000, ['Resolusi' => '4 MP'], false],
        ['Hilux D-Cab 4x4', 'VEH', 'Vehicle', 'Toyota', 'Hilux 2.4 E D-Cab', 512_000_000, ['Mesin' => '2.4L Diesel', 'No. Polisi' => 'D 8742 TX'], true],
        ['Fusion Splicer 70S+', 'TLS', 'Measurement', 'Fujikura', '70S+', 142_000_000, ['Tipe' => 'Core alignment'], true],
        ['OTDR MaxTester 730C', 'TLS', 'Measurement', 'EXFO', 'MaxTester 730C', 98_000_000, ['Panjang gelombang' => '1310/1550 nm'], true],
    ];

    private const SUPPLIERS = ['PT Datascrip', 'PT Bhinneka Mentari Dimensi', 'PT Metrodata Electronics', 'PT Berca Hardayaperkasa', 'PT Astra International', 'CV Sinar Furnindo', 'PT Cummins Indonesia'];

    private const VENDORS = ['PT Mitra Teknik Sejahtera', 'CV Prima Service', 'Internal – O&M Team', 'Authorized Service Center', 'Internal – IT Support'];

    private int $userId;

    private $users;

    private $employees;

    private $locations;

    public function run(): void
    {
        if (App::environment('production')) {
            $this->command?->error('Ditolak: seeder dummy tidak boleh berjalan di production.');

            return;
        }
        if (Asset::withTrashed()->where('description', 'like', self::TAG . '%')->exists()) {
            $this->command?->warn('Dummy aset sudah ada — dilewati.');

            return;
        }

        $this->users = User::where('status', 'ACTIVE')->orderBy('id')->limit(5)->pluck('id');
        if ($this->users->isEmpty()) {
            $this->command?->error('Butuh minimal satu user ACTIVE.');

            return;
        }
        $this->userId = (int) $this->users->first();

        mt_srand(20261112);

        DB::transaction(function () {
            $this->masters();
            $this->employees = $this->ensureEmployees();
            $assets = $this->assets();
            $this->history($assets);
            $this->schedulesAndInspections($assets);
            $this->procurement();
            $this->opnames();
        });

        $this->command?->info('Dummy Asset Management dibuat: ' . Asset::where('description', 'like', self::TAG . '%')->count() . ' aset.');
    }

    /* ------------------------------------------------------------------ */

    private function masters(): void
    {
        foreach (self::CATEGORIES as [$code, $name, $life, $method, $res, $acc, $desc]) {
            AssetCategory::firstOrCreate(['code' => $code], [
                'name' => $name, 'life_years' => $life, 'depreciation_method' => $method,
                'residual_percent' => $res, 'account' => $acc, 'description' => $desc, 'status' => 'ACTIVE',
            ]);
        }
        foreach (self::LOCATIONS as [$code, $name, $type, $addr, $pic]) {
            AssetLocation::firstOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'address' => $addr, 'pic_name' => $pic, 'status' => 'ACTIVE',
            ]);
        }
        $this->locations = AssetLocation::whereIn('code', array_column(self::LOCATIONS, 0))->get()->keyBy('code');
    }

    private function ensureEmployees()
    {
        $ada = Employee::active()->orderBy('id')->limit(15)->get(['id', 'name']);
        if ($ada->count() >= 5) {
            return $ada;
        }
        foreach (['Yoga Pratama', 'Rina Kartika', 'Hendra Wijaya', 'Agus Salim', 'Siti Nurhaliza', 'Bayu Saputra', 'Dewi Lestari', 'Fajar Nugroho'] as $nama) {
            Employee::create(['name' => $nama, 'status' => 'ACTIVE', 'notes' => self::TAG . ' karyawan dummy modul aset']);
        }

        return Employee::active()->orderBy('id')->limit(15)->get(['id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int, Asset> */
    private function assets()
    {
        $cats  = AssetCategory::whereIn('code', array_column(self::CATEGORIES, 0))->get()->keyBy('code');
        $depts = Department::where('status', 'ACTIVE')->pluck('id');
        $today = now()->startOfDay();
        $hasil = collect();

        // Status sengaja disebar supaya semua tab & KPI terisi.
        $pool = ['ASSIGNED', 'ASSIGNED', 'ASSIGNED', 'AVAILABLE', 'AVAILABLE', 'IN_USE', 'IN_USE', 'CHECKED_OUT', 'MAINTENANCE', 'DISPOSED'];

        for ($i = 0; $i < 60; $i++) {
            $t = self::TEMPLATES[$i < count(self::TEMPLATES) ? $i : mt_rand(0, count(self::TEMPLATES) - 1)];
            [$name, $catCode, $type, $brand, $model, $base, $specs, $perorangan] = $t;
            $cat = $cats[$catCode];

            $acq = Carbon::create(mt_rand(2020, 2026), 1, 1)->addDays(mt_rand(0, 360));
            if ($acq->gt($today->copy()->subDays(20))) {
                $acq = $today->copy()->subDays(mt_rand(20, 200));
            }

            $status = $pool[mt_rand(0, count($pool) - 1)];
            if (! $perorangan && in_array($status, ['ASSIGNED', 'CHECKED_OUT'], true)) {
                $status = 'IN_USE';
            }
            if ($perorangan && $status === 'IN_USE') {
                $status = 'ASSIGNED';
            }
            if ($status === 'DISPOSED' && $acq->year > 2023) {
                $status = 'AVAILABLE';
            }

            $loc = match (true) {
                $status === 'AVAILABLE' || $status === 'DISPOSED' => 'WH-GDB',
                in_array($type, ['Generator', 'UPS'], true)       => ['SITE-LMB', 'HO-BDG', 'PRJ-CRB'][mt_rand(0, 2)],
                in_array($type, ['Measurement', 'Vehicle'], true) => ['PRJ-CRB', 'SITE-LMB', 'WH-GDB'][mt_rand(0, 2)],
                default                                           => ['HO-BDG', 'HO-BDG', 'BR-JKT', 'BR-SBY', 'PRJ-CRB'][mt_rand(0, 4)],
            };

            $age  = $acq->diffInYears($today);
            $cond = $status === 'DISPOSED' ? 'DAMAGED'
                : ($status === 'MAINTENANCE' ? ['FAIR', 'POOR'][mt_rand(0, 1)]
                : ($age < 1 ? 'EXCELLENT' : ($age < 3 ? ['GOOD', 'GOOD', 'EXCELLENT'][mt_rand(0, 2)] : ['GOOD', 'FAIR', 'FAIR', 'POOR'][mt_rand(0, 3)])));

            $cost = (int) (round($base * (0.94 + mt_rand(0, 12) / 100) / 1000) * 1000);
            $sn   = strtoupper(substr($brand, 0, 2)) . chr(65 + mt_rand(0, 25)) . mt_rand(1000000, 9999999);

            $asset = Asset::create([
                'code'                => AssetNumber::assetCode($cat->code, $acq->year),
                'name'                => $name,
                'asset_category_id'   => $cat->id,
                'type'                => $type,
                'brand'               => $brand,
                'model'               => $model,
                'serial_number'       => $sn,
                'asset_location_id'   => $this->locations[$loc]->id,
                'department_id'       => $depts->isNotEmpty() ? $depts[mt_rand(0, $depts->count() - 1)] : null,
                'custodian_name'      => $perorangan ? null : $this->locations[$loc]->pic_name,
                'acquisition_date'    => $acq->toDateString(),
                'acquisition_type'    => mt_rand(1, 10) === 1 ? 'TRANSFER_IN' : 'PURCHASE',
                'cost'                => $cost,
                'residual_value'      => (int) round($cost * $cat->residual_percent / 100),
                'life_years'          => $cat->life_years,
                'depreciation_method' => $cat->depreciation_method,
                'condition'           => $cond,
                'status'              => 'AVAILABLE', // status akhir diisi history()
                'supplier'            => self::SUPPLIERS[mt_rand(0, count(self::SUPPLIERS) - 1)],
                'po_number'           => sprintf('PO/TRX/%d/%04d', $acq->year, mt_rand(10, 480)),
                'warranty_end'        => $acq->copy()->addYears(mt_rand(1, 3))->toDateString(),
                'specs'               => $specs,
                'description'         => self::TAG . " {$type} untuk operasional di {$this->locations[$loc]->name}.",
                'created_by'          => $this->userId,
                'updated_by'          => $this->userId,
            ]);
            $this->log($asset, 'create', 'Aset didaftarkan.', $acq);
            $asset->setAttribute('target_status', $status);
            $hasil->push($asset);
        }

        return $hasil;
    }

    /** Buat dokumen perpindahan/maintenance sesuai status akhir tiap aset. */
    private function history($assets): void
    {
        $today = now()->startOfDay();
        $n = 0;

        foreach ($assets as $a) {
            $target = $a->getAttribute('target_status');
            $a->offsetUnset('target_status');
            $n++;
            $emp = $this->employees[$n % $this->employees->count()];
            $acq = Carbon::parse($a->acquisition_date);
            $mulai = $acq->copy()->max($today->copy()->subDays(400));

            // Riwayat lampau acak: maintenance selesai (± 1/3 aset).
            if ($n % 3 === 0) {
                $start = $this->between($mulai, $today->copy()->subDays(30));
                $est = (int) (round($a->cost * mt_rand(1, 4) / 100 / 10000) * 10000);
                $this->maintenance($a, [
                    'maintenance_type' => ['PREVENTIVE', 'CORRECTIVE', 'CALIBRATION', 'INSPECTION'][mt_rand(0, 3)],
                    'problem' => ['Service rutin berkala', 'Kipas berisik & overheat', 'Kalibrasi tahunan', 'Ganti baterai', 'Port flapping'][mt_rand(0, 4)],
                    'estimated_cost' => $est, 'actual_cost' => (int) (round($est * (0.85 + mt_rand(0, 30) / 100) / 10000) * 10000),
                    'start_date' => $start, 'end_date' => $start->copy()->addDays(mt_rand(1, 5)),
                    'status' => 'COMPLETED', 'asset_status_before' => 'AVAILABLE', 'result_notes' => 'Selesai, aset berfungsi normal.',
                ]);
            }

            switch ($target) {
                case 'ASSIGNED':
                    // Sebagian pernah dipegang orang lain lalu dikembalikan.
                    if ($n % 4 === 0) {
                        $d1 = $this->between($mulai, $today->copy()->subDays(60));
                        $prev = $this->employees[($n + 3) % $this->employees->count()];
                        $this->movement($a, 'HANDOVER', 'COMPLETED', $d1, ['to_employee_id' => $prev->id]);
                        $this->movement($a, 'RETURN', 'COMPLETED', $d1->copy()->addDays(mt_rand(10, 40)), [
                            'from_employee_id' => $prev->id, 'to_location_id' => $a->asset_location_id, 'condition_after' => 'GOOD',
                        ]);
                    }
                    $this->movement($a, 'HANDOVER', 'COMPLETED', $this->between($mulai, $today->copy()->subDays(3)), [
                        'to_employee_id' => $emp->id, 'to_location_id' => $a->asset_location_id, 'notes' => 'Untuk kebutuhan kerja harian.',
                    ]);
                    $a->update(['status' => 'ASSIGNED', 'employee_id' => $emp->id]);
                    break;

                case 'CHECKED_OUT':
                    $out = $today->copy()->subDays(mt_rand(1, 12));
                    $this->movement($a, 'CHECKOUT', 'CHECKED_OUT', $out, [
                        'to_employee_id' => $emp->id, 'asset_status_before' => 'AVAILABLE',
                        'expected_return_date' => $out->copy()->addDays(mt_rand(3, 7)),
                        'notes' => ['Site survey BTS', 'PM rutin tower', 'Splicing FO link Cirebon', 'Troubleshoot link down'][mt_rand(0, 3)],
                    ]);
                    $a->update(['status' => 'CHECKED_OUT']);
                    break;

                case 'MAINTENANCE':
                    $before = in_array($a->type, ['Generator', 'UPS', 'Switch', 'Router', 'Air Conditioner', 'CCTV', 'Server', 'Printer'], true) ? 'IN_USE' : 'AVAILABLE';
                    $this->maintenance($a, [
                        'maintenance_type' => 'CORRECTIVE',
                        'problem' => ['Baterai drop, runtime < 5 menit', 'Kompresor tidak dingin', 'Layar berkedip', 'Overheat & mati sendiri'][mt_rand(0, 3)],
                        'estimated_cost' => (int) (round($a->cost * 0.03 / 10000) * 10000),
                        'start_date' => $today->copy()->subDays(mt_rand(1, 10)),
                        'status' => mt_rand(0, 1) ? 'IN_PROGRESS' : 'WAITING_PARTS', 'asset_status_before' => $before,
                    ]);
                    $a->update(['status' => 'MAINTENANCE']);
                    break;

                case 'DISPOSED':
                    $d = $this->between($mulai, $today->copy()->subDays(5));
                    $method = ['SOLD', 'SCRAPPED', 'WRITTEN_OFF'][mt_rand(0, 2)];
                    $this->movement($a, 'DISPOSAL', 'APPROVED', $d, [
                        'reason' => 'Rusak berat, biaya perbaikan melebihi nilai buku.', 'disposal_method' => $method,
                        'disposal_value' => $method === 'SOLD' ? (int) (round($a->cost * 0.05 / 10000) * 10000) : 0,
                        'approved_by' => $this->userId, 'approved_at' => $d->copy()->addDay(),
                    ]);
                    $a->update(['status' => 'DISPOSED', 'disposed_at' => $d->toDateString(), 'employee_id' => null]);
                    break;

                default: // AVAILABLE / IN_USE
                    $a->update(['status' => $target]);
                    // Check-out yang sudah dikembalikan.
                    if ($n % 2 === 0 && $target === 'AVAILABLE') {
                        $out = $this->between($mulai, $today->copy()->subDays(20));
                        $this->movement($a, 'CHECKOUT', 'RETURNED', $out, [
                            'to_employee_id' => $emp->id, 'asset_status_before' => 'AVAILABLE',
                            'expected_return_date' => $out->copy()->addDays(5), 'returned_date' => $out->copy()->addDays(mt_rand(2, 6)),
                            'condition_after' => 'GOOD', 'notes' => 'Commissioning PLTS Garut',
                        ]);
                    }
                    // Transfer: approved / pending / rejected; disposal pending.
                    $pilih = $n % 7;
                    if ($pilih === 1) {
                        $d = $this->between($mulai, $today->copy()->subDays(10));
                        $this->movement($a, 'TRANSFER', 'APPROVED', $d, [
                            'from_location_id' => $this->locations['WH-GDB']->id, 'to_location_id' => $a->asset_location_id,
                            'reason' => 'Mobilisasi proyek', 'approved_by' => $this->userId, 'approved_at' => $d->copy()->addDay(),
                        ]);
                    } elseif ($pilih === 2) {
                        $this->movement($a, 'TRANSFER', 'PENDING', $today->copy()->subDays(mt_rand(0, 3)), [
                            'to_location_id' => $this->locations['BR-JKT']->id, 'reason' => 'Realokasi ke cabang Jakarta',
                        ]);
                    } elseif ($pilih === 3) {
                        $d = $today->copy()->subDays(mt_rand(5, 30));
                        $this->movement($a, 'TRANSFER', 'REJECTED', $d, [
                            'to_location_id' => $this->locations['BR-SBY']->id, 'reason' => 'Unit pengganti',
                            'approved_by' => $this->userId, 'approved_at' => $d->copy()->addDay(), 'reject_reason' => 'Unit masih dipakai proyek berjalan.',
                        ]);
                    } elseif ($pilih === 4 && $a->acquisition_date->year <= 2022) {
                        $this->movement($a, 'DISPOSAL', 'PENDING', $today->copy()->subDays(mt_rand(0, 4)), [
                            'reason' => 'Usang, sudah melewati umur ekonomis.', 'disposal_method' => 'SOLD',
                            'disposal_value' => (int) (round($a->cost * 0.08 / 10000) * 10000),
                        ]);
                    }
            }
        }
    }

    private function schedulesAndInspections($assets): void
    {
        $today = now()->startOfDay();
        $live  = $assets->where('status', '!=', 'DISPOSED')->values();

        foreach ($live->whereIn('type', ['Generator', 'UPS', 'Air Conditioner', 'Vehicle', 'Measurement', 'Server'])->values() as $i => $a) {
            [$judul, $tipe, $hari] = match ($a->type) {
                'Generator'       => ['Service 250 jam genset', 'PREVENTIVE', 90],
                'UPS'             => ['Cek baterai UPS', 'INSPECTION', 180],
                'Air Conditioner' => ['Cuci AC', 'PREVENTIVE', 90],
                'Vehicle'         => ['Servis berkala kendaraan', 'PREVENTIVE', 120],
                'Measurement'     => ['Kalibrasi alat ukur', 'CALIBRATION', 365],
                default           => ['Pemeliharaan server', 'PREVENTIVE', 180],
            };
            // Sebar: lewat jatuh tempo, segera, dan masih jauh.
            $offset = [-6, 5, 10, 40, 75][$i % 5];
            AssetMaintenanceSchedule::create([
                'asset_id' => $a->id, 'title' => $judul, 'maintenance_type' => $tipe, 'interval_days' => $hari,
                'next_due_date' => $today->copy()->addDays($offset), 'last_done_date' => $today->copy()->addDays($offset - $hari),
                'vendor' => self::VENDORS[$i % count(self::VENDORS)], 'status' => 'ACTIVE', 'created_by' => $this->userId,
            ]);
        }

        foreach ($live->shuffle()->take(15) as $a) {
            $hasil = ['PASSED', 'PASSED', 'PASSED', 'MINOR_FINDING', 'MAJOR_FINDING'][mt_rand(0, 4)];
            $tgl = $today->copy()->subDays(mt_rand(1, 90));
            AssetInspection::create([
                'number' => AssetNumber::document('asset_inspections', 'INS-AST'),
                'asset_id' => $a->id, 'inspection_date' => $tgl, 'inspector_name' => $this->employees->random()->name,
                'condition' => $a->condition, 'result' => $hasil,
                'findings' => $hasil === 'PASSED' ? self::TAG . ' Tidak ada temuan.' : self::TAG . ' ' . ['Label aset terkelupas', 'Kabel power longgar', 'Casing retak', 'Baterai menggembung'][mt_rand(0, 3)],
                'follow_up' => $hasil === 'PASSED' ? null : 'Jadwalkan perbaikan.',
                'created_by' => $this->userId,
            ]);
            $this->log($a, 'inspection', 'Inspeksi dicatat.', $tgl);
        }
    }

    private function procurement(): void
    {
        $today = now()->startOfDay();
        $cat = fn ($c) => AssetCategory::where('code', $c)->value('id');
        $depts = Department::where('status', 'ACTIVE')->pluck('id');
        $dept = fn () => $depts->isNotEmpty() ? $depts->random() : null;

        $prs = [
            ['Laptop ThinkPad E14 untuk staf baru', 'ITE', 4, 13_500_000, 'PENDING', null],
            ['Kursi kerja ergonomis', 'FUR', 6, 2_750_000, 'PENDING', null],
            ['Power meter optik', 'TLS', 2, 6_800_000, 'APPROVED', null],
            ['Genset portable 5 kVA', 'PWR', 1, 38_000_000, 'REJECTED', 'Pakai genset existing di gudang.'],
            ['Monitor 27" untuk tim Finance', 'ITE', 2, 3_900_000, 'APPROVED', null],
        ];
        foreach ($prs as $i => [$item, $c, $qty, $harga, $status, $alasan]) {
            $pr = AssetPurchaseRequest::create([
                'number' => AssetNumber::document('asset_purchase_requests', 'PR-AST'),
                'item_name' => $item, 'asset_category_id' => $cat($c), 'department_id' => $dept(), 'qty' => $qty,
                'estimated_unit_cost' => $harga, 'needed_date' => $today->copy()->addDays(14 + $i * 7),
                'justification' => self::TAG . ' Kebutuhan operasional & penambahan personel.', 'status' => $status,
                'requested_by' => $this->users[$i % $this->users->count()],
                'approved_by' => $status === 'PENDING' ? null : $this->userId,
                'approved_at' => $status === 'PENDING' ? null : $today->copy()->subDays(3),
                'reject_reason' => $alasan,
            ]);

            // PR monitor diterima penuh -> RECEIVED + 2 aset baru.
            if ($i === 4) {
                $category = AssetCategory::find($pr->asset_category_id);
                $tgl = $today->copy()->subDays(2);
                $gr = AssetReceiving::create([
                    'number' => AssetNumber::document('asset_receivings', 'GR-AST'),
                    'asset_purchase_request_id' => $pr->id, 'item_name' => 'Monitor 27" P2723D',
                    'asset_category_id' => $category->id, 'asset_location_id' => $this->locations['HO-BDG']->id,
                    'supplier' => 'PT Datascrip', 'po_number' => 'PO/TRX/' . $tgl->year . '/0512', 'received_date' => $tgl,
                    'qty' => 2, 'unit_cost' => 3_850_000, 'notes' => self::TAG, 'received_by' => $this->userId,
                ]);
                for ($u = 1; $u <= 2; $u++) {
                    $a = Asset::create([
                        'code' => AssetNumber::assetCode($category->code, $tgl->year), 'name' => $gr->item_name,
                        'asset_category_id' => $category->id, 'type' => 'Monitor', 'brand' => 'Dell', 'model' => 'P2723D',
                        'serial_number' => 'CN0P27' . $u . mt_rand(10000, 99999), 'asset_location_id' => $gr->asset_location_id,
                        'department_id' => $pr->department_id, 'acquisition_date' => $tgl->toDateString(), 'acquisition_type' => 'PURCHASE',
                        'cost' => $gr->unit_cost, 'residual_value' => (int) round($gr->unit_cost * $category->residual_percent / 100),
                        'life_years' => $category->life_years, 'depreciation_method' => $category->depreciation_method,
                        'condition' => 'EXCELLENT', 'status' => 'AVAILABLE', 'supplier' => $gr->supplier, 'po_number' => $gr->po_number,
                        'asset_receiving_id' => $gr->id, 'description' => self::TAG . ' Diterima dari ' . $gr->number . '.',
                        'created_by' => $this->userId, 'updated_by' => $this->userId,
                    ]);
                    $this->log($a, 'create', "Didaftarkan dari penerimaan {$gr->number}.", $tgl);
                }
                $pr->update(['status' => 'RECEIVED']);
            }
        }
    }

    private function opnames(): void
    {
        $today = now()->startOfDay();

        foreach ([['HO-BDG', 'COMPLETED', 20], ['WH-GDB', 'COUNTING', 1]] as [$code, $status, $hariLalu]) {
            $loc = $this->locations[$code];
            $tgl = $today->copy()->subDays($hariLalu);
            $o = AssetOpname::create([
                'number' => AssetNumber::document('asset_opnames', 'SO-AST'), 'asset_location_id' => $loc->id,
                'opname_date' => $tgl, 'status' => $status, 'notes' => self::TAG . ' Opname periodik.',
                'created_by' => $this->userId,
                'completed_by' => $status === 'COMPLETED' ? $this->userId : null,
                'completed_at' => $status === 'COMPLETED' ? $tgl->copy()->addDay() : null,
            ]);
            $assets = Asset::live()->where('asset_location_id', $loc->id)->get(['id', 'condition']);
            foreach ($assets as $k => $a) {
                // Opname berjalan: separuh baris belum diisi.
                if ($status === 'COUNTING' && $k % 2 === 1) {
                    $result = null;
                } else {
                    $result = $k % 9 === 4 ? 'MISSING' : ($k % 7 === 3 ? 'DISCREPANCY' : 'MATCHED');
                }
                AssetOpnameItem::create([
                    'asset_opname_id' => $o->id, 'asset_id' => $a->id, 'result' => $result,
                    'condition' => $result === 'MATCHED' ? $a->condition : null,
                    'notes' => $result === 'MISSING' ? 'Tidak ditemukan di ruangan tercatat.' : ($result === 'DISCREPANCY' ? 'Serial number berbeda dengan data.' : null),
                ]);
            }
        }
    }

    /* ------------------------------------------------------------ helper */

    private function movement(Asset $a, string $kind, string $status, Carbon $date, array $extra = []): AssetMovement
    {
        $m = AssetMovement::create(array_merge([
            'number'           => AssetNumber::document('asset_movements', AssetMovement::PREFIX[$kind], $date->year),
            'kind'             => $kind,
            'asset_id'         => $a->id,
            'status'           => $status,
            'from_location_id' => $a->asset_location_id,
            'movement_date'    => $date->toDateString(),
            'condition_before' => $a->condition,
            'requested_by'     => $this->users[mt_rand(0, $this->users->count() - 1)],
        ], $extra, [
            'notes' => trim(self::TAG . ' ' . ($extra['notes'] ?? '')),
        ]));
        $this->log($a, strtolower($kind), AssetMovement::KINDS[$kind] . " {$m->number} ({$status}).", $date);

        return $m;
    }

    private function maintenance(Asset $a, array $data): AssetMaintenance
    {
        $start = Carbon::parse($data['start_date']);
        $m = AssetMaintenance::create(array_merge([
            'number'     => AssetNumber::document('asset_maintenances', 'MR-AST', $start->year),
            'asset_id'   => $a->id,
            'vendor'     => self::VENDORS[mt_rand(0, count(self::VENDORS) - 1)],
            'created_by' => $this->userId,
        ], $data, ['problem' => self::TAG . ' ' . $data['problem']]));
        $this->log($a, 'maintenance', "Maintenance {$m->number} ({$m->status}).", $start);

        return $m;
    }

    private function log(Asset $a, string $action, string $desc, Carbon $at): void
    {
        AssetLog::create([
            'asset_id' => $a->id, 'user_id' => $this->userId, 'action' => $action,
            'description' => $desc, 'created_at' => $at->copy()->setTime(mt_rand(8, 16), mt_rand(0, 59)),
        ]);
    }

    private function between(Carbon $from, Carbon $to): Carbon
    {
        if ($to->lte($from)) {
            return $from->copy();
        }

        return $from->copy()->addDays(mt_rand(0, (int) $from->diffInDays($to)));
    }
}
