<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** P01.07 database rules. */
class LicenseTypeSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
        ]);
    }

    public function test_columns_and_keys_match_the_specification(): void
    {
        $columns = collect(Schema::getColumns('license_types'))->keyBy('name');

        $this->assertEqualsCanonicalizing(['id', 'country_code', 'code', 'name', 'applies_to', 'is_active'], $columns->keys()->all());
        foreach ($columns as $column) {
            $this->assertFalse($column['nullable'], $column['name'].' must be NOT NULL');
        }

        $indexes = collect(Schema::getIndexes('license_types'));
        $this->assertSame(['id'], $indexes->firstWhere('primary', true)['columns'] ?? []);
        $unique = $indexes->where('unique', true)->where('primary', false)->pluck('columns')->all();
        $this->assertContains(['country_code', 'code'], $unique);
        $this->assertContains(['country_code', 'id'], $unique);

        $foreign = Schema::getForeignKeys('license_types');
        $this->assertSame('countries', $foreign[0]['foreign_table']);
        $this->assertSame(['iso2'], $foreign[0]['foreign_columns']);
    }

    public function test_code_is_unique_per_country_and_country_must_exist(): void
    {
        $this->type('AF', 'DENTIST_LIC');
        $this->type('PK', 'DENTIST_LIC'); // same code in another country

        foreach ([fn () => $this->type('AF', 'DENTIST_LIC'), fn () => $this->type('ZZ', 'OTHER')] as $insert) {
            try {
                $insert();
                $this->fail('Invalid license type was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(2, DB::table('license_types')->count());
    }

    public function test_status_defaults_to_active_and_used_countries_cannot_be_deleted(): void
    {
        DB::table('license_types')->insert(['country_code' => 'AF', 'code' => 'CLINIC_LIC', 'name' => 'Clinic', 'applies_to' => 'facility']);
        $this->assertSame(1, (int) DB::table('license_types')->value('is_active'));

        $this->expectException(QueryException::class);
        DB::table('countries')->where('iso2', 'AF')->delete();
    }

    private function type(string $country, string $code): void
    {
        DB::table('license_types')->insert([
            'country_code' => $country, 'code' => $code, 'name' => 'Dentist', 'applies_to' => 'practitioner', 'is_active' => true,
        ]);
    }
}
