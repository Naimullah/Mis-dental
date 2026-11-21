<?php

namespace Tests\Feature;

use App\Services\Countries\CountryQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CountryDatabaseTestCase;

class CountryQueryServiceTest extends CountryDatabaseTestCase
{
    private CountryQueryService $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->query = new CountryQueryService;
    }

    public function test_default_listing_uses_iso2_order_and_twenty_rows_per_page(): void
    {
        $codes = array_map(fn (string $letter): string => 'A'.$letter, range('A', 'U'));
        foreach (array_reverse($codes) as $code) {
            $this->insertCountry($code, null, 'Country '.$code);
        }

        $firstPage = $this->query->paginate();
        $secondPage = $this->query->paginate(['page' => '2']);

        $this->assertSame(20, $firstPage->perPage());
        $this->assertSame(21, $firstPage->total());
        $this->assertSame(2, $firstPage->lastPage());
        $this->assertSame(array_slice($codes, 0, 20), $firstPage->getCollection()->modelKeys());
        $this->assertSame(['AU'], $secondPage->getCollection()->modelKeys());
        $this->assertSame(100, $this->query->paginate(['per_page' => '100'])->perPage());
    }

    public function test_search_matches_all_three_fields_and_combines_with_status_filter(): void
    {
        $this->insertCountry('AF', 'AFG', 'Afghanistan');
        $this->insertCountry('DE', 'DEU', 'Germany', false);
        $this->insertCountry('GB', null, 'Éire sample');

        $this->assertSame(['AF'], $this->keys(['search' => ' af ']));
        $this->assertSame(['DE'], $this->keys(['search' => 'deu']));
        $this->assertSame(['DE'], $this->keys(['search' => 'MANY']));
        $this->assertSame(['GB'], $this->keys(['search' => 'Éire']));
        $this->assertSame([], $this->keys(['search' => 'deu', 'status' => 'active']));
        $this->assertSame(['DE'], $this->keys(['search' => 'deu', 'status' => 'inactive']));
        $this->assertSame(['AF', 'GB'], $this->keys(['status' => 'active']));
        $this->assertSame(['DE'], $this->keys(['status' => 'inactive']));
        $this->assertSame(['AF', 'DE', 'GB'], $this->keys(['status' => 'all']));
    }

    public function test_search_treats_sql_and_wildcard_characters_as_literal_text(): void
    {
        $this->insertCountry('AA', null, 'Plain');
        $this->insertCountry('AB', null, 'Literal %_! marker');

        $this->assertSame(['AB'], $this->keys(['search' => '%_!']));
        $this->assertSame([], $this->keys(['search' => "' OR 1=1 --"]));
        $this->assertDatabaseCount('countries', 2);
    }

    public function test_sorting_is_stable_across_pages_and_links_retain_query_options(): void
    {
        $this->insertCountry('CA', 'CAN', 'Test country');
        $this->insertCountry('BE', 'BEL', 'Test country');
        $this->insertCountry('AD', 'AND', 'Test entry');

        $options = ['search' => 'Test', 'status' => 'active', 'sort' => 'english_name', 'direction' => 'desc', 'per_page' => 2];
        $firstPage = $this->query->paginate($options);
        $secondPage = $this->query->paginate($options + ['page' => 2]);

        $this->assertSame(['AD', 'BE'], $firstPage->getCollection()->modelKeys());
        $this->assertSame(['CA'], $secondPage->getCollection()->modelKeys());
        parse_str((string) parse_url($firstPage->nextPageUrl(), PHP_URL_QUERY), $linkOptions);
        $this->assertSame(array_map('strval', $options + ['page' => 2]), $linkOptions);
        $this->assertSame(['CA', 'BE', 'AD'], $this->keys(['sort' => 'iso3', 'direction' => 'desc']));
    }

    #[DataProvider('invalidOptions')]
    public function test_invalid_query_options_are_rejected(string $field, mixed $value): void
    {
        try {
            $this->query->paginate([$field => $value]);
            $this->fail('An invalid query option was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame([$field], array_keys($exception->errors()));
        }
    }

    public static function invalidOptions(): array
    {
        return [
            ['search', ['AF']],
            ['search', str_repeat('a', 121)],
            ['status', 'pending'],
            ['sort', 'english_name desc, iso2'],
            ['sort', ['iso2']],
            ['direction', 'desc; select 1'],
            ['page', 0],
            ['page', '99999999999999999999'],
            ['per_page', 0],
            ['per_page', 101],
            ['per_page', 1.5],
        ];
    }

    private function insertCountry(string $iso2, ?string $iso3, string $name, bool $active = true): void
    {
        DB::table('countries')->insert([
            'iso2' => $iso2,
            'iso3' => $iso3,
            'english_name' => $name,
            'is_active' => $active,
        ]);
    }

    /** @param array<string, mixed> $options */
    private function keys(array $options): array
    {
        return $this->query->paginate($options)->getCollection()->modelKeys();
    }
}
