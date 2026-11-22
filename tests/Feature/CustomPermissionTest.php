<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomPermissionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/security/permissions';

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

    public function test_only_platform_definition_maintainers_can_manage_custom_permissions(): void
    {
        $this->withoutVite();
        $url = self::URL;
        $this->post($url)->assertRedirect(route('login'));
        $viewer = User::factory()->create();
        $maintainer = User::factory()->create();
        $clinic = User::factory()->create(['scope' => 'clinic']);
        config([
            'permissions.viewer_ids' => [(string) $viewer->id],
            'permissions.translation_maintainer_ids' => [(string) $viewer->id],
            'permissions.definition_maintainer_ids' => [(string) $maintainer->id, (string) $clinic->id],
        ]);
        $this->actingAs($viewer)->post($url)->assertForbidden();
        $this->actingAs($clinic)->post($url)->assertForbidden();
        $this->actingAs($maintainer);

        $input = ['code' => 'Custom.Reporting.View', 'description' => '  View reports.  ', 'sensitivity' => 'medium'];
        $this->postJson($url, array_replace($input, ['code' => Permission::VIEW_CATALOG, 'sensitivity' => 'unknown']))
            ->assertUnprocessable()->assertJsonValidationErrors(['code', 'sensitivity']);
        $this->from($url)->post($url, $input)->assertRedirect($url);
        $permission = Permission::query()->sole();
        $this->assertSame('custom.reporting.view', $permission->code);
        $this->assertSame('View reports.', $permission->description);
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('canManageDefinitions', true)->where('editablePermissionIds', [$permission->id]));
        $record = $url.'/'.$permission->id;
        $this->putJson($record, ['code' => 'custom.renamed', 'description' => 'Updated', 'sensitivity' => 'high'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->put($record, ['description' => 'Updated', 'sensitivity' => 'high'])->assertRedirect();
        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id, 'code' => $permission->code, 'description' => 'Updated', 'sensitivity' => 'high',
        ]);

        DB::table('locales')->insert(['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR', 'is_active' => true]);
        $translation = ['permission_id' => $permission->id, 'locale_code' => 'en', 'display_name' => 'Reports'];
        DB::table('permission_translations')->insert($translation);
        $this->deleteJson($record)->assertUnprocessable()->assertJsonValidationErrors('permission');
        $this->assertDatabaseHas('permission_translations', $translation);
        DB::table('permission_translations')->where('permission_id', $permission->id)->delete();

        $this->actingAs($viewer)->delete($record)->assertForbidden();
        $this->actingAs($maintainer)->delete($record)->assertRedirect();
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_source_controlled_codes_are_protected_even_in_the_custom_namespace(): void
    {
        $maintainer = User::factory()->create();
        $this->actingAs($maintainer);
        config(['permissions.definition_maintainer_ids' => [(string) $maintainer->id]]);
        $definition = ['code' => 'custom.reserved', 'description' => 'Source definition', 'sensitivity' => 'low'];
        config(['permissions.definitions' => [...config('permissions.definitions'), $definition]]);
        $this->postJson(self::URL, $definition)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->artisan('permissions:sync', ['--apply' => true])->assertSuccessful();

        foreach ([Permission::VIEW_CATALOG, 'custom.reserved'] as $code) {
            $permission = Permission::query()->where('code', $code)->firstOrFail();
            $record = self::URL.'/'.$permission->id;
            $this->put($record, ['description' => 'Changed', 'sensitivity' => 'low'])->assertForbidden();
            $this->delete($record)->assertForbidden();
            $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'description' => $permission->description]);
        }
    }
}
