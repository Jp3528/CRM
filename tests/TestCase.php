<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTestingEnvironment();
    }

    /**
     * Safety guard to prevent tests from ever running against development or production databases.
     */
    protected function ensureTestingEnvironment(): void
    {
        if (config('app.env') !== 'testing') {
            throw new \RuntimeException('ABORTED: Tests must only run in APP_ENV=testing. Current env: '.config('app.env'));
        }

        $connection = config('database.default');
        $databaseName = (string) config("database.connections.{$connection}.database");

        // SQLite in-memory or designated test sqlite file is allowed
        if ($connection === 'sqlite') {
            if ($databaseName !== ':memory:' && ! str_contains($databaseName, 'test')) {
                throw new \RuntimeException("ABORTED: SQLite database '{$databaseName}' is not explicitly an in-memory or testing database.");
            }

            return;
        }

        // For external relational databases (pgsql, mysql, etc.), enforce that the database name contains 'test'
        if (! str_contains(strtolower($databaseName), 'test')) {
            throw new \RuntimeException("ABORTED: Database '{$databaseName}' is not a designated test database. Database name must contain 'test'.");
        }
    }
}
