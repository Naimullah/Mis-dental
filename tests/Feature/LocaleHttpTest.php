<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\StoreLocaleRequest;
use App\Http\Requests\UpdateLocaleRequest;
use App\Models\Locale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocaleHttpTest extends TestCase
{
    private const BASE = '/platform/master-data/locales';

    public function test_management_routes_are_unregistered_but_language_switching_remains_available(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertFalse(str_starts_with($route->uri(), ltrim(self::BASE, '/')));
        }

        $this->assertTrue(Route::has('locale.update'));
    }

    public function test_guests_cannot_reach_locale_management(): void
    {
        $this->assertManagementEndpointsAreUnavailable();
    }

    public function test_temporary_master_data_access_does_not_expose_locale_management(): void
    {
        config(['master_data.open_access' => true]);
        $user = new User;
        $this->actingAs($user);
        $this->assertTrue($user->can('manage-master-data'));
        $this->assertNull($this->localeSidebarUrl($user));

        $this->assertManagementEndpointsAreUnavailable();

        foreach ([new StoreLocaleRequest, new UpdateLocaleRequest] as $request) {
            $request->setUserResolver(fn () => $user);
            $this->assertFalse($request->authorize());
        }
    }

    public function test_sidebar_link_requires_a_registered_route_and_locale_read_authorization(): void
    {
        $user = new User;
        Gate::define('viewAny', fn (User $viewer, string $model): bool => $viewer === $user && $model === Locale::class);
        $this->assertNull($this->localeSidebarUrl($user));

        // This route and its permission exist only for the sidebar contract test.
        Route::get(self::BASE, fn () => abort(404))->name('platform.master-data.locales.index');
        Route::getRoutes()->refreshNameLookups();

        $this->assertNull($this->localeSidebarUrl(null));
        $this->assertNull($this->localeSidebarUrl(new User));
        $this->assertSame(self::BASE, $this->localeSidebarUrl($user));
    }

    private function localeSidebarUrl(?User $user): ?string
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        return (new HandleInertiaRequests)->share($request)['localeManagementUrl']();
    }

    private function assertManagementEndpointsAreUnavailable(): void
    {
        $this->getJson(self::BASE)->assertNotFound();
        $this->postJson(self::BASE)->assertNotFound();
        $this->putJson(self::BASE.'/en')->assertNotFound();
        $this->patchJson(self::BASE.'/en/status')->assertNotFound();
        $this->getJson(self::BASE.'/export/pdf')->assertNotFound();
        $this->getJson(self::BASE.'/export/xlsx')->assertNotFound();
    }
}
