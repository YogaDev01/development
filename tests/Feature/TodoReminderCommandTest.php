<?php

namespace Tests\Feature;

use App\Notifications\AppNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Reminder To-Do List terjadwal (command todo:remind).
 */
class TodoReminderCommandTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Head bukan role K3 (dibatasi Access Group): CR SUBMITTED baru muncul di
     * antreannya bila proyeknya ada di Access Group head itu.
     */
    private function headDenganCrSubmitted()
    {
        $plan = $this->makeBudgetPlan();
        $this->makeCostRequest(['status' => 'SUBMITTED', 'budget_plan' => $plan]);

        return $this->giveProjectAccess($this->userWithRole('head'), $plan->project);
    }

    public function test_kirim_reminder_saat_due_dan_ada_todo(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 8, 0, 0)); // jam default 08:00
        Notification::fake();

        $head = $this->headDenganCrSubmitted(); // tugas approval Head (dalam Access Group-nya)

        $this->artisan('todo:remind')->assertSuccessful();

        Notification::assertSentTo($head, AppNotification::class, function ($n) {
            return $n->category === 'todo';
        });
    }

    public function test_tidak_kirim_saat_tidak_ada_todo(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 8, 0, 0));
        Notification::fake();

        $user = $this->userWithRole('maker'); // tanpa record apa pun

        $this->artisan('todo:remind')->assertSuccessful();

        Notification::assertNothingSentTo($user);
    }

    public function test_tidak_kirim_saat_jam_tidak_cocok(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 9, 0, 0)); // 09:00 bukan jadwal default
        Notification::fake();

        $head = $this->headDenganCrSubmitted();

        $this->artisan('todo:remind')->assertSuccessful();

        Notification::assertNothingSentTo($head);
    }

    public function test_tidak_kirim_saat_reminder_dimatikan(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 8, 0, 0));
        Notification::fake();

        $head = $this->headDenganCrSubmitted();
        $head->update(['notification_prefs' => ['todo_reminder' => ['enabled' => false]]]);

        $this->artisan('todo:remind')->assertSuccessful();

        Notification::assertNothingSentTo($head);
    }

    public function test_mode_interval_jatuh_tempo_tiap_n_jam(): void
    {
        // interval 4 jam => due pada 00:00, 04:00, 08:00, ... ; 08:00 = due.
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 8, 0, 0));

        $head = $this->userWithRole('head');
        $head->update(['notification_prefs' => [
            'todo_reminder' => ['enabled' => true, 'mode' => 'interval', 'interval_hours' => 4],
        ]]);

        $this->assertTrue($head->todoReminderDueNow());

        Carbon::setTestNow(Carbon::create(2026, 10, 8, 9, 0, 0)); // 9 % 4 != 0
        $this->assertFalse($head->todoReminderDueNow());
    }
}
