<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\CoaAccount;
use App\Models\DailyTransaction;
use App\Models\Journal;
use App\Models\JournalSource;
use App\Models\SourceFund;
use App\Models\User;
use App\Services\JournalGenerator;
use App\Support\AccountingMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Accounting — jurnal manual, submit/post, pembalik, generate dari sumber, periode, akses.
 * Nominal di tabel akuntansi = sen (rupiah x 100).
 * (Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.)
 */
class AccountingJournalTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private CoaAccount $kas;
    private CoaAccount $beban;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kas = $this->coa('1101', 'Kas Uji', 'ASSET', 'DEBIT');
        $this->beban = $this->coa('6101', 'Beban Uji', 'EXPENSE', 'DEBIT');
        $this->period('Nov 2026', '2026-11-01', '2026-11-30');

        $this->giveMenuAccess($this->role('accounting'), 'accounting-journal', 'accounting-period');
        $this->giveMenuAccess($this->role('finance_manager'), 'accounting-journal', 'accounting-period');
    }

    /* ---------------------------------------------------------------- pembantu */

    private function coa(string $code, string $name, string $type, string $normal, array $extra = []): CoaAccount
    {
        return CoaAccount::create(array_merge([
            'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normal,
            'is_postable' => true, 'status' => 'ACTIVE',
        ], $extra));
    }

    private function period(string $name, string $start, string $end, string $status = 'OPEN'): AccountingPeriod
    {
        return AccountingPeriod::create(['name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => $status]);
    }

    /** Payload jurnal manual dua baris (nominal teks format Indonesia). */
    private function manualPayload(string $debit = '1.000.000', string $credit = '1.000.000', string $date = '2026-11-05'): array
    {
        return [
            'journal_type' => 'GENERAL', 'journal_date' => $date, 'description' => 'Jurnal uji',
            'lines' => [
                ['coa_account_id' => $this->beban->id, 'debit' => $debit, 'credit' => ''],
                ['coa_account_id' => $this->kas->id, 'debit' => '', 'credit' => $credit],
            ],
        ];
    }

    /** Jurnal langsung lewat model (untuk menyiapkan status tertentu tanpa lewat UI). */
    private function journalDirect(string $status, int $debit, int $credit, string $date = '2026-11-05', ?User $creator = null): Journal
    {
        $j = Journal::create([
            'journal_number' => Journal::nextNumber(), 'journal_date' => $date,
            'accounting_period_id' => AccountingPeriod::forDate($date)?->id,
            'journal_type' => 'GENERAL', 'status' => $status, 'description' => 'uji',
            'total_debit' => $debit, 'total_credit' => $credit, 'created_by' => $creator?->id,
        ]);
        $j->lines()->create(['line_no' => 1, 'coa_account_id' => $this->beban->id, 'debit' => $debit, 'credit' => 0]);
        $j->lines()->create(['line_no' => 2, 'coa_account_id' => $this->kas->id, 'debit' => 0, 'credit' => $credit]);

        return $j;
    }

    private function storeManual(User $u, array $payload): ?Journal
    {
        $this->actingAs($u)->post('/accounting/journal/manual', $payload);

        return Journal::latest('id')->first();
    }

    /** Batch Top Up PAID + dua rekening yang ditautkan ke akun COA (tanpa mapping/biaya bank). */
    private function topUpSource(): DailyTransaction
    {
        $main = SourceFund::create(['name' => 'Main Uji', 'status' => 'ACTIVE', 'is_main_account' => true]);
        $ops = SourceFund::create(['name' => 'Ops Uji', 'status' => 'ACTIVE']);
        $this->coa('1111', 'Bank Main', 'ASSET', 'DEBIT', ['source_fund_id' => $main->id]);
        $this->coa('1112', 'Bank Ops', 'ASSET', 'DEBIT', ['source_fund_id' => $ops->id]);

        return DailyTransaction::create([
            'code' => 'TU-ACC-1', 'date' => '2026-11-05', 'type' => 'TOP_UP', 'status' => 'PAID',
            'source_fund_id' => $main->id, 'to_source_fund_id' => $ops->id, 'captured_amount' => 2_000_000,
        ]);
    }

    private function generate(User $u, DailyTransaction $b)
    {
        return $this->actingAs($u)->post('/accounting/journal/generate', [
            'source_type' => JournalGenerator::TREASURY, 'source_id' => $b->id,
        ]);
    }

    /* ---------------------------------------------------------------- uang */

    public function test_money_parse_format_indonesia_ke_sen(): void
    {
        $this->assertSame(1234567, AccountingMoney::parse('12.345,67'));
        $this->assertSame(100000, AccountingMoney::parse('1000'));
        $this->assertSame(100050, AccountingMoney::parse('1.000,5'));
        $this->assertNull(AccountingMoney::parse('1000.50'));
        $this->assertNull(AccountingMoney::parse('1,234'));
        $this->assertSame('12.345,67', AccountingMoney::format(1234567));
    }

    public function test_input_pecahan_tersimpan_dalam_sen(): void
    {
        $j = $this->storeManual($this->userWithRole('accounting'), $this->manualPayload('12.345,67', '12.345,67'));

        $this->assertNotNull($j);
        $this->assertSame(1234567, $j->total_debit);
        $this->assertSame(1234567, $j->total_credit);
        $this->assertSame([1234567, 0], [$j->lines[0]->debit, $j->lines[0]->credit]);
        $this->assertSame([0, 1234567], [$j->lines[1]->debit, $j->lines[1]->credit]);
        $this->assertSame(Journal::DRAFT, $j->status);
    }

    /* ---------------------------------------------------------------- validasi baris & keseimbangan */

    public function test_baris_debit_dan_kredit_sekaligus_ditolak(): void
    {
        $payload = $this->manualPayload();
        $payload['lines'][0]['credit'] = '500.000';

        $this->actingAs($this->userWithRole('accounting'))->post('/accounting/journal/manual', $payload)
            ->assertSessionHas('error');

        $this->assertSame(0, Journal::count());
    }

    public function test_baris_debit_kredit_sekaligus_di_db_ditolak_saat_submit(): void
    {
        $acc = $this->userWithRole('accounting');
        $j = $this->journalDirect(Journal::DRAFT, 1_000_00, 1_000_00, creator: $acc);
        $j->lines()->where('line_no', 1)->update(['credit' => 100]);
        $j->lines()->where('line_no', 2)->update(['debit' => 100]);

        $this->actingAs($acc)->post("/accounting/journal/{$j->id}/submit")->assertSessionHas('error');
        $this->assertSame(Journal::DRAFT, $j->fresh()->status);
    }

    public function test_jurnal_manual_tidak_seimbang_ditolak_saat_submit(): void
    {
        $acc = $this->userWithRole('accounting');
        $j = $this->storeManual($acc, $this->manualPayload('1.000.000', '900.000'));
        $this->assertNotNull($j, 'Draft tak seimbang tetap boleh disimpan; ditolak di Submit.');

        $this->actingAs($acc)->post("/accounting/journal/{$j->id}/submit")->assertSessionHas('error');
        $this->assertSame(Journal::DRAFT, $j->fresh()->status);
    }

    public function test_jurnal_tidak_seimbang_ditolak_saat_post(): void
    {
        $j = $this->journalDirect(Journal::SUBMITTED, 1_000_00, 900_00, creator: $this->userWithRole('accounting'));

        $this->actingAs($this->userWithRole('finance_manager'))->post("/accounting/journal/{$j->id}/post")
            ->assertSessionHas('error');
        $this->assertSame(Journal::SUBMITTED, $j->fresh()->status);
    }

    /* ---------------------------------------------------------------- alur submit/post */

    public function test_alur_manual_submit_lalu_post_oleh_finance_manager(): void
    {
        $acc = $this->userWithRole('accounting');
        $fm = $this->userWithRole('finance_manager');
        $j = $this->storeManual($acc, $this->manualPayload());

        $this->actingAs($acc)->post("/accounting/journal/{$j->id}/submit")->assertSessionHas('success');
        $this->actingAs($fm)->post("/accounting/journal/{$j->id}/post")->assertSessionHas('success');

        $j->refresh();
        $this->assertSame(Journal::POSTED, $j->status);
        $this->assertSame($fm->id, (int) $j->posted_by);
    }

    public function test_posting_ganda_ditolak(): void
    {
        $fm = $this->userWithRole('finance_manager');
        $j = $this->journalDirect(Journal::SUBMITTED, 1_000_00, 1_000_00, creator: $this->userWithRole('accounting'));

        $this->actingAs($fm)->post("/accounting/journal/{$j->id}/post")->assertSessionHas('success');
        $postedAt = $j->fresh()->posted_at;

        $fm2 = $this->userWithRole('finance_manager');
        $this->actingAs($fm2)->post("/accounting/journal/{$j->id}/post")->assertSessionHas('error');

        $j->refresh();
        $this->assertSame(Journal::POSTED, $j->status);
        $this->assertSame($fm->id, (int) $j->posted_by);
        $this->assertEquals($postedAt, $j->posted_at);
        $this->assertSame(1, \App\Models\AccountingAuditLog::where('entity_type', 'journal')
            ->where('entity_id', $j->id)->where('action', 'post')->count());
    }

    public function test_jurnal_posted_tidak_bisa_diedit_atau_dihapus(): void
    {
        $acc = $this->userWithRole('accounting');
        $j = $this->journalDirect(Journal::POSTED, 1_000_00, 1_000_00, creator: $acc);

        $this->actingAs($acc)->put("/accounting/journal/{$j->id}", [
            'journal_date' => '2026-11-06', 'description' => 'ubah',
            'lines' => [
                ['coa_account_id' => $this->beban->id, 'debit' => '5.000', 'credit' => ''],
                ['coa_account_id' => $this->kas->id, 'debit' => '', 'credit' => '5.000'],
            ],
        ])->assertSessionHas('error');

        $this->actingAs($acc)->delete("/accounting/journal/{$j->id}", ['reason' => 'hapus'])->assertSessionHas('error');

        $j = Journal::withTrashed()->find($j->id);
        $this->assertFalse($j->trashed());
        $this->assertSame(Journal::POSTED, $j->status);
        $this->assertSame('2026-11-05', $j->journal_date->toDateString());
        $this->assertSame(1_000_00, $j->total_debit);
        $this->assertSame([1_000_00, 1_000_00], [(int) $j->lines()->sum('debit'), (int) $j->lines()->sum('credit')]);
    }

    /* ---------------------------------------------------------------- pemisahan tugas */

    public function test_sod_pembuat_tidak_boleh_post(): void
    {
        $fm = $this->userWithRole('finance_manager');
        $j = $this->storeManual($fm, $this->manualPayload());
        $this->actingAs($fm)->post("/accounting/journal/{$j->id}/submit")->assertSessionHas('success');

        $this->actingAs($fm)->post("/accounting/journal/{$j->id}/post")->assertSessionHas('error');
        $this->assertSame(Journal::SUBMITTED, $j->fresh()->status);
    }

    public function test_sod_editor_terakhir_tidak_boleh_post(): void
    {
        $acc = $this->userWithRole('accounting');
        $editor = $this->userWithRole('finance_manager');
        $j = $this->storeManual($acc, $this->manualPayload());

        $this->actingAs($editor)->put("/accounting/journal/{$j->id}", [
            'journal_date' => '2026-11-05', 'description' => 'diedit',
            'lines' => [
                ['coa_account_id' => $this->beban->id, 'debit' => '2.000.000', 'credit' => ''],
                ['coa_account_id' => $this->kas->id, 'debit' => '', 'credit' => '2.000.000'],
            ],
        ])->assertSessionHas('success');
        $this->assertSame($editor->id, (int) $j->fresh()->last_edited_by);

        $this->actingAs($acc)->post("/accounting/journal/{$j->id}/submit")->assertSessionHas('success');
        $this->actingAs($editor)->post("/accounting/journal/{$j->id}/post")->assertSessionHas('error');
        $this->assertSame(Journal::SUBMITTED, $j->fresh()->status);

        // Pengguna lain boleh.
        $this->actingAs($this->userWithRole('finance_manager'))->post("/accounting/journal/{$j->id}/post")->assertSessionHas('success');
        $this->assertSame(Journal::POSTED, $j->fresh()->status);
    }

    /* ---------------------------------------------------------------- periode */

    public function test_post_ke_periode_closed_atau_locked_ditolak(): void
    {
        $this->period('Okt 2026', '2026-10-01', '2026-10-31', 'CLOSED');
        $this->period('Sep 2026', '2026-09-01', '2026-09-30', 'LOCKED');
        $acc = $this->userWithRole('accounting');
        $fm = $this->userWithRole('finance_manager');

        foreach (['2026-10-15', '2026-09-15'] as $date) {
            $j = $this->journalDirect(Journal::SUBMITTED, 1_000_00, 1_000_00, $date, $acc);
            $this->actingAs($fm)->post("/accounting/journal/{$j->id}/post")->assertSessionHas('error');
            $this->assertSame(Journal::SUBMITTED, $j->fresh()->status, "Tanggal {$date}");
        }
    }

    public function test_post_tanpa_periode_ditolak(): void
    {
        $j = $this->journalDirect(Journal::SUBMITTED, 1_000_00, 1_000_00, '2027-01-10', $this->userWithRole('accounting'));

        $this->actingAs($this->userWithRole('finance_manager'))->post("/accounting/journal/{$j->id}/post")->assertSessionHas('error');
        $this->assertSame(Journal::SUBMITTED, $j->fresh()->status);
    }

    public function test_tutup_periode_ditolak_bila_masih_ada_draft_atau_submitted(): void
    {
        $acc = $this->userWithRole('accounting');
        $p = AccountingPeriod::where('name', 'Nov 2026')->first();

        foreach ([Journal::DRAFT, Journal::SUBMITTED] as $status) {
            $j = $this->journalDirect($status, 1_000_00, 1_000_00, '2026-11-10', $acc);
            $this->actingAs($acc)->post("/accounting/period/{$p->id}/close")->assertSessionHas('error');
            $this->assertSame('OPEN', $p->fresh()->status, "Status jurnal {$status}");
            $j->update(['status' => Journal::POSTED]);
        }

        // Semua sudah Posted, tetapi Closing operasional bulan itu belum ditutup -> tetap ditolak.
        $this->actingAs($acc)->post("/accounting/period/{$p->id}/close")->assertSessionHas('error');
        $this->assertSame('OPEN', $p->fresh()->status);

        // Closing operasional ditutup lebih dulu (kunci periode) -> boleh ditutup.
        \App\Models\Closing::create([
            'code' => 'CLS/2026/11', 'period' => '2026-11', 'start_date' => '2026-11-01',
            'end_date' => '2026-11-30', 'status' => 'CLOSED',
        ]);
        $this->actingAs($acc)->post("/accounting/period/{$p->id}/close")->assertSessionHas('success');
        $this->assertSame('CLOSED', $p->fresh()->status);
    }

    /* ---------------------------------------------------------------- generate & pembalik */

    public function test_generate_dua_kali_dari_sumber_sama_ditolak(): void
    {
        $acc = $this->userWithRole('accounting');
        $b = $this->topUpSource();

        $this->generate($acc, $b)->assertSessionHas('success');
        $this->generate($acc, $b)->assertSessionHas('error');

        $this->assertSame(1, Journal::count());
        $this->assertSame(1, JournalSource::whereNotNull('active_key')->count());
        $j = Journal::first();
        $this->assertSame('SOURCE', $j->journal_type);
        $this->assertSame(AccountingMoney::fromRupiah(2_000_000), $j->total_debit);
        $this->assertSame($j->total_debit, $j->total_credit);
    }

    public function test_reverse_membuat_jurnal_pembalik_dan_melepas_active_key(): void
    {
        $acc = $this->userWithRole('accounting');
        $fm = $this->userWithRole('finance_manager');
        $b = $this->topUpSource();

        $this->generate($acc, $b)->assertSessionHas('success');
        $orig = Journal::first();
        $this->actingAs($acc)->post("/accounting/journal/{$orig->id}/submit")->assertSessionHas('success');
        $this->actingAs($fm)->post("/accounting/journal/{$orig->id}/post")->assertSessionHas('success');
        $this->assertSame(Journal::POSTED, $orig->fresh()->status);

        $this->actingAs($acc)->post("/accounting/journal/{$orig->id}/reverse", [
            'journal_date' => '2026-11-20', 'reason' => 'Salah akun tujuan',
        ])->assertSessionHas('success');

        $rev = Journal::where('reverses_journal_id', $orig->id)->first();
        $this->assertNotNull($rev);
        $this->assertSame('REVERSAL', $rev->journal_type);
        $this->assertSame(Journal::DRAFT, $rev->status);
        $origLines = $orig->lines()->get()->keyBy('line_no');
        foreach ($rev->lines()->get() as $l) {
            $o = $origLines[$l->line_no];
            $this->assertSame($o->coa_account_id, $l->coa_account_id);
            $this->assertSame([$o->credit, $o->debit], [$l->debit, $l->credit]);
        }

        // Pembalik kedua ditolak selagi pembalik pertama ada.
        $this->actingAs($acc)->post("/accounting/journal/{$orig->id}/reverse", [
            'journal_date' => '2026-11-20', 'reason' => 'Coba lagi',
        ])->assertSessionHas('error');
        $this->assertSame(1, Journal::where('reverses_journal_id', $orig->id)->count());

        // Sebelum pembalik diposting, sumber masih terkunci.
        $this->assertSame(1, JournalSource::where('journal_id', $orig->id)->whereNotNull('active_key')->count());

        $this->actingAs($acc)->post("/accounting/journal/{$rev->id}/submit")->assertSessionHas('success');
        $this->actingAs($fm)->post("/accounting/journal/{$rev->id}/post")->assertSessionHas('success');

        $this->assertSame(Journal::POSTED, $rev->fresh()->status);
        $this->assertSame(Journal::REVERSED, $orig->fresh()->status);
        $this->assertSame(0, JournalSource::where('journal_id', $orig->id)->whereNotNull('active_key')->count());

        // Sumber boleh dijurnal ulang (jurnal pengganti).
        $this->generate($acc, $b)->assertSessionHas('success');
        $this->assertSame(1, JournalSource::whereNotNull('active_key')->count());
    }

    /* ---------------------------------------------------------------- akses */

    public function test_role_tanpa_akses_ditolak(): void
    {
        // finance: punya menu, tetapi bukan ACCOUNTING_MANAGE -> tidak boleh membuat/generate.
        $this->giveMenuAccess($this->role('finance'), 'accounting-journal');
        $finance = $this->userWithRole('finance');
        $this->actingAs($finance)->post('/accounting/journal/manual', $this->manualPayload())->assertForbidden();
        $this->actingAs($finance)->post('/accounting/journal/generate', ['source_type' => JournalGenerator::TREASURY, 'source_id' => 1])->assertForbidden();
        $this->assertSame(0, Journal::count());

        // accounting: boleh membuat, tetapi bukan ACCOUNTING_POST -> posting ditolak route.
        $acc = $this->userWithRole('accounting');
        $j = $this->journalDirect(Journal::SUBMITTED, 1_000_00, 1_000_00, creator: $this->userWithRole('accounting'));
        $this->actingAs($acc)->post("/accounting/journal/{$j->id}/post")->assertForbidden();
        $this->assertSame(Journal::SUBMITTED, $j->fresh()->status);

        // accounting tidak boleh membuka kembali periode (ACCOUNTING_REOPEN).
        $p = $this->period('Okt 2026', '2026-10-01', '2026-10-31', 'CLOSED');
        $this->actingAs($acc)->post("/accounting/period/{$p->id}/reopen", ['reason' => 'uji buka ulang'])->assertForbidden();
        $this->assertSame('CLOSED', $p->fresh()->status);

        // Role yang benar tetapi tanpa menu accounting-journal -> ditolak middleware menu:.
        $this->role('accounting')->menuItems()->detach();
        $this->actingAs($this->userWithRole('accounting'))->get('/accounting/journal')->assertForbidden();
    }
}
