<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Server feature tests need no asset build; tests/browser checks compiled CSS.
        $this->withoutVite();
    }
}
