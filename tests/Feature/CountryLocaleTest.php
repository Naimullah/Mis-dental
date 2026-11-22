<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Locale;
use App\Models\User;
use App\Services\CountryLocales\CountryLocaleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/** P01.04 country_locales: languages used in a country (not country-name translations). */
class CountryLocaleTest extends TestCase
{
    use RefreshDatabase;

    private CountryLocaleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));

        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Germany', 'is_active' => false],
        ]);
        DB::table('locales')->insert([
            ['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR'],
            ['code' => 'fa-AF', 'english_name' => 'Dari (Afghanistan)', 'text_direction' => 'RTL'],
            ['code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL'],
        ]);

        $this->service = new CountryLocaleService;
        $this->withoutVite();
    }

    public function test_schema_keys_and_foreign_keys(): void
    {
        $this->assertEqualsCanonicalizing(
            ['country_code', 'locale_code', 'is_default'],
            array_column(Schema::getColumns('country_locales'), 'name'),
        );
        $primary = collect(Schema::getIndexes('country_locales'))->firstWhere('primary', true);
        $this->assertSame(['country_code', 'locale_code'], $primary['columns'] ?? []);

        $foreign = collect(Schema::getForeignKeys('country_locales'))->keyBy(fn (array $key) => $key['columns'][0]);
        $this->assertSame('countries', $foreign['country_code']['foreign_table']);
        $this->assertSame(['iso2'], $foreign['country_code']['foreign_columns']);
        $this->assertSame('locales', $foreign['locale_code']['foreign_table']);
        $this->assertSame(['code'], $foreign['locale_code']['foreign_columns']);
    }

    public function test_database_rejects_duplicates_unknown_codes_and_second_default(): void
    {
        $this->link('AF', 'ps-AF', true);
        $this->link('AF', 'fa-AF', false);
        $this->link('AF', 'en', false);
        $this->link('DE', 'en', true); // a default in another country is fine

        foreach ([
            ['AF', 'ps-AF', false], // duplicate pair
            ['ZZ', 'en', false],    // unknown country
            ['AF', 'xx', false],    // unknown locale
        ] as [$country, $locale, $default]) {
            try {
                $this->link($country, $locale, $default);
                $this->fail("Invalid link {$country}/{$locale} was accepted.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(QueryException::class);
        DB::table('country_locales')->where('country_code', 'AF')->where('locale_code', 'fa-AF')->update(['is_default' => true]);
    }

    public function test_linked_countries_and_locales_cannot_be_deleted_or_rekeyed(): void
    {
        $this->link('AF', 'ps-AF', true);

        foreach ([
            fn () => DB::table('countries')->where('iso2', 'AF')->delete(),
            fn () => DB::table('locales')->where('code', 'ps-AF')->delete(),
            fn () => DB::table('countries')->where('iso2', 'AF')->update(['iso2' => 'AX']),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A referenced row was changed.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseHas('countries', ['iso2' => 'AF']);
        $this->assertDatabaseHas('locales', ['code' => 'ps-AF']);
    }

    public function test_service_keeps_at_most_one_default(): void
    {
        $af = Country::query()->findOrFail('AF');

        $this->service->add($af, Locale::query()->findOrFail('ps-AF'), true);
        $this->service->add($af, Locale::query()->findOrFail('fa-AF'), true);
        $this->assertSame(['fa-AF'], $this->defaults('AF'));

        $this->service->setDefault($af, Locale::query()->findOrFail('ps-AF'), true);
        $this->assertSame(['ps-AF'], $this->defaults('AF'));

        $this->service->setDefault($af, Locale::query()->findOrFail('ps-AF'), false);
        $this->assertSame([], $this->defaults('AF'));
        $this->assertSame(2, DB::table('country_locales')->where('country_code', 'AF')->count());
    }

    public function test_service_rejects_duplicates_and_unlinked_changes(): void
    {
        $af = Country::query()->findOrFail('AF');
        $ps = Locale::query()->findOrFail('ps-AF');
        $this->service->add($af, $ps, false);

        try {
            $this->service->add($af, $ps, true);
            $this->fail('Duplicate link was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['locale_code'], array_keys($exception->errors()));
        }
        $this->assertSame([], $this->defaults('AF'));

        $this->expectException(NotFoundHttpException::class);
        $this->service->setDefault($af, Locale::query()->findOrFail('en'), true);
    }

    public function test_service_removes_only_the_link_and_allows_inactive_countries(): void
    {
        $de = Country::query()->findOrFail('DE');
        $en = Locale::query()->findOrFail('en');

        $this->service->add($de, $en, true);
        $this->service->remove($de, $en);

        $this->assertDatabaseCount('country_locales', 0);
        $this->assertDatabaseHas('countries', ['iso2' => 'DE', 'is_active' => false]);
        $this->assertDatabaseHas('locales', ['code' => 'en']);

        $this->expectException(NotFoundHttpException::class);
        $this->service->remove($de, $en);
    }

    public function test_change_routes_are_closed_without_the_access_switch(): void
    {
        config(['master_data.open_access' => false]);
        $this->actingAs(User::factory()->create());
        $this->link('AF', 'ps-AF', true);

        $base = '/platform/master-data/countries/AF/locales';
        $this->post($base, ['locale_code' => 'fa-AF', 'is_default' => false])->assertForbidden();
        $this->patch($base.'/ps-AF/default', ['is_default' => false])->assertForbidden();
        $this->delete($base.'/ps-AF')->assertForbidden();

        $this->assertSame(['ps-AF'], $this->defaults('AF'));
        $this->assertDatabaseCount('country_locales', 1);
    }

    public function test_links_can_be_added_defaulted_and_removed_with_access(): void
    {
        $this->openAccess();
        $base = '/platform/master-data/countries/AF/locales';

        $this->post($base, ['locale_code' => 'ps-AF', 'is_default' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($base, ['locale_code' => 'fa-AF', 'is_default' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['ps-AF'], $this->defaults('AF'));

        $this->post($base, ['locale_code' => 'fa-AF', 'is_default' => false])->assertSessionHasErrors(['locale_code']);
        $this->post($base, ['locale_code' => 'xx', 'is_default' => false])->assertSessionHasErrors(['locale_code']);

        $this->patch($base.'/fa-AF/default', ['is_default' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['fa-AF'], $this->defaults('AF'));

        $this->delete($base.'/fa-AF')->assertRedirect();
        $this->assertSame([], $this->defaults('AF'));
        $this->assertDatabaseMissing('country_locales', ['country_code' => 'AF', 'locale_code' => 'fa-AF']);

        $this->delete($base.'/fa-AF')->assertNotFound();
        $this->post('/platform/master-data/countries/ZZ/locales', ['locale_code' => 'en', 'is_default' => false])->assertNotFound();

        // Countries' own changes stay blocked.
        $this->put('/platform/master-data/countries/AF', ['iso3' => null, 'english_name' => 'X', 'is_active' => true])->assertForbidden();
    }

    public function test_countries_page_offers_link_management_with_access(): void
    {
        $this->openAccess();

        $this->get('/platform/master-data/countries?languages=DE')->assertInertia(fn (Assert $page) => $page
            ->where('canManageCountryLocales', true)
            ->where('countryLocales.country.is_active', false)
            ->has('countryLocales.available', 3));
    }

    public function test_countries_page_shares_read_only_languages_panel(): void
    {
        $this->openAccess();
        $this->link('AF', 'fa-AF', false);
        $this->link('AF', 'ps-AF', true);

        $this->get('/platform/master-data/countries?languages=AF')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('countryLocales.country', ['iso2' => 'AF', 'english_name' => 'Afghanistan', 'is_active' => true])
                ->has('countryLocales.links', 2)
                ->where('countryLocales.links.0', ['code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL', 'is_default' => true, 'is_active' => true])
                ->where('countryLocales.links.1.code', 'fa-AF')
                ->where('countryLocales.available', [['code' => 'en', 'english_name' => 'English']]));

        foreach (['ZZ', 'af', 'AFG'] as $code) {
            $this->get('/platform/master-data/countries?languages='.$code)
                ->assertInertia(fn (Assert $page) => $page->where('countryLocales', null));
        }
    }

    public function test_disabled_languages_cannot_be_newly_linked_or_made_default(): void
    {
        $af = Country::query()->findOrFail('AF');
        $this->link('AF', 'fa-AF', false);
        DB::table('locales')->whereIn('code', ['fa-AF', 'en'])->update(['is_active' => false]);

        try {
            $this->service->add($af, Locale::query()->findOrFail('en'), false);
            $this->fail('A disabled language was linked.');
        } catch (ValidationException $exception) {
            $this->assertSame(['locale_code'], array_keys($exception->errors()));
        }

        try {
            $this->service->setDefault($af, Locale::query()->findOrFail('fa-AF'), true);
            $this->fail('A disabled language became the default.');
        } catch (ValidationException $exception) {
            $this->assertSame(['is_default'], array_keys($exception->errors()));
        }

        // An existing link to a now-disabled language is kept and shown as inactive.
        $panel = $this->service->panel('AF');
        $this->assertSame([['code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)']], $panel['available']);
        $this->assertFalse($panel['links'][0]['is_active']);
        $this->assertSame(1, DB::table('country_locales')->count());
        $this->assertSame([], $this->defaults('AF'));
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function link(string $country, string $locale, bool $default): void
    {
        DB::table('country_locales')->insert(['country_code' => $country, 'locale_code' => $locale, 'is_default' => $default]);
    }

    /** @return list<string> */
    private function defaults(string $country): array
    {
        return DB::table('country_locales')->where('country_code', $country)->where('is_default', true)->pluck('locale_code')->all();
    }
}
