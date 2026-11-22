<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PermissionCatalogTest extends TestCase
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

    public function test_sync_previews_by_default_and_preserves_ids_and_unlisted_rows(): void
    {
        $this->artisan('permissions:sync')->expectsOutputToContain(Permission::VIEW_CATALOG)->assertSuccessful();
        $this->assertDatabaseCount('permissions', 0);

        $this->artisan('permissions:sync', ['--apply' => true])->assertSuccessful();
        $original = Permission::query()->where('code', Permission::VIEW_CATALOG)->firstOrFail();
        config(['permissions.definitions' => [[
            'code' => Permission::VIEW_CATALOG, 'description' => 'Updated details.', 'sensitivity' => 'critical',
        ]]]);

        $this->artisan('permissions:sync')
            ->expectsOutput('Update: '.Permission::VIEW_CATALOG)
            ->expectsOutput('Unlisted (retained): '.Permission::MANAGE_MASTER_DATA.', '.Permission::MANAGE_DEFINITIONS.', '.Permission::MANAGE_TRANSLATIONS.', '.Permission::MANAGE_USERS)->assertSuccessful();
        $this->assertDatabaseHas('permissions', ['id' => $original->id, 'sensitivity' => 'medium']);
        $this->artisan('permissions:sync', ['--apply' => true])->assertSuccessful();
        $this->artisan('permissions:sync')->expectsOutput('Update: none')->assertSuccessful();
        $this->assertDatabaseHas('permissions', ['id' => $original->id, 'code' => $original->code, 'description' => 'Updated details.', 'sensitivity' => 'critical']);
        $this->assertDatabaseCount('permissions', 5);
    }

    public function test_invalid_catalog_is_rejected_before_writing(): void
    {
        config(['permissions.definitions' => [
            ['code' => 'platform.example.read', 'description' => 'Valid definition', 'sensitivity' => 'low'],
            ['code' => 'platform.example.read', 'description' => 'Duplicate', 'sensitivity' => 'low'],
            ['code' => 'Invalid code', 'description' => '  ', 'sensitivity' => 'unknown'],
        ]]);

        $this->artisan('permissions:sync', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_schema_rejects_duplicates_invalid_sensitivity_and_blank_descriptions(): void
    {
        $columns = collect(DB::select("PRAGMA table_info('permissions')"))->keyBy('name');
        $this->assertSame(['id', 'code', 'description', 'sensitivity'], $columns->keys()->all());
        $this->assertEquals(1, $columns['id']->pk);
        foreach (['code', 'description', 'sensitivity'] as $field) {
            $this->assertEquals(1, $columns[$field]->notnull);
        }

        $row = ['code' => 'platform.example.read', 'description' => 'Example', 'sensitivity' => 'low'];
        DB::table('permissions')->insert($row);
        foreach ([
            [$row, 'UNIQUE constraint failed'],
            [array_replace($row, ['code' => 'platform.example.write', 'sensitivity' => 'invalid']), 'permissions_sensitivity_check'],
            [array_replace($row, ['code' => 'platform.example.write', 'description' => '  ']), 'permissions_description_check'],
        ] as [$invalid, $message]) {
            try {
                DB::table('permissions')->insert($invalid);
                $this->fail('The database accepted an invalid definition.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function test_read_access_is_explicit_and_the_web_catalog_is_read_only(): void
    {
        $this->withoutVite();
        $this->get(self::URL)->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user);
        config(['permissions.viewer_ids' => [], 'master_data.open_access' => true]);
        $this->get(self::URL)->assertForbidden();

        config(['permissions.viewer_ids' => [(string) $user->id]]);
        $this->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/security/permissions/index')
            ->where('can.viewPermissionCatalog', true));
        $this->post(self::URL)->assertForbidden();
        $this->put(self::URL)->assertMethodNotAllowed();
        $this->delete(self::URL)->assertMethodNotAllowed();
        $this->put(self::URL.'/1')->assertNotFound();
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_search_sensitivity_order_filter_and_pagination(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->actingAs($user);
        config(['permissions.viewer_ids' => [(string) $user->id]]);

        foreach (range(1, 27) as $number) {
            DB::table('permissions')->insert([
                'code' => sprintf('platform.example.rule_%02d', $number), 'description' => 'Example definition', 'sensitivity' => 'medium',
            ]);
        }
        DB::table('permissions')->insert([
            ['code' => 'platform.other.low', 'description' => 'Check % literal', 'sensitivity' => 'low'],
            ['code' => 'platform.other.critical', 'description' => 'Unrelated', 'sensitivity' => 'critical'],
        ]);

        $this->get(self::URL.'?q=EXAMPLE&sensitivity=medium&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('permissions.total', 27)->has('permissions.data', 2)
            ->where('permissions.data.0.code', 'platform.example.rule_26')
            ->where('filters.sensitivity', 'medium'));
        $this->get(self::URL.'?q=rule_02')->assertInertia(fn (Assert $page) => $page->where('permissions.total', 1));
        $this->get(self::URL.'?q=%25')->assertInertia(fn (Assert $page) => $page
            ->where('permissions.total', 1)->where('permissions.data.0.code', 'platform.other.low'));
        $this->get(self::URL.'?sort=sensitivity&direction=desc')->assertInertia(fn (Assert $page) => $page
            ->where('permissions.data.0.sensitivity', 'critical')
            ->where('permissions.data.1.code', 'platform.example.rule_01'));
    }
}
