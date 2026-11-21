<?php

namespace App\Http\Middleware;

use App\Models\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => app()->getLocale(),
            'direction' => config('locales.supported.'.app()->getLocale().'.dir', 'ltr'),
            'locales' => collect(config('locales.supported', []))
                ->map(fn (array $locale, string $code): array => ['code' => $code, 'name' => $locale['name']])
                ->values(),
            'translations' => fn (): array => $this->translations(app()->getLocale()),
            'can' => [
                'manageMasterData' => fn (): bool => $request->user()?->can('manage-master-data') ?? false,
            ],
            'localeManagementUrl' => fn (): ?string => $this->localeManagementUrl($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function localeManagementUrl(Request $request): ?string
    {
        $name = 'platform.master-data.locales.index';

        if (! Route::has($name) || ! $request->user()?->can('viewAny', Locale::class)) {
            return null;
        }

        return route($name, absolute: false);
    }

    /** @return array<string, string> */
    private function translations(string $locale): array
    {
        $path = lang_path($locale.'.json');

        if ($locale === 'en' || ! is_file($path)) {
            return [];
        }

        $translations = json_decode((string) file_get_contents($path), true);

        return is_array($translations) ? $translations : [];
    }
}
