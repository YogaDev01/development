<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Models\Site;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketRca;
use App\Models\TroubleTicketSubRca;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Buat Trouble Ticket massal dari Excel: template, pratinjau, lalu konfirmasi.
 *
 * Pratinjau tidak menyimpan apa pun. Saat konfirmasi berkas yang sama dikirim
 * lagi dan divalidasi ulang, jadi isi yang dibuat selalu hasil pemeriksaan
 * terbaru. Tiap baris dibuat lewat TroubleTicketWorkflow::create() (jalur yang
 * sama dengan form), dalam transaksinya sendiri: baris yang gagal tidak
 * membatalkan baris lain.
 */
trait ImportsTroubleTickets
{
    private const IMPORT_MAX_ROWS = 500;

    /** Batas baris yang dibaca dari sheet (header + data + baris kosong di sela). */
    private const IMPORT_READ_ROWS = 2000;

    private const IMPORT_HEADERS = ['Customer', 'Site ID', 'RCA', 'Sub-RCA', 'No. Tiket Custom', 'Date Open', 'Keterangan', 'Teknisi'];

    /** Site ACTIVE yang boleh dipakai user (customer team-nya bila ia terikat team). */
    private function importSites(): Collection
    {
        $customerIds = $this->teamCustomerIds(Auth::user());

        return Site::with('customer:id,name')
            ->where('status', 'ACTIVE')
            ->when($customerIds->isNotEmpty(), fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->orderBy('tid')
            ->get(['id', 'tid', 'sitename', 'customer_id']);
    }

    public function importTemplate()
    {
        $this->abortIfCannotCreate();

        $sites = $this->importSites();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        foreach (self::IMPORT_HEADERS as $i => $label) {
            $sheet->setCellValue([$i + 1, 1], $label);
            $sheet->getStyle([$i + 1, 1])->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($label === 'Keterangan' ? 48 : 22);
        }
        $sheet->freezePane('A2');

        $ref = $spreadsheet->createSheet();
        $ref->setTitle('Referensi');

        $daftar = [
            'A' => ['Customer', $sites->pluck('customer.name')->filter()->unique()->sort()->values()],
            'B' => ['Site ID', $sites->pluck('tid')->values()],
            'C' => ['RCA', TroubleTicketRca::where('status', 'ACTIVE')->orderBy('name')->pluck('name')],
            'D' => ['Sub-RCA', TroubleTicketSubRca::where('status', 'ACTIVE')->orderBy('name')->pluck('name')->unique()->values()],
            'H' => ['Teknisi (username)', $this->importTechnicianOptions($sites)],
        ];

        foreach ($daftar as $kolom => [$judul, $nilai]) {
            $ref->setCellValue($kolom . '1', $judul);
            $ref->getStyle($kolom . '1')->getFont()->setBold(true);
            $ref->getColumnDimension($kolom)->setWidth(28);

            foreach ($nilai->values() as $i => $v) {
                $ref->setCellValueExplicit($kolom . ($i + 2), (string) $v, DataType::TYPE_STRING);
            }

            if ($nilai->isEmpty()) {
                continue;
            }

            // Dropdown dari sheet Referensi; nilainya tetap diperiksa server.
            $dv = new DataValidation();
            $dv->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Tidak ada di daftar')
                ->setError('Pilih nilai dari daftar.')
                ->setFormula1('Referensi!$' . $kolom . '$2:$' . $kolom . '$' . ($nilai->count() + 1));
            $sheet->setDataValidation($kolom . '2:' . $kolom . (self::IMPORT_MAX_ROWS + 1), $dv);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), 'template-import-trouble-ticket.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Username teknisi yang eligible di site mana pun yang boleh dipakai user:
     * Teknisi site (sites.technician_user_id) atau akun PIC internal di site_pics.
     */
    private function importTechnicianOptions(Collection $sites): Collection
    {
        return User::query()
            ->where('status', 'ACTIVE')
            ->where(fn ($q) => $q
                ->whereIn('id', \App\Models\SitePic::query()
                    ->join('master_pics', 'master_pics.id', '=', 'site_pics.pic_id')
                    ->whereIn('site_pics.site_id', $sites->pluck('id'))
                    ->where('master_pics.type', 'internal')
                    ->where('master_pics.status', 'ACTIVE')
                    ->whereNotNull('master_pics.user_id')
                    ->select('master_pics.user_id'))
                ->when(\App\Models\Site::supportsTechnicianUser(), fn ($q) => $q->orWhereIn('id', \App\Models\Site::query()
                    ->whereIn('id', $sites->pluck('id'))
                    ->whereNotNull('technician_user_id')
                    ->select('technician_user_id'))))
            ->orderBy('username')
            ->pluck('username');
    }

    /** Pratinjau: tidak menyimpan apa pun. */
    public function importPreview(Request $request)
    {
        $this->abortIfCannotCreate();

        $hasil = $this->bacaImport($request);

        if (isset($hasil['error'])) {
            return response()->json(['message' => $hasil['error']], 422);
        }

        return response()->json([
            'token' => (string) Str::uuid(),
            'rows'  => array_map(fn ($r) => array_diff_key($r, ['data' => 1]), $hasil['rows']),
        ]);
    }

    /**
     * Konfirmasi: validasi ulang berkas lalu buat tiket per baris. Token sekali
     * pakai mencegah klik ganda membuat tiket dua kali.
     */
    public function importCommit(Request $request)
    {
        $this->abortIfCannotCreate();

        $data = $request->validate(['token' => 'required|uuid', 'include_warnings' => 'nullable|boolean']);

        $hasil = $this->bacaImport($request);

        if (isset($hasil['error'])) {
            return response()->json(['message' => $hasil['error']], 422);
        }

        if (! Cache::add('tt-import:' . Auth::id() . ':' . $data['token'], 1, now()->addHour())) {
            return response()->json(['message' => 'Import ini sudah diproses. Muat ulang halaman untuk melihat hasilnya.'], 409);
        }

        // Baris dibuat satu per satu beserta notifikasinya; bisa melebihi batas bawaan.
        set_time_limit(300);

        $sertakanPeringatan = (bool) ($data['include_warnings'] ?? false);
        $dibuat = [];
        $gagal  = [];

        foreach ($hasil['rows'] as $r) {
            if ($r['status'] === 'gagal') {
                $gagal[] = ['line' => $r['line'], 'message' => implode(' ', $r['messages'])];
                continue;
            }

            if ($r['status'] === 'peringatan' && ! $sertakanPeringatan) {
                $gagal[] = ['line' => $r['line'], 'message' => 'Dilewati: ' . implode(' ', $r['messages'])];
                continue;
            }

            try {
                $t = $this->workflow->create(Auth::user(), $r['data']);
                $dibuat[] = ['id' => $t->id, 'ticket_number' => $t->ticket_number];
            } catch (ValidationException $e) {
                $gagal[] = ['line' => $r['line'], 'message' => implode(' ', array_merge(...array_values($e->errors())))];
            } catch (\Throwable $e) {
                // Satu baris bermasalah tidak boleh menghilangkan ringkasan baris lain.
                report($e);
                $gagal[] = ['line' => $r['line'], 'message' => 'Gagal disimpan karena kesalahan sistem.'];
            }
        }

        UserActivityLog::record('trouble_ticket', 'create', 'Import ' . count($dibuat) . ' Trouble Ticket dari Excel, ' . count($gagal) . ' baris tidak dibuat');

        session()->flash('tt_baru', array_column($dibuat, 'id'));

        return response()->json(['created' => $dibuat, 'failed' => $gagal]);
    }

    /**
     * Membaca & memeriksa berkas. Status baris: ok, peringatan (mungkin duplikat,
     * pengguna yang memutuskan), atau gagal.
     *
     * @return array{error?: string, rows?: array<int, array<string, mixed>>}
     */
    private function bacaImport(Request $request): array
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120'], [], ['file' => 'File Excel']);

        /*
        | Hanya sheet Data, kolom template, dan baris dalam batas baca yang
        | dimuat: workbook besar atau sangat terkompresi tidak boleh menghabiskan
        | memori shared hosting sebelum batas IMPORT_MAX_ROWS diperiksa. Batas
        | baca lebih longgar dari batas data supaya baris kosong di sela tidak
        | membuat baris berisi di bawahnya terlewat diam-diam.
        */
        try {
            $path   = $request->file('file')->getRealPath();
            $reader = IOFactory::createReaderForFile($path);
            $info   = collect($reader->listWorksheetInfo($path));
            $target = $info->firstWhere('worksheetName', 'Data') ?? $info->first();

            if (! $target) {
                return ['error' => 'File tidak dapat dibaca. Gunakan template yang disediakan.'];
            }
            if ((int) $target['totalRows'] > self::IMPORT_READ_ROWS) {
                return ['error' => 'Sheet Data terlalu panjang (' . number_format((int) $target['totalRows'], 0, ',', '.') . ' baris). Maksimal ' . self::IMPORT_MAX_ROWS . ' baris data per file.'];
            }

            $kolomAkhir = chr(ord('A') + count(self::IMPORT_HEADERS) - 1);
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$target['worksheetName']]);
            $reader->setReadFilter(new class(self::IMPORT_READ_ROWS, $kolomAkhir) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                public function __construct(private int $baris, private string $kolom) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->baris && strlen($columnAddress) === 1 && $columnAddress <= $this->kolom;
                }
            });

            $book = $reader->load($path);
            $rows = ($book->getSheetByName($target['worksheetName']) ?? $book->getSheet(0))->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'File tidak dapat dibaca. Gunakan template yang disediakan.'];
        }

        $head = array_map(fn ($h) => trim((string) $h), array_slice($rows[0] ?? [], 0, count(self::IMPORT_HEADERS)));
        if ($head !== self::IMPORT_HEADERS) {
            return ['error' => 'Kolom file tidak sesuai template. Unduh ulang template terbaru.'];
        }

        $baris = [];
        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $row = array_pad(array_slice($row, 0, count(self::IMPORT_HEADERS)), count(self::IMPORT_HEADERS), null);
            if (collect($row)->every(fn ($v) => blank($v))) {
                continue;
            }
            $baris[$i + 1] = array_combine(self::IMPORT_HEADERS, array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row));
        }

        if (! $baris) {
            return ['error' => 'Tidak ada baris data. Isi mulai baris ke-2 sheet Data.'];
        }
        if (count($baris) > self::IMPORT_MAX_ROWS) {
            return ['error' => 'Maksimal ' . self::IMPORT_MAX_ROWS . ' baris per file.'];
        }

        // Master dimuat sekali, bukan per baris.
        $sites     = $this->importSites()->keyBy(fn ($s) => mb_strtolower($s->tid));
        $customers = Customer::whereIn('id', $sites->pluck('customer_id')->unique())->get(['id', 'name'])
            ->keyBy(fn ($c) => mb_strtolower($c->name));
        $rcas      = TroubleTicketRca::where('status', 'ACTIVE')->get(['id', 'name'])->keyBy(fn ($r) => mb_strtolower($r->name));
        $subRcas   = TroubleTicketSubRca::where('status', 'ACTIVE')->get(['id', 'rca_id', 'name'])
            ->keyBy(fn ($s) => $s->rca_id . '|' . mb_strtolower($s->name));
        $nomorAda  = TroubleTicket::whereIn('ticket_number', collect($baris)->pluck('No. Tiket Custom')->filter()->map(fn ($v) => (string) $v))
            ->pluck('ticket_number')->map(fn ($v) => mb_strtolower($v))->flip();
        $userNames = collect($baris)->pluck('Teknisi')->filter()->map(fn ($v) => mb_strtolower((string) $v))->unique();
        $users     = User::where('status', 'ACTIVE')->whereIn('username', $userNames)->get(['id', 'username'])
            ->keyBy(fn ($u) => mb_strtolower($u->username));

        $tiketTerbuka = TroubleTicket::whereIn('site_id', $sites->pluck('id'))
            ->where('status', '!=', 'CLOSED')
            ->get(['site_id', 'rca_id', 'reported_at', 'ticket_number'])
            ->keyBy(fn ($t) => $t->site_id . '|' . $t->rca_id . '|' . $t->reported_at?->toDateString());

        $eligible = [];
        $nomorDiFile = [];
        $kunciDiFile = [];
        $hasil = [];

        foreach ($baris as $line => $c) {
            $salah = [];
            $ingat = [];

            $customer = $customers->get(mb_strtolower((string) $c['Customer']));
            if (! $customer) {
                $salah[] = blank($c['Customer']) ? 'Customer wajib diisi.' : "Customer \"{$c['Customer']}\" tidak ditemukan atau di luar team Anda.";
            }

            $site = $sites->get(mb_strtolower((string) $c['Site ID']));
            if (! $site) {
                $salah[] = blank($c['Site ID']) ? 'Site ID wajib diisi.' : "Site \"{$c['Site ID']}\" tidak ditemukan, tidak aktif, atau di luar team Anda.";
            } elseif ($customer && (int) $site->customer_id !== (int) $customer->id) {
                $salah[] = "Site {$site->tid} bukan milik customer {$customer->name}.";
            }

            $rca = null;
            if (filled($c['RCA'])) {
                $rca = $rcas->get(mb_strtolower((string) $c['RCA']));
                if (! $rca) {
                    $salah[] = "RCA \"{$c['RCA']}\" tidak ditemukan.";
                }
            }

            $subRca = null;
            if (filled($c['Sub-RCA'])) {
                if (! $rca) {
                    $salah[] = 'Sub-RCA diisi tetapi RCA kosong atau tidak valid.';
                } else {
                    $subRca = $subRcas->get($rca->id . '|' . mb_strtolower((string) $c['Sub-RCA']));
                    if (! $subRca) {
                        $salah[] = "Sub-RCA \"{$c['Sub-RCA']}\" bukan bagian dari RCA {$rca->name}.";
                    }
                }
            }

            $nomor = filled($c['No. Tiket Custom']) ? trim((string) $c['No. Tiket Custom']) : null;
            if ($nomor !== null) {
                $kunciNomor = mb_strtolower($nomor);
                if (mb_strlen($nomor) > 50) {
                    $salah[] = 'No. tiket custom maksimal 50 karakter.';
                } elseif ($nomorAda->has($kunciNomor)) {
                    $salah[] = "No. tiket {$nomor} sudah dipakai tiket lain.";
                } elseif (isset($nomorDiFile[$kunciNomor])) {
                    $salah[] = "No. tiket {$nomor} sama dengan baris {$nomorDiFile[$kunciNomor]}.";
                }
                $nomorDiFile[$kunciNomor] ??= $line;
            }

            [$tanggal, $salahTanggal] = $this->bacaTanggalImport($c['Date Open']);
            if ($salahTanggal) {
                $salah[] = $salahTanggal;
            }

            $keterangan = trim((string) $c['Keterangan']);
            if ($keterangan === '') {
                $salah[] = 'Keterangan wajib diisi.';
            } elseif (mb_strlen($keterangan) > 10000) {
                $salah[] = 'Keterangan maksimal 10.000 karakter.';
            }

            $teknisi = $users->get(mb_strtolower((string) $c['Teknisi']));
            if (! $teknisi) {
                $salah[] = blank($c['Teknisi']) ? 'Teknisi wajib diisi.' : "Teknisi \"{$c['Teknisi']}\" tidak ditemukan atau tidak aktif.";
            } elseif ($site) {
                $eligible[$site->id] ??= $this->workflow->eligibleTechnicians($site->id)->pluck('id')->all();
                if (! in_array((int) $teknisi->id, array_map('intval', $eligible[$site->id]), true)) {
                    $salah[] = "{$teknisi->username} bukan teknisi yang bisa ditugaskan di site {$site->tid}.";
                }
            }

            // Mungkin duplikat: site + RCA + tanggal sama. Pengguna yang memutuskan.
            if ($site) {
                $hari  = ($tanggal ?? now())->toDateString();
                $kunci = $site->id . '|' . ($rca?->id) . '|' . $hari;

                if ($sama = $tiketTerbuka->get($kunci)) {
                    $ingat[] = "Mungkin duplikat tiket terbuka {$sama->ticket_number} (site, RCA, dan tanggal sama).";
                }
                if (isset($kunciDiFile[$kunci])) {
                    $ingat[] = "Mungkin duplikat baris {$kunciDiFile[$kunci]} (site, RCA, dan tanggal sama).";
                }
                $kunciDiFile[$kunci] ??= $line;
            }

            $hasil[] = [
                'line'     => $line,
                'status'   => $salah ? 'gagal' : ($ingat ? 'peringatan' : 'ok'),
                'messages' => $salah ?: $ingat,
                'site'     => $site?->tid ?? (string) $c['Site ID'],
                'teknisi'  => (string) $c['Teknisi'],
                'keterangan' => Str::limit($keterangan, 80),
                'data'     => $salah ? [] : [
                    'site_id' => $site->id, 'assigned_to' => $teknisi->id, 'description' => $keterangan,
                    'rca_id' => $rca?->id, 'sub_rca_id' => $subRca?->id, 'ticket_number' => $nomor,
                    'reported_at' => $tanggal,
                ],
            ];
        }

        return ['rows' => $hasil];
    }

    /**
     * Date Open: sel tanggal Excel atau teks. Kosong = waktu pembuatan.
     *
     * @return array{0: ?Carbon, 1: ?string}  [tanggal, pesan salah]
     */
    private function bacaTanggalImport($nilai): array
    {
        if (blank($nilai)) {
            return [null, null];
        }

        $tanggal = null;

        if (is_numeric($nilai)) {
            $tanggal = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $nilai, config('app.timezone')));
        } else {
            foreach (['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
                try {
                    $coba = Carbon::createFromFormat('!' . $format, (string) $nilai);
                } catch (\Throwable) {
                    continue;
                }
                if ($coba && $coba->format($format) === (string) $nilai) {
                    $tanggal = $coba;
                    break;
                }
            }
        }

        if ($tanggal === null) {
            return [null, "Date Open \"{$nilai}\" tidak terbaca. Pakai format dd/mm/yyyy hh:mm."];
        }

        if ($tanggal->isFuture()) {
            return [null, 'Date Open tidak boleh di masa depan.'];
        }

        return [$tanggal, null];
    }
}
