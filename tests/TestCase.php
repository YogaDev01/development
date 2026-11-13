<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Cache per-request PeriodLock bersifat static; jangan bocor antar test.
        \App\Support\PeriodLock::flush();
    }

    protected function tearDown(): void
    {
        \App\Support\PeriodLock::flush();
        parent::tearDown();
    }
}
