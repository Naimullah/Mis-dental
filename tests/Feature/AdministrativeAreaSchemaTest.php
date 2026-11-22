<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** P01.05/P01.06 database rules, enforced even when the application is bypassed. */
class AdministrativeAreaSchemaTest extends TestCase
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
        DB::table('locales')->insert(['code' => 'fa-AF', 'english_name' => 'Dari (Afghanistan)', 'text_direction' => 'RTL', 'is_active' => true]);
    }

    public function test_area_columns_match_the_specification(): void
    {
        $columns = collect(Schema::getColumns('administrative_areas'))->keyBy('name');

        $this->assertEqualsCanonicalizing(
            ['id', 'country_code', 'parent_id', 'level_code', 'area_code', 'canonical_name', 'is_active'],
            $columns->keys()->all(),
        );
        foreach (['parent_id', 'area_code'] as $nullable) {
            $this->assertTrue($columns[$nullable]['nullable']);
        }
        foreach (['country_code', 'level_code', 'canonical_name', 'is_active'] as $required) {
            $this->assertFalse($columns[$required]['nullable']);
        }

        $this->assertEqualsCanonicalizing(
            ['area_id', 'locale_code', 'display_name'],
            array_column(Schema::getColumns('administrative_area_translations'), 'name'),
        );
        $primary = collect(Schema::getIndexes('administrative_area_translations'))->firstWhere('primary', true);
        $this->assertSame(['area_id', 'locale_code'], $primary['columns'] ?? []);
    }

    public function test_database_blocks_a_parent_in_another_country(): void
    {
        $kabul = $this->area('AF', null, 'Kabul');
        $this->area('AF', $kabul, 'Paghman');
        $this->area('PK', null, 'Punjab');

        $this->expectException(QueryException::class);
        $this->area('PK', $kabul, 'Cross-border child');
    }

    public function test_database_blocks_unknown_country_and_parent(): void
    {
        foreach ([fn () => $this->area('ZZ', null, 'Nowhere'), fn () => $this->area('AF', 999, 'Orphan')] as $insert) {
            try {
                $insert();
                $this->fail('Invalid area was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_area_codes_are_unique_per_country_when_present(): void
    {
        $this->area('AF', null, 'Kabul', 'KBL');
        $this->area('PK', null, 'Karachi', 'KBL'); // same code in another country
        $this->area('AF', null, 'No code one');
        $this->area('AF', null, 'No code two'); // several areas without a code

        $this->expectException(QueryException::class);
        $this->area('AF', null, 'Duplicate', 'KBL');
    }

    public function test_used_countries_and_parents_cannot_be_deleted(): void
    {
        $kabul = $this->area('AF', null, 'Kabul');
        $this->area('AF', $kabul, 'Paghman');

        foreach ([
            fn () => DB::table('administrative_areas')->where('id', $kabul)->delete(),
            fn () => DB::table('countries')->where('iso2', 'AF')->delete(),
        ] as $delete) {
            try {
                $delete();
                $this->fail('A referenced row was deleted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(2, DB::table('administrative_areas')->count());
    }

    public function test_one_display_name_per_area_and_locale_with_valid_references(): void
    {
        $kabul = $this->area('AF', null, 'Kabul');
        DB::table('administrative_area_translations')->insert(['area_id' => $kabul, 'locale_code' => 'fa-AF', 'display_name' => 'کابل']);

        foreach ([
            ['area_id' => $kabul, 'locale_code' => 'fa-AF', 'display_name' => 'Duplicate'],
            ['area_id' => $kabul, 'locale_code' => 'xx', 'display_name' => 'Unknown locale'],
            ['area_id' => 999, 'locale_code' => 'fa-AF', 'display_name' => 'Unknown area'],
        ] as $row) {
            try {
                DB::table('administrative_area_translations')->insert($row);
                $this->fail('Invalid translation was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('کابل', DB::table('administrative_area_translations')->value('display_name'));
    }

    private function area(string $country, ?int $parent, string $name, ?string $code = null): int
    {
        return DB::table('administrative_areas')->insertGetId([
            'country_code' => $country, 'parent_id' => $parent, 'level_code' => $parent === null ? 'province' : 'district',
            'area_code' => $code, 'canonical_name' => $name, 'is_active' => true,
        ]);
    }
}
