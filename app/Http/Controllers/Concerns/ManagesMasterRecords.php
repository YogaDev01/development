<?php

namespace App\Http\Controllers\Concerns;

use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD bersama untuk master data sederhana (daftar + modal tambah/edit).
 *
 * Controller pemakai cukup mendefinisikan master() (model, field, kolom,
 * filter, cek dependensi) dan rules(). Tampilan memakai
 * resources/views/master-data/index.blade.php.
 */
trait ManagesMasterRecords
{
    use CapsLargeLists;

    abstract protected function master(): array;

    abstract protected function rules(Request $request, ?Model $record): array;

    /** Kait sebelum record baru disimpan (mis. mengisi kode otomatis). */
    protected function beforeCreate(Model $record, array $data): void
    {
    }

    public function index()
    {
        $m = $this->master();

        [$rows, $truncated, $total] = $this->cappedRows(
            $m['model']::query()->with($m['with'] ?? [])->withCount($m['withCount'] ?? [])
        );

        return view('master-data.index', [
            'm'         => $m,
            'rows'      => $rows,
            'truncated' => $truncated,
            'total'     => $total,
        ]);
    }

    public function store(Request $request)
    {
        $m    = $this->master();
        $data = $this->validatedData($request, null);

        try {
            $record = DB::transaction(function () use ($m, $data) {
                $record = new $m['model']($data);
                $this->beforeCreate($record, $data);
                $record->save();

                return $record;
            });
        } catch (QueryException $e) {
            report($e);

            return back()->withInput()->with('error', 'Data gagal disimpan. Periksa kembali isian (kemungkinan duplikat).');
        }

        UserActivityLog::record('master_data', 'create', 'Menambah ' . $m['singular'] . ' ' . $this->describe($record), $record);

        return redirect($m['url'])->with('success', $m['singular'] . ' berhasil ditambahkan');
    }

    public function editData($id)
    {
        $m      = $this->master();
        $record = $m['model']::findOrFail($id);

        return response()->json($record->only(array_keys($m['fields'])));
    }

    public function update(Request $request, $id)
    {
        $m      = $this->master();
        $record = $m['model']::findOrFail($id);
        $data   = $this->validatedData($request, $record);

        try {
            $record->update($data);
        } catch (QueryException $e) {
            report($e);

            return back()->withInput()->with('error', 'Data gagal diperbarui. Periksa kembali isian (kemungkinan duplikat).');
        }

        UserActivityLog::record('master_data', 'update', 'Mengubah ' . $m['singular'] . ' ' . $this->describe($record), $record);

        return redirect($m['url'])->with('success', $m['singular'] . ' berhasil diperbarui');
    }

    public function destroy($id)
    {
        $m      = $this->master();
        $record = $m['model']::findOrFail($id);

        foreach ($m['dependents'] ?? [] as $relation => $message) {
            if ($record->{$relation}()->exists()) {
                return back()->with('error', $m['singular'] . ' tidak bisa dihapus karena ' . $message . '.');
            }
        }

        $label = $this->describe($record);

        try {
            $record->delete();
        } catch (QueryException $e) {
            // Jaring pengaman FK RESTRICT bila ada referensi yang belum dicek di atas.
            return back()->with('error', $m['singular'] . ' tidak bisa dihapus karena masih dipakai data lain.');
        }

        UserActivityLog::record('master_data', 'delete', 'Menghapus ' . $m['singular'] . ' ' . $label);

        return redirect($m['url'])->with('success', $m['singular'] . ' berhasil dihapus');
    }

    protected function validatedData(Request $request, ?Model $record): array
    {
        $fields = $this->formFields($record);

        $rules = array_intersect_key($this->rules($request, $record), $fields);

        $labels = array_map(fn ($f) => $f['label'], $fields);

        return $request->validate($rules, [], $labels);
    }

    /** Field yang berlaku: create_only hanya saat tambah, edit_only hanya saat edit. */
    protected function formFields(?Model $record): array
    {
        return array_filter(
            $this->master()['fields'],
            fn ($f) => $record ? empty($f['create_only']) : empty($f['edit_only'])
        );
    }

    protected function describe(Model $record): string
    {
        $describe = $this->master()['describe'] ?? null;

        return $describe ? (string) $describe($record) : '#' . $record->getKey();
    }
}
