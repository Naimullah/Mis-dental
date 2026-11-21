<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class CurrencyHttpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/currencies';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', DB::connection()->getConfig('driver'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(self::BASE)->assertRedirect(route('login'));
    }

    public function test_access_is_closed_by_default(): void
    {
        config(['master_data.open_access' => false]);
        $this->seedCurrencies();
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE)->assertForbidden();
        $this->post(self::BASE, $this->validInput())->assertForbidden();
        $this->put(self::BASE.'/AFN', ['english_name' => 'Changed', 'minor_units' => 2, 'is_active' => true])->assertForbidden();
        $this->patch(self::BASE.'/AFN/status', ['is_active' => false])->assertForbidden();
        $this->get(self::BASE.'/export/xlsx')->assertForbidden();
        $this->get(self::BASE.'/export/pdf')->assertForbidden();

        $this->assertDatabaseCount('currencies', 5);
        $this->assertDatabaseHas('currencies', ['code' => 'AFN', 'english_name' => 'Afghani', 'is_active' => true]);
    }

    public function test_access_stays_closed_in_production_even_when_switch_is_on(): void
    {
        config(['master_data.open_access' => true]);
        $this->app['env'] = 'production';
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE)->assertForbidden();
    }

    public function test_index_lists_currencies_sorted_by_code_with_default_filters(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $this->get(self::BASE)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/master-data/currencies/index')
                ->has('currencies.data', 5)
                ->where('currencies.data.0.code', 'AFN')
                ->where('currencies.data.0.symbol', '؋')
                ->where('currencies.data.0.minor_units', 2)
                ->where('currencies.data.0.is_active', true)
                ->where('currencies.data.4.code', 'USD')
                ->where('filters', ['q' => '', 'status' => 'all', 'sort' => 'code', 'dir' => 'asc', 'per_page' => 25])
                ->where('can.manageMasterData', true)
                ->has('exportsAvailable'));
    }

    public function test_search_matches_code_name_and_symbol_case_insensitively(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        foreach (['usd' => 'USD', 'DOLLAR' => 'USD', '؋' => 'AFN', 'yen' => 'JPY'] as $term => $code) {
            $this->get(self::BASE.'?q='.urlencode($term))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('currencies.data', 1)
                    ->where('currencies.data.0.code', $code));
        }
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        foreach (['%', '_', '\\'] as $term) {
            $this->get(self::BASE.'?q='.urlencode($term))
                ->assertInertia(fn (Assert $page) => $page->has('currencies.data', 0));
        }
    }

    public function test_status_filter_and_sort_with_stable_tie_breaker(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $this->get(self::BASE.'?status=inactive')
            ->assertInertia(fn (Assert $page) => $page
                ->has('currencies.data', 1)
                ->where('currencies.data.0.code', 'EUR'));

        $this->get(self::BASE.'?status=active')
            ->assertInertia(fn (Assert $page) => $page->has('currencies.data', 4));

        $this->get(self::BASE.'?sort=minor_units&dir=desc')
            ->assertInertia(function (Assert $page): void {
                $page->has('currencies.data', 5);
                foreach (['KWD', 'AFN', 'EUR', 'USD', 'JPY'] as $index => $code) {
                    $page->where("currencies.data.{$index}.code", $code);
                }
            });
    }

    public function test_invalid_filters_fall_back_to_defaults(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $this->get(self::BASE.'?sort=password&dir=sideways&status=deleted&per_page=7&q[]=x')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['q' => '', 'status' => 'all', 'sort' => 'code', 'dir' => 'asc', 'per_page' => 25])
                ->has('currencies.data', 5));
    }

    public function test_pagination_keeps_query_string(): void
    {
        $this->openAccess();

        $rows = [];
        foreach (range(0, 29) as $index) {
            $code = 'Q'.chr(65 + intdiv($index, 26)).chr(65 + $index % 26);
            $rows[] = ['code' => $code, 'english_name' => "Test {$code}", 'symbol' => null, 'minor_units' => 2, 'is_active' => true];
        }
        DB::table('currencies')->insert($rows);

        $this->get(self::BASE.'?per_page=10&page=2&status=active')
            ->assertInertia(fn (Assert $page) => $page
                ->has('currencies.data', 10)
                ->where('currencies.current_page', 2)
                ->where('currencies.last_page', 3)
                ->where('currencies.total', 30)
                ->where('currencies.data.0.code', 'QAK')
                ->where('currencies.next_page_url', fn (string $url): bool => str_contains($url, 'per_page=10')
                    && str_contains($url, 'status=active')
                    && str_contains($url, 'page=3')));
    }

    public function test_store_creates_a_currency(): void
    {
        $this->openAccess();

        $this->post(self::BASE, $this->validInput())->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('currencies', [
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);
    }

    public function test_store_reports_validation_errors_and_saves_nothing(): void
    {
        $this->openAccess();

        $this->post(self::BASE, ['code' => 'afn', 'english_name' => ' ', 'symbol' => str_repeat('€', 13), 'minor_units' => 5, 'is_active' => 'maybe'])
            ->assertSessionHasErrors(['code', 'english_name', 'symbol', 'minor_units', 'is_active']);

        $this->assertDatabaseCount('currencies', 0);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $this->post(self::BASE, $this->validInput())->assertSessionHasErrors(['code']);
        $this->assertDatabaseCount('currencies', 5);
    }

    public function test_update_changes_details_but_never_the_code(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $this->put(self::BASE.'/AFN', ['english_name' => ' Afghan Afghani ', 'symbol' => '', 'minor_units' => '2', 'is_active' => true])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('currencies', ['code' => 'AFN', 'english_name' => 'Afghan Afghani', 'symbol' => null]);

        $this->put(self::BASE.'/AFN', ['code' => 'AFX', 'english_name' => 'Afghani', 'minor_units' => 2, 'is_active' => true])
            ->assertSessionHasErrors(['code']);
        $this->assertDatabaseMissing('currencies', ['code' => 'AFX']);
    }

    public function test_status_route_deactivates_and_activates(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $this->patch(self::BASE.'/AFN/status', ['is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('currencies', ['code' => 'AFN', 'is_active' => false]);

        $this->patch(self::BASE.'/AFN/status', ['is_active' => true])->assertRedirect();
        $this->assertDatabaseHas('currencies', ['code' => 'AFN', 'is_active' => true]);

        $this->patch(self::BASE.'/AFN/status', ['is_active' => 'perhaps'])->assertSessionHasErrors(['is_active']);
        $this->assertDatabaseHas('currencies', ['code' => 'AFN', 'is_active' => true, 'english_name' => 'Afghani']);
    }

    public function test_unknown_or_malformed_codes_return_not_found(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $details = ['english_name' => 'X', 'minor_units' => 2, 'is_active' => true];
        $this->put(self::BASE.'/XXX', $details)->assertNotFound();
        $this->put(self::BASE.'/afn', $details)->assertNotFound();
        $this->put(self::BASE.'/AFNX', $details)->assertNotFound();
        $this->patch(self::BASE.'/XXX/status', ['is_active' => false])->assertNotFound();
    }

    public function test_currencies_cannot_be_deleted(): void
    {
        $this->openAccess();
        $this->seedCurrencies();

        $this->delete(self::BASE.'/AFN')->assertMethodNotAllowed();
        $this->assertDatabaseCount('currencies', 5);
    }

    public function test_locale_switch_shares_rtl_direction_and_translations(): void
    {
        $this->openAccess();

        $this->post('/locale', ['locale' => 'fa-AF'])->assertRedirect()->assertSessionHas('locale', 'fa-AF');

        $this->get(self::BASE)
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fa-AF')
                ->where('direction', 'rtl')
                ->where('translations.Currencies', 'واحدهای پولی'));

        $this->post('/locale', ['locale' => 'ps-AF']);
        $this->get(self::BASE)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')
            ->where('translations.Currencies', 'پولي واحدونه'));

        $this->post('/locale', ['locale' => 'en']);
        $this->get(self::BASE)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'ltr')
            ->where('translations', []));
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/locale', ['locale' => 'de'])->assertSessionHasErrors(['locale']);
        $this->assertNull(session('locale'));
    }

    public function test_translation_files_share_the_same_keys(): void
    {
        $dari = json_decode((string) file_get_contents(lang_path('fa-AF.json')), true);
        $pashto = json_decode((string) file_get_contents(lang_path('ps-AF.json')), true);

        $this->assertIsArray($dari);
        $this->assertIsArray($pashto);
        $this->assertEqualsCanonicalizing(array_keys($dari), array_keys($pashto));
        $this->assertNotContains('', array_values($dari));
        $this->assertNotContains('', array_values($pashto));
    }

    public function test_exports_report_missing_packages(): void
    {
        $this->openAccess();

        if (! class_exists(Spreadsheet::class)) {
            $this->get(self::BASE.'/export/xlsx')->assertStatus(501);
        }
        if (! class_exists(Mpdf::class)) {
            $this->get(self::BASE.'/export/pdf')->assertStatus(501);
        }

        $this->assertTrue(true);
    }

    public function test_xlsx_export_uses_list_filters(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();
        $this->seedCurrencies();

        $response = $this->get(self::BASE.'/export/xlsx?status=inactive')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('/currencies-\d{8}-\d{4}\.xlsx/', (string) $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame(['Code', 'English name', 'Symbol', 'Minor units', 'Status'], $sheet->rangeToArray('A1:E1')[0]);
        $this->assertSame('EUR', $sheet->getCell('A2')->getValue());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertNull($sheet->getCell('A3')->getValue());
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        if (! class_exists(Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf is not installed.');
        }
        $this->openAccess();
        $this->seedCurrencies();

        $response = $this->get(self::BASE.'/export/pdf')->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_export_row_cap_is_enforced(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();
        $this->seedCurrencies();
        config(['master_data.export_max_rows' => 2]);

        $this->get(self::BASE.'/export/xlsx')->assertStatus(422);
    }

    private function openAccess(): User
    {
        config(['master_data.open_access' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function seedCurrencies(): void
    {
        DB::table('currencies')->insert([
            ['code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true],
            ['code' => 'USD', 'english_name' => 'US Dollar', 'symbol' => '$', 'minor_units' => 2, 'is_active' => true],
            ['code' => 'EUR', 'english_name' => 'Euro', 'symbol' => '€', 'minor_units' => 2, 'is_active' => false],
            ['code' => 'JPY', 'english_name' => 'Yen', 'symbol' => '¥', 'minor_units' => 0, 'is_active' => true],
            ['code' => 'KWD', 'english_name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'minor_units' => 3, 'is_active' => true],
        ]);
    }

    /** @return array<string, mixed> */
    private function validInput(): array
    {
        return ['code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true];
    }
}
