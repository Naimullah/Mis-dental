<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Countries are read-only until explicit platform permissions exist (owner decision). */
class CountryHttpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/countries';

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
        $this->seedCountries();
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE)->assertForbidden();
    }

    public function test_access_stays_closed_in_production_even_when_switch_is_on(): void
    {
        config(['master_data.open_access' => true]);
        $this->app['env'] = 'production';
        $this->actingAs(User::factory()->create());

        $this->get(self::BASE)->assertForbidden();
    }

    public function test_changes_and_exports_are_blocked_even_with_access(): void
    {
        $this->openAccess();
        $this->seedCountries();

        $this->post(self::BASE, ['iso2' => 'XK', 'iso3' => null, 'english_name' => 'Kosovo', 'is_active' => true])->assertForbidden();
        $this->put(self::BASE.'/AF', ['iso3' => null, 'english_name' => 'Changed', 'is_active' => false])->assertForbidden();
        $this->patch(self::BASE.'/AF/status', ['is_active' => false])->assertForbidden();
        $this->get(self::BASE.'/export/xlsx')->assertForbidden();
        $this->get(self::BASE.'/export/pdf')->assertForbidden();
        $this->delete(self::BASE.'/AF')->assertMethodNotAllowed();

        $this->assertDatabaseCount('countries', 4);
        $this->assertDatabaseHas('countries', ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true]);
    }

    public function test_index_lists_countries_read_only(): void
    {
        $this->openAccess();
        $this->seedCountries();

        $this->get(self::BASE)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/master-data/countries/index')
                ->has('countries.data', 4)
                ->where('countries.data.0', ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true])
                ->where('countries.data.3.iso2', 'PK')
                ->where('countries.per_page', 20)
                ->where('filters', [])
                ->where('canMutate', false)
                ->where('exportsAvailable', ['xlsx' => false, 'pdf' => false])
                ->where('countryLocales', null));
    }

    public function test_search_status_and_sort_are_applied_and_echoed(): void
    {
        $this->openAccess();
        $this->seedCountries();

        $this->get(self::BASE.'?search=%20irn%20')
            ->assertInertia(fn (Assert $page) => $page
                ->has('countries.data', 1)
                ->where('countries.data.0.iso2', 'IR')
                ->where('filters.search', 'irn'));

        $this->get(self::BASE.'?status=inactive')
            ->assertInertia(fn (Assert $page) => $page
                ->has('countries.data', 1)
                ->where('countries.data.0.iso2', 'DE'));

        $this->get(self::BASE.'?sort=english_name&direction=desc&per_page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('countries.data', 2)
                ->where('countries.data.0.iso2', 'PK')
                ->where('countries.data.1.iso2', 'IR')
                ->where('filters.per_page', 2)
                ->where('countries.last_page', 2));
    }

    public function test_invalid_query_options_are_rejected(): void
    {
        $this->openAccess();
        $this->seedCountries();

        $this->get(self::BASE.'?sort=password')->assertSessionHasErrors(['sort']);
        $this->get(self::BASE.'?per_page=500')->assertSessionHasErrors(['per_page']);
    }

    public function test_dari_locale_is_shared_with_the_countries_page(): void
    {
        $this->openAccess();
        $this->post('/locale', ['locale' => 'fa-AF']);

        $this->get(self::BASE)
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('direction', 'rtl')
                ->where('translations.Countries', 'کشورها'));
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function seedCountries(): void
    {
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
            ['iso2' => 'IR', 'iso3' => 'IRN', 'english_name' => 'Iran', 'is_active' => true],
            ['iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Germany', 'is_active' => false],
        ]);
    }
}
