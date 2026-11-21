<?php

namespace Tests\Feature;

use App\Models\Country;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CountrySchemaTest extends TestCase
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

        $migration = require database_path('migrations/2026_11_21_000000_create_countries_table.php');
        $migration->up();
    }

    public function test_schema_has_exactly_four_columns_with_expected_nullability(): void
    {
        $columns = collect(Schema::getColumns('countries'))->keyBy('name');

        $this->assertEqualsCanonicalizing(['iso2', 'iso3', 'english_name', 'is_active'], $columns->keys()->all());
        $this->assertTrue($columns['iso3']['nullable']);
        foreach (['iso2', 'english_name', 'is_active'] as $name) {
            $this->assertFalse($columns[$name]['nullable']);
        }

        $primary = collect(Schema::getIndexes('countries'))->firstWhere('primary', true);
        $this->assertSame(['iso2'], $primary['columns'] ?? []);
    }

    public function test_multiple_countries_can_have_null_iso3(): void
    {
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => null, 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'DE', 'iso3' => null, 'english_name' => 'Germany', 'is_active' => false],
        ]);

        $this->assertSame(2, DB::table('countries')->whereNull('iso3')->count());
    }

    #[DataProvider('invalidRows')]
    public function test_database_rejects_invalid_rows(array $overrides): void
    {
        DB::table('countries')->insert([
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        DB::table('countries')->insert(array_replace([
            'iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Germany', 'is_active' => false,
        ], $overrides));
    }

    public static function invalidRows(): array
    {
        return [
            'duplicate ISO2' => [['iso2' => 'AF']],
            'duplicate ISO3' => [['iso3' => 'AFG']],
            'null ISO2' => [['iso2' => null]],
            'null name' => [['english_name' => null]],
            'null status' => [['is_active' => null]],
        ];
    }

    public function test_model_declares_key_cast_timestamps_and_fillable_attributes(): void
    {
        $country = new Country;

        $this->assertSame('iso2', $country->getKeyName());
        $this->assertSame('string', $country->getKeyType());
        $this->assertFalse($country->getIncrementing());
        $this->assertFalse($country->usesTimestamps());
        $this->assertSame('boolean', $country->getCasts()['is_active'] ?? null);
        $this->assertEqualsCanonicalizing(['iso3', 'english_name', 'is_active'], $country->getFillable());
    }

    public function test_model_persists_string_key_unicode_name_and_boolean_status(): void
    {
        $country = new Country(['iso3' => 'AFG', 'english_name' => 'افغانستان', 'is_active' => false]);
        $country->iso2 = 'AF';
        $country->save();

        $country = Country::query()->findOrFail('AF');
        $this->assertSame('AF', $country->getKey());
        $this->assertSame('افغانستان', $country->english_name);
        $this->assertFalse($country->is_active);

        $country->update(['is_active' => true]);
        $this->assertTrue($country->refresh()->is_active);
    }

    public function test_model_prevents_changing_persisted_iso2(): void
    {
        $country = new Country(['english_name' => 'Afghanistan', 'is_active' => true]);
        $this->assertFalse($country->isFillable('iso2'));
        $country->iso2 = 'AF';
        $country->save();

        $this->expectException(LogicException::class);
        $country->iso2 = 'DE';
    }
}
