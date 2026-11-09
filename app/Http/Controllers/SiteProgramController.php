<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\SiteProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Master Program Site — pilihan dropdown di form Site. */
class SiteProgramController extends Controller
{
    use ManagesMasterRecords;

    protected function master(): array
    {
        return [
            'model'    => SiteProgram::class,
            'url'      => '/site-program',
            'title'    => 'Program Site',
            'singular' => 'Program Site',
            'icon'     => 'fas fa-clipboard-list',
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
            'name' => ['required', 'string', 'max:100', Rule::unique('site_programs', 'name')->ignore($record?->id)],
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

        for ($i = 2; SiteProgram::where('code', $code)->exists(); $i++) {
            $code = $base . '_' . $i;
        }

        return $code;
    }
}
