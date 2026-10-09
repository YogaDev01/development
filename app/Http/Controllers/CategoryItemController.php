<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\CategoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryItemController extends Controller
{
    use ManagesMasterRecords;

    protected function master(): array
    {
        return [
            'model'    => CategoryItem::class,
            'url'      => '/category-item',
            'title'    => 'Category Item',
            'singular' => 'Category Item',
            'icon'     => 'fas fa-tags',
            'fields'   => [
                'type'          => ['label' => 'Type', 'type' => 'select', 'options' => CategoryItem::TYPES, 'required' => true],
                'category_name' => ['label' => 'Nama Kategori', 'type' => 'text', 'required' => true],
                'merk'          => ['label' => 'Merk', 'type' => 'text', 'required' => true],
            ],
            'columns' => [
                ['label' => 'Type', 'value' => fn ($r) => CategoryItem::TYPES[$r->type] ?? $r->type, 'badge' => true],
                ['label' => 'Nama Kategori', 'value' => fn ($r) => $r->category_name, 'strong' => true],
                ['label' => 'Merk', 'value' => fn ($r) => $r->merk],
            ],
            'filters' => [
                ['key' => 'type', 'label' => 'Type', 'value' => fn ($r) => CategoryItem::TYPES[$r->type] ?? $r->type],
            ],
            'dependents' => [
                'materials'   => 'sudah dipakai Master Material',
                'accessories' => 'sudah dipakai Accessories',
            ],
            'describe' => fn ($r) => $r->label,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'type'          => ['required', Rule::in(array_keys(CategoryItem::TYPES))],
            'category_name' => [
                'required', 'string', 'max:150',
                Rule::unique('category_item')
                    ->where(fn ($q) => $q->where('type', $request->input('type'))->where('merk', $request->input('merk')))
                    ->ignore($record?->id),
            ],
            'merk' => ['required', 'string', 'max:150'],
        ];
    }
}
