<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAccountsMigrationTest extends TestCase
{
    public function test_migration_backfills_existing_accounts_and_preserves_authentication_data(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());
        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getConfig('driver'));
        $this->assertSame(':memory:', $connection->getConfig('database'));
        $this->assertEmpty($connection->getConfig('read'));
        $this->assertEmpty($connection->getConfig('write'));

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2024_01_01_000000_create_passkeys_table.php',
            '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
            '2026_11_21_000002_create_locales_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        $password = Hash::make('existing-password');
        $id = DB::table('users')->insertGetId([
            'name' => 'Existing', 'email' => 'existing@example.test', 'password' => $password,
            'email_verified_at' => '2026-11-20 08:00:00', 'two_factor_secret' => 'existing-secret',
        ]);
        DB::table('passkeys')->insert([
            'user_id' => $id, 'name' => 'Existing key', 'credential_id' => 'key', 'credential' => '{}',
        ]);

        $migration = require database_path('migrations/2026_11_22_000019_align_users_for_platform_accounts.php');
        $migration->up();

        $this->assertDatabaseHas('users', [
            'id' => $id, 'username' => 'user-'.$id, 'scope' => 'platform', 'is_active' => true,
            'password' => $password, 'email_verified_at' => '2026-11-20 08:00:00',
            'two_factor_secret' => 'existing-secret',
        ]);
        $this->assertDatabaseHas('passkeys', ['user_id' => $id, 'credential_id' => 'key']);
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
        try {
            DB::table('users')->where('id', $id)->update(['scope' => 'unknown']);
            $this->fail('The database accepted an invalid account scope.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('CHECK constraint failed', $exception->getMessage());
        }

        $migration->down();
        $this->assertDatabaseHas('users', ['id' => $id, 'password' => $password]);
        $this->assertDatabaseHas('passkeys', ['user_id' => $id, 'credential_id' => 'key']);
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
    }
}
