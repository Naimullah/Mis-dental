<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PermissionSensitivity;
use App\Http\Controllers\Controller;
use App\Models\Locale;
use App\Models\Permission;
use App\Services\Permissions\CustomPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public const PER_PAGE = [10, 25, 50, 100];

    public function index(Request $request, CustomPermissions $custom): Response
    {
        $search = $request->query('q');
        $search = is_string($search) ? mb_substr(trim($search), 0, 120) : '';
        $sensitivity = $request->query('sensitivity');
        $sensitivity = is_string($sensitivity) ? PermissionSensitivity::tryFrom($sensitivity) : null;
        $sort = $request->query('sort') === 'sensitivity' ? 'sensitivity' : 'code';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 25;
        $canMaintain = $request->user()?->can(Permission::MANAGE_TRANSLATIONS) ?? false;
        $canManageDefinitions = $request->user()?->can(Permission::MANAGE_DEFINITIONS) ?? false;
        $locales = Locale::query()->when(! $canMaintain, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('code')->get(['code', 'english_name', 'native_name', 'text_direction', 'is_active']);
        $activeCodes = $locales->where('is_active', true)->pluck('code')->all();
        $locale = $request->query('locale');
        $locale = in_array($locale, $activeCodes, true) ? $locale : '';
        $filters = ['q' => $search, 'sensitivity' => $sensitivity->value ?? 'all', 'sort' => $sort, 'direction' => $direction, 'locale' => $locale, 'per_page' => $perPage];

        $query = Permission::query()->with(['translations' => fn ($translations) => $translations
            ->whereIn('locale_code', $locales->pluck('code')->all())->orderBy('locale_code')]);
        if ($locale !== '') {
            $query->whereHas('translations', fn (Builder $translation) => $translation->where('locale_code', $locale));
        }

        if ($search !== '') {
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $searchLocales = $locale === '' ? $activeCodes : [$locale];
            $query->where(fn (Builder $matches) => $matches
                ->whereRaw("LOWER(code) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(description) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereHas('translations', fn (Builder $translation) => $translation
                    ->whereIn('locale_code', $searchLocales)
                    ->whereRaw("LOWER(display_name) LIKE LOWER(?) ESCAPE '!'", [$pattern])));
        }
        if ($sensitivity !== null) {
            $query->where('sensitivity', $sensitivity->value);
        }

        if ($sort === 'sensitivity') {
            $query->orderByRaw("CASE sensitivity WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'critical' THEN 4 END ".$direction)
                ->orderBy('code');
        } else {
            $query->orderBy('code', $direction);
        }

        $permissions = $query->paginate($perPage)->appends($filters);
        $localized = [];
        $editablePermissionIds = [];
        $requested = $locale === '' ? app()->getLocale() : $locale;
        $base = Str::before($requested, '-');
        foreach ($permissions->items() as $permission) {
            if ($canManageDefinitions && $custom->canEdit($permission)) {
                $editablePermissionIds[] = $permission->id;
            }
            $available = $permission->translations->whereInStrict('locale_code', $activeCodes)->keyBy('locale_code');
            $translation = $available->get($requested) ?? $available->get($base);
            $localized[$permission->id] = [
                'display_name' => $translation->display_name ?? $permission->code,
                'description' => $translation->description ?? $permission->description,
            ];
        }

        return Inertia::render('platform/security/permissions/index', [
            'permissions' => $permissions,
            // Not named "locales": that shared prop feeds the interface language switcher.
            'languageOptions' => $locales,
            'localized' => $localized,
            'canMaintainTranslations' => $canMaintain,
            'canManageDefinitions' => $canManageDefinitions,
            'editablePermissionIds' => $editablePermissionIds,
            'filters' => $filters,
            'sensitivities' => array_map(
                fn (PermissionSensitivity $level): array => ['value' => $level->value, 'label' => $level->label()],
                PermissionSensitivity::cases(),
            ),
        ]);
    }
}
