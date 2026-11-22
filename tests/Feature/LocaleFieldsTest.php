<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Locales\LocaleService;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** P01.03 v5.1 fields, enabled state and fallback rules. */
class LocaleFieldsTest extends TestCase
{
    use RefreshDatabase;

    private LocaleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        $this->service = new LocaleService;
        $this->withoutVite();
    }

    public function test_presentation_fields_are_stored_trimmed_and_optional(): void
    {
        $locale = $this->service->create([
            'code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL',
            'is_active' => false, 'native_name' => '  پښتو  ',
            'default_calendar_code' => 'persian', 'default_numbering_system_code' => 'arabext',
        ])->refresh();

        $this->assertFalse($locale->is_active);
        $this->assertSame('پښتو', $locale->native_name);
        $this->assertSame('persian', $locale->default_calendar_code);
        $this->assertSame('arabext', $locale->default_numbering_system_code);

        $plain = $this->service->create(['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR'])->refresh();
        $this->assertTrue($plain->is_active);
        $this->assertNull($plain->native_name);
        $this->assertNull($plain->default_calendar_code);

        $updated = $this->service->update('ps-AF', [
            'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL',
            'native_name' => ' ', 'default_calendar_code' => '',
        ])->refresh();
        $this->assertNull($updated->native_name);
        $this->assertNull($updated->default_calendar_code);
        $this->assertSame('arabext', $updated->default_numbering_system_code, 'Omitted fields are preserved.');
    }

    /** CLDR identifiers worldwide, not an Afghan-only list. */
    public function test_cldr_identifiers_from_any_region_are_accepted(): void
    {
        foreach ([['de', 'gregory', 'latn'], ['ar', 'islamic-umalqura', 'arab'], ['hi', 'indian', 'deva'], ['zh-Hans', 'chinese', 'hanidec']] as [$code, $calendar, $numbers]) {
            $entry = config("locale_catalog.{$code}");
            $this->service->create([
                'code' => $code, 'english_name' => $entry['name'], 'text_direction' => $entry['direction'],
                'default_calendar_code' => $calendar, 'default_numbering_system_code' => $numbers,
            ]);
        }

        $this->assertDatabaseCount('locales', 4);
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_presentation_fields_are_rejected(array $fields, array $errors): void
    {
        try {
            $this->service->create(['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR', ...$fields]);
            $this->fail('Invalid fields were accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame($errors, array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('locales', 0);
    }

    public static function invalidFields(): array
    {
        return [
            'unknown calendar' => [['default_calendar_code' => 'solar-hijri'], ['default_calendar_code']],
            'calendar wrong case' => [['default_calendar_code' => 'Persian'], ['default_calendar_code']],
            'unknown numbering system' => [['default_numbering_system_code' => 'latin'], ['default_numbering_system_code']],
            'native name too long' => [['native_name' => str_repeat('ب', 121)], ['native_name']],
            'status not boolean' => [['is_active' => 'maybe'], ['is_active']],
            'status null' => [['is_active' => null], ['is_active']],
        ];
    }

    public function test_code_lengths_fit_the_v51_columns(): void
    {
        foreach ([...config('locale_options.calendars'), ...config('locale_options.numbering_systems')] as $code) {
            $this->assertMatchesRegularExpression('/\A[a-z0-9]+(-[a-z0-9]+)*\z/', $code);
            $this->assertLessThanOrEqual(32, strlen($code));
        }

        $this->assertTrue(Schema::hasColumns('locales', ['default_calendar_code', 'default_numbering_system_code']));
    }

    public function test_seeder_fills_native_names_and_leaves_presentation_codes_empty(): void
    {
        $this->seed(LocaleSeeder::class);

        $this->assertDatabaseHas('locales', ['code' => 'en', 'native_name' => 'English', 'is_active' => true, 'default_calendar_code' => null]);
        $this->assertDatabaseHas('locales', ['code' => 'fa-AF', 'native_name' => 'دری', 'default_numbering_system_code' => null]);
        $this->assertDatabaseHas('locales', ['code' => 'ps-AF', 'native_name' => 'پښتو', 'text_direction' => 'RTL']);
    }

    public function test_disabled_interface_language_is_not_offered_or_selectable_and_falls_back(): void
    {
        $this->seed(LocaleSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->post('/locale', ['locale' => 'ps-AF'])->assertSessionHasNoErrors();
        DB::table('locales')->where('code', 'ps-AF')->update(['is_active' => false]);

        // The session still asks for ps-AF, but the disabled language falls back to English.
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->where('direction', 'ltr')
            ->where('locales', [['code' => 'en', 'name' => 'English'], ['code' => 'fa-AF', 'name' => 'دری']]));

        $this->post('/locale', ['locale' => 'ps-AF'])->assertSessionHasErrors(['locale']);
    }

    public function test_fallback_language_cannot_be_disabled_for_the_interface(): void
    {
        $this->seed(LocaleSeeder::class);
        DB::table('locales')->where('code', 'en')->update(['is_active' => false]);
        $this->actingAs(User::factory()->create());

        $this->post('/locale', ['locale' => 'en'])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('locales.0', ['code' => 'en', 'name' => 'English']));
    }

    public function test_interface_without_locale_rows_uses_configuration(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/locale', ['locale' => 'fa-AF'])->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'fa-AF')
            ->where('direction', 'rtl')
            ->has('locales', 3));
    }

    public function test_translations_fall_back_from_region_to_base_language_to_source_text(): void
    {
        $dir = storage_path('framework/testing/lang-'.uniqid());
        File::ensureDirectoryExists($dir);
        File::put($dir.'/fa.json', json_encode(['Base only' => 'پایه', 'Shared' => 'از پایه', 'Blank' => 'پر']));
        File::put($dir.'/fa-AF.json', json_encode(['Shared' => 'از منطقه', 'Blank' => '']));
        $this->app->useLangPath($dir);

        try {
            $this->actingAs(User::factory()->create());
            $this->post('/locale', ['locale' => 'fa-AF']);

            $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
                ->where('translations', ['Base only' => 'پایه', 'Shared' => 'از منطقه', 'Blank' => 'پر']));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
