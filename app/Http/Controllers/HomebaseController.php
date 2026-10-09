<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\Homebase;
use App\Models\HomebaseDetail;
use App\Models\MasterPic;
use App\Models\Region;
use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomebaseController extends Controller
{
    use ManagesMasterRecords;

    protected function master(): array
    {
        return [
            'model'    => Homebase::class,
            'url'      => '/homebase',
            'title'    => 'Homebase',
            'singular' => 'Homebase',
            'icon'     => 'fas fa-warehouse',
            'with'     => ['region'],
            'withCount' => ['details'],
            'show'     => true,
            'fields'   => [
                'region_id' => ['label' => 'Region', 'type' => 'select', 'options' => fn () => Region::orderBy('region_name')->pluck('region_name', 'id')->all(), 'required' => true, 'searchable' => true],
                'address'   => ['label' => 'Alamat', 'type' => 'textarea', 'required' => true],
                'latitude'  => ['label' => 'Latitude', 'type' => 'decimal', 'col' => 6, 'placeholder' => '-6.2000000'],
                'longitude' => ['label' => 'Longitude', 'type' => 'decimal', 'col' => 6, 'placeholder' => '106.8166660'],
            ],
            'columns' => [
                ['label' => 'Alamat', 'value' => fn ($r) => $r->address, 'strong' => true],
                ['label' => 'Region', 'value' => fn ($r) => $r->region?->region_name],
                ['label' => 'Koordinat', 'value' => fn ($r) => $r->latitude !== null ? $r->latitude . ', ' . $r->longitude : '-'],
                ['label' => 'Jumlah PIC', 'value' => fn ($r) => $r->details_count],
            ],
            'filters' => [
                ['key' => 'region', 'label' => 'Region', 'value' => fn ($r) => $r->region?->region_name],
            ],
            'dependents' => [
                'details' => 'masih memiliki PIC (hapus PIC di halaman detail terlebih dahulu)',
            ],
            'describe' => fn ($r) => \Illuminate\Support\Str::limit($r->address, 60),
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'region_id' => ['required', 'integer', 'exists:region,id'],
            'address'   => ['required', 'string', 'max:1000'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    public function show($id)
    {
        $homebase = Homebase::with(['region.pulau', 'details.pic'])->findOrFail($id);

        $availablePics = MasterPic::where('status', 'ACTIVE')
            ->whereNotIn('id', $homebase->details->pluck('pic_id'))
            ->orderBy('pic')
            ->get(['id', 'pic', 'type', 'position']);

        return view('homebase.show', compact('homebase', 'availablePics'));
    }

    public function addPic(Request $request, $id)
    {
        $homebase = Homebase::findOrFail($id);

        $data = $request->validate([
            'pic_id' => [
                'required', 'integer',
                Rule::exists('master_pics', 'id')->where('status', 'ACTIVE'),
                Rule::unique('homebase_detail', 'pic_id')->where('hb_id', $homebase->id),
            ],
        ], [
            'pic_id.unique' => 'PIC ini sudah terdaftar di homebase tersebut.',
        ], ['pic_id' => 'PIC']);

        $detail = HomebaseDetail::create(['hb_id' => $homebase->id, 'pic_id' => $data['pic_id']]);

        UserActivityLog::record('master_data', 'create', 'Menambah PIC ke Homebase #' . $homebase->id, $detail);

        return redirect('/homebase/' . $homebase->id)->with('success', 'PIC berhasil ditambahkan');
    }

    public function removePic($id, $detailId)
    {
        $detail = HomebaseDetail::where('hb_id', $id)->findOrFail($detailId);
        $detail->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas PIC #' . $detail->pic_id . ' dari Homebase #' . $id);

        return redirect('/homebase/' . $id)->with('success', 'PIC berhasil dilepas dari homebase');
    }
}
