<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class TaxCodeHttpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/tax-codes';

    private int $vatOld;

    private int $vatCurrent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        $this->withoutVite();
        Carbon::setTestNow('2026-06-15 10:00:00');

        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
        ]);
        $this->vatOld = $this->row('AF', 'VAT_STD', 'Standard tax', '10.00000', '2025-01-01', '2025-12-31');
        $this->vatCurrent = $this->row('AF', 'VAT_STD', 'Standard tax', '12.50000', '2026-01-01', null);
        $this->row('AF', 'BRT', 'Receipts tax', '4.00000', '2027-01-01', null);
        $this->row('PK', 'GST', 'Sales tax', '17.00000', '2024-01-01', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guests_are_redirected_and_access_is_closed_by_default(): void
    {
        $this->get(self::BASE)->assertRedirect(route('login'));

        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE.'?country=AF')->assertForbidden();
        $this->post(self::BASE, $this->newCode())->assertForbidden();
        $this->put(self::BASE."/{$this->vatCurrent}", ['description' => 'X'])->assertForbidden();
        $this->patch(self::BASE."/{$this->vatCurrent}/close", ['effective_to' => '2026-12-31'])->assertForbidden();
        $this->post(self::BASE."/{$this->vatCurrent}/versions", ['rate_percent' => '15', 'effective_from' => '2027-01-01'])->assertForbidden();
        $this->get(self::BASE.'/export/xlsx?country=AF')->assertForbidden();

        $this->assertDatabaseCount('tax_codes', 4);
        $this->assertNull(DB::table('tax_codes')->where('id', $this->vatCurrent)->value('effective_to'));
    }

    public function test_page_asks_for_a_country_then_lists_versions_newest_first(): void
    {
        $this->openAccess();

        $this->get(self::BASE)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/master-data/tax-codes/index')
            ->where('taxCodes', null)
            ->where('today', '2026-06-15')
            ->has('countries', 2));

        $this->get(self::BASE.'?country=AF')->assertInertia(fn (Assert $page) => $page
            ->has('taxCodes.data', 3)
            ->where('taxCodes.data.0.code', 'BRT')
            ->where('taxCodes.data.0.status', 'future')
            ->where('taxCodes.data.1', [
                'id' => $this->vatCurrent, 'country_code' => 'AF', 'code' => 'VAT_STD', 'description' => 'Standard tax',
                'rate_percent' => '12.50000', 'effective_from' => '2026-01-01', 'effective_to' => null, 'status' => 'current',
            ])
            ->where('taxCodes.data.2.id', $this->vatOld)
            ->where('taxCodes.data.2.status', 'ended'));
    }

    public function test_filters_search_sort_and_lenient_parameters(): void
    {
        $this->openAccess();

        $this->get(self::BASE.'?country=AF&on=2025-06-01')->assertInertia(fn (Assert $page) => $page
            ->has('taxCodes.data', 1)
            ->where('taxCodes.data.0.id', $this->vatOld));

        $this->get(self::BASE.'?country=AF&on=2026-01-01')->assertInertia(fn (Assert $page) => $page
            ->has('taxCodes.data', 1)
            ->where('taxCodes.data.0.id', $this->vatCurrent));

        $this->get(self::BASE.'?country=AF&q=receipts')->assertInertia(fn (Assert $page) => $page
            ->has('taxCodes.data', 1)
            ->where('taxCodes.data.0.code', 'BRT'));

        $this->get(self::BASE.'?country=AF&sort=rate_percent&dir=desc')->assertInertia(fn (Assert $page) => $page
            ->where('taxCodes.data.0.rate_percent', '12.50000')
            ->where('taxCodes.data.2.rate_percent', '4.00000'));

        $this->get(self::BASE.'?country=AF&sort=DROP&dir=x&on=2026-02-30&per_page=7&q=%25')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'code')
            ->where('filters.dir', 'asc')
            ->where('filters.on', '')
            ->where('filters.per_page', 25)
            ->has('taxCodes.data', 0));

        $this->get(self::BASE.'?country=ZZ')->assertOk()->assertInertia(fn (Assert $page) => $page->where('taxCodes', null));
    }

    public function test_store_edit_close_and_new_version(): void
    {
        $this->openAccess();

        $this->post(self::BASE, $this->newCode())->assertRedirect()->assertSessionHasNoErrors();
        $id = (int) DB::table('tax_codes')->where('code', 'SVC_TAX')->value('id');
        $this->assertSame('5.25000', number_format((float) DB::table('tax_codes')->where('id', $id)->value('rate_percent'), 5, '.', ''));

        $this->put(self::BASE."/{$id}", ['description' => 'Service tax (amended)'])->assertSessionHasNoErrors();
        $this->assertSame('Service tax (amended)', DB::table('tax_codes')->where('id', $id)->value('description'));

        $this->put(self::BASE."/{$id}", ['description' => 'X', 'rate_percent' => '6'])->assertSessionHasErrors('rate_percent');

        $this->post(self::BASE."/{$this->vatCurrent}/versions", ['rate_percent' => '15', 'effective_from' => '2027-01-01'])->assertSessionHasNoErrors();
        $this->assertSame('2026-12-31', DB::table('tax_codes')->where('id', $this->vatCurrent)->value('effective_to'));
        $this->assertSame(1, DB::table('tax_codes')->where('code', 'VAT_STD')->whereNull('effective_to')->count());

        $this->patch(self::BASE."/{$id}/close", ['effective_to' => '2026-12-31'])->assertSessionHasNoErrors();
        $this->assertSame('2026-12-31', DB::table('tax_codes')->where('id', $id)->value('effective_to'));
        $this->patch(self::BASE."/{$id}/close", ['effective_to' => '2027-12-31'])->assertSessionHasErrors('effective_to');
    }

    public function test_invalid_input_is_rejected_without_writing(): void
    {
        $this->openAccess();

        $this->post(self::BASE, $this->newCode(['rate_percent' => '100.5']))->assertSessionHasErrors('rate_percent');
        $this->post(self::BASE, $this->newCode(['code' => 'bad code!']))->assertSessionHasErrors('code');
        $this->post(self::BASE, $this->newCode(['country_code' => 'ZZ']))->assertSessionHasErrors('country_code');
        $this->post(self::BASE, $this->newCode(['effective_to' => '2026-01-01']))->assertSessionHasErrors('effective_to');
        $this->post(self::BASE, $this->newCode(['code' => 'VAT_STD', 'effective_from' => '2026-03-01']))->assertSessionHasErrors('effective_from');
        $this->post(self::BASE, $this->newCode(['description' => "Tax\u{202E}"]))->assertSessionHasErrors('description');

        $this->assertDatabaseCount('tax_codes', 4);
    }

    public function test_unknown_malformed_and_delete_requests(): void
    {
        $this->openAccess();

        $this->put(self::BASE.'/99999', ['description' => 'X'])->assertNotFound();
        $this->put(self::BASE.'/abc', ['description' => 'X'])->assertNotFound();
        $this->patch(self::BASE.'/99999/close', ['effective_to' => '2026-12-31'])->assertNotFound();
        $this->delete(self::BASE."/{$this->vatCurrent}")->assertMethodNotAllowed();
        $this->assertDatabaseCount('tax_codes', 4);
    }

    public function test_dari_interface_translates_the_page(): void
    {
        $this->openAccess();
        $this->post('/locale', ['locale' => 'fa-AF']);

        $this->get(self::BASE)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')
            ->where('translations.Tax codes', 'کودهای مالیه'));
    }

    public function test_xlsx_export_requires_a_country_and_keeps_rates_as_text(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();

        $this->get(self::BASE.'/export/xlsx')->assertStatus(422);

        $response = $this->get(self::BASE.'/export/xlsx?country=AF&q=VAT')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame(['Country', 'Code', 'Description', 'Translated name', 'Rate (%)', 'Effective from', 'Effective to', 'Status'], $sheet->rangeToArray('A1:H1')[0]);
        $this->assertSame(['AF', 'VAT_STD', 'Standard tax'], $sheet->rangeToArray('A2:C2')[0]);
        $this->assertSame(['12.50000', '2026-01-01', 'Open-ended', 'In effect'], $sheet->rangeToArray('E2:H2')[0]);
        $this->assertSame('10.00000', $sheet->getCell('E3')->getValue());
        $this->assertSame('Ended', $sheet->getCell('H3')->getValue());
        $this->assertNull($sheet->getCell('B4')->getValue());
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        if (! class_exists(Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf is not installed.');
        }
        $this->openAccess();

        $this->assertStringStartsWith('%PDF', (string) $this->get(self::BASE.'/export/pdf?country=AF')->assertOk()->getContent());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function newCode(array $overrides = []): array
    {
        return [
            'country_code' => 'AF',
            'code' => 'SVC_TAX',
            'description' => 'Service tax',
            'rate_percent' => '5.25',
            'effective_from' => '2026-07-01',
            'effective_to' => '',
            ...$overrides,
        ];
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function row(string $country, string $code, string $description, string $rate, string $from, ?string $to): int
    {
        return DB::table('tax_codes')->insertGetId([
            'country_code' => $country, 'code' => $code, 'description' => $description,
            'rate_percent' => $rate, 'effective_from' => $from, 'effective_to' => $to,
        ]);
    }
}
