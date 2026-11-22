<?php

namespace App\Http\Middleware;

use App\Models\Locale;
use App\Support\InterfaceLocales;
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
            'direction' => app(InterfaceLocales::class)->direction(app()->getLocale()),
            'locales' => collect(app(InterfaceLocales::class)->enabled())
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

    /**
     * Interface strings for the fallback chain (e.g. fa-AF → fa → en). More specific
     * files win; keys missing everywhere show the English source text.
     *
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $merged = [];

        foreach (array_reverse(app(InterfaceLocales::class)->chain($locale)) as $code) {
            $path = lang_path($code.'.json');

            if (! is_file($path)) {
                continue;
            }

            $translations = json_decode((string) file_get_contents($path), true);

            if (is_array($translations)) {
                $merged = array_replace($merged, array_filter($translations, fn (mixed $value): bool => is_string($value) && $value !== ''));
            }
        }

        return $merged;
    }
}
