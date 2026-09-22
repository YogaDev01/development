<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerAttachment;
use App\Models\Project;
use App\Models\BudgetPlan;
use App\Models\MasterPic;

class CustomerController extends Controller
{
    /**
     * Aturan validasi untuk detail (kontak) & lampiran, dipakai store & update.
     */
    private function childRules(): array
    {
        return [
            'pics'                 => 'nullable|array',
            'pics.*'               => 'nullable|exists:master_pics,id',

            'attachments'          => 'nullable|array',
            'attachments.*.title'  => 'nullable|string|max:255',
            'attachments.*.file'   => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ];
    }

    /**
     * Sinkron PIC customer (pivot ke Master PIC).
     */
    private function syncPics(Customer $customer, Request $request): void
    {
        $ids = array_values(array_unique(array_filter((array) $request->input('pics', []))));
        $customer->pics()->sync($ids);
    }

    /**
     * Simpan lampiran yang baru diupload.
     */
    private function storeAttachments(Customer $customer, Request $request): void
    {
        foreach ((array) $request->input('attachments', []) as $i => $row) {

            $file = $request->file("attachments.$i.file");

            if (! $file) {
                continue;
            }

            $path = $file->store('customer_attachments', 'secure');

            $customer->attachments()->create([
                'title'     => $row['title'] ?? null,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'input_by'  => Auth::id(),
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::with('customerType')->latest()->get();

        $types = CustomerType::all();

        $summary = [
            'total'    => $customers->count(),
            'active'   => $customers->where('status', 'ACTIVE')->count(),
            'inactive' => $customers->where('status', 'INACTIVE')->count(),
        ];

        return view('customer.index', compact(
            'customers',
            'types',
            'summary'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $types = CustomerType::all();
        $picsCustomer = MasterPic::where('type', 'customer')->where('status', 'ACTIVE')->orderBy('pic')->get();

        return view('customer.create', compact('types', 'picsCustomer'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate(array_merge([
            'name'             => 'required',
            'customer_type_id' => 'required',
            'npwp'             => 'nullable|string|max:255',
            'initial'          => 'nullable|string|size:3|alpha|unique:customers,initial',
        ], $this->childRules()));

        DB::transaction(function () use ($request) {

            $customer = Customer::create([
                'name'             => strtoupper($request->name),
                'initial'          => $request->initial ? strtoupper($request->initial) : null,
                'address'          => $request->address,
                'npwp'             => $request->npwp,
                'customer_type_id' => $request->customer_type_id,
                'input_by'         => Auth::id(),
                'status'           => 'ACTIVE',
            ]);

            $this->syncPics($customer, $request);
            $this->storeAttachments($customer, $request);
        });

        return redirect('/customer')
            ->with('success', 'Customer ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    /**
     * MN-01 — Detail Customer sebagai HALAMAN, bukan modal.
     *
     * Modal lama memuat seluruh isinya lewat satu endpoint JSON dan merangkai
     * tabelnya di JavaScript. Akibatnya: tidak bisa di-bookmark, tidak bisa
     * dibuka di tab baru, dan daftar PO yang panjang tidak punya pencarian
     * maupun paging sama sekali.
     *
     * Versi halaman ini merender datanya dari server dan memakai DataTable
     * bertingkat: Customer -> PO -> (No. PO, Invoice, Budget Plan). Seluruh
     * informasi yang dulu ada di modal tetap ada, ditambah yang sebelumnya
     * tidak muat di sana.
     */
    public function show($id)
    {
        $customer = Customer::with([
            'customerType',
            'pics',
            'attachments',
        ])->findOrFail($id);

        /*
        | Semua relasi anak PO di-eager-load sekali di sini. Tanpa ini, tabel
        | bertingkat akan memicu query per baris saat dirender (N+1) — persis
        | jenis masalah yang gampang lolos karena halamannya tetap "jalan".
        */
        $projects = Project::with([
            'manager:id,username,name',
            'poNumbers',
            'budgetPlans',
            'invoices.cashReceipts',
        ])
        ->where('customer_id', $customer->id)
        ->latest()
        ->get();

        // Ringkasan dihitung dari himpunan project yang SAMA dengan yang
        // ditampilkan, bukan dari query terpisah.
        $invoices = $projects->flatMap->invoices;

        $summary = [
            'po_qty'         => $projects->count(),
            'contract_value' => (int) $projects->sum('contract'),
            'po_value'       => (int) $projects->sum('value'),
            'po_number_qty'  => $projects->sum(fn ($p) => $p->poNumbers->count()),
            'invoice_qty'    => $invoices->count(),
            'invoice_value'  => (int) $invoices->sum('captured_total'),
            'paid_value'     => (int) $invoices->sum(fn ($inv) =>
                (int) $inv->cashReceipts->sum('amount') + (int) $inv->cashReceipts->sum('withholding_tax')),
        ];

        $summary['outstanding'] = max(0, $summary['invoice_value'] - $summary['paid_value']);

        $budgetPlans = BudgetPlan::with(['project:id,code,name', 'attachments'])
            ->whereHas('project', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest()
            ->get();

        return view('customer.show', compact(
            'customer',
            'projects',
            'budgetPlans',
            'summary'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $customer = Customer::with(['pics', 'attachments'])->findOrFail($id);

        $types = CustomerType::all();
        $picsCustomer = MasterPic::where('type', 'customer')->where('status', 'ACTIVE')->orderBy('pic')->get();

        return view('customer.edit', compact(
            'customer',
            'types',
            'picsCustomer'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate(array_merge([
            'name'                => 'required',
            'customer_type_id'    => 'required',
            'npwp'                => 'nullable|string|max:255',
            'initial'             => 'nullable|string|size:3|alpha|unique:customers,initial,' . $id,
            'status'              => 'required|in:ACTIVE,INACTIVE',
            'delete_attachments'  => 'nullable|array',
        ], $this->childRules()));

        $customer = Customer::findOrFail($id);

        DB::transaction(function () use ($request, $customer) {

            $customer->update([
                'name'             => strtoupper($request->name),
                'initial'          => $request->initial ? strtoupper($request->initial) : null,
                'address'          => $request->address,
                'npwp'             => $request->npwp,
                'customer_type_id' => $request->customer_type_id,
                'status'           => $request->status,
            ]);

            // KONTAK (PIC): sync pivot Master PIC.
            $this->syncPics($customer, $request);

            // LAMPIRAN: hapus yang dicentang untuk dihapus.
            $deleteIds = (array) $request->input('delete_attachments', []);

            if (! empty($deleteIds)) {

                $toDelete = CustomerAttachment::where('customer_id', $customer->id)
                    ->whereIn('id', $deleteIds)
                    ->get();

                foreach ($toDelete as $att) {
                    Storage::disk('secure')->delete($att->file_path);
                    $att->delete();
                }
            }

            // LAMPIRAN: tambah yang baru diupload.
            $this->storeAttachments($customer, $request);
        });

        return redirect('/customer')
            ->with('success', 'Customer berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * AJAX — auto-suggest initial 3 huruf dari nama customer yang sedang diketik.
     */
    public function suggestInitial(Request $request)
    {
        $name = (string) $request->query('name', '');
        if (blank($name)) {
            return response()->json(['initial' => '']);
        }

        return response()->json(['initial' => Customer::suggestInitial($name)]);
    }

    public function destroy($id)
    {
        $customer = Customer::with('attachments')->findOrFail($id);

        if (\App\Models\Project::where('customer_id', $customer->id)->exists()) {
            return back()->with('error', 'Customer tidak bisa dihapus karena sudah memiliki Purchase Order/Project.');
        }

        // Bersihkan file lampiran dari storage sebelum hapus record.
        foreach ($customer->attachments as $att) {
            Storage::disk('secure')->delete($att->file_path);
        }

        $customer->delete();

        return redirect('/customer')
            ->with('success', 'Customer berhasil dihapus');
    }
    
    public function showJson($id)
    {
        try {
            $customer = Customer::with([
                'customerType',
                'pics',
                'attachments',
            ])->findOrFail($id);

            /*
            |--------------------------------------------------------------------------
            | PROJECT
            |
            | Relasi access-group belum tentu terpasang di model Project.
            | Kita bangun daftar eager-load secara defensif: hanya menambahkan
            | 'accessGroups.*' bila relasi tersebut memang ada, supaya query
            | tidak melempar RelationNotFoundException saat customer punya project.
            |--------------------------------------------------------------------------
            */

            $hasAccessGroups = method_exists(Project::class, 'accessGroups');

            $with = ['budgetPlans'];

            if ($hasAccessGroups) {
                $with[] = 'accessGroups.users.role';
                $with[] = 'accessGroups.users.accessGroup';
            }

            $projects = Project::with($with)
                ->where('customer_id', $customer->id)
                ->latest()
                ->get();

            /*
            |--------------------------------------------------------------------------
            | USERS
            |
            | Hanya dihitung bila relasi accessGroups tersedia. Bila tidak,
            | dikembalikan koleksi kosong (TOTAL USER = 0) tanpa error.
            |--------------------------------------------------------------------------
            */

            $users = collect();

            if ($hasAccessGroups) {
                $users = $projects
                    ->flatMap(fn ($project) => $project->accessGroups ?? collect())
                    ->flatMap(fn ($group) => $group->users ?? collect())
                    ->unique('id')
                    ->values();
            }

            /*
            |--------------------------------------------------------------------------
            | SUMMARY
            |--------------------------------------------------------------------------
            */

            $totalPo       = $projects->count();
            $totalPoAmount = $projects->sum('value');

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'id'            => $customer->id,
                'name'          => $customer->name,
                'initial'       => $customer->initial,
                'address'       => $customer->address,
                'npwp'          => $customer->npwp,
                'status'        => $customer->status,
                'customer_type' => optional($customer->customerType)->name,
                'created_at'    => optional($customer->created_at)->format('d M Y') ?? '-',

                'total_po'        => $totalPo,
                'total_po_amount' => $totalPoAmount,

                /* PIC */
                'pics' => $customer->pics->map(fn ($p) => [
                    'pic'      => $p->pic,
                    'position' => $p->position,
                    'phone'    => $p->phone,
                    'email'    => $p->email,
                ]),

                /* ATTACHMENT */
                'attachments' => $customer->attachments->map(fn ($attachment) => [
                    'title'     => $attachment->title,
                    'file_name' => $attachment->file_name,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                ]),

                /* USER */
                'users' => $users->map(fn ($user) => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'role'         => optional($user->role)->name,
                    'access_group' => optional($user->accessGroup)->name,
                ]),

                /* PROJECT */
                'projects' => $projects->map(fn ($project) => [
                    'id'     => $project->id,
                    'code'   => $project->code,
                    'name'   => $project->name,
                    'po'     => $project->po_number,
                    'value'  => $project->value,
                    'status' => $project->status,
                ]),

            ]);

        } catch (\Throwable $e) {

            \Illuminate\Support\Facades\Log::error(
                'Customer showJson gagal untuk id ' . $id . ': ' . $e->getMessage(),
                ['exception' => $e]
            );

            return response()->json([
                'message' => 'Gagal memuat data customer: ' . $e->getMessage(),
            ], 500);
        }
    }
}