<?php

namespace Tests\Feature;

use App\Services\Locales\LocaleQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocaleQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());
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

        $migration = require database_path('migrations/2026_11_21_000002_create_locales_table.php');
        $migration->up();

        // Test-only fields for sorting while the schema extension awaits approval.
        Schema::table('locales', function (Blueprint $table): void {
            $table->string('native_name', 120)->nullable();
            $table->boolean('is_active');
        });

        DB::table('locales')->insert([
            ['code' => 'ps', 'english_name' => 'Shared', 'native_name' => 'Bravo', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'de', 'english_name' => 'Shared', 'native_name' => 'Zulu', 'text_direction' => 'LTR', 'is_active' => false],
            ['code' => 'en', 'english_name' => 'English', 'native_name' => 'Bravo', 'text_direction' => 'LTR', 'is_active' => true],
        ]);
    }

    #[DataProvider('approvedSorts')]
    public function test_approved_sorts_are_stable(string $column, string $direction, array $expected): void
    {
        $query = new LocaleQuery;
        $filters = $query->filters(Request::create('/', 'GET', ['sort' => $column, 'dir' => $direction]));

        $this->assertSame($column, $filters['sort']);
        $this->assertSame($direction, $filters['dir']);
        $this->assertSame($expected, $query->build($filters)->get()->modelKeys());
    }

    public static function approvedSorts(): array
    {
        return [
            'code ascending' => ['code', 'asc', ['de', 'en', 'ps']],
            'code descending' => ['code', 'desc', ['ps', 'en', 'de']],
            'English name' => ['english_name', 'desc', ['de', 'ps', 'en']],
            'native name' => ['native_name', 'asc', ['en', 'ps', 'de']],
            'direction' => ['text_direction', 'asc', ['de', 'en', 'ps']],
            'active status' => ['is_active', 'desc', ['en', 'ps', 'de']],
        ];
    }

    public function test_unapproved_sort_inputs_fall_back_to_code_at_both_entry_points(): void
    {
        $query = new LocaleQuery;
        $filters = $query->filters(Request::create('/', 'GET', [
            'sort' => 'english_name desc, code', 'dir' => 'desc; select 1',
        ]));

        $this->assertSame('code', $filters['sort']);
        $this->assertSame('asc', $filters['dir']);
        $this->assertSame(['de', 'en', 'ps'], $query->build(array_replace($filters, [
            'sort' => 'password', 'dir' => 'desc; select 1',
        ]))->get()->modelKeys());
    }
}
