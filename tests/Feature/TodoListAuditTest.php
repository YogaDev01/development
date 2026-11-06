<?php

namespace Tests\Feature;

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\TodoListController;
use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Perbaikan Todo List hasil audit (Bagian A): pemisahan query berat dari
 * Dashboard, batas bulk, dan validasi Assignment.
 */
class TodoListAuditTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function userTodo(string $role)
    {
        $user = $this->userWithRole($role);
        $this->giveMenuAccess($user->role, 'todo-list');

        return $user;
    }

    /* ------------------------------------------------------------ A1 */

    public function test_actionqueues_tidak_menjalankan_query_respontime(): void
    {
        $user = $this->userTodo('head');
        $this->actingAs($user);

        DB::enableQueryLog();
        $hasil = app(TodoListController::class)->actionQueues($user);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");

        $this->assertFalse($hasil['gagal']);
        $this->assertStringNotContainsString('LAG(', $sql, 'Respon time tidak boleh dihitung untuk Dashboard.');
    }

    public function test_halaman_todo_list_menyimpan_respontime_di_cache(): void
    {
        $user = $this->userTodo('head');
        Cache::forget('todo-list.respontime.v1');

        $this->actingAs($user)->get(route('todo-list.index'))->assertOk();

        $this->assertTrue(Cache::has('todo-list.respontime.v1'));
    }

    /* ------------------------------------------------------------ A2 */

    public function test_bulk_lebih_dari_50_id_ditolak(): void
    {
        $user = $this->userTodo('head');

        // 'settle-check' ada di BULK_ROUTES dan digerbangi role:superadmin,head.
        $this->actingAs($user)
            ->from(route('todo-list.index'))
            ->post(route('todo-list.bulk', ['settle-check', 'approve']), ['ids' => range(1, 51)])
            ->assertSessionHasErrors('ids');
    }

    public function test_bulk_tanpa_id_ditolak(): void
    {
        $user = $this->userTodo('head');

        $this->actingAs($user)
            ->post(route('todo-list.bulk', ['settle-check', 'approve']), [])
            ->assertSessionHasErrors('ids');
    }

    public function test_bulk_role_tanpa_hak_mendapat_403(): void
    {
        // collection punya menu todo-list (lolos menu:todo-list) tetapi bukan
        // superadmin/head. Urutan di bulk(): validasi ids dulu, BARU gerbang role
        // route tujuan (role:superadmin,head milik head-check-settlement). ids [1]
        // valid sehingga lolos validasi lalu ditolak gerbang role dengan 403.
        $user = $this->userTodo('collection');

        $this->actingAs($user)
            ->post(route('todo-list.bulk', ['settle-check', 'approve']), ['ids' => [1]])
            ->assertForbidden();
    }

    public function test_bulk_item_tak_ada_dilaporkan_gagal_tanpa_menggagalkan_request(): void
    {
        $user = $this->userTodo('head');

        $this->actingAs($user)
            ->post(route('todo-list.bulk', ['settle-check', 'approve']), ['ids' => [999991, 999992]])
            ->assertRedirect(route('todo-list.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');
    }

    /* ------------------------------------------------------------ A3 */

    public function test_label_kpi_menyebut_cost_request(): void
    {
        $this->actingAs($this->userTodo('head'))
            ->get(route('todo-list.index'))
            ->assertOk()
            ->assertSee('Tindakan Selesai Hari Ini')
            ->assertDontSee('Completed Today');
    }

    /* ------------------------------------------------------------ A4 */

    private function payload(int $assigneeId, array $extra = []): array
    {
        return array_merge([
            'title' => 'Tugas uji', 'assignee_id' => $assigneeId, 'priority' => 'MEDIUM',
        ], $extra);
    }

    public function test_due_date_lampau_ditolak_saat_membuat_tugas(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');

        $this->actingAs($pemberi)
            ->post('/assignment', $this->payload($penerima->id, ['due_date' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('due_date');

        $this->assertSame(0, Assignment::count());
    }

    public function test_tugas_untuk_diri_sendiri_ditolak(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)->post('/assignment', $this->payload($user->id))
            ->assertSessionHas('error');

        $this->assertSame(0, Assignment::count());
    }

    public function test_update_boleh_mempertahankan_due_date_lama_tapi_tidak_mengganti_ke_masa_lalu(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $lama = now()->subDays(5)->toDateString();
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING', 'due_date' => $lama,
        ]);

        $this->actingAs($pemberi)
            ->put(route('assignment.update', $a), $this->payload($penerima->id, ['due_date' => $lama, 'title' => 'Baru']))
            ->assertSessionHas('success');
        $this->assertSame('Baru', $a->fresh()->title);

        $this->actingAs($pemberi)
            ->put(route('assignment.update', $a), $this->payload($penerima->id, ['due_date' => now()->subDays(2)->toDateString()]))
            ->assertSessionHas('error');
        $this->assertSame($lama, $a->fresh()->due_date->toDateString());
    }

    public function test_daftar_todo_list_membatasi_tugas_selesai_30_hari_tetapi_halaman_assignment_semua(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $buat = fn (string $judul, string $status, $selesai) => Assignment::create([
            'title' => $judul, 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'LOW', 'status' => $status, 'done_at' => $selesai,
        ]);
        $buat('terbuka', 'PENDING', null);
        $buat('baru-selesai', 'CLOSED', now()->subDays(3));
        $buat('lama-selesai', 'CLOSED', now()->subDays(60));

        $ctl = app(AssignmentController::class);

        [$ringkas] = $ctl->listsFor($penerima);
        $this->assertEqualsCanonicalizing(['terbuka', 'baru-selesai'], $ringkas->pluck('title')->all());

        [$semua] = $ctl->listsFor($penerima, semua: true);
        $this->assertCount(3, $semua);
    }

    public function test_prefetch_tidak_menandai_tugas_dibaca(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING',
        ]);

        $this->actingAs($penerima)
            ->withHeaders(['Sec-Purpose' => 'prefetch'])
            ->get(route('assignment.logs', $a))->assertOk();
        $this->assertSame('PENDING', $a->fresh()->status);

        // withHeaders() bersifat menempel pada instance test; buang sebelum
        // request "nyata", kalau tidak request kedua ikut dianggap prefetch.
        $this->withoutHeader('Sec-Purpose')
            ->actingAs($penerima)->get(route('assignment.logs', $a))->assertOk();
        $this->assertSame('READ', $a->fresh()->status);
    }

    /* ================================================== BAGIAN B */

    private function keyAntrean($user): array
    {
        $this->actingAs($user);
        $hasil = app(TodoListController::class)->actionQueues($user);
        $this->assertFalse($hasil['gagal']);

        return collect($hasil['queues'])->pluck('key')->all();
    }

    /* ---------------------------------------------- B1: badge = halaman */

    public function test_has_pending_konsisten_dengan_antrean_halaman(): void
    {
        $plan = $this->makeBudgetPlan();
        $this->makeCostRequest(['status' => 'SUBMITTED', 'budget_plan' => $plan]);

        $head = $this->giveProjectAccess($this->userWithRole('head'), $plan->project);
        $lain = $this->userWithRole('head'); // tanpa Access Group -> tidak melihat CR itu

        $this->assertContains('head', $this->keyAntrean($head));
        $this->assertTrue(\App\Services\TodoService::hasPending($head));
        $this->assertNotContains('head', $this->keyAntrean($lain));
        $this->assertFalse(\App\Services\TodoService::hasPending($lain));

        $this->giveMenuAccess($head->role, 'todo-list');
        $this->actingAs($head)->getJson(route('todo-list.has-pending'))
            ->assertOk()->assertJson(['has_pending' => true]);
    }

    public function test_jumlah_pending_service_sama_dengan_total_dashboard(): void
    {
        $plan = $this->makeBudgetPlan();
        $this->makeCostRequest(['status' => 'SUBMITTED', 'budget_plan' => $plan]);
        $this->makeCostRequest(['status' => 'SUBMITTED', 'budget_plan' => $plan]);
        $head = $this->giveProjectAccess($this->userWithRole('head'), $plan->project);

        $this->actingAs($head);
        $dashboard = app(TodoListController::class)->actionQueues($head);

        $this->assertSame($dashboard['total'], \App\Services\TodoService::pendingTotal($head));
        $this->assertSame(2, \App\Services\TodoService::pendingTotal($head));
    }

    /* ---------------------------------------------- B2: accounting */

    public function test_accounting_melihat_dan_bisa_bulk_verify_invoice_dan_bukan_coming_soon(): void
    {
        $po      = $this->makePoNumber($this->makeProject());
        $invoice = $this->makeInvoice($po, ['status' => 'SUBMITTED', 'submitted_at' => now()]);

        $accounting = $this->userWithRole('accounting');
        $this->giveMenuAccess($accounting->role, 'todo-list');

        $this->assertContains('invoice-verify', $this->keyAntrean($accounting));

        $this->actingAs($accounting)->get(route('todo-list.index'))
            ->assertOk()->assertDontSee('Coming Soon');

        $this->actingAs($accounting)
            ->post(route('todo-list.bulk', ['invoice-verify', 'approve']), ['ids' => [$invoice->id]]);
        $this->assertSame('VERIFIED', $invoice->refresh()->status);
    }

    public function test_invoice_menunggu_approval_tidak_terlihat_accounting(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $this->makeInvoice($po, ['status' => 'VERIFIED', 'verified_at' => now()]);

        $this->assertNotContains('invoice-approve', $this->keyAntrean($this->userWithRole('accounting')));
    }

    /* ---------------------------------------------- B3: collection berbasis BAST */

    public function test_collection_waiting_invoice_berbasis_bast(): void
    {
        $collection = $this->userWithRole('collection');

        // BAST belum tertagih -> muncul (apa pun status No. PO-nya).
        $poBaru = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $this->makeBast($poBaru, 100_000_000, '2026-10-01');

        // Semua BAST sudah punya invoice aktif -> tidak muncul.
        $poTertagih = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $bastTertagih = $this->makeBast($poTertagih, 100_000_000, '2026-09-01');
        $this->makeInvoice($poTertagih, ['status' => 'SENT'], $bastTertagih);

        // Invoice CANCELLED tidak dihitung -> BAST kembali menunggu.
        $poBatal = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $bastBatal = $this->makeBast($poBatal, 100_000_000, '2026-09-10');
        $this->makeInvoice($poBatal, ['status' => 'CANCELLED'], $bastBatal);

        // No. PO lama (tanpa baris BAST) memakai aturan lama.
        $poLegacy = $this->makePoNumber($this->makeProject(), ['status' => 'BAST_SUBMITTED']);

        // Tanpa BAST dan bukan status tagih -> tidak muncul.
        $poKosong = $this->makePoNumber($this->makeProject(), ['status' => 'RELEASED']);

        $this->actingAs($collection);
        $ids = app(TodoListController::class)->queueQuery('collectionWaitingInvoice')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$poBaru->id, $poBatal->id, $poLegacy->id], $ids);
        $this->assertNotContains($poTertagih->id, $ids);
        $this->assertNotContains($poKosong->id, $ids);

        $this->assertSame(3, \App\Services\TodoService::pendingCounts($collection)['collectionWaitingInvoice']);
    }

    /* ---------------------------------------------- B4: scope K3 */

    public function test_role_k3_melihat_di_luar_access_group_sedangkan_head_manager_guest_tidak(): void
    {
        $plan = $this->makeBudgetPlan();
        $this->makeCostRequest(['status' => 'CHECKED', 'budget_plan' => $plan]);
        $this->makeCostRequest(['status' => 'VERIFIED', 'budget_plan' => $plan]);
        $this->makeCostRequest(['status' => 'SUBMITTED', 'budget_plan' => $plan]);

        // K3: tanpa Access Group tetap melihat.
        $this->assertContains('cost_control_checked', $this->keyAntrean($this->userWithRole('cost_control')));
        $this->assertContains('cost_control_checked', $this->keyAntrean($this->userWithRole('finance')));
        $this->assertContains('ceo', $this->keyAntrean($this->userWithRole('approver')));

        // Non-K3: dibatasi Access Group.
        $this->assertNotContains('head', $this->keyAntrean($this->userWithRole('head')));
        $this->assertNotContains('ceo', $this->keyAntrean($this->userWithRole('guest')));

        // Guest dengan Access Group yang mencakup proyeknya melihatnya.
        $guest = $this->giveProjectAccess($this->userWithRole('guest'), $plan->project);
        $this->assertContains('ceo', $this->keyAntrean($guest));
    }

    public function test_batch_treasury_menunggu_proses_dibatasi_access_group_untuk_guest(): void
    {
        $plan = $this->makeBudgetPlan();
        $cr   = $this->makeCostRequest(['status' => 'APPROVED', 'budget_plan' => $plan]);
        $batch = \App\Models\DailyTransaction::create([
            'code' => 'DT-UJI-1', 'date' => now()->toDateString(), 'type' => 'CASH_OUT',
            'status' => 'PENDING_APPROVER', 'description' => 'Batch uji',
            'created_by' => $this->userWithRole('treasury')->id,
        ]);
        \App\Models\DailyTransactionItem::create([
            'daily_transaction_id' => $batch->id, 'budget_request_id' => $cr->id, 'status' => 'INCLUDED',
        ]);

        $this->assertContains('batch-process', $this->keyAntrean($this->userWithRole('approver')));
        $this->assertNotContains('batch-process', $this->keyAntrean($this->userWithRole('guest')));
        $this->assertContains(
            'batch-process',
            $this->keyAntrean($this->giveProjectAccess($this->userWithRole('guest'), $plan->project))
        );
    }

    /* ---------------------------------------------- B5: export */

    private function exportRows($user)
    {
        $this->actingAs($user);
        $m = new \ReflectionMethod(TodoListController::class, 'exportRows');
        $m->setAccessible(true);

        return $m->invoke(app(TodoListController::class), $user);
    }

    public function test_export_memuat_banyak_modul_dan_hanya_yang_boleh_dilihat(): void
    {
        $plan = $this->makeBudgetPlan(null, ['status' => 'VERIFIED']);
        $cr   = $this->makeCostRequest(['status' => 'VERIFIED', 'budget_plan' => $plan]);
        $po   = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $this->makeBast($po, 1_000_000);

        $approver = $this->userWithRole('approver');
        $rows = $this->exportRows($approver);

        $this->assertContains('cr:' . $cr->id, $rows->pluck('key')->all());
        $this->assertContains('bp:' . $plan->id, $rows->pluck('key')->all());
        $this->assertSame('Menunggu Approval CEO', $rows->firstWhere('key', 'cr:' . $cr->id)['tahap']);
        // PO collection bukan antrean approver.
        $this->assertNotContains('ponum:' . $po->id, $rows->pluck('key')->all());

        $collection = $this->exportRows($this->userWithRole('collection'));
        $this->assertContains('ponum:' . $po->id, $collection->pluck('key')->all());
        $this->assertNotContains('cr:' . $cr->id, $collection->pluck('key')->all());
    }

    public function test_export_ids_modul_id_hanya_mempersempit(): void
    {
        $plan = $this->makeBudgetPlan(null, ['status' => 'VERIFIED']);
        $cr   = $this->makeCostRequest(['status' => 'VERIFIED', 'budget_plan' => $plan]);
        $approver = $this->userWithRole('approver');
        $this->giveMenuAccess($approver->role, 'todo-list');

        $this->actingAs($approver)->post(route('todo-list.export'), ['ids' => ['cr:' . $cr->id]])
            ->assertOk();

        // Kunci yang di luar hak (mis. milik antrean lain) tidak memperluas hasil.
        $baris = $this->exportRows($this->userWithRole('collection'));
        $this->assertFalse($baris->pluck('key')->contains('cr:' . $cr->id));
    }

    /* ---------------------------------------------- A1: jendela respon time */

    public function test_respontime_menghitung_transisi_dalam_jendela_dengan_pendahulu_di_luar_jendela(): void
    {
        $plan = $this->makeBudgetPlan();
        $cr1  = $this->makeCostRequest(['status' => 'CHECKED', 'budget_plan' => $plan]);
        $cr2  = $this->makeCostRequest(['status' => 'CHECKED', 'budget_plan' => $plan]);
        $head = $this->userWithRole('head');
        $fin  = $this->userWithRole('finance');
        $pengaju = $this->userWithRole('manager');

        $log = function ($cr, $aksi, $oleh, $waktu) {
            \DB::table('request_status_logs')->insert([
                'budget_request_id' => $cr->id, 'acted_by' => $oleh->id, 'action_type' => $aksi,
                'status_to' => 'SUBMITTED', 'actor_name' => $oleh->name, 'actor_role' => 'uji',
                'created_at' => $waktu,
            ]);
        };

        // CR1: submit 100 hari lalu (di luar jendela 90 hari), disetujui head 89 hari
        // lalu (di dalam). Selisih 11 hari dihitung untuk head karena pendahulunya
        // tetap dibaca walau di luar jendela.
        $log($cr1, 'submit', $head, now()->subDays(100));
        $log($cr1, 'approve', $head, now()->subDays(89));

        // CR2 (dokumen lain; LAG dihitung per dokumen): seluruh transisi di luar
        // jendela -> tidak dihitung, finance tidak muncul di tabel.
        $log($cr2, 'submit', $pengaju, now()->subDays(120));
        $log($cr2, 'check', $fin, now()->subDays(95));

        $m = new \ReflectionMethod(TodoListController::class, 'buildRespontimeTable');
        $m->setAccessible(true);
        $tabel = $m->invoke(app(TodoListController::class));

        $barisHead = collect($tabel['rows'])->firstWhere('user', $head->name);
        $this->assertNotNull($barisHead);
        $this->assertSame(1, $barisHead['count']);
        $this->assertEqualsWithDelta(11 * 86400, $barisHead['avg_seconds'], 5);
        $this->assertNull(collect($tabel['rows'])->firstWhere('user', $fin->name));
        $this->assertNull(collect($tabel['rows'])->firstWhere('user', $pengaju->name));
    }

    /* ---------------------------------------------- A3: KPI lintas modul */

    public function test_tindakan_selesai_hari_ini_menghitung_lintas_modul(): void
    {
        $user = $this->userWithRole('finance_manager');
        $plan = $this->makeBudgetPlan();
        \DB::table('budget_plan_logs')->insert([
            'budget_plan_id' => $plan->id, 'user_id' => $user->id, 'action' => 'verify',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user);
        $hasil = app(TodoListController::class)->actionQueues($user);

        $this->assertGreaterThanOrEqual(1, $hasil['completedToday']);
    }

    /* ---------------------------------------------- A5: Assignment */

    public function test_ganti_penerima_mereset_status_ke_pending_dan_memberi_tahu_keduanya(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $pemberi = $this->userWithRole('manager');
        $lama    = $this->userWithRole('finance');
        $baru    = $this->userWithRole('treasury');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $lama->id,
            'priority' => 'MEDIUM', 'status' => 'ON_PROGRESS', 'read_at' => now(), 'started_at' => now(),
        ]);

        $this->actingAs($pemberi)
            ->put(route('assignment.update', $a), $this->payload($baru->id))
            ->assertSessionHas('success');

        $a->refresh();
        $this->assertSame($baru->id, $a->assignee_id);
        $this->assertSame('PENDING', $a->status);
        $this->assertNull($a->read_at);
        $this->assertNull($a->started_at);
        $this->assertDatabaseHas('assignment_logs', ['assignment_id' => $a->id, 'action' => 'reassign']);
        \Illuminate\Support\Facades\Notification::assertSentTo($baru, \App\Notifications\AppNotification::class);
        \Illuminate\Support\Facades\Notification::assertSentTo($lama, \App\Notifications\AppNotification::class);
    }

    public function test_edit_tanpa_ganti_penerima_tidak_mengubah_status(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'ON_PROGRESS', 'read_at' => now(), 'started_at' => now(),
        ]);

        $this->actingAs($pemberi)->put(route('assignment.update', $a), $this->payload($penerima->id, ['title' => 'Baru']));

        $this->assertSame('ON_PROGRESS', $a->fresh()->status);
    }

    public function test_aksi_ganda_bersamaan_hanya_satu_yang_berhasil(): void
    {
        $pemberi  = $this->userWithRole('manager');
        $penerima = $this->userWithRole('finance');
        $a = Assignment::create([
            'title' => 'T', 'assigner_id' => $pemberi->id, 'assignee_id' => $penerima->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING',
        ]);

        // Dua pemanggil memegang salinan lama (status masih PENDING di memori).
        $salinan1 = Assignment::find($a->id);
        $salinan2 = Assignment::find($a->id);

        $this->actingAs($pemberi);
        $ctl = app(AssignmentController::class);
        $req = \Illuminate\Http\Request::create('/', 'POST');

        $ctl->markDone($req, $salinan1);
        $this->assertSame('CLOSED', $a->fresh()->status);

        // Pemanggil kedua lolos cek di memori, tetapi update bersyarat gagal.
        $hasil = $ctl->cancel($req, $salinan2);
        $this->assertSame('CLOSED', $a->fresh()->status);
        $this->assertSame('Status sudah berubah, muat ulang.', $hasil->getSession()->get('error'));
        $this->assertSame(1, \App\Models\AssignmentLog::where('assignment_id', $a->id)->whereIn('action', ['close', 'cancel'])->count());
    }


    /* ---------------------------------------------- Collection: invoice lama belum tertaut */

    public function test_collection_tidak_menampilkan_no_po_dengan_invoice_lama_belum_tertaut(): void
    {
        $collection = $this->userWithRole('collection');

        // BAST belum tertagih, tetapi ada invoice lama aktif tanpa tautan BAST.
        $poLama = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $this->makeBast($poLama, 100_000_000, '2026-10-01');
        $this->makeInvoice($poLama, ['status' => 'SENT']); // project_po_bast_id NULL

        // Invoice lama yang sudah CANCELLED tidak mengecualikan.
        $poBatal = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $this->makeBast($poBatal, 100_000_000, '2026-10-02');
        $this->makeInvoice($poBatal, ['status' => 'CANCELLED']);

        $this->actingAs($collection);
        $ids = app(TodoListController::class)->queueQuery('collectionWaitingInvoice')->pluck('id')->all();

        $this->assertNotContains($poLama->id, $ids);
        $this->assertContains($poBatal->id, $ids);
    }

    public function test_collection_no_po_lama_tanpa_bast_tetap_tampil_walau_invoice_termin_belum_tertaut(): void
    {
        $poLegacy = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $this->makeInvoice($poLegacy, ['status' => 'SENT']); // termin lama, tanpa tautan BAST

        $this->actingAs($this->userWithRole('collection'));
        $ids = app(TodoListController::class)->queueQuery('collectionWaitingInvoice')->pluck('id')->all();

        $this->assertContains($poLegacy->id, $ids);
    }

    public function test_collection_bast_dengan_invoice_lama_belum_tertaut_tidak_tampil_bast_tanpa_invoice_tampil(): void
    {
        // BAST + invoice lama aktif belum tertaut -> tidak tampil. (Kode tidak membaca
        // kolom origin; origin di bawah hanya data uji yang realistis.)
        $poSintetis = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $this->makeBast($poSintetis, 100_000_000, '2026-09-01', null, ['origin' => \App\Models\ProjectPoBast::ORIGIN_BACKFILL]);
        $this->makeInvoice($poSintetis, ['status' => 'SENT']);

        // BAST tanpa invoice lama belum tertaut -> tampil, termasuk BAST sisa (keputusan pemilik).
        $poSisa = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
        $this->makeBast($poSisa, 60_000, '2026-10-05', null, ['origin' => \App\Models\ProjectPoBast::ORIGIN_SPLIT_SISA]);

        $this->actingAs($this->userWithRole('collection'));
        $ids = app(TodoListController::class)->queueQuery('collectionWaitingInvoice')->pluck('id')->all();

        $this->assertNotContains($poSintetis->id, $ids);
        $this->assertContains($poSisa->id, $ids);
    }

    /* ---------------------------------------------- Export: tanpa N+1 */

    /** @return string[] SQL (tanpa binding) semua query yang dijalankan exportRows(). */
    private function queryExport($user): array
    {
        $this->actingAs($user);
        $m = new \ReflectionMethod(TodoListController::class, 'exportRows');
        $m->setAccessible(true);

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        $m->invoke(app(TodoListController::class), $user);
        // Placeholder IN (?, ?, ...) dinormalkan: panjang daftar ikut jumlah baris
        // dan bukan query tambahan.
        $sql = array_map(
            fn ($q) => preg_replace('/\bin \((\?(, ?)?)+\)/i', 'in (?...)', $q),
            array_column(\DB::getQueryLog(), 'query')
        );
        \DB::disableQueryLog();

        return $sql;
    }

    /**
     * Jumlah query exportRows() untuk $user harus sama pada 3 dan 9 baris
     * ($buatSatu membuat satu baris antrean). Pemanasan satu kali dulu; bila
     * berbeda, pesan memuat SQL yang bertambah.
     */
    private function assertQueryExportKonstan($user, callable $buatSatu): void
    {
        $buatSatu();
        $this->queryExport($user); // pemanasan

        for ($i = 0; $i < 2; $i++) { $buatSatu(); } // total 3
        $sedikit = $this->queryExport($user);
        for ($i = 0; $i < 6; $i++) { $buatSatu(); } // total 9
        $banyak = $this->queryExport($user);

        $cs = array_count_values($sedikit);
        $tambahan = [];
        foreach (array_count_values($banyak) as $sql => $n) {
            if ($n - ($cs[$sql] ?? 0) > 0) {
                $tambahan[$sql] = $n - ($cs[$sql] ?? 0);
            }
        }

        $this->assertSame(
            count($sedikit),
            count($banyak),
            'Query export tumbuh seiring jumlah baris (N+1). Query bertambah: '
                . json_encode($tambahan, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_jumlah_query_export_collection_tidak_bertambah_per_baris(): void
    {
        $this->assertQueryExportKonstan($this->userWithRole('collection'), function () {
            $po = $this->makePoNumber($this->makeProject(), ['status' => 'PARTIAL']);
            $this->makeBast($po, 1_000_000);
        });
    }

    public function test_jumlah_query_export_invoice_tidak_bertambah_per_baris(): void
    {
        $this->assertQueryExportKonstan($this->userWithRole('finance_manager'), function () {
            $po = $this->makePoNumber($this->makeProject());
            $this->makeInvoice($po, ['status' => 'SUBMITTED', 'submitted_at' => now()]);
        });
    }

    public function test_jumlah_query_export_cost_request_tidak_bertambah_per_baris(): void
    {
        $this->assertQueryExportKonstan($this->userWithRole('approver'), function () {
            $this->makeCostRequest(['status' => 'VERIFIED']);
        });
    }

    /* ---------------------------------------------- hasPending: exists() + cache */

    public function test_has_pending_berhenti_di_antrean_pertama_dan_menghormati_cache(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        $plan = $this->makeBudgetPlan();
        $this->makeCostRequest(['status' => 'SUBMITTED', 'budget_plan' => $plan]);
        $head = $this->giveProjectAccess($this->userWithRole('head'), $plan->project);
        $this->actingAs($head);

        \DB::enableQueryLog();
        $this->assertTrue(\App\Services\TodoService::hasPending($head));
        $sql = collect(\DB::getQueryLog())->pluck('query')->implode("\n");

        // Antrean pertama (head) sudah berisi: antrean berikutnya tidak diperiksa.
        $this->assertStringNotContainsString('`invoices`', $sql);
        $this->assertStringNotContainsString('`daily_transactions`', $sql);
        $this->assertStringNotContainsString('`project_details`', $sql);
        $this->assertStringContainsString('exists', strtolower($sql));

        // Panggilan kedua dilayani cache: tidak ada query ke tabel antrean.
        \DB::flushQueryLog();
        $this->assertTrue(\App\Services\TodoService::hasPending($head));
        $this->assertStringNotContainsString('`budget_request`', collect(\DB::getQueryLog())->pluck('query')->implode("\n"));
    }

    public function test_has_pending_false_bila_semua_antrean_kosong_sama_dengan_halaman(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        $head = $this->userWithRole('head');

        $this->assertFalse(\App\Services\TodoService::hasPending($head));
        $this->assertSame(0, \App\Services\TodoService::pendingTotal($head));
    }

    /* ---------------------------------------------- Export: id format lama */

    public function test_export_dengan_id_format_lama_ditolak_dengan_pesan_jelas(): void
    {
        $approver = $this->userWithRole('approver');
        $this->giveMenuAccess($approver->role, 'todo-list');

        $this->actingAs($approver)
            ->post(route('todo-list.export'), ['ids' => [12, 'cr:5']])
            ->assertRedirect(route('todo-list.index'))
            ->assertSessionHas('error');
    }


    /* ---------------------------------------------- Takeout: Project Monitoring & Assignment dari export */

    public function test_todo_list_tidak_lagi_memuat_antrean_milestone(): void
    {
        $this->assertArrayNotHasKey('milestoneOverdue', \App\Services\TodoService::QUEUES);
        $this->assertCount(36, \App\Services\TodoService::QUEUES);
    }

    public function test_assignment_tidak_pernah_ikut_export(): void
    {
        $approver = $this->userWithRole('approver');
        $pemberi  = $this->userWithRole('manager');
        Assignment::create([
            'title' => 'Tugas terbuka', 'assigner_id' => $pemberi->id, 'assignee_id' => $approver->id,
            'priority' => 'MEDIUM', 'status' => 'PENDING',
        ]);

        $rows = $this->exportRows($approver);

        $this->assertSame(0, $rows->where('modul', 'Assignment')->count());
        $this->assertSame(0, $rows->filter(fn ($r) => str_starts_with($r['key'], 'asg:'))->count());
    }
}
