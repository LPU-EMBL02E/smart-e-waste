<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * The isolation level of the production database connections.
 * System_Plan.md §6.1 (concurrency rules), API_Design.md P24.
 *
 * The test suite runs on SQLite, which has no isolation level to set, so this
 * guards the setting itself. Without it, simultaneous requests fail on MariaDB
 * 11.8 with error 1020, which SQLite can never reproduce.
 */
class DatabaseIsolationTest extends TestCase
{
    public function test_it_uses_read_committed_on_mariadb(): void
    {
        $this->assertSame('READ COMMITTED', config('database.connections.mariadb.isolation_level'));
    }

    public function test_it_uses_read_committed_on_mysql(): void
    {
        $this->assertSame('READ COMMITTED', config('database.connections.mysql.isolation_level'));
    }
}
