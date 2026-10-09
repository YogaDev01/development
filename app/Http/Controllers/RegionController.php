<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\Pulau;
use App\Models\Region;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegionController extends Controller
{
    use ManagesMasterRecords;

    protected function master(): array
    {
        return [
            'model'    => Region::class,
            'url'      => '/region',
            'title'    => 'Region',
            'singular' => 'Region',
            'icon'     => 'fas fa-globe-asia',
            'with'     => ['pulau'],
            'fields'   => [
                'region_name' => ['label' => 'Nama Region', 'type' => 'text', 'required' => true],
                'pulau_id'    => ['label' => 'Pulau', 'type' => 'select', 'options' => fn () => Pulau::orderBy('name')->pluck('name', 'id')->all(), 'required' => true],
            ],
            'columns' => [
                ['label' => 'Nama Region', 'value' => fn ($r) => $r->region_name, 'strong' => true],
                ['label' => 'Pulau', 'value' => fn ($r) => $r->pulau?->name],
            ],
            'filters' => [
                ['key' => 'pulau', 'label' => 'Pulau', 'value' => fn ($r) => $r->pulau?->name],
            ],
            'dependents' => [
                'homebases' => 'masih dipakai Homebase',
            ],
            'describe' => fn ($r) => $r->region_name,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'region_name' => ['required', 'string', 'max:150', Rule::unique('region', 'region_name')->ignore($record?->id)],
            'pulau_id'    => ['required', 'integer', 'exists:pulau,id'],
        ];
    }
}
