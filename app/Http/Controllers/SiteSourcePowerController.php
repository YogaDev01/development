<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\SiteSourcePower;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Master Source Power — pilihan dropdown di form Site. */
class SiteSourcePowerController extends Controller
{
    use ManagesMasterRecords;

    protected function master(): array
    {
        return [
            'model'    => SiteSourcePower::class,
            'url'      => '/site-source-power',
            'title'    => 'Source Power',
            'singular' => 'Source Power',
            'icon'     => 'fas fa-bolt',
            'withCount' => ['sites'],
            'fields'   => [
                'name' => ['label' => 'Nama', 'type' => 'text', 'required' => true],
            ],
            'columns' => [
                ['label' => 'Nama', 'value' => fn ($r) => $r->name, 'strong' => true],
                ['label' => 'Kode', 'value' => fn ($r) => $r->code],
                ['label' => 'Jumlah Site', 'value' => fn ($r) => $r->sites_count],
            ],
            'dependents' => [
                'sites' => 'masih dipakai Site',
            ],
            'describe' => fn ($r) => $r->name,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('site_source_powers', 'name')->ignore($record?->id)],
        ];
    }

    /** Kode dibuat sekali dari nama; tidak ikut berubah saat nama diedit agar site lama tetap terhubung. */
    protected function beforeCreate(Model $record, array $data): void
    {
        $record->code = self::uniqueCode($data['name']);
    }

    private static function uniqueCode(string $name): string
    {
        $base = substr(strtoupper(Str::slug($name, '_')), 0, 26) ?: 'OPSI';
        $code = $base;

        for ($i = 2; SiteSourcePower::where('code', $code)->exists(); $i++) {
            $code = $base . '_' . $i;
        }

        return $code;
    }
}
