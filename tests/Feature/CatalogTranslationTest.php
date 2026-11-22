<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogTranslationTest extends TestCase
{
    use RefreshDatabase;

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
        DB::table('currencies')->insert(['code' => 'AFN', 'english_name' => 'Afghani', 'minor_units' => 2, 'is_active' => true]);
        DB::table('locales')->insert([
            ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'fa', 'english_name' => 'Persian', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto', 'text_direction' => 'RTL', 'is_active' => false],
        ]);
    }

    /** @return array<string, array{string, string, string, string, string, int}> */
    public static function catalogs(): array
    {
        return [
            'country' => ['countries', 'country_translations', 'country_code', 'AF', 'Afghanistan', 160],
            'currency' => ['currencies', 'currency_translations', 'currency_code', 'AFN', 'Afghani', 120],
        ];
    }

    #[DataProvider('catalogs')]
    public function test_translation_workflow(string $catalog, string $table, string $foreignKey, string $code, string $canonical, int $maxLength): void
    {
        $index = '/platform/master-data/'.$catalog;
        $endpoint = "$index/$code/translations";
        $identity = [$foreignKey => $code, 'locale_code' => 'fa-AF'];

        $this->put($endpoint)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        config(['master_data.open_access' => false]);
        $this->put($endpoint, ['translations' => ['fa-AF' => 'Denied']])->assertForbidden();
        config(['master_data.open_access' => true]);

        $this->putJson($endpoint, ['translations' => [
            'fa-AF' => str_repeat('a', $maxLength + 1),
            'ps-AF' => 'Disabled',
        ]])->assertUnprocessable()->assertJsonValidationErrors('translations.fa-AF');
        $this->putJson($endpoint, ['translations' => ['fa-AF' => 'Valid', 'ps-AF' => 'Disabled', 'fr' => 'Unknown']])
            ->assertUnprocessable()->assertJsonValidationErrors(['translations.ps-AF', 'translations.fr']);
        $this->assertDatabaseCount($table, 0);

        $this->from($index)->put($endpoint, ['translations' => ['fa-AF' => '  افغانستان  ', 'fa' => 'Base']])
            ->assertRedirect($index);
        $this->assertDatabaseHas($table, $identity + ['display_name' => 'افغانستان']);

        $this->withSession(['locale' => 'fa-AF'])->get($index)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')
            ->where("catalogTranslations.displayNames.$code", 'افغانستان')
            ->has('catalogTranslations.locales', 2)
            ->where("catalogTranslations.names.$code.fa-AF", 'افغانستان'));

        $this->put($endpoint, ['translations' => ['fa-AF' => 'Updated']])->assertRedirect();
        $this->assertDatabaseHas($table, $identity + ['display_name' => 'Updated']);
        $this->assertDatabaseCount($table, 2);

        $this->put($endpoint, ['translations' => ['fa-AF' => '']])->assertRedirect();
        $this->assertDatabaseMissing($table, $identity);
        $this->get($index)->assertInertia(fn (Assert $page) => $page->where("catalogTranslations.displayNames.$code", 'Base'));

        DB::table('locales')->where('code', 'fa')->update(['is_active' => false]);
        $this->get($index)->assertInertia(fn (Assert $page) => $page->where("catalogTranslations.displayNames.$code", $canonical));
        $this->assertDatabaseHas($table, [$foreignKey => $code, 'locale_code' => 'fa', 'display_name' => 'Base']);
    }

    #[DataProvider('catalogs')]
    public function test_composite_key_and_foreign_keys(string $catalog, string $table, string $foreignKey, string $code, string $canonical, int $maxLength): void
    {
        $columns = collect(DB::select("PRAGMA table_info('$table')"))->keyBy('name');
        $this->assertSame([$foreignKey, 'locale_code', 'display_name'], $columns->keys()->all());
        $this->assertEquals(1, $columns[$foreignKey]->pk);
        $this->assertEquals(2, $columns['locale_code']->pk);
        $this->assertEquals(1, $columns['display_name']->notnull);

        $row = [$foreignKey => $code, 'locale_code' => 'fa-AF', 'display_name' => $canonical];
        DB::table($table)->insert($row);

        foreach ([
            [$row, 'UNIQUE constraint failed'],
            [array_replace($row, [$foreignKey => str_repeat('Z', strlen($code))]), 'FOREIGN KEY constraint failed'],
            [array_replace($row, ['locale_code' => 'fr']), 'FOREIGN KEY constraint failed'],
        ] as [$invalid, $message]) {
            try {
                DB::table($table)->insert($invalid);
                $this->fail('The database accepted an invalid translation.');
            } catch (QueryException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }

        $this->assertDatabaseCount($table, 1);
    }
}
