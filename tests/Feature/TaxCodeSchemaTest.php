<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** P01.10 database rules. */
class TaxCodeSchemaTest extends TestCase
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
        $columns = collect(Schema::getColumns('tax_codes'))->keyBy('name');

        $this->assertEqualsCanonicalizing(
            ['id', 'country_code', 'code', 'description', 'rate_percent', 'effective_from', 'effective_to'],
            $columns->keys()->all(),
        );
        foreach ($columns as $name => $column) {
            $this->assertSame($name === 'effective_to', $column['nullable'], $name.' nullability');
        }

        $indexes = collect(Schema::getIndexes('tax_codes'));
        $this->assertSame(['id'], $indexes->firstWhere('primary', true)['columns'] ?? []);
        $unique = $indexes->where('unique', true)->where('primary', false)->pluck('columns')->all();
        $this->assertContains(['country_code', 'code', 'effective_from'], $unique);
        $this->assertContains(['country_code', 'id'], $unique);
        $this->assertContains(['country_code', 'code', 'effective_to'], $indexes->pluck('columns')->all());

        $foreign = Schema::getForeignKeys('tax_codes');
        $this->assertCount(1, $foreign);
        $this->assertSame(['country_code'], $foreign[0]['columns']);
        $this->assertSame('countries', $foreign[0]['foreign_table']);
        $this->assertSame(['iso2'], $foreign[0]['foreign_columns']);
    }

    public function test_start_date_is_unique_per_code_and_country_must_exist(): void
    {
        $this->insert('AF', 'VAT_STD', '2026-01-01');
        $this->insert('AF', 'VAT_STD', '2027-01-01'); // later version of the same code
        $this->insert('PK', 'VAT_STD', '2026-01-01'); // same code in another country

        foreach ([fn () => $this->insert('AF', 'VAT_STD', '2026-01-01'), fn () => $this->insert('ZZ', 'VAT_STD', '2026-01-01')] as $insert) {
            try {
                $insert();
                $this->fail('Invalid tax code row was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('tax_codes', 3);
    }

    public function test_a_country_with_tax_codes_cannot_be_deleted(): void
    {
        $this->insert('AF', 'VAT_STD', '2026-01-01');

        $this->expectException(QueryException::class);
        DB::table('countries')->where('iso2', 'AF')->delete();
    }

    public function test_rate_keeps_five_decimal_places(): void
    {
        DB::table('tax_codes')->insert([
            'country_code' => 'AF', 'code' => 'BRT', 'description' => 'Receipts tax',
            'rate_percent' => '12.34567', 'effective_from' => '2026-01-01',
        ]);

        $stored = DB::table('tax_codes')->value('rate_percent');
        $this->assertSame('12.34567', number_format((float) $stored, 5, '.', ''));
    }

    private function insert(string $country, string $code, string $from): void
    {
        DB::table('tax_codes')->insert([
            'country_code' => $country,
            'code' => $code,
            'description' => 'Tax',
            'rate_percent' => '10.00000',
            'effective_from' => $from,
            'effective_to' => null,
        ]);
    }
}
