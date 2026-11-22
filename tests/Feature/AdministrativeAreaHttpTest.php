<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class AdministrativeAreaHttpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/administrative-areas';

    private int $kabul;

    private int $paghman;

    private int $herat;

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
        DB::table('locales')->insert([
            ['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR', 'is_active' => true],
            ['code' => 'fa-AF', 'english_name' => 'Dari (Afghanistan)', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'de', 'english_name' => 'German', 'text_direction' => 'LTR', 'is_active' => false],
        ]);

        $this->kabul = $this->area('AF', null, 'Kabul', 'province', 'KBL');
        $this->paghman = $this->area('AF', $this->kabul, 'Paghman', 'district', 'KBL-PG');
        $this->herat = $this->area('AF', null, 'Herat', 'province', 'HRT');
        $this->area('PK', null, 'Punjab', 'province', 'PB');
        DB::table('administrative_area_translations')->insert(['area_id' => $this->paghman, 'locale_code' => 'fa-AF', 'display_name' => 'پغمان']);
    }

    public function test_guests_are_redirected_and_access_is_closed_by_default(): void
    {
        $this->get(self::BASE)->assertRedirect(route('login'));

        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE.'?country=AF')->assertForbidden();
        $this->post(self::BASE, ['country_code' => 'AF', 'level_code' => 'province', 'canonical_name' => 'Balkh'])->assertForbidden();
        $this->put(self::BASE."/{$this->kabul}/translations", ['translations' => ['fa-AF' => 'کابل']])->assertForbidden();
        $this->get(self::BASE.'/parents?country=AF')->assertForbidden();
        $this->assertDatabaseCount('administrative_areas', 4);
    }

    public function test_page_asks_for_a_country_first(): void
    {
        $this->openAccess();

        $this->get(self::BASE)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/master-data/administrative-areas/index')
            ->where('areas', null)
            ->has('countries', 2)
            ->where('translationLocales', fn ($locales): bool => collect($locales)->pluck('code')->all() === ['en', 'fa-AF']));
    }

    public function test_lists_top_level_areas_then_drills_down(): void
    {
        $this->openAccess();

        $this->get(self::BASE.'?country=AF')->assertInertia(fn (Assert $page) => $page
            ->has('areas.data', 2)
            ->where('areas.data.0.canonical_name', 'Herat')
            ->where('areas.data.1.canonical_name', 'Kabul')
            ->where('areas.data.1.children_count', 1)
            ->where('ancestors', [])
            ->where('levels', ['district', 'province']));

        $this->get(self::BASE."?country=AF&parent={$this->kabul}")->assertInertia(fn (Assert $page) => $page
            ->has('areas.data', 1)
            ->where('areas.data.0.canonical_name', 'Paghman')
            ->where('areas.data.0.parent_path', 'Kabul')
            ->where('ancestors', [['id' => $this->kabul, 'name' => 'Kabul']]));
    }

    public function test_search_covers_the_whole_country_including_translations(): void
    {
        $this->openAccess();

        $this->get(self::BASE.'?country=AF&q='.urlencode('پغ'))->assertInertia(fn (Assert $page) => $page
            ->has('areas.data', 1)
            ->where('areas.data.0.id', $this->paghman)
            ->where('areas.data.0.parent_path', 'Kabul'));

        $this->get(self::BASE.'?country=AF&q=pb')->assertInertia(fn (Assert $page) => $page->has('areas.data', 0));
        $this->get(self::BASE.'?country=AF&q=%25')->assertInertia(fn (Assert $page) => $page->has('areas.data', 0));
    }

    public function test_display_names_follow_the_interface_language(): void
    {
        $this->openAccess();
        $this->post('/locale', ['locale' => 'fa-AF']);

        $this->get(self::BASE."?country=AF&parent={$this->kabul}")->assertInertia(fn (Assert $page) => $page
            ->where('areas.data.0.display_name', 'پغمان')
            ->where('areas.data.0.translations', ['fa-AF' => 'پغمان']));
    }

    public function test_store_update_status_and_translations(): void
    {
        $this->openAccess();

        $this->post(self::BASE, ['country_code' => 'AF', 'parent_id' => $this->kabul, 'level_code' => 'district', 'area_code' => '', 'canonical_name' => 'Bagrami'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $bagrami = (int) DB::table('administrative_areas')->where('canonical_name', 'Bagrami')->value('id');
        $this->assertDatabaseHas('administrative_areas', ['id' => $bagrami, 'parent_id' => $this->kabul, 'area_code' => null, 'is_active' => true]);

        $this->post(self::BASE, ['country_code' => 'PK', 'parent_id' => $this->kabul, 'level_code' => 'district', 'canonical_name' => 'Cross'])
            ->assertSessionHasErrors(['parent_id']);

        $this->put(self::BASE."/{$bagrami}", ['parent_id' => $this->herat, 'level_code' => 'district', 'canonical_name' => 'Bagrami'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('administrative_areas', ['id' => $bagrami, 'parent_id' => $this->herat]);

        $this->put(self::BASE."/{$this->kabul}", ['parent_id' => $this->paghman, 'level_code' => 'province', 'canonical_name' => 'Kabul'])
            ->assertSessionHasErrors(['parent_id']);

        $this->patch(self::BASE."/{$this->kabul}/status", ['is_active' => false])->assertSessionHasErrors(['is_active']);
        $this->patch(self::BASE."/{$this->paghman}/status", ['is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('administrative_areas', ['id' => $this->paghman, 'is_active' => false]);

        $this->put(self::BASE."/{$this->kabul}/translations", ['translations' => ['fa-AF' => 'کابل', 'en' => '']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('administrative_area_translations', ['area_id' => $this->kabul, 'locale_code' => 'fa-AF', 'display_name' => 'کابل']);
        $this->put(self::BASE."/{$this->kabul}/translations", ['translations' => ['de' => 'Kabul']])
            ->assertSessionHasErrors(['translations.de']);
    }

    public function test_parent_picker_returns_same_country_areas_only(): void
    {
        $this->openAccess();

        $this->getJson(self::BASE.'/parents?country=AF&q=pag')->assertOk()->assertExactJson([
            ['id' => $this->paghman, 'path' => 'Kabul › Paghman', 'level_code' => 'district', 'is_active' => true],
        ]);
        $this->getJson(self::BASE."/parents?country=AF&exclude={$this->kabul}")->assertOk()->assertJsonCount(2);
        $this->getJson(self::BASE.'/parents?country=PK')->assertOk()->assertJsonCount(1);
        $this->getJson(self::BASE.'/parents')->assertStatus(422);
    }

    public function test_unknown_malformed_and_delete_requests(): void
    {
        $this->openAccess();

        $this->put(self::BASE.'/99999', ['level_code' => 'x', 'canonical_name' => 'X'])->assertNotFound();
        $this->put(self::BASE.'/abc', ['level_code' => 'x', 'canonical_name' => 'X'])->assertNotFound();
        $this->delete(self::BASE."/{$this->kabul}")->assertMethodNotAllowed();
        $this->assertDatabaseCount('administrative_areas', 4);
    }

    public function test_xlsx_export_uses_filters_and_requires_a_country(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();

        $this->get(self::BASE.'/export/xlsx')->assertStatus(422);

        $response = $this->get(self::BASE."/export/xlsx?country=AF&parent={$this->kabul}")->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame(['ID', 'Country', 'Level', 'Area code', 'Canonical name', 'Display name', 'Path', 'Status'], $sheet->rangeToArray('A1:H1')[0]);
        $this->assertSame([(string) $this->paghman, 'AF', 'district', 'KBL-PG', 'Paghman', 'Paghman', 'Kabul › Paghman', 'Active'], $sheet->rangeToArray('A2:H2')[0]);
        $this->assertNull($sheet->getCell('A3')->getValue());
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function area(string $country, ?int $parent, string $name, string $level, ?string $code): int
    {
        return DB::table('administrative_areas')->insertGetId([
            'country_code' => $country, 'parent_id' => $parent, 'level_code' => $level,
            'area_code' => $code, 'canonical_name' => $name, 'is_active' => true,
        ]);
    }
}
