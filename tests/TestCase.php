<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: refuse to run against any non-sqlite / non-memory database.
     *
     * A stale bootstrap/cache/config.php once overrode phpunit.xml and pointed
     * the suite at the real MySQL database — RefreshDatabase then wiped it.
     * This guard makes that impossible: tests abort before touching the DB.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $default = config('database.default');
        $connection = config("database.connections.{$default}");

        $isSqlite = ($connection['driver'] ?? null) === 'sqlite';
        $isMemoryOrTestDb = in_array($connection['database'] ?? null, [':memory:', 'testing'], true);

        if (! $isSqlite || ! $isMemoryOrTestDb) {
            throw new RuntimeException(
                'Refusing to run tests: database connection is not sqlite :memory: '
                ."(driver=[{$connection['driver']}], database=[{$connection['database']}]). "
                .'Run `php artisan config:clear` — a cached config is overriding phpunit.xml.'
            );
        }
    }
}
