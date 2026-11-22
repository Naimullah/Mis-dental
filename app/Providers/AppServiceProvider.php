<?php

namespace App\Providers;

use App\Auth\ActiveUserProvider;
use App\Models\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('active-eloquent', fn ($app, array $config) => new ActiveUserProvider($app['hash'], $config['model']));
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && $event->user instanceof User) {
                User::query()->whereKey($event->user->id)->where('is_active', true)
                    ->update(['last_login_at' => now()]);
            }
        });
        $this->configureDefaults();
        $this->configureMasterDataAccess();
        // P03.04: only active platform users on the role maintainer list; clinic users never.
        Gate::define(Permission::MANAGE_ROLES, fn (User $user): bool => $user->is_active
            && $user->scope === 'platform'
            && in_array((string) $user->id, config('roles.maintainer_ids', []), true));
        Gate::define(Permission::MANAGE_USERS, fn (User $user): bool => $user->is_active
            && $user->scope === 'platform'
            && in_array((string) $user->id, config('accounts.maintainer_ids', []), true));
        Gate::define(
            Permission::MANAGE_TRANSLATIONS,
            fn (User $user): bool => in_array((string) $user->id, config('permissions.translation_maintainer_ids', []), true),
        );
        Gate::define(
            Permission::MANAGE_DEFINITIONS,
            fn (User $user): bool => $user->is_active
                && $user->scope === 'platform'
                && in_array((string) $user->id, config('permissions.definition_maintainer_ids', []), true),
        );
        Gate::define(
            Permission::VIEW_CATALOG,
            fn (User $user): bool => $user->can(Permission::MANAGE_TRANSLATIONS)
                || $user->can(Permission::MANAGE_DEFINITIONS)
                || in_array((string) $user->id, config('permissions.viewer_ids', []), true),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Temporary master-data gate until the roles/permissions foundation exists.
     * Closed by default, and always closed in production.
     */
    protected function configureMasterDataAccess(): void
    {
        Gate::define('manage-master-data', fn (User $user): bool => ! app()->isProduction()
            && (bool) config('master_data.open_access'));
        Gate::define(Permission::MANAGE_MASTER_DATA, fn (User $user): bool => $user->can('manage-master-data'));
    }
}
