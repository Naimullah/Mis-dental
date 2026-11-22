<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class LicenseTypeHttpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/license-types';

    private int $dentist;

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
        $this->dentist = $this->type('AF', 'DENTIST_LIC', 'Dentist', 'practitioner', true);
        $this->type('AF', 'CLINIC_LIC', 'Clinic', 'facility', true);
        $this->type('AF', 'COMPANY_LIC', 'Company', 'legal_entity', false);
        $this->type('PK', 'PK_DENTIST', 'Dentist (PK)', 'practitioner', true);
    }

    public function test_guests_are_redirected_and_access_is_closed_by_default(): void
    {
        $this->get(self::BASE)->assertRedirect(route('login'));

        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE.'?country=AF')->assertForbidden();
        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'NEW_LIC', 'name' => 'New', 'applies_to' => 'facility'])->assertForbidden();
        $this->patch(self::BASE."/{$this->dentist}/status", ['is_active' => false])->assertForbidden();
        $this->get(self::BASE.'/export/xlsx?country=AF')->assertForbidden();
        $this->assertDatabaseCount('license_types', 4);
    }

    public function test_page_asks_for_a_country_then_lists_its_types(): void
    {
        $this->openAccess();

        $this->get(self::BASE)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/master-data/license-types/index')
            ->where('licenseTypes', null)
            ->has('countries', 2)
            ->where('appliesToOptions', [
                ['value' => 'practitioner', 'label' => 'Practitioner'],
                ['value' => 'facility', 'label' => 'Facility'],
                ['value' => 'legal_entity', 'label' => 'Legal entity'],
            ]));

        $this->get(self::BASE.'?country=AF')->assertInertia(fn (Assert $page) => $page
            ->has('licenseTypes.data', 3)
            ->where('licenseTypes.data.0', ['id' => $this->licenseTypeId('CLINIC_LIC'), 'country_code' => 'AF', 'code' => 'CLINIC_LIC', 'name' => 'Clinic', 'applies_to' => 'facility', 'is_active' => true])
            ->where('filters.country', 'AF'));
    }

    public function test_filters_search_and_sort(): void
    {
        $this->openAccess();

        $this->get(self::BASE.'?country=AF&applies_to=practitioner')->assertInertia(fn (Assert $page) => $page
            ->has('licenseTypes.data', 1)->where('licenseTypes.data.0.code', 'DENTIST_LIC'));
        $this->get(self::BASE.'?country=AF&status=inactive')->assertInertia(fn (Assert $page) => $page
            ->has('licenseTypes.data', 1)->where('licenseTypes.data.0.code', 'COMPANY_LIC'));
        $this->get(self::BASE.'?country=AF&q=clin')->assertInertia(fn (Assert $page) => $page
            ->has('licenseTypes.data', 1)->where('licenseTypes.data.0.code', 'CLINIC_LIC'));
        $this->get(self::BASE.'?country=AF&q=%25')->assertInertia(fn (Assert $page) => $page->has('licenseTypes.data', 0));
        $this->get(self::BASE.'?country=AF&sort=name&dir=desc')->assertInertia(fn (Assert $page) => $page
            ->where('licenseTypes.data.0.code', 'DENTIST_LIC'));
        $this->get(self::BASE.'?country=AF&sort=password&applies_to=patient')->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'code')->where('filters.applies_to', 'all')->has('licenseTypes.data', 3));
    }

    public function test_store_update_and_status(): void
    {
        $this->openAccess();

        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'HYGIENIST_LIC', 'name' => 'Dental hygienist', 'applies_to' => 'practitioner'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('license_types', ['country_code' => 'AF', 'code' => 'HYGIENIST_LIC', 'is_active' => true]);

        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'DENTIST_LIC', 'name' => 'Duplicate', 'applies_to' => 'practitioner'])
            ->assertSessionHasErrors(['code']);
        $this->post(self::BASE, ['country_code' => 'AF', 'code' => 'x', 'name' => '', 'applies_to' => 'patient'])
            ->assertSessionHasErrors(['code', 'name', 'applies_to']);

        $this->put(self::BASE."/{$this->dentist}", ['name' => 'Dental practitioner'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('license_types', ['id' => $this->dentist, 'name' => 'Dental practitioner', 'code' => 'DENTIST_LIC']);
        $this->put(self::BASE."/{$this->dentist}", ['name' => 'Dentist', 'code' => 'CHANGED', 'applies_to' => 'facility'])
            ->assertSessionHasErrors(['code', 'applies_to']);

        $this->patch(self::BASE."/{$this->dentist}/status", ['is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('license_types', ['id' => $this->dentist, 'is_active' => false]);
        $this->patch(self::BASE."/{$this->dentist}/status", ['is_active' => 'perhaps'])->assertSessionHasErrors(['is_active']);
    }

    public function test_unknown_malformed_and_delete_requests(): void
    {
        $this->openAccess();

        $this->put(self::BASE.'/99999', ['name' => 'X'])->assertNotFound();
        $this->put(self::BASE.'/abc', ['name' => 'X'])->assertNotFound();
        $this->delete(self::BASE."/{$this->dentist}")->assertMethodNotAllowed();
        $this->assertDatabaseCount('license_types', 4);
    }

    public function test_dari_interface_translates_the_page(): void
    {
        $this->openAccess();
        $this->post('/locale', ['locale' => 'fa-AF']);

        $this->get(self::BASE)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')
            ->where('translations.License types', 'انواع جواز')
            ->where('appliesToOptions.1.label', 'مرکز صحی'));
    }

    public function test_xlsx_export_requires_a_country_and_uses_filters(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();

        $this->get(self::BASE.'/export/xlsx')->assertStatus(422);

        $response = $this->get(self::BASE.'/export/xlsx?country=AF&status=active')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame(['Country', 'Code', 'Name', 'Applies to', 'Status'], $sheet->rangeToArray('A1:E1')[0]);
        $this->assertSame(['AF', 'CLINIC_LIC', 'Clinic', 'Facility', 'Active'], $sheet->rangeToArray('A2:E2')[0]);
        $this->assertSame('DENTIST_LIC', $sheet->getCell('B3')->getValue());
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

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function type(string $country, string $code, string $name, string $appliesTo, bool $active): int
    {
        return DB::table('license_types')->insertGetId([
            'country_code' => $country, 'code' => $code, 'name' => $name, 'applies_to' => $appliesTo, 'is_active' => $active,
        ]);
    }

    private function licenseTypeId(string $code): int
    {
        return (int) DB::table('license_types')->where('code', $code)->value('id');
    }
}
