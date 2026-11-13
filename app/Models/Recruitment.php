<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu proses rekrutmen: satu kandidat untuk satu rincian kebutuhan (MP). */
class Recruitment extends Model
{
    public const STAGES = ['SCREENING', 'INTERVIEW HR', 'INTERVIEW USER', 'TRAINING', 'KONTRAK', 'ONBOARD'];

    protected $fillable = ['detail_id', 'candidate_id', 'stage', 'status', 'scheduled', 'decided_by', 'decided_at'];

    protected $casts = ['scheduled' => 'date', 'decided_at' => 'date'];

    public function detail() { return $this->belongsTo(ManpowerRequestDetail::class, 'detail_id'); }
    public function candidate() { return $this->belongsTo(Candidate::class); }

    /** Tahap yang berlaku untuk sebuah posisi: TRAINING hanya posisi teknisi. */
    public static function stagesFor(?string $position): array
    {
        return array_values(array_filter(
            self::STAGES,
            fn ($s) => $s !== 'TRAINING' || preg_match('/teknisi/i', (string) $position)
        ));
    }

    /** Tahap berikutnya (tidak pernah mundur); null bila sudah di ujung. */
    public function nextStage(): ?string
    {
        $arr = self::stagesFor($this->detail->position ?? '');
        $i   = array_search($this->stage, $arr, true);
        if ($i !== false) {
            return $arr[$i + 1] ?? null;
        }
        for ($k = array_search($this->stage, self::STAGES, true) + 1; $k < count(self::STAGES); $k++) {
            if (in_array(self::STAGES[$k], $arr, true)) {
                return self::STAGES[$k];
            }
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === 'ON_PROCESS';
    }
}
