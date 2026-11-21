<?php

namespace Tests\Feature;

use App\Models\Locale;
use Database\Seeders\LocaleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PDO;
use Tests\TestCase;

class LocaleSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());

        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getConfig('driver'));
        $this->assertSame(':memory:', $connection->getConfig('database'));
        $this->assertEmpty($connection->getConfig('read'));
        $this->assertEmpty($connection->getConfig('write'));
        $this->assertSame([$connection->getName()], $this->connectionsToTransact());

        $pdo = $connection->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(['main'], array_column($databases, 'name'));
        $this->assertSame([''], array_column($databases, 'file'));
    }

    public function test_schema_has_exactly_seven_columns_and_code_primary_key(): void
    {
        $columns = collect(Schema::getColumns('locales'))->keyBy('name');
        $required = ['code', 'english_name', 'text_direction', 'is_active'];
        $optional = ['native_name', 'default_calendar_code', 'default_numbering_system_code'];

        $primary = collect(Schema::getIndexes('locales'))->firstWhere('primary', true);
        $this->assertSame(['code'], $primary['columns'] ?? []);
        $this->assertEqualsCanonicalizing([...$required, ...$optional], $columns->keys()->all());
        foreach ($required as $name) {
            $this->assertFalse($columns[$name]['nullable']);
        }
        foreach ($optional as $name) {
            $this->assertTrue($columns[$name]['nullable']);
        }
    }

    public function test_database_accepts_null_optional_values_and_rejects_null_status(): void
    {
        $optional = ['native_name' => null, 'default_calendar_code' => null, 'default_numbering_system_code' => null];
        $this->assertTrue(Schema::hasColumns('locales', ['is_active', ...array_keys($optional)]), 'The Step 02 schema extension is required.');

        $row = ['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR', 'is_active' => false, ...$optional];
        DB::table('locales')->insert($row);
        $this->assertDatabaseHas('locales', $row);
        $this->assertFalse(Locale::query()->findOrFail('en')->is_active);

        $this->expectException(QueryException::class);
        DB::table('locales')->insert(array_replace($row, ['code' => 'de', 'is_active' => null]));
    }

    public function test_database_rejects_duplicate_and_null_values(): void
    {
        DB::table('locales')->insert(['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR']);

        foreach ([
            ['code' => 'en', 'english_name' => 'Duplicate', 'text_direction' => 'LTR'],
            ['code' => null, 'english_name' => 'X', 'text_direction' => 'LTR'],
            ['code' => 'fa', 'english_name' => null, 'text_direction' => 'RTL'],
            ['code' => 'ps', 'english_name' => 'Pashto', 'text_direction' => null],
        ] as $row) {
            try {
                DB::table('locales')->insert($row);
                $this->fail('Invalid row was accepted: '.json_encode($row));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, DB::table('locales')->count());
    }

    public function test_model_key_and_immutable_code(): void
    {
        $locale = new Locale(['english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL']);
        $this->assertSame('code', $locale->getKeyName());
        $this->assertSame('string', $locale->getKeyType());
        $this->assertFalse($locale->getIncrementing());
        $this->assertFalse($locale->usesTimestamps());
        $this->assertFalse($locale->isFillable('code'));
        $this->assertSame('boolean', $locale->getCasts()['is_active']);
        $this->assertEqualsCanonicalizing([
            'english_name', 'text_direction', 'is_active', 'native_name',
            'default_calendar_code', 'default_numbering_system_code',
        ], $locale->getFillable());

        $locale->code = 'ps-AF';
        $locale->save();
        $this->assertSame('ps-AF', Locale::query()->findOrFail('ps-AF')->getKey());

        $this->expectException(LogicException::class);
        $locale->code = 'ps';
    }

    public function test_seeder_adds_interface_languages_without_overwriting(): void
    {
        DB::table('locales')->insert(['code' => 'en', 'english_name' => 'English (custom)', 'text_direction' => 'LTR']);

        $this->seed(LocaleSeeder::class);
        $this->seed(LocaleSeeder::class);

        $this->assertSame(3, DB::table('locales')->count());
        $this->assertDatabaseHas('locales', ['code' => 'en', 'english_name' => 'English (custom)']);
        $this->assertDatabaseHas('locales', ['code' => 'fa-AF', 'english_name' => 'Dari (Afghanistan)', 'text_direction' => 'RTL']);
        $this->assertDatabaseHas('locales', ['code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL']);
    }

    public function test_catalog_entries_are_well_formed(): void
    {
        foreach (config('locale_catalog') as $code => $entry) {
            $this->assertMatchesRegularExpression('/\A[a-z]{2,3}(-[A-Z][a-z]{3})?(-[A-Z]{2})?\z/', $code);
            $this->assertContains($entry['direction'], ['LTR', 'RTL']);
            $this->assertLessThanOrEqual(100, mb_strlen($entry['name']));
            $this->assertLessThanOrEqual(20, strlen($code));
        }

        $this->assertArrayNotHasKey('af', config('locale_catalog'));
    }
}
