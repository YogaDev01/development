<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\Network;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class NetworkController extends Controller
{
    use ManagesMasterRecords;

    protected function master(): array
    {
        return [
            'model'    => Network::class,
            'url'      => '/network',
            'title'    => 'Network',
            'singular' => 'Network',
            'icon'     => 'fas fa-satellite-dish',
            'fields'   => [
                'penyedia_lc' => ['label' => 'Penyedia LC', 'type' => 'text'],
                'hub'         => ['label' => 'Hub', 'type' => 'text'],
                'satelit'     => ['label' => 'Satelit', 'type' => 'text'],
                'gateway'     => ['label' => 'Gateway', 'type' => 'text'],
                'spotbeam'    => ['label' => 'Spotbeam', 'type' => 'text'],
            ],
            'columns' => [
                ['label' => 'Penyedia LC', 'value' => fn ($r) => $r->penyedia_lc, 'strong' => true],
                ['label' => 'Hub', 'value' => fn ($r) => $r->hub],
                ['label' => 'Satelit', 'value' => fn ($r) => $r->satelit],
                ['label' => 'Gateway', 'value' => fn ($r) => $r->gateway],
                ['label' => 'Spotbeam', 'value' => fn ($r) => $r->spotbeam],
            ],
            'filters' => [
                ['key' => 'penyedia', 'label' => 'Penyedia LC', 'value' => fn ($r) => $r->penyedia_lc],
                ['key' => 'satelit', 'label' => 'Satelit', 'value' => fn ($r) => $r->satelit],
            ],
            'dependents' => [
                'sites' => 'masih dipakai Site',
            ],
            'describe' => fn ($r) => $r->label,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        $rule = ['nullable', 'string', 'max:150'];

        return [
            // Minimal satu kolom terisi supaya tidak ada record kosong.
            'penyedia_lc' => array_merge($rule, ['required_without_all:hub,satelit,gateway,spotbeam']),
            'hub'         => $rule,
            'satelit'     => $rule,
            'gateway'     => $rule,
            'spotbeam'    => $rule,
        ];
    }
}
