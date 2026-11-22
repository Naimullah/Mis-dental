<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** P01.08 database rules. */
class IdentityDocumentTypeSchemaTest extends TestCase
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
        $columns = collect(Schema::getColumns('identity_document_types'))->keyBy('name');

        $this->assertEqualsCanonicalizing(['id', 'country_code', 'code', 'name', 'optional_format_regex', 'is_active'], $columns->keys()->all());
        $this->assertTrue($columns['optional_format_regex']['nullable']);
        foreach (['country_code', 'code', 'name', 'is_active'] as $required) {
            $this->assertFalse($columns[$required]['nullable']);
        }

        $unique = collect(Schema::getIndexes('identity_document_types'))->where('unique', true)->where('primary', false)->pluck('columns')->all();
        $this->assertContains(['country_code', 'code'], $unique);
        $this->assertContains(['country_code', 'id'], $unique);
        $this->assertSame('countries', Schema::getForeignKeys('identity_document_types')[0]['foreign_table']);
    }

    /** Acceptance focus: no identity document is ever mandatory to create a patient. */
    public function test_there_is_no_required_or_mandatory_flag(): void
    {
        foreach (Schema::getColumnListing('identity_document_types') as $column) {
            $this->assertDoesNotMatchRegularExpression('/required|mandatory|compulsory/i', $column);
        }
    }

    public function test_code_is_unique_per_country_and_country_must_exist(): void
    {
        $this->type('AF', 'NATIONAL_ID');
        $this->type('PK', 'NATIONAL_ID');

        foreach ([fn () => $this->type('AF', 'NATIONAL_ID'), fn () => $this->type('ZZ', 'OTHER')] as $insert) {
            try {
                $insert();
                $this->fail('Invalid document type was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(2, DB::table('identity_document_types')->count());
    }

    private function type(string $country, string $code): void
    {
        DB::table('identity_document_types')->insert([
            'country_code' => $country, 'code' => $code, 'name' => 'National ID', 'optional_format_regex' => null, 'is_active' => true,
        ]);
    }
}
