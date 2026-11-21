<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use PDO;

abstract class CurrencyDatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());

        // Inspect resolved configuration, including any database URL, before opening PDO.
        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getConfig('driver'));
        $this->assertSame(':memory:', $connection->getConfig('database'));
        $this->assertEmpty($connection->getConfig('read'));
        $this->assertEmpty($connection->getConfig('write'));

        $pdo = $connection->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(['main'], array_column($databases, 'name'));
        $this->assertSame([''], array_column($databases, 'file'));

        $migration = require database_path('migrations/2026_11_21_000001_create_currencies_table.php');
        $migration->up();
    }
}
