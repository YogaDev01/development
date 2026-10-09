<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ImportsMasterRecords;
use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\CategoryItem;
use App\Models\Customer;
use App\Models\MasterMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterMaterialController extends Controller
{
    use ManagesMasterRecords;
    use ImportsMasterRecords;

    protected function importConfig(): array
    {
        $customers  = Customer::whereNotNull('initial')->pluck('id', 'initial')
            ->mapWithKeys(fn ($id, $ini) => [strtoupper($ini) => $id]);
        $categories = CategoryItem::whereIn('type', CategoryItem::MATERIAL_TYPES)->get()
            ->mapWithKeys(fn ($c) => [mb_strtolower($c->category_name . '|' . $c->merk) => $c->id]);

        return [
            'headers' => [
                'Inisial Customer' => 'BTS',
                'Nama Kategori'    => 'Modem',
                'Merk'             => 'iDirect',
                'Serial Number'    => 'SN123456',
                'Status'           => 'Warehouse',
            ],
            'map' => function (array $c) use ($customers, $categories) {
                $errors = [];

                $initial    = strtoupper((string) $c['Inisial Customer']);
                $customerId = $customers[$initial] ?? null;
                if ($initial !== '' && ! $customerId) {
                    $errors['customer_id'] = "Customer dengan inisial '{$initial}' tidak ditemukan.";
                }

                $catKey     = mb_strtolower($c['Nama Kategori'] . '|' . $c['Merk']);
                $categoryId = $categories[$catKey] ?? null;
                if (($c['Nama Kategori'] ?? '') !== '' && ! $categoryId) {
                    $errors['category_id'] = "Kategori '{$c['Nama Kategori']}' merk '{$c['Merk']}' tidak ditemukan (tipe Material/Tools).";
                }

                return [[
                    'customer_id'   => $customerId,
                    'category_id'   => $categoryId,
                    'serial_number' => $c['Serial Number'] === null ? null : (string) $c['Serial Number'],
                    'status'        => self::matchOption(MasterMaterial::STATUSES, $c['Status']),
                ], $errors];
            },
            'reference' => fn () => [
                'Inisial Customer' => Customer::whereNotNull('initial')->where('initial', '!=', '')->orderBy('initial')
                    ->get(['initial', 'name'])->map(fn ($x) => $x->initial . ' — ' . $x->name)->all(),
                'Nama Kategori | Merk' => CategoryItem::whereIn('type', CategoryItem::MATERIAL_TYPES)->orderBy('category_name')
                    ->get()->map(fn ($x) => $x->category_name . ' | ' . $x->merk)->all(),
                'Status' => array_values(MasterMaterial::STATUSES),
            ],
        ];
    }

    protected function master(): array
    {
        return [
            'model'    => MasterMaterial::class,
            'url'      => '/master-material',
            'title'    => 'Master Material',
            'singular' => 'Material',
            'icon'     => 'fas fa-microchip',
            'with'     => ['customer:id,name,initial', 'category'],
            'import'   => true,
            'note'     => 'Product Code dibuat otomatis: P + Inisial Customer + Bulan Tahun + nomor urut. Customer tidak dapat diubah setelah disimpan.',
            'fields'   => [
                'customer_id'   => ['label' => 'Customer', 'type' => 'select', 'options' => fn () => self::customerOptions(), 'required' => true, 'create_only' => true, 'searchable' => true],
                'category_id'   => ['label' => 'Kategori', 'type' => 'select', 'options' => fn () => self::categoryOptions(CategoryItem::MATERIAL_TYPES), 'required' => true, 'searchable' => true],
                'serial_number' => ['label' => 'Serial Number', 'type' => 'text', 'required' => true],
                'status'        => ['label' => 'Status', 'type' => 'select', 'options' => MasterMaterial::STATUSES, 'required' => true],
            ],
            'columns' => [
                ['label' => 'Product Code', 'value' => fn ($r) => $r->product_code, 'strong' => true],
                ['label' => 'Customer', 'value' => fn ($r) => $r->customer?->name],
                ['label' => 'Kategori', 'value' => fn ($r) => $r->category?->label],
                ['label' => 'Serial Number', 'value' => fn ($r) => $r->serial_number],
                ['label' => 'Status', 'value' => fn ($r) => MasterMaterial::STATUSES[$r->status] ?? $r->status, 'badge' => true],
            ],
            'filters' => [
                ['key' => 'customer', 'label' => 'Customer', 'value' => fn ($r) => $r->customer?->name],
                ['key' => 'status', 'label' => 'Status', 'value' => fn ($r) => MasterMaterial::STATUSES[$r->status] ?? $r->status],
            ],
            'dependents' => [
                'siteLinks' => 'masih terpasang di Site',
            ],
            'describe' => fn ($r) => $r->product_code,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'customer_id'   => ['required', 'integer', self::customerWithInitialRule()],
            'category_id'   => ['required', 'integer', Rule::exists('category_item', 'id')->whereIn('type', CategoryItem::MATERIAL_TYPES)],
            'serial_number' => ['required', 'string', 'max:150'],
            'status'        => ['required', Rule::in(array_keys(MasterMaterial::STATUSES))],
        ];
    }

    protected function beforeCreate(Model $record, array $data): void
    {
        $record->product_code = MasterMaterial::generateCode(Customer::findOrFail($data['customer_id']));
    }

    public static function customerOptions(): array
    {
        return Customer::whereNotNull('initial')->where('initial', '!=', '')
            ->orderBy('name')->get(['id', 'name', 'initial'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->name . ' (' . $c->initial . ')'])
            ->all();
    }

    public static function categoryOptions(array $types): array
    {
        return CategoryItem::whereIn('type', $types)->orderBy('category_name')->orderBy('merk')->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->label])
            ->all();
    }

    // Kode item memakai inisial customer, jadi customer tanpa inisial ditolak.
    public static function customerWithInitialRule()
    {
        return Rule::exists('customers', 'id')->whereNotNull('initial')->where('initial', '!=', '');
    }
}
