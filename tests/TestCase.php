<?php

namespace Tests;

use App\Models\Holiday;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Holiday keys are cached in a static property; a test's database is
        // rolled back without model events, so start every test with a clean cache.
        Holiday::flushKeys();
        \App\Services\LeaveCalculator::flushUserSites();
    }
}
