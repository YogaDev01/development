<?php

namespace App\Support;

use App\Exceptions\PeriodLockedException;
use App\Models\AccountingPeriod;
use App\Models\Closing;
use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Kunci periode: Closing berstatus CLOSED memblokir penulisan dokumen finance
 * yang tanggal acuannya jatuh di periode itu. DRAFT dan REOPENED = terbuka.
 *
 * Ini guard KEADAAN DATA (seperti cek status), bukan otorisasi, jadi tidak
 * dipasang sebagai middleware: tanggalnya ada di record. Controller adalah
 * penentu; Blade mencerminkannya lewat forModel()/isLocked().
 *
 * Tanggal acuan per modul sengaja sama dengan yang direkap ClosingService
 * (tanggal diblokir = tanggal direkap). Resolver-nya ada di dateOf().
 *
 * Superadmin asli (bukan "View As") boleh menembus dengan alasan wajib
 * (field `alasan_periode`); penembusan dicatat di UserActivityLog. Bila
 * periode itu juga sudah CLOSED/LOCKED di AccountingPeriod, penembusan
 * ditolak: Accounting harus dibuka dulu supaya jurnal tidak tidak-sinkron.
 */
class PeriodLock
{
    public const REASON_FIELD = 'alasan_periode';

    /** @var Collection<int, Closing>|null Cache per request. */
    private static ?Collection $closed = null;

    /** Kosongkan cache (setelah status Closing berubah, dan di test). */
    public static function flush(): void
    {
        self::$closed = null;
    }

    /** Closing CLOSED yang berlaku (sesuai enforce_from_period), dibaca sekali per request. */
    private static function closedPeriods(): Collection
    {
        if (self::$closed === null) {
            $from = config('closing.enforce_from_period');

            self::$closed = Closing::where('status', 'CLOSED')
                ->when($from, fn ($q) => $q->where('period', '>=', $from))
                ->get(['id', 'code', 'period', 'start_date', 'end_date']);
        }

        return self::$closed;
    }

    /**
     * Closing CLOSED yang memuat tanggal itu. $lock=true membaca ulang dari DB
     * dengan shared lock (wajib di dalam DB::transaction) supaya Closing::close
     * tidak menyelip di antara pemeriksaan dan penulisan.
     */
    public static function closingFor($date, bool $lock = false): ?Closing
    {
        if ($date === null || $date === '') {
            return null;
        }

        $d = Carbon::parse($date)->toDateString();

        if ($lock) {
            $from = config('closing.enforce_from_period');

            // Closing bersifat bulanan: cari lewat `period` (indeks unik) supaya kunci bersama hanya
            // menyentuh satu baris, bukan seluruh tabel.
            return Closing::where('status', 'CLOSED')
                ->where('period', Carbon::parse($d)->format('Y-m'))
                ->when($from, fn ($q) => $q->where('period', '>=', $from))
                ->whereDate('start_date', '<=', $d)
                ->whereDate('end_date', '>=', $d)
                ->sharedLock()
                ->first();
        }

        return self::closedPeriods()->first(
            fn ($c) => $c->start_date->toDateString() <= $d && $c->end_date->toDateString() >= $d
        );
    }

    public static function isLocked($date): bool
    {
        return self::closingFor($date) !== null;
    }

    /** Pesan galat Bahasa Indonesia, atau null bila tanggal itu terbuka. Murni (tanpa efek samping). */
    public static function blocker($date, string $konteks = 'Dokumen', bool $lock = false): ?string
    {
        $c = self::closingFor($date, $lock);

        if (! $c) {
            return null;
        }

        return 'Periode ' . Carbon::createFromFormat('!Y-m', $c->period)->translatedFormat('F Y')
            . ' sudah ditutup (' . $c->code . '). ' . $konteks . ' tidak dapat diubah. '
            . 'Minta Superadmin membuka kembali periode, atau gunakan tanggal pada periode yang masih terbuka.';
    }

    /** Superadmin asli, bukan sedang "View As". */
    public static function canOverride(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user && $user->isRealSuperadmin() && ! $user->isViewingAs();
    }

    /** Pesan bila penembusan tidak boleh karena Accounting sudah menutup tanggal itu; null = boleh. */
    private static function accountingBlocker($date): ?string
    {
        $p = AccountingPeriod::forDate($date);

        if ($p && in_array($p->status, ['CLOSED', 'LOCKED'], true)) {
            return 'Periode akuntansi ' . $p->name . ' berstatus ' . $p->status
                . '. Buka kembali periode akuntansi lebih dulu sebelum mengubah dokumen ini.';
        }

        return null;
    }

