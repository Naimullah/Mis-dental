<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class IdentityDocumentTypeHttpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/identity-document-types';

    private int $tazkira;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        $this->withoutVite();

        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
        ]);
        $this->tazkira = $this->type('AF', 'NATIONAL_ID', 'Tazkira', '\d{4}-\d{4}-\d{5}', true);
        $this->type('AF', 'PASSPORT', 'Passport', '[A-Z]{1,2}\d{7}', true);
        $this->type('AF', 'OLD_ID', 'Old paper ID', null, false);
        $this->type('PK', 'CNIC', 'CNIC', '\d{5}-\d{7}-\d', true);
    }

    public function test_guests_are_redirected_and_access_is_closed_by_default(): void
    {
        $this->get(self::BASE)->assertRedirect(route('login'));

        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE.'?country=AF')->assertForbidden();
        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'NEW', 'name' => 'New'])->assertForbidden();
        $this->patch(self::BASE."/{$this->tazkira}/status", ['is_active' => false])->assertForbidden();
        $this->assertDatabaseCount('identity_document_types', 4);
    }

    public function test_page_asks_for_a_country_then_lists_and_filters(): void
    {
        $this->openAccess();

        $this->get(self::BASE)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/master-data/identity-document-types/index')
            ->where('documentTypes', null)
            ->where('patternMaxLength', 200));

        $this->get(self::BASE.'?country=AF')->assertInertia(fn (Assert $page) => $page
            ->has('documentTypes.data', 3)
            ->where('documentTypes.data.0', ['id' => $this->tazkira, 'country_code' => 'AF', 'code' => 'NATIONAL_ID', 'name' => 'Tazkira', 'optional_format_regex' => '\d{4}-\d{4}-\d{5}', 'is_active' => true]));

        $this->get(self::BASE.'?country=AF&status=inactive')->assertInertia(fn (Assert $page) => $page
            ->has('documentTypes.data', 1)->where('documentTypes.data.0.code', 'OLD_ID'));
        $this->get(self::BASE.'?country=AF&q=pass')->assertInertia(fn (Assert $page) => $page
            ->has('documentTypes.data', 1)->where('documentTypes.data.0.code', 'PASSPORT'));
        $this->get(self::BASE.'?country=AF&sort=name&dir=asc')->assertInertia(fn (Assert $page) => $page
            ->where('documentTypes.data.0.code', 'OLD_ID'));
    }

    public function test_store_update_and_status(): void
    {
        $this->openAccess();

        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'DRIVING', 'name' => 'Driving licence', 'optional_format_regex' => ''])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('identity_document_types', ['code' => 'DRIVING', 'optional_format_regex' => null, 'is_active' => true]);

        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'UNSAFE', 'name' => 'Unsafe', 'optional_format_regex' => '(a+)+'])
            ->assertSessionHasErrors(['optional_format_regex']);
        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'NATIONAL_ID', 'name' => 'Duplicate'])
            ->assertSessionHasErrors(['code']);

        $this->put(self::BASE."/{$this->tazkira}", ['name' => 'National ID (Tazkira)', 'optional_format_regex' => '\d{13}'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('identity_document_types', ['id' => $this->tazkira, 'name' => 'National ID (Tazkira)', 'optional_format_regex' => '\d{13}', 'code' => 'NATIONAL_ID']);
        $this->put(self::BASE."/{$this->tazkira}", ['name' => 'X', 'code' => 'CHANGED'])->assertSessionHasErrors(['code']);

        $this->patch(self::BASE."/{$this->tazkira}/status", ['is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('identity_document_types', ['id' => $this->tazkira, 'is_active' => false]);
    }

    public function test_unknown_malformed_and_delete_requests(): void
    {
        $this->openAccess();

        $this->put(self::BASE.'/99999', ['name' => 'X'])->assertNotFound();
        $this->put(self::BASE.'/abc', ['name' => 'X'])->assertNotFound();
        $this->delete(self::BASE."/{$this->tazkira}")->assertMethodNotAllowed();
        $this->assertDatabaseCount('identity_document_types', 4);
    }

    public function test_dari_interface_translates_the_page(): void
    {
        $this->openAccess();
        $this->post('/locale', ['locale' => 'fa-AF']);

        $this->get(self::BASE)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')
            ->where('translations.Identity document types', 'انواع اسناد هویت'));
    }

    public function test_xlsx_export_keeps_patterns_as_plain_text(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();
        $formulaLike = $this->type('AF', 'FORMULA', 'Formula-like', '=1+1', true);

        $this->get(self::BASE.'/export/xlsx')->assertStatus(422);

        $response = $this->get(self::BASE.'/export/xlsx?country=AF&q=formula')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame(['Country', 'Code', 'Name', 'Format pattern', 'Status'], $sheet->rangeToArray('A1:E1')[0]);
        $this->assertSame('=1+1', $sheet->getCell('D2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D2')->getDataType(), 'Never stored as a formula.');
        $this->assertGreaterThan(0, $formulaLike);
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function type(string $country, string $code, string $name, ?string $pattern, bool $active): int
    {
        return DB::table('identity_document_types')->insertGetId([
            'country_code' => $country, 'code' => $code, 'name' => $name, 'optional_format_regex' => $pattern, 'is_active' => $active,
        ]);
    }
}
