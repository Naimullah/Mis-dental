<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PermissionTranslationTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/security/permissions';

    private int $id;

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
        $this->id = DB::table('permissions')->insertGetId([
            'code' => 'platform.example.view', 'description' => 'Canonical description', 'sensitivity' => 'medium',
        ]);
        DB::table('locales')->insert([
            ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'fa', 'english_name' => 'Persian', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto', 'text_direction' => 'RTL', 'is_active' => false],
        ]);
    }

    public function test_translations_require_maintainer_access_and_active_locale_to_save(): void
    {
        $url = self::URL.'/'.$this->id.'/translations/';
        $this->put($url.'fa')->assertRedirect(route('login'));
        $viewer = User::factory()->create();
        config(['permissions.viewer_ids' => [(string) $viewer->id]]);
        $this->actingAs($viewer);
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page->where('canMaintainTranslations', false));
        $this->put($url.'fa', ['display_name' => 'Denied'])->assertForbidden();
        $this->delete($url.'fa')->assertForbidden();

        $this->maintainer();
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page->where('canMaintainTranslations', true));
        $this->putJson($url.'ps-AF', ['display_name' => 'Disabled'])->assertUnprocessable()->assertJsonValidationErrors('locale_code');
        $this->putJson($url.'fr', ['display_name' => 'Unknown'])->assertNotFound();
        $this->putJson($url.'fa', ['display_name' => str_repeat('a', 161)])->assertUnprocessable()->assertJsonValidationErrors('display_name');
        $this->putJson($url.'fa', ['display_name' => '  '])->assertUnprocessable()->assertJsonValidationErrors('display_name');
        $this->putJson($url.'fa', ['display_name' => 'Valid', 'description' => "Bad\u{202E}"])->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('permission_translations', 0);
    }

    public function test_search_filter_fallback_update_and_inactive_removal(): void
    {
        $this->maintainer();
        $url = self::URL.'/'.$this->id.'/translations/';
        $localized = 'localized.'.$this->id;
        $this->from(self::URL)->put($url.'fa', ['display_name' => 'Base name'])->assertRedirect(self::URL);
        $this->from(self::URL)->put($url.'fa-AF', ['display_name' => '  نمایش  ', 'description' => "First\r\nSecond"])->assertRedirect(self::URL);

        $this->withSession(['locale' => 'fa-AF'])->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where($localized, ['display_name' => 'نمایش', 'description' => "First\nSecond"]));
        $this->get(self::URL.'?q='.rawurlencode('نمایش').'&locale=fa-AF')->assertInertia(fn (Assert $page) => $page->where('permissions.total', 1));
        $this->get(self::URL.'?q='.rawurlencode('نمایش').'&locale=fa')->assertInertia(fn (Assert $page) => $page->where('permissions.total', 0));

        $this->put($url.'fa-AF', ['display_name' => 'Updated'])->assertRedirect();
        $this->assertDatabaseCount('permission_translations', 2);
        $this->delete($url.'fa-AF')->assertRedirect();
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where($localized, ['display_name' => 'Base name', 'description' => 'Canonical description']));

        DB::table('locales')->where('code', 'fa')->update(['is_active' => false]);
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where($localized, ['display_name' => 'platform.example.view', 'description' => 'Canonical description'])
            ->has('permissions.data.0.translations', 1));
        $this->get(self::URL.'?q=Base+name')->assertInertia(fn (Assert $page) => $page->where('permissions.total', 0));
        $this->delete($url.'fa')->assertRedirect();
        $this->assertDatabaseCount('permission_translations', 0);
        $this->assertDatabaseHas('permissions', ['id' => $this->id, 'code' => 'platform.example.view', 'description' => 'Canonical description']);
    }

    public function test_database_enforces_composite_uniqueness_and_foreign_keys(): void
    {
        $row = ['permission_id' => $this->id, 'locale_code' => 'fa', 'display_name' => 'Base'];
        DB::table('permission_translations')->insert($row);

        foreach ([
            [$row, 'UNIQUE constraint failed'],
            [array_replace($row, ['permission_id' => $this->id + 1]), 'FOREIGN KEY constraint failed'],
            [array_replace($row, ['locale_code' => 'fr']), 'FOREIGN KEY constraint failed'],
        ] as [$invalid, $message]) {
            try {
                DB::table('permission_translations')->insert($invalid);
                $this->fail('The database accepted an invalid translation.');
            } catch (QueryException $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
    }

    private function maintainer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        config(['permissions.translation_maintainer_ids' => [(string) $user->id]]);
    }
}
