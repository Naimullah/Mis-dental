<?php

namespace App\Http\Controllers\Platform;

use App\Enums\LicenseAppliesTo;
use App\Http\Controllers\Controller;
use App\Http\Requests\LicenseTypeRequest;
use App\Models\Country;
use App\Models\LicenseType;
use App\Services\CatalogTranslations\TypeTranslations;
use App\Services\LicenseTypes\LicenseTypeExporter;
use App\Services\LicenseTypes\LicenseTypeQuery;
use App\Services\LicenseTypes\LicenseTypeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** P01.07 license types. */
class LicenseTypeController extends Controller
{
    public function __construct(
        private readonly LicenseTypeService $types,
        private readonly LicenseTypeQuery $query,
    ) {}

    public function index(Request $request, TypeTranslations $translations): Response
    {
        $filters = $this->query->filters($request);
        $hasCountry = $this->countryExists($filters['country']);

        $types = $hasCountry
                ? $this->query->build($filters)->paginate($filters['per_page'])->withQueryString()
                    ->through(fn (LicenseType $type): array => [
                        'id' => $type->id,
                        'country_code' => $type->country_code,
                        'code' => $type->code,
                        'name' => $type->name,
                        'applies_to' => $type->applies_to,
                        'is_active' => $type->is_active,
                    ])
                : null;

        return Inertia::render('platform/master-data/license-types/index', [
            'licenseTypes' => $types,
            'translationData' => $types === null ? null : $translations->forPage(
                new LicenseType,
                array_values($types->getCollection()->pluck('id')->all()),
                app()->getLocale(),
            ),
            'filters' => $filters,
            'countries' => Country::query()->orderBy('english_name')->get(['iso2', 'english_name', 'is_active'])
                ->map(fn (Country $country): array => ['iso2' => $country->iso2, 'english_name' => $country->english_name, 'is_active' => $country->is_active]),
            'appliesToOptions' => array_map(
                fn (LicenseAppliesTo $option): array => ['value' => $option->value, 'label' => $option->label()],
                LicenseAppliesTo::cases(),
            ),
            'exportsAvailable' => LicenseTypeExporter::available(),
        ]);
    }

    public function store(LicenseTypeRequest $request): RedirectResponse
    {
        $type = $this->types->create($request->validated());

        return $this->done(__('License type :code created.', ['code' => $type->code]));
    }

    public function update(LicenseTypeRequest $request, LicenseType $licenseType): RedirectResponse
    {
        $this->types->update($licenseType->id, $request->validated());

        return $this->done(__('License type :code updated.', ['code' => $licenseType->code]));
    }

    public function updateStatus(Request $request, LicenseType $licenseType): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);

        $active ? $this->types->activate($licenseType->id) : $this->types->deactivate($licenseType->id);

        return $this->done($active
            ? __('License type :code activated.', ['code' => $licenseType->code])
            : __('License type :code deactivated.', ['code' => $licenseType->code]));
    }

    public function updateTranslations(Request $request, LicenseType $licenseType, TypeTranslations $translations): RedirectResponse
    {
        $translations->update($licenseType, $request->input('translations'));

        return $this->done(__('Translations for :name saved.', ['name' => $licenseType->name]));
    }

    public function exportXlsx(Request $request, LicenseTypeExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->exportQuery($request));
    }

    public function exportPdf(Request $request, LicenseTypeExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->exportQuery($request));
    }

    /** @return Builder<LicenseType> */
    private function exportQuery(Request $request): Builder
    {
        $filters = $this->query->filters($request);
        abort_unless($this->countryExists($filters['country']), 422, __('Choose a country first.'));

        return $this->query->build($filters);
    }

    private function countryExists(string $code): bool
    {
        return $code !== '' && Country::query()->whereKey($code)->exists();
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
