<?php

namespace Tests\Feature;

use App\Models\LicenseType;
use App\Models\User;
use App\Services\CatalogTranslations\TypeTranslations;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LicenseTypeTranslationTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/master-data/license-types';

    private int $licenseType;

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
        DB::table('countries')->insert(['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true]);
        $this->licenseType = DB::table('license_types')->insertGetId([
            'country_code' => 'AF', 'code' => 'DENTIST', 'name' => 'Dentist',
            'applies_to' => 'practitioner', 'is_active' => true,
        ]);
        DB::table('locales')->insert([
            ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'fa', 'english_name' => 'Persian', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto', 'text_direction' => 'RTL', 'is_active' => false],
        ]);
    }

    public function test_access_and_validation_are_enforced(): void
    {
        $url = self::URL.'/'.$this->licenseType.'/translations';
        $this->put($url)->assertRedirect(route('login'));
        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());
        $this->put($url)->assertForbidden();
        config(['master_data.open_access' => true]);

        $this->putJson($url, ['translations' => ['fa' => ['display_name' => str_repeat('a', 181)]]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa.display_name');
        $this->putJson($url, ['translations' => ['fa' => ['display_name' => '', 'help_text' => 'Help']]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa.display_name');
        $this->putJson($url, ['translations' => ['fa' => ['display_name' => 'Safe', 'help_text' => "Bad\u{202E}"]]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa.help_text');
        $this->putJson($url, ['translations' => [
            'fa' => ['display_name' => 'Valid'],
            'ps-AF' => ['display_name' => 'Disabled'],
            'fr' => ['display_name' => 'Unknown'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['translations.ps-AF', 'translations.fr']);
        $this->assertDatabaseCount('license_type_translations', 0);
    }

    public function test_authorized_editor_and_deterministic_fallback(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
        $url = self::URL.'/'.$this->licenseType.'/translations';
        $list = self::URL.'?country=AF';
        $identity = ['license_type_id' => $this->licenseType, 'locale_code' => 'fa-AF'];
        $this->from($list)->put($url, ['translations' => [
            'fa-AF' => ['display_name' => '  داکتر دندان  ', 'help_text' => "First\r\nSecond"],
            'fa' => ['display_name' => 'Base'],
        ]])->assertRedirect($list);
        $this->assertDatabaseHas('license_type_translations', $identity + [
            'display_name' => 'داکتر دندان', 'help_text' => "First\nSecond",
        ]);

        $localized = 'translationData.localized.'.$this->licenseType;
        $this->withSession(['locale' => 'fa-AF'])->get($list)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')->has('translationData.locales', 2)
            ->where($localized, ['display_name' => 'داکتر دندان', 'help_text' => "First\nSecond"]));

        $this->put($url, ['translations' => ['fa-AF' => ['display_name' => 'Updated', 'help_text' => '']]])->assertRedirect();
        $this->assertDatabaseHas('license_type_translations', $identity + ['display_name' => 'Updated', 'help_text' => null]);
        $this->assertDatabaseCount('license_type_translations', 2);

        DB::table('locales')->where('code', 'fa-AF')->update(['is_active' => false]);
        $this->assertSame(
            ['display_name' => 'Base', 'help_text' => null],
            app(TypeTranslations::class)->forPage(new LicenseType, [$this->licenseType], 'fa-AF')['localized'][$this->licenseType],
        );
        DB::table('locales')->where('code', 'fa-AF')->update(['is_active' => true]);

        $this->put($url, ['translations' => ['fa-AF' => ['display_name' => '', 'help_text' => '']]])->assertRedirect();
        $this->assertDatabaseMissing('license_type_translations', $identity);
        DB::table('locales')->where('code', 'fa')->update(['is_active' => false]);
        $this->get($list)->assertInertia(fn (Assert $page) => $page
            ->missing($localized)->where('licenseTypes.data.0.name', 'Dentist'));
    }

    public function test_schema_enforces_composite_identity_and_foreign_keys(): void
    {
        $table = 'license_type_translations';
        $columns = collect(DB::select("PRAGMA table_info('license_type_translations')"))->keyBy('name');
        $this->assertSame(['license_type_id', 'locale_code', 'display_name', 'help_text'], $columns->keys()->all());
        $this->assertEquals(1, $columns['license_type_id']->pk);
        $this->assertEquals(2, $columns['locale_code']->pk);
        $this->assertEquals(1, $columns['display_name']->notnull);
        $this->assertEquals(0, $columns['help_text']->notnull);

        $row = ['license_type_id' => $this->licenseType, 'locale_code' => 'fa-AF', 'display_name' => 'Dentist'];
        DB::table($table)->insert($row);
        foreach ([
            [$row, 'UNIQUE constraint failed'],
            [array_replace($row, ['license_type_id' => $this->licenseType + 1]), 'FOREIGN KEY constraint failed'],
            [array_replace($row, ['locale_code' => 'fr']), 'FOREIGN KEY constraint failed'],
        ] as [$invalid, $message]) {
            try {
                DB::table($table)->insert($invalid);
                $this->fail('The database accepted an invalid translation.');
            } catch (QueryException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
    }
}
