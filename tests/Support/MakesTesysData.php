<?php

namespace Tests\Support;

use App\Models\BudgetPlan;
use App\Models\AccessGroup;
use App\Models\AccessGroupDetail;
use App\Models\Beneficiary;
use App\Models\BudgetRequest;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectPoNumber;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\User;

/**
 * Pembuat data untuk uji.
 *
 * Aplikasi ini belum punya factory selain User, dan membuat Cost Request yang
 * sah butuh rantai Customer -> Project -> BudgetPlan -> Cluster -> RequestType.
 * Merangkainya ulang di setiap berkas uji akan membuat uji lebih panjang dari
 * yang diuji, jadi rantainya dikumpulkan di sini.
 *
 * Nilai yang tidak relevan bagi uji sengaja diisi seadanya; yang penting hanya
 * kolom yang ikut menentukan aturan bisnis (status, amount, relasi).
 */
trait MakesTesysData
{
    protected function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name], ['status' => 'ACTIVE']);
    }

    protected function userWithRole(string $roleName, array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name'     => ucfirst($roleName) . ' ' . $n,
            'username' => $roleName . $n,
            'email'    => $roleName . $n . '@example.test',
            'password' => bcrypt('rahasia123'),
            'role_id'  => $this->role($roleName)->id,
            'status'   => 'ACTIVE',
        ], $attributes));
    }

    /**
     * Beri user akses ke proyek tertentu lewat Access Group.
     *
     * Dipakai uji yang melibatkan role bertingkat (Head/Manager): di aplikasi
     * ini keduanya dibatasi Access Group, jadi tanpa ini mereka memang tidak
     * melihat CR apa pun - dan uji akan menguji keadaan yang tidak wajar.
     */
    protected function giveProjectAccess(User $user, Project $project): User
    {
        $group = AccessGroup::create(['name' => 'AG Uji ' . $user->id]);

        AccessGroupDetail::create([
            'access_group_id' => $group->id,
            'project_id'      => $project->id,
        ]);

        $user->update(['access_group_id' => $group->id]);

        return $user->refresh();
    }

    protected function makeCustomer(): Customer
    {
        static $n = 0;
        $n++;

        return Customer::create([
            'name' => 'PT Uji ' . $n,
            // Kolom initial unik di basis data; dibuat berbeda tiap pemanggilan.
            'initial' => 'U' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'status' => 'ACTIVE',
        ]);
    }

    protected function makeProject(array $attributes = []): Project
    {
        static $n = 0;
        $n++;

        $manager = $attributes['manager_id'] ?? $this->userWithRole('manager')->id;

        return Project::create(array_merge([
            'code'        => str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'name'        => 'Proyek Uji ' . $n,
            'customer_id' => $this->makeCustomer()->id,
            'manager_id'  => $manager,
            'contract'    => 1_000_000_000,
            'value'       => 1_000_000_000,
            'status'      => 'ACTIVE',
            'input_by'    => $manager,
        ], $attributes));
    }

    protected function makePoNumber(Project $project, array $attributes = []): ProjectPoNumber
    {
        static $n = 0;
        $n++;

        return ProjectPoNumber::create(array_merge([
            'project_id' => $project->id,
            'po_number'  => 'PO-UJI-' . $n,
            'nominal'    => 500_000_000,
            'status'     => 'RELEASED',
        ], $attributes));
    }

    protected function makeBudgetPlan(?Project $project = null, array $attributes = []): BudgetPlan
    {
        static $n = 0;
        $n++;

        $project = $project ?: $this->makeProject();

        return BudgetPlan::create(array_merge([
            'code'        => 'BP-UJI-' . $n,
            'project_id'  => $project->id,
            'description' => 'Budget Uji ' . $n,
            'amount'      => 10_000_000,
            // enum budget_plans: DRAFT/SUBMITTED/CHECKED/VERIFIED/ACTIVE/...
            // 'ACTIVE' = budget yang sudah boleh dipakai Cost Request.
            'status'      => 'ACTIVE',
            'input_by'    => $project->manager_id,
        ], $attributes));
    }

    protected function makeCluster(): Cluster
    {
        static $n = 0;
        $n++;

        return Cluster::create([
            'name'   => 'Cluster Uji ' . $n,
            'code'   => str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'status' => 'ACTIVE',
        ]);
    }

    protected function makeRequestType(string $name = 'Cash Advance'): RequestType
    {
        // Tabel request_type tidak punya kolom timestamps.
        $existing = RequestType::where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $type = new RequestType(['name' => $name]);
        $type->timestamps = false;
        $type->save();

        return $type;
    }

    protected function makeBeneficiary(): Beneficiary
    {
        static $n = 0;
        $n++;

        return Beneficiary::create([
            'nickname'      => 'Penerima ' . $n,
            'complete_name' => 'Penerima Uji ' . $n,
            'status'        => 'ACTIVE',
        ]);
    }

    protected function makeCostRequest(array $attributes = []): BudgetRequest
    {
        static $n = 0;
        $n++;

        $plan = $attributes['budget_plan'] ?? $this->makeBudgetPlan();
        unset($attributes['budget_plan']);

        return BudgetRequest::create(array_merge([
            'code'            => 'CR-UJI-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'budget_plan_id'  => $plan->id,
            'cluster_id'      => $this->makeCluster()->id,
            'type_id'         => $this->makeRequestType()->id,
            'beneficiary_id'  => $this->makeBeneficiary()->id,
            'applicant_id'    => $plan->input_by,
            'description'     => 'Pengajuan uji ' . $n,
            'amount'          => 1_000_000,
            'status'          => 'DRAFT',
        ], $attributes));
    }

    protected function makeInvoice(ProjectPoNumber $poNumber, array $attributes = []): Invoice
    {
        static $n = 0;
        $n++;

        return Invoice::create(array_merge([
            'code'                 => 'INV-UJI-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'po_id'                => $poNumber->project_id,
            'project_po_number_id' => $poNumber->id,
            'payment_term_type'    => 'Full Payment',
            'invoice_date'         => now()->toDateString(),
            'due_date'             => now()->addDays(30)->toDateString(),
            'tax_rate'             => 11,
            'subtotal'             => 100_000_000,
            'tax_amount'           => 11_000_000,
            'captured_total'       => 111_000_000,
            'status'               => 'SENT',
        ], $attributes));
    }
}
