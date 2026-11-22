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

/**
 * P01.03 Locales management, unlocked behind the temporary master-data switch
 * (owner decision 2026-11-22) until platform roles and permissions exist.
 */
class LocaleHttpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/locales';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        $this->withoutVite();
    }

    public function test_guests_are_redirected_and_access_is_closed_by_default(): void
    {
        $this->get(self::BASE)->assertRedirect(route('login'));

        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());
        // An existing row, so the status route reaches the access check instead of a 404.
        DB::table('locales')->insert(['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR', 'is_active' => true]);

        $this->get(self::BASE)->assertForbidden();
        $this->post(self::BASE, ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL'])->assertForbidden();
        $this->patch(self::BASE.'/en/status', ['is_active' => false])->assertForbidden();
        $this->get(self::BASE.'/export/xlsx')->assertForbidden();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('localeManagementUrl', null));
        $this->assertDatabaseCount('locales', 1);
        $this->assertDatabaseHas('locales', ['code' => 'en', 'is_active' => true]);
    }

    public function test_access_stays_closed_in_production_even_when_switch_is_on(): void
    {
        config(['master_data.open_access' => true]);
        $this->app['env'] = 'production';
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE)->assertForbidden();
    }

    public function test_index_lists_all_seven_fields_with_options_and_sidebar_link(): void
    {
        $this->openAccess();
        $this->seedLocales();

        $this->get(self::BASE)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/master-data/locales/index')
                ->has('localeRows.data', 4)
                ->where('localeRows.data.0', [
                    'code' => 'de', 'english_name' => 'German', 'text_direction' => 'LTR', 'is_active' => false,
                    'native_name' => 'Deutsch', 'default_calendar_code' => 'gregory', 'default_numbering_system_code' => 'latn',
                ])
                ->where('filters', ['q' => '', 'direction' => 'all', 'status' => 'all', 'sort' => 'code', 'dir' => 'asc', 'per_page' => 25])
                ->where('calendars', config('locale_options.calendars'))
                ->where('numberingSystems', config('locale_options.numbering_systems'))
                ->where('localeManagementUrl', self::BASE)
                ->where('locales', fn ($locales): bool => collect($locales)->pluck('code')->all() === ['en', 'fa-AF', 'ps-AF']));
    }

    public function test_search_and_filters(): void
    {
        $this->openAccess();
        $this->seedLocales();

        $this->get(self::BASE.'?q='.urlencode('پښ'))->assertInertia(fn (Assert $page) => $page
            ->has('localeRows.data', 1)->where('localeRows.data.0.code', 'ps-AF'));
        $this->get(self::BASE.'?status=inactive')->assertInertia(fn (Assert $page) => $page
            ->has('localeRows.data', 1)->where('localeRows.data.0.code', 'de'));
        $this->get(self::BASE.'?direction=RTL&status=active')->assertInertia(fn (Assert $page) => $page
            ->has('localeRows.data', 2));
        $this->get(self::BASE.'?q=%25')->assertInertia(fn (Assert $page) => $page->has('localeRows.data', 0));
        $this->get(self::BASE.'?sort=is_active&dir=asc')->assertInertia(fn (Assert $page) => $page
            ->where('localeRows.data.0.code', 'de'));
    }

    public function test_store_update_status_and_validation(): void
    {
        $this->openAccess();

        $this->post(self::BASE, [
            'code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL',
            'native_name' => 'پښتو', 'default_calendar_code' => 'persian', 'default_numbering_system_code' => 'arabext',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('locales', ['code' => 'ps-AF', 'is_active' => true, 'default_calendar_code' => 'persian']);

        $this->post(self::BASE, ['code' => 'afg', 'english_name' => 'pashto', 'text_direction' => 'LTR'])
            ->assertSessionHasErrors(['code']);
        $this->post(self::BASE, ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'default_calendar_code' => 'solar'])
            ->assertSessionHasErrors(['default_calendar_code']);

        $this->put(self::BASE.'/ps-AF', ['english_name' => 'Pashto', 'text_direction' => 'RTL', 'native_name' => '', 'default_calendar_code' => null])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('locales', ['code' => 'ps-AF', 'english_name' => 'Pashto', 'native_name' => null, 'default_calendar_code' => null, 'default_numbering_system_code' => 'arabext']);

        $this->put(self::BASE.'/ps-AF', ['english_name' => 'Pashto', 'text_direction' => 'LTR'])->assertSessionHasErrors(['text_direction']);
        $this->put(self::BASE.'/ps-AF', ['code' => 'ps', 'english_name' => 'Pashto', 'text_direction' => 'RTL'])->assertSessionHasErrors(['code']);

        $this->patch(self::BASE.'/ps-AF/status', ['is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('locales', ['code' => 'ps-AF', 'is_active' => false]);
        $this->patch(self::BASE.'/ps-AF/status', ['is_active' => true])->assertRedirect();
        $this->assertDatabaseHas('locales', ['code' => 'ps-AF', 'is_active' => true]);
        $this->patch(self::BASE.'/ps-AF/status', ['is_active' => 'perhaps'])->assertSessionHasErrors(['is_active']);
    }

    public function test_unknown_malformed_and_delete_requests(): void
    {
        $this->openAccess();
        $this->seedLocales();

        $details = ['english_name' => 'X', 'text_direction' => 'LTR'];
        $this->put(self::BASE.'/fr', $details)->assertNotFound();
        $this->put(self::BASE.'/1', $details)->assertNotFound();
        $this->patch(self::BASE.'/fr/status', ['is_active' => false])->assertNotFound();
        $this->delete(self::BASE.'/en')->assertMethodNotAllowed();
        $this->assertDatabaseCount('locales', 4);
    }

    public function test_language_switching_stays_available(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/locale', ['locale' => 'fa-AF'])->assertRedirect()->assertSessionHas('locale', 'fa-AF');
    }

    public function test_xlsx_export_has_all_fields_and_uses_filters(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();
        $this->seedLocales();

        $response = $this->get(self::BASE.'/export/xlsx?direction=LTR')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame(['Code', 'English name', 'Text direction', 'Status', 'Native name', 'Calendar', 'Numbering system'], $sheet->rangeToArray('A1:G1')[0]);
        $this->assertSame(['de', 'German', 'LTR', 'Inactive', 'Deutsch', 'gregory', 'latn'], $sheet->rangeToArray('A2:G2')[0]);
        $this->assertSame('en', $sheet->getCell('A3')->getValue());
        $this->assertNull($sheet->getCell('A4')->getValue());
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        if (! class_exists(Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf is not installed.');
        }
        $this->openAccess();
        $this->seedLocales();

        $this->assertStringStartsWith('%PDF', (string) $this->get(self::BASE.'/export/pdf')->assertOk()->getContent());
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function seedLocales(): void
    {
        DB::table('locales')->insert([
            ['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR', 'is_active' => true, 'native_name' => 'English', 'default_calendar_code' => null, 'default_numbering_system_code' => null],
            ['code' => 'fa-AF', 'english_name' => 'Dari (Afghanistan)', 'text_direction' => 'RTL', 'is_active' => true, 'native_name' => 'دری', 'default_calendar_code' => null, 'default_numbering_system_code' => null],
            ['code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL', 'is_active' => true, 'native_name' => 'پښتو', 'default_calendar_code' => null, 'default_numbering_system_code' => null],
            ['code' => 'de', 'english_name' => 'German', 'text_direction' => 'LTR', 'is_active' => false, 'native_name' => 'Deutsch', 'default_calendar_code' => 'gregory', 'default_numbering_system_code' => 'latn'],
        ]);
    }
}