    /**
     * Pemeriksaan lengkap untuk satu tanggal. Mengembalikan pesan galat bila
     * penulisan HARUS ditolak, null bila boleh. Superadmin yang mengisi
     * `alasan_periode` lolos dan penembusannya dicatat ($changes = lama→baru).
     * Jalur bulk/otomatis jangan memberi $request: tidak ada bypass.
     */
    public static function guard(
        $date,
        string $konteks = 'Dokumen',
        ?Request $request = null,
        ?Model $subject = null,
        ?array $changes = null,
        bool $lock = false,
    ): ?string {
        $msg = self::blocker($date, $konteks, $lock);

        if ($msg === null) {
            return null;
        }

        if (! $request || ! self::canOverride()) {
            return $msg;
        }

        $raw    = $request->input(self::REASON_FIELD);
        $reason = is_string($raw) ? trim($raw) : '';
        if ($reason === '') {
            return $msg . ' (Superadmin: isi alasan penembusan periode.)';
        }

        if ($acc = self::accountingBlocker($date)) {
            return $acc;
        }

        $c = self::closingFor($date, $lock);
        $log = UserActivityLog::record(
            'closing',
            'period_override',
            'Superadmin menembus periode ' . $c->period . ': ' . $konteks,
            $subject,
            $reason,
            $c->code,
            $changes,
        );

        // Penembusan tanpa jejak tidak boleh lolos (record() mengembalikan null bila gagal menulis).
        if (! $log) {
            return 'Penembusan periode dibatalkan: log penembusan tidak dapat dicatat. Coba lagi.';
        }

        return null;
    }

    /** Versi melempar PeriodLockedException; aman dipanggil di dalam DB::transaction. */
    public static function assertOpen(
        $date,
        string $konteks = 'Dokumen',
        ?Request $request = null,
        ?Model $subject = null,
        ?array $changes = null,
        bool $lock = false,
    ): void {
        if ($msg = self::guard($date, $konteks, $request, $subject, $changes, $lock)) {
            throw new PeriodLockedException($msg);
        }
    }

    /**
     * Bulan (Y-m) dalam rentang yang wajib punya Closing CLOSED tetapi belum.
     * Dipakai menutup AccountingPeriod: Closing operasional ditutup lebih dulu.
     * Bulan di bawah `closing.enforce_from_period` dibebaskan. Dibaca langsung dari DB (tanpa cache).
     *
     * @return list<string>
     */
    public static function unclosedMonths($start, $end): array
    {
        $from = config('closing.enforce_from_period');
        $s    = Carbon::parse($start)->startOfMonth();
        $e    = Carbon::parse($end)->startOfMonth();

        $closed = Closing::where('status', 'CLOSED')
            ->whereBetween('period', [$s->format('Y-m'), $e->format('Y-m')])
            ->pluck('period')->all();

        $out = [];
        for ($m = $s->copy(); $m->lte($e); $m->addMonth()) {
            $p = $m->format('Y-m');
            if (($from && $p < $from) || in_array($p, $closed, true)) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /** Pesan bila ada AccountingPeriod CLOSED/LOCKED yang beririsan dengan rentang itu (mis. saat membuka Closing); null = boleh. */
    public static function accountingBlockerForRange($start, $end): ?string
    {
        $p = AccountingPeriod::whereDate('start_date', '<=', Carbon::parse($end)->toDateString())
            ->whereDate('end_date', '>=', Carbon::parse($start)->toDateString())
            ->whereIn('status', ['CLOSED', 'LOCKED'])
            ->first();

        return $p
            ? 'Periode akuntansi ' . $p->name . ' berstatus ' . $p->status . '. Buka kembali periode akuntansi lebih dulu.'
            : null;
    }

    /**
     * Tanggal acuan sebuah dokumen = tanggal yang direkap ClosingService.
     * Null = tidak punya tanggal acuan (mis. CR yang belum dibayar: tidak diguard).
     */
    public static function dateOf(Model $m): ?string
    {
        $v = match (true) {
            $m instanceof \App\Models\BudgetRequest    => $m->paid_at,
            $m instanceof \App\Models\DailyTransaction => $m->date,
            $m instanceof \App\Models\Invoice          => $m->invoice_date,
            $m instanceof \App\Models\CashReceipt,
            $m instanceof \App\Models\CashReceiptHeader => $m->received_date,
            $m instanceof \App\Models\CashTransfer     => $m->transfer_date,
            $m instanceof \App\Models\CashAdjustment   => $m->effective_date,
            $m instanceof \App\Models\CashFlow         => $m->date,
            default => null,
        };

        return $v ? Carbon::parse($v)->toDateString() : null;
    }

    /**
     * Untuk Blade/workflowActions: status kunci sebuah dokumen.
     *
     * @return array{locked: bool, message: ?string, can_override: bool}
     */
    public static function forModel(Model $m, string $konteks = 'Dokumen'): array
    {
        $date = self::dateOf($m);
        $msg  = $date ? self::blocker($date, $konteks) : null;

        return [
            'locked'       => $msg !== null,
            'message'      => $msg,
            'can_override' => $msg !== null && self::canOverride(),
        ];
    }
}
