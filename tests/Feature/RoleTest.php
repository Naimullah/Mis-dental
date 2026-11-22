<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Roles\RoleCatalog;
use App\Services\Roles\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** P03.04 system role catalog, custom roles, scope isolation and the Roles page. */
class RoleTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/security/roles';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
    }

    public function test_catalog_previews_then_applies_the_eight_system_roles(): void
    {
        $catalog = app(RoleCatalog::class);

        $preview = $catalog->sync();
        $this->assertCount(8, $preview['add']);
        $this->assertDatabaseCount('roles', 0);

        $catalog->sync(apply: true);
        $this->assertSame(8, Role::query()->system()->whereNull('clinic_id')->count());
        $this->assertDatabaseHas('roles', ['code' => 'platform_admin', 'scope' => 'platform', 'is_system' => true, 'clinic_id' => null]);
        $this->assertDatabaseHas('roles', ['code' => 'dentist', 'scope' => 'clinic', 'is_system' => true, 'clinic_id' => null]);
        $this->assertSame(['add' => [], 'update' => [], 'unlisted' => []], $catalog->sync(apply: true));

        $ids = Role::query()->pluck('id', 'code')->all();
        config(['roles.definitions' => [
            ['code' => 'platform_admin', 'name' => 'Platform Admin', 'scope' => 'platform'],
        ]]);
        $changes = $catalog->sync(apply: true);
        $this->assertSame(['platform_admin'], $changes['update']);
        $this->assertCount(7, $changes['unlisted']);
        $this->assertSame('Platform Admin', Role::query()->where('code', 'platform_admin')->value('name'));
        $this->assertSame($ids, Role::query()->pluck('id', 'code')->all(), 'ids and unlisted rows are kept');
    }

    public function test_catalog_refuses_invalid_definitions_scope_changes_and_codes_used_by_custom_roles(): void
    {
        $catalog = app(RoleCatalog::class);

        foreach ([
            [['code' => 'Bad Code', 'name' => 'Bad', 'scope' => 'platform']],
            [['code' => 'dup_role', 'name' => 'One', 'scope' => 'platform'], ['code' => 'dup_role', 'name' => 'Two', 'scope' => 'clinic']],
            [['code' => 'no_scope', 'name' => 'No scope', 'scope' => 'global']],
        ] as $definitions) {
            config(['roles.definitions' => $definitions]);
            $this->assertInvalid(fn () => $catalog->sync(apply: true));
        }
        $this->assertDatabaseCount('roles', 0);

        config(['roles.definitions' => [['code' => 'dentist', 'name' => 'Dentist', 'scope' => 'clinic']]]);
        $catalog->sync(apply: true);
        config(['roles.definitions' => [['code' => 'dentist', 'name' => 'Dentist', 'scope' => 'platform']]]);
        $this->assertInvalid(fn () => $catalog->sync(apply: true));

        $this->service()->create($this->platformUser(), ['code' => 'support_desk', 'name' => 'Support desk', 'scope' => 'platform']);
        config(['roles.definitions' => [['code' => 'support_desk', 'name' => 'Support', 'scope' => 'platform']]]);
        $this->assertInvalid(fn () => $catalog->sync(apply: true));
        $this->assertFalse(Role::query()->where('code', 'support_desk')->firstOrFail()->is_system);
    }

    public function test_platform_users_create_and_rename_custom_platform_roles_only(): void
    {
        app(RoleCatalog::class)->sync(apply: true);
        $actor = $this->platformUser();

        $role = $this->service()->create($actor, ['code' => '  Support_Desk ', 'name' => ' Support desk ', 'scope' => 'platform']);
        $this->assertSame(['support_desk', 'Support desk', 'platform', false, null], [$role->code, $role->name, $role->scope, $role->is_system, $role->clinic_id]);

        $this->assertInvalid(fn () => $this->service()->create($actor, ['code' => 'night_shift', 'name' => 'Night', 'scope' => 'clinic']), 'scope');
        $this->assertInvalid(fn () => $this->service()->create($actor, ['code' => 'platform_admin', 'name' => 'Copy', 'scope' => 'platform']), 'code');
        $this->assertInvalid(fn () => $this->service()->create($actor, ['code' => 'with_flag', 'name' => 'Flag', 'scope' => 'platform', 'is_system' => true]), 'is_system');
        $this->assertInvalid(fn () => $this->service()->create($actor, ['code' => 'with_clinic', 'name' => 'Clinic', 'scope' => 'platform', 'clinic_id' => 5]), 'clinic_id');
        $this->assertInvalid(fn () => $this->service()->create($actor, ['code' => 'bad code', 'name' => 'Bad', 'scope' => 'platform']), 'code');
        $this->assertInvalid(fn () => $this->service()->create($actor, ['code' => 'hidden', 'name' => "Bad\u{202E}", 'scope' => 'platform']), 'name');

        $this->service()->rename($actor, $role->id, ['name' => 'Help desk']);
        $this->assertSame('Help desk', $role->fresh()->name);
        $this->assertInvalid(fn () => $this->service()->rename($actor, $role->id, ['name' => 'X', 'code' => 'other_code']), 'code');
        $this->assertInvalid(fn () => $this->service()->rename($actor, $role->id, ['name' => 'X', 'scope' => 'clinic']), 'scope');

        $system = Role::query()->where('code', 'platform_admin')->firstOrFail();
        $this->assertInvalid(fn () => $this->service()->rename($actor, $system->id, ['name' => 'Renamed']), 'name');
        $this->assertSame('Platform Administrator', $system->fresh()->name);
    }

    public function test_clinic_and_inactive_users_can_never_change_roles(): void
    {
        app(RoleCatalog::class)->sync(apply: true);
        $custom = $this->service()->create($this->platformUser(), ['code' => 'support_desk', 'name' => 'Support', 'scope' => 'platform']);
        $admin = Role::query()->where('code', 'platform_admin')->firstOrFail();
        $clinicUser = User::factory()->create(['scope' => 'clinic']);
        $inactive = User::factory()->create(['is_active' => false]);

        foreach ([$clinicUser, $inactive] as $actor) {
            foreach ([
                fn () => $this->service()->create($actor, ['code' => 'my_admin', 'name' => 'Mine', 'scope' => 'platform']),
                fn () => $this->service()->rename($actor, $custom->id, ['name' => 'Taken over']),
                fn () => $this->service()->rename($actor, $admin->id, ['name' => 'Taken over']),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('A non-platform or inactive user changed a role.');
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }
        }

        $this->assertSame('Support', $custom->fresh()->name);
        $this->assertSame('Platform Administrator', $admin->fresh()->name);
        $this->assertDatabaseMissing('roles', ['code' => 'my_admin']);
    }

    public function test_model_protects_system_roles_identifiers_and_deletion(): void
    {
        app(RoleCatalog::class)->sync(apply: true);
        $custom = $this->service()->create($this->platformUser(), ['code' => 'support_desk', 'name' => 'Support', 'scope' => 'platform']);
        $system = Role::query()->where('code', 'platform_admin')->firstOrFail();

        foreach ([
            fn () => $system->fill(['name' => 'Changed'])->save(),
            fn () => tap($custom->fresh(), fn (Role $role) => $role->code = 'other_code')->save(),
            fn () => tap($custom->fresh(), fn (Role $role) => $role->scope = 'clinic')->save(),
            fn () => tap($custom->fresh(), fn (Role $role) => $role->is_system = true)->save(),
            fn () => $custom->fresh()->delete(),
            fn () => $system->fresh()->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A protected role change was saved.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('roles', 9);
    }

    public function test_page_access_is_limited_to_listed_active_platform_users(): void
    {
        $this->withoutVite();
        $this->get(self::URL)->assertRedirect(route('login'));

        $platform = $this->platformUser();
        $clinic = User::factory()->create(['scope' => 'clinic']);
        $other = User::factory()->create();

        $this->actingAs($other)->get(self::URL)->assertForbidden();
        $this->actingAs($other)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('can.manageRoles', false));

        config(['roles.maintainer_ids' => [(string) $platform->id, (string) $clinic->id]]);
        $this->actingAs($clinic)->get(self::URL)->assertForbidden();
        $this->actingAs($clinic)->post(self::URL, ['code' => 'my_admin', 'name' => 'Mine', 'scope' => 'platform'])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['code' => 'my_admin']);

        $this->actingAs($platform)->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/security/roles/index')
            ->where('can.manageRoles', true));
    }

    public function test_listing_search_filters_sorting_and_page_size(): void
    {
        $this->withoutVite();
        app(RoleCatalog::class)->sync(apply: true);
        $actor = $this->platformUser();
        config(['roles.maintainer_ids' => [(string) $actor->id]]);
        $this->service()->create($actor, ['code' => 'support_desk', 'name' => 'Support desk', 'scope' => 'platform']);
        // A clinic's own role is never listed on the platform page.
        DB::table('roles')->insert(['clinic_id' => 7, 'code' => 'night_shift', 'name' => 'Night shift', 'scope' => 'clinic', 'is_system' => false]);
        $this->actingAs($actor);

        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where('roles.total', 9)
            ->where('roles.data.0.name', 'Clinic Sub Administrator')
            ->where('filters', ['q' => '', 'scope' => 'all', 'kind' => 'all', 'sort' => 'name', 'dir' => 'asc', 'per_page' => 25]));

        $this->get(self::URL.'?scope=platform')->assertInertia(fn (Assert $page) => $page->where('roles.total', 2));
        $this->get(self::URL.'?kind=custom')->assertInertia(fn (Assert $page) => $page
            ->where('roles.total', 1)
            ->where('roles.data.0', ['id' => (int) Role::query()->where('code', 'support_desk')->value('id'), 'code' => 'support_desk', 'name' => 'Support desk', 'scope' => 'platform', 'is_system' => false]));
        $this->get(self::URL.'?kind=system&scope=clinic')->assertInertia(fn (Assert $page) => $page->where('roles.total', 7));
        $this->get(self::URL.'?q=ADMIN')->assertInertia(fn (Assert $page) => $page->where('roles.total', 3));
        $this->get(self::URL.'?q=%25')->assertInertia(fn (Assert $page) => $page->where('roles.total', 0));
        $this->get(self::URL.'?sort=code&dir=desc')->assertInertia(fn (Assert $page) => $page->where('roles.data.0.code', 'support_desk'));
        $this->get(self::URL.'?per_page=10&sort=bogus&scope=x&kind=y')->assertInertia(fn (Assert $page) => $page
            ->has('roles.data', 9)
            ->where('filters.per_page', 10)
            ->where('filters.sort', 'name')
            ->where('filters.scope', 'all')
            ->where('filters.kind', 'all'));
        $this->get(self::URL.'?per_page=7')->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 25));
    }

    public function test_http_create_rename_and_protections(): void
    {
        app(RoleCatalog::class)->sync(apply: true);
        $actor = $this->platformUser();
        config(['roles.maintainer_ids' => [(string) $actor->id]]);
        $this->actingAs($actor);

        $this->post(self::URL, ['code' => 'support_desk', 'name' => 'Support desk', 'scope' => 'platform'])->assertSessionHasNoErrors();
        $id = (int) Role::query()->where('code', 'support_desk')->value('id');
        $this->put(self::URL."/{$id}", ['name' => 'Help desk'])->assertSessionHasNoErrors();
        $this->assertSame('Help desk', Role::query()->whereKey($id)->value('name'));

        $this->post(self::URL, ['code' => 'night_shift', 'name' => 'Night', 'scope' => 'clinic'])->assertSessionHasErrors('scope');
        $this->put(self::URL."/{$id}", ['name' => 'X', 'code' => 'renamed'])->assertSessionHasErrors('code');

        $system = (int) Role::query()->where('code', 'platform_admin')->value('id');
        $this->put(self::URL."/{$system}", ['name' => 'Mine now'])->assertSessionHasErrors('name');
        $this->delete(self::URL."/{$id}")->assertMethodNotAllowed();
        $this->put(self::URL.'/99999', ['name' => 'X'])->assertNotFound();
        $this->put(self::URL.'/abc', ['name' => 'X'])->assertNotFound();

        $this->assertDatabaseCount('roles', 9);
        $this->assertSame('Platform Administrator', Role::query()->whereKey($system)->value('name'));
    }

    public function test_dari_interface_translates_the_page(): void
    {
        $this->withoutVite();
        $actor = $this->platformUser();
        config(['roles.maintainer_ids' => [(string) $actor->id]]);
        $this->actingAs($actor)->post('/locale', ['locale' => 'fa-AF']);

        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'rtl')
            ->where('translations.Roles', 'نقش‌ها'));
    }

    private function service(): RoleService
    {
        return app(RoleService::class);
    }

    private function platformUser(): User
    {
        return User::factory()->create(['scope' => 'platform', 'is_active' => true]);
    }

    private function assertInvalid(callable $write, ?string $field = null): void
    {
        try {
            $write();
            $this->fail('Expected a validation error'.($field ? " on {$field}" : '').'.');
        } catch (ValidationException $exception) {
            if ($field !== null) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
    }
}
