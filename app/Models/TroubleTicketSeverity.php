<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Severity Trouble Ticket. Tidak ada UI: diisi langsung di basis data (tabel
 * trouble_ticket_severities). Tiket menyimpan `code`-nya (trouble_tickets.severity,
 * FK ON UPDATE CASCADE). `level` 1 = paling parah — menentukan urutan & warna.
 */
class TroubleTicketSeverity extends Model
{
    protected $table = 'trouble_ticket_severities';

    protected $fillable = ['code', 'name', 'level', 'aging_from', 'aging_to', 'status'];

    protected $casts = [
        'level'      => 'integer',
        'aging_from' => 'integer',
        'aging_to'   => 'integer',
    ];

    /** Cache per-request: severity_label dibaca sekali per baris daftar. */
    private static ?array $tangga = null;

    /**
     * Tangga severity menurut umur tiket, urut dari yang paling tua.
     *
     * Dibaca dari master bila ADA severity aktif yang mengisi `aging_from`;
     * selama belum ada, dipakai tangga bawaan di kode
     * (TroubleTicket::SEVERITY_AGING) supaya perilaku lama tidak berubah.
     * Baris aktif tanpa `aging_from` diabaikan — ia hanya label kebijakan SLA.
     *
     * @return array<int, array{0:int, 1:string, 2:int}>  [batas bawah, nama, level]
     */
    public static function agingSteps(): array
    {
        if (self::$tangga !== null) {
            return self::$tangga;
        }

        $rows = static::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('aging_from')
            ->orderByDesc('aging_from')
            ->get(['name', 'level', 'aging_from']);

        return self::$tangga = $rows->isEmpty()
            ? TroubleTicket::SEVERITY_AGING
            : $rows->map(fn ($r) => [(int) $r->aging_from, $r->name, (int) $r->level])->all();
    }

    /** Dipakai tes & sesudah master diubah dalam satu request. */
    public static function lupakanTangga(): void
    {
        self::$tangga = null;
    }

    /** Teks rentang untuk layar: "3–7 hari" / "≥8 hari" / "—". */
    public function getAgingRangeLabelAttribute(): string
    {
        if ($this->aging_from === null) {
            return '—';
        }

        return $this->aging_to === null
            ? '≥'.$this->aging_from.' hari'
            : $this->aging_from.'–'.$this->aging_to.' hari';
    }

    /** Severity aktif, paling parah dulu. */
    public static function active()
    {
        return static::where('status', 'ACTIVE')->orderBy('level')->orderBy('name')->get();
    }
}
