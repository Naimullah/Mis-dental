<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdministrativeAreaRequest;
use App\Models\AdministrativeArea;
use App\Models\Country;
use App\Services\AdministrativeAreas\AdministrativeAreaExporter;
use App\Services\AdministrativeAreas\AdministrativeAreaQuery;
use App\Services\AdministrativeAreas\AdministrativeAreaService;
use App\Services\AdministrativeAreas\AdministrativeAreaTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** P01.05 administrative areas and P01.06 their translations. */
class AdministrativeAreaController extends Controller
{
    public function __construct(
        private readonly AdministrativeAreaService $areas,
        private readonly AdministrativeAreaQuery $query,
        private readonly AdministrativeAreaTranslationService $translations,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->query->filters($request);
        $hasCountry = $filters['country'] !== '' && Country::query()->whereKey($filters['country'])->exists();

        $ancestors = $hasCountry ? $this->query->ancestors($filters['country'], $filters['parent']) : [];
        // Drill-down rows share one parent path; search results can come from anywhere.
        $sharedParentPath = implode(' › ', array_column($ancestors, 'name'));

        $areas = $hasCountry
            ? $this->query->build($filters)->paginate($filters['per_page'])->withQueryString()
                ->through(fn (AdministrativeArea $area): array => $this->row($area) + [
                    'parent_path' => $area->parent_id === null
                        ? ''
                        : ($filters['q'] === '' ? $sharedParentPath : $this->query->pathName($area->parent)),
                ])
            : null;

        return Inertia::render('platform/master-data/administrative-areas/index', [
            'areas' => $areas,
            'filters' => $filters,
            'countries' => Country::query()->orderBy('english_name')->get(['iso2', 'english_name', 'is_active'])
                ->map(fn (Country $country): array => ['iso2' => $country->iso2, 'english_name' => $country->english_name, 'is_active' => $country->is_active]),
            'ancestors' => $ancestors,
            'levels' => $hasCountry
                ? AdministrativeArea::query()->where('country_code', $filters['country'])->distinct()->orderBy('level_code')->pluck('level_code')
                : [],
            'translationLocales' => DB::table('locales')->where('is_active', true)->orderBy('code')
                ->get(['code', 'english_name', 'native_name', 'text_direction']),
            'exportsAvailable' => AdministrativeAreaExporter::available(),
        ]);
    }

    public function parents(Request $request): JsonResponse
    {
        $country = $request->query('country');
        abort_unless(is_string($country) && preg_match('/\A[A-Z]{2}\z/', $country) === 1, 422);

        $q = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 100) : '';
        $exclude = filter_var($request->query('exclude'), FILTER_VALIDATE_INT) ?: null;

        $areas = AdministrativeArea::query()
            ->where('country_code', $country)
            ->when($exclude !== null, fn ($query) => $query->whereKeyNot($exclude))
            ->when($q !== '', function ($query) use ($q): void {
                $term = '%'.addcslashes(mb_strtolower($q), '\\%_').'%';
                $query->where(fn ($where) => $where
                    ->whereRaw("LOWER(canonical_name) LIKE ? ESCAPE '\\'", [$term])
                    ->orWhereRaw("LOWER(area_code) LIKE ? ESCAPE '\\'", [$term]));
            })
            ->orderBy('canonical_name')->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json($areas->map(fn (AdministrativeArea $area): array => [
            'id' => $area->id,
            'path' => $this->query->pathName($area),
            'level_code' => $area->level_code,
            'is_active' => $area->is_active,
        ])->values());
    }

    public function store(AdministrativeAreaRequest $request): RedirectResponse
    {
        $area = $this->areas->create($request->validated());

        return $this->done(__('Area :name created.', ['name' => $area->canonical_name]));
    }

    public function update(AdministrativeAreaRequest $request, AdministrativeArea $area): RedirectResponse
    {
        $area = $this->areas->update($area->id, $request->validated());

        return $this->done(__('Area :name updated.', ['name' => $area->canonical_name]));
    }

    public function updateStatus(Request $request, AdministrativeArea $area): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);

        $active ? $this->areas->activate($area->id) : $this->areas->deactivate($area->id);

        return $this->done($active
            ? __('Area :name activated.', ['name' => $area->canonical_name])
            : __('Area :name deactivated.', ['name' => $area->canonical_name]));
    }

    public function updateTranslations(Request $request, AdministrativeArea $area): RedirectResponse
    {
        $translations = $request->input('translations');
        abort_unless(is_array($translations), 422);

        $this->translations->sync($area, $translations);

        return $this->done(__('Translations for :name saved.', ['name' => $area->canonical_name]));
    }

    public function exportXlsx(Request $request, AdministrativeAreaExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->exportQuery($request));
    }

    public function exportPdf(Request $request, AdministrativeAreaExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->exportQuery($request));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AdministrativeArea> */
    private function exportQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $filters = $this->query->filters($request);
        abort_unless($filters['country'] !== '' && Country::query()->whereKey($filters['country'])->exists(), 422, __('Choose a country first.'));

        return $this->query->build($filters);
    }

    /** @return array<string, mixed> */
    private function row(AdministrativeArea $area): array
    {
        return [
            'id' => $area->id,
            'country_code' => $area->country_code,
            'parent_id' => $area->parent_id,
            'level_code' => $area->level_code,
            'area_code' => $area->area_code,
            'canonical_name' => $area->canonical_name,
            'display_name' => $area->displayName(),
            'is_active' => $area->is_active,
            'children_count' => (int) $area->children_count,
            'translations' => $area->translations->pluck('display_name', 'locale_code'),
        ];
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
