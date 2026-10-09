<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\Accessory;
use App\Models\CategoryItem;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccessoryController extends Controller
{
    use ManagesMasterRecords;

    protected function master(): array
    {
        return [
            'model'    => Accessory::class,
            'url'      => '/accessories',
            'title'    => 'Accessories',
            'singular' => 'Accessories',
            'icon'     => 'fas fa-plug',
            'with'     => ['customer:id,name,initial', 'category'],
            'note'     => 'Accessories Code dibuat otomatis: A + Inisial Customer + Bulan Tahun + nomor urut. Customer tidak dapat diubah setelah disimpan.',
            'fields'   => [
                'customer_id'      => ['label' => 'Customer', 'type' => 'select', 'options' => fn () => MasterMaterialController::customerOptions(), 'required' => true, 'create_only' => true, 'searchable' => true],
                'accessories_name' => ['label' => 'Nama Accessories', 'type' => 'text', 'required' => true],
                'category_id'      => ['label' => 'Kategori', 'type' => 'select', 'options' => fn () => MasterMaterialController::categoryOptions(CategoryItem::ACCESSORIES_TYPES), 'required' => true, 'searchable' => true],
                'status'           => ['label' => 'Status', 'type' => 'select', 'options' => Accessory::STATUSES, 'required' => true],
            ],
            'columns' => [
                ['label' => 'Accessories Code', 'value' => fn ($r) => $r->accessories_code, 'strong' => true],
                ['label' => 'Nama', 'value' => fn ($r) => $r->accessories_name],
                ['label' => 'Customer', 'value' => fn ($r) => $r->customer?->name],
                ['label' => 'Kategori', 'value' => fn ($r) => $r->category?->label],
                ['label' => 'Status', 'value' => fn ($r) => Accessory::STATUSES[$r->status] ?? $r->status, 'badge' => true],
            ],
            'filters' => [
                ['key' => 'customer', 'label' => 'Customer', 'value' => fn ($r) => $r->customer?->name],
                ['key' => 'status', 'label' => 'Status', 'value' => fn ($r) => Accessory::STATUSES[$r->status] ?? $r->status],
            ],
            'dependents' => [
                'siteLinks' => 'masih terpasang di Site',
            ],
            'describe' => fn ($r) => $r->accessories_code,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'customer_id'      => ['required', 'integer', MasterMaterialController::customerWithInitialRule()],
            'accessories_name' => ['required', 'string', 'max:150'],
            'category_id'      => ['required', 'integer', Rule::exists('category_item', 'id')->whereIn('type', CategoryItem::ACCESSORIES_TYPES)],
            'status'           => ['required', Rule::in(array_keys(Accessory::STATUSES))],
        ];
    }

    protected function beforeCreate(Model $record, array $data): void
    {
        $record->accessories_code = Accessory::generateCode(Customer::findOrFail($data['customer_id']));
    }
}
