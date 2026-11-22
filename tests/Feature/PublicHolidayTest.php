<?php

namespace Tests\Feature;

use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PublicHolidayTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/master-data/public-holidays';

    private int $areaId;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());
        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getConfig('driver'));
        $this->assertSame(':memory:', $connection->getConfig('database'));
        $this->assertEmpty($connection->getConfig('read'));
        $this->assertEmpty($connection->getConfig('write'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
        ]);

        $this->areaId = DB::table('administrative_areas')->insertGetId([
            'country_code' => 'AF',
            'level_code' => 'province',
            'canonical_name' => 'Kabul',
        ]);
    }

    public function test_routes_require_master_data_access(): void
    {
        $this->get(self::URL)->assertRedirect(route('login'));
        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());
        $holiday = PublicHoliday::create($this->attributes());

        $this->get(self::URL)->assertForbidden();
        $this->post(self::URL, $this->attributes())->assertForbidden();
        $this->put(self::URL.'/'.$holiday->id, $this->attributes())->assertForbidden();
        $this->patch(self::URL.'/'.$holiday->id.'/status', ['status' => 'inactive'])->assertForbidden();
        $this->get(self::URL.'/export/pdf?country=AF')->assertForbidden();
        $this->get(self::URL.'/export/xlsx?country=AF')->assertForbidden();
    }

    public function test_holidays_can_be_created_edited_and_deactivated(): void
    {
        $this->openAccess();

        $this->post(self::URL, $this->attributes())->assertRedirect();
        $holiday = PublicHoliday::query()->sole();
        $this->assertNull($holiday->administrative_area_id);
        $this->assertSame('2026-11-22', $holiday->getRawOriginal('holiday_date'));

        $this->put(self::URL.'/'.$holiday->id, $this->attributes([
            'administrative_area_id' => $this->areaId,
            'name' => 'Kabul holiday',
        ]))->assertRedirect();
        $this->patch(self::URL.'/'.$holiday->id.'/status', ['status' => 'inactive'])->assertRedirect();
        $this->assertDatabaseHas('public_holidays', [
            'id' => $holiday->id,
            'administrative_area_id' => $this->areaId,
            'name' => 'Kabul holiday',
            'status' => 'inactive',
        ]);
    }

    public function test_validation_rejects_invalid_fields_and_cross_country_area(): void
    {
        $this->openAccess();

        $this->postJson(self::URL, $this->attributes([
            'country_code' => 'PK',
            'administrative_area_id' => $this->areaId,
            'holiday_date' => '2026-02-30',
            'name' => str_repeat('a', 181),
            'status' => 'pending',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'administrative_area_id', 'holiday_date', 'name', 'status',
        ]);

        $this->postJson(self::URL, $this->attributes(['country_code' => 'XX']))
            ->assertUnprocessable()->assertJsonValidationErrors('country_code');
        $this->assertDatabaseCount('public_holidays', 0);
    }

    public function test_sqlite_schema_has_exact_columns_and_rejects_cross_country_area(): void
    {
        $columns = collect(DB::select("PRAGMA table_info('public_holidays')"))->keyBy('name');
        $this->assertSame(['id', 'country_code', 'administrative_area_id', 'holiday_date', 'name', 'status'], $columns->keys()->all());
        $this->assertEquals(1, $columns['id']->pk);
        $this->assertEquals(0, $columns['administrative_area_id']->notnull);
        foreach (['country_code', 'holiday_date', 'name', 'status'] as $required) {
            $this->assertEquals(1, $columns[$required]->notnull);
        }
        $this->assertSame('date', strtolower($columns['holiday_date']->type));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY constraint failed');
        DB::table('public_holidays')->insert($this->attributes([
            'country_code' => 'PK',
            'administrative_area_id' => $this->areaId,
        ]));
    }

    public function test_country_search_status_sort_and_stable_pagination(): void
    {
        $this->openAccess();

        foreach (range(1, 11) as $number) {
            PublicHoliday::create($this->attributes(['name' => 'Holiday '.$number]));
        }
        $last = PublicHoliday::query()->latest('id')->firstOrFail();
        PublicHoliday::create($this->attributes(['country_code' => 'PK']));
        PublicHoliday::create($this->attributes(['status' => 'inactive']));

        $this->get(self::URL.'?country=AF&q=holiday&status=active&per_page=10&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/master-data/public-holidays/index')
                ->where('holidays.total', 11)
                ->has('holidays.data', 1)
                ->where('holidays.data.0.id', $last->id)
                ->where('holidays.data.0.holiday_date', '2026-11-22')
                ->where('holidays.data.0.administrative_area', null));
        $this->get(self::URL.'?country=AF&q=%25')->assertInertia(fn (Assert $page) => $page->where('holidays.total', 0));
        $this->get(self::URL.'?country=AF&status=active&sort=name&dir=desc')
            ->assertInertia(fn (Assert $page) => $page->where('holidays.data.0.name', 'Holiday 9'));
    }

    public function test_filtered_exports(): void
    {
        $this->openAccess();
        PublicHoliday::create($this->attributes(['name' => '=Nationwide']));
        PublicHoliday::create($this->attributes(['name' => 'Excluded', 'status' => 'inactive']));

        $query = '?country=AF&status=active';
        $xlsx = $this->get(self::URL.'/export/xlsx'.$query)->assertOk();
        $temp = tempnam(sys_get_temp_dir(), 'holidays-');
        $this->assertNotFalse($temp);

        try {
            file_put_contents($temp, $xlsx->streamedContent());
            $sheet = IOFactory::createReader('Xlsx')->load($temp)->getActiveSheet();
            $this->assertSame('=Nationwide', $sheet->getCell('C2')->getValue());
            $this->assertSame('s', $sheet->getCell('C2')->getDataType());
            $this->assertSame(2, $sheet->getHighestDataRow());
        } finally {
            unlink($temp);
        }

        $this->get(self::URL.'/export/pdf'.$query)
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload();
        $this->get(self::URL.'/export/pdf')->assertUnprocessable();
    }

    public function test_calendar_uses_the_selected_interface_language_and_direction(): void
    {
        $this->openAccess();

        foreach (['en' => 'ltr', 'fa-AF' => 'rtl', 'ps-AF' => 'rtl'] as $locale => $direction) {
            $this->withSession(['locale' => $locale])
                ->get(self::URL.'?country=AF')
                ->assertInertia(fn (Assert $page) => $page
                    ->where('locale', $locale)
                    ->where('direction', $direction)
                    ->where('translations', fn (Collection $translations) => $locale === 'en'
                        || preg_match('/\p{Arabic}/u', $translations['Public holidays'] ?? '') === 1));
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function attributes(array $overrides = []): array
    {
        return array_replace([
            'country_code' => 'AF',
            'administrative_area_id' => null,
            'holiday_date' => '2026-11-22',
            'name' => 'Nationwide holiday',
            'status' => 'active',
        ], $overrides);
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }
}
