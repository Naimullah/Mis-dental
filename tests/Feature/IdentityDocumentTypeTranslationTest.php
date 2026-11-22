<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdentityDocumentTypeTranslationTest extends TestCase
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

    public function test_document_translations_use_the_document_key_and_fall_back_by_locale(): void
    {
        $this->withoutVite();
        DB::table('countries')->insert(['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true]);
        $id = DB::table('identity_document_types')->insertGetId([
            'country_code' => 'AF', 'code' => 'PASSPORT', 'name' => 'Passport', 'is_active' => true,
        ]);
        DB::table('locales')->insert([
            ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'fa', 'english_name' => 'Persian', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto', 'text_direction' => 'RTL', 'is_active' => false],
        ]);

        $base = '/platform/master-data/identity-document-types';
        $url = "$base/$id/translations";
        $list = "$base?country=AF";
        $this->put($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        config(['master_data.open_access' => false]);
        $this->put($url)->assertForbidden();
        config(['master_data.open_access' => true]);

        $this->putJson($url, ['translations' => ['fa-AF' => ['display_name' => str_repeat('a', 161)]]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa-AF.display_name');
        $this->putJson($url, ['translations' => ['ps-AF' => ['display_name' => 'Disabled']]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.ps-AF');

        $name = str_repeat('a', 160);
        $this->from($list)->put($url, ['translations' => [
            'fa-AF' => ['display_name' => $name, 'help_text' => "First\r\nSecond"],
            'fa' => ['display_name' => 'Base'],
        ]])->assertRedirect($list);
        $table = 'identity_document_type_translations';
        $identity = ['document_type_id' => $id, 'locale_code' => 'fa-AF'];
        $this->assertDatabaseHas($table, $identity + ['display_name' => $name, 'help_text' => "First\nSecond"]);
        $this->assertDatabaseCount('license_type_translations', 0);

        $localized = "translationData.localized.$id";
        $this->withSession(['locale' => 'fa-AF'])->get($list)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')->where($localized, ['display_name' => $name, 'help_text' => "First\nSecond"]));
        $this->put($url, ['translations' => ['fa-AF' => ['display_name' => 'Updated']]])->assertRedirect();
        $this->assertDatabaseHas($table, $identity + ['display_name' => 'Updated', 'help_text' => null]);
        $this->put($url, ['translations' => ['fa-AF' => ['display_name' => '']]])->assertRedirect();
        $this->get($list)->assertInertia(fn (Assert $page) => $page->where($localized, ['display_name' => 'Base', 'help_text' => null]));

        DB::table('locales')->where('code', 'fa')->update(['is_active' => false]);
        $this->get($list)->assertInertia(fn (Assert $page) => $page->missing($localized)->where('documentTypes.data.0.name', 'Passport'));

        $row = ['document_type_id' => $id, 'locale_code' => 'fa', 'display_name' => 'Duplicate'];
        foreach ([
            [$row, 'UNIQUE constraint failed'],
            [array_replace($row, ['document_type_id' => $id + 1]), 'FOREIGN KEY constraint failed'],
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
