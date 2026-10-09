<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\Kota;
use App\Models\Provinsi;
use App\Models\Pulau;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Master wilayah berjenjang. Satu controller untuk lima jenjang; jenjang aktif
 * dibaca dari segmen URL /wilayah/{level} (rutenya didaftarkan per jenjang).
 */
class WilayahController extends Controller
{
    use ManagesMasterRecords;

    public const LEVELS = [
        'pulau'     => ['model' => Pulau::class,     'label' => 'Pulau',          'parent' => null,        'child' => 'provinsi'],
        'provinsi'  => ['model' => Provinsi::class,  'label' => 'Provinsi',       'parent' => 'pulau',     'child' => 'kota'],
        'kota'      => ['model' => Kota::class,      'label' => 'Kota/Kabupaten', 'parent' => 'provinsi',  'child' => 'kecamatan'],
        'kecamatan' => ['model' => Kecamatan::class, 'label' => 'Kecamatan',      'parent' => 'kota',      'child' => 'desa'],
        'desa'      => ['model' => Desa::class,      'label' => 'Desa/Kelurahan', 'parent' => 'kecamatan', 'child' => null],
    ];

    private function level(): string
    {
        $level = request()->segment(2);
        abort_unless(isset(self::LEVELS[$level]), 404);

        return $level;
    }

    protected function master(): array
    {
        $level  = $this->level();
        $def    = self::LEVELS[$level];
        $parent = $def['parent'];

        $fields  = [];
        $columns = [];
        $filters = [];

        if ($parent) {
            $parentDef = self::LEVELS[$parent];
            $fields[$parent . '_id'] = [
                'label'      => $parentDef['label'],
                'type'       => 'select',
                'options'    => fn () => $parentDef['model']::orderBy('name')->pluck('name', 'id')->all(),
                'required'   => true,
                'searchable' => true,
            ];
            $columns[] = ['label' => $parentDef['label'], 'value' => fn ($r) => $r->{$parent}?->name];
            $filters[] = ['key' => 'parent', 'label' => $parentDef['label'], 'value' => fn ($r) => $r->{$parent}?->name];
        }

        $fields['name'] = ['label' => 'Nama ' . $def['label'], 'type' => 'text', 'required' => true];
        array_unshift($columns, ['label' => 'Nama ' . $def['label'], 'value' => fn ($r) => $r->name, 'strong' => true]);

        $dependents = ['sites' => 'masih dipakai Site'];
        if ($def['child']) {
            $dependents = [$def['child'] => 'masih memiliki data ' . self::LEVELS[$def['child']]['label']] + $dependents;
        }
        if ($level === 'pulau') {
            $dependents['regions'] = 'masih dipakai Region';
        }

        return [
            'model'      => $def['model'],
            'url'        => '/wilayah/' . $level,
            'title'      => 'Wilayah — ' . $def['label'],
            'singular'   => $def['label'],
            'icon'       => 'fas fa-map-marked-alt',
            'with'       => $parent ? [$parent] : [],
            'tabs'       => collect(self::LEVELS)->map(fn ($d, $k) => ['label' => $d['label'], 'url' => '/wilayah/' . $k, 'active' => $k === $level])->all(),
            'fields'     => $fields,
            'columns'    => $columns,
            'filters'    => $filters,
            'dependents' => $dependents,
            'describe'   => fn ($r) => $r->name,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        $level  = $this->level();
        $parent = self::LEVELS[$level]['parent'];

        $unique = Rule::unique($level, 'name')->ignore($record?->id);
        $rules  = [];

        if ($parent) {
            $rules[$parent . '_id'] = ['required', 'integer', 'exists:' . $parent . ',id'];
            $unique->where($parent . '_id', $request->input($parent . '_id'));
        }

        // Desa & kelurahan resmi bisa bernama sama dalam satu kecamatan.
        $rules['name'] = array_merge(['required', 'string', 'max:150'], $level === 'desa' ? [] : [$unique]);

        return $rules;
    }

    /** Opsi dropdown berjenjang (AJAX): GET /wilayah/{level}/options?parent_id= */
    public function options(Request $request)
    {
        $level  = $this->level();
        $parent = self::LEVELS[$level]['parent'];

        $query = self::LEVELS[$level]['model']::query()->orderBy('name');

        if ($parent) {
            $parentId = (int) $request->query('parent_id');
            if ($parentId <= 0) {
                return response()->json([]);
            }
            $query->where($parent . '_id', $parentId);
        }

        return response()->json($query->get(['id', 'name']));
    }
}
