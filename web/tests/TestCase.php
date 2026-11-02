<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The tests don't build the front end, so there is no Vite manifest to read
     * (SETUP.md §3).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
