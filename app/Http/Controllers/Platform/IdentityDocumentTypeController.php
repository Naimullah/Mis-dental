<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\IdentityDocumentTypeRequest;
use App\Models\Country;
use App\Models\IdentityDocumentType;
use App\Services\CatalogTranslations\TypeTranslations;
use App\Services\IdentityDocumentTypes\IdentityDocumentTypeExporter;
use App\Services\IdentityDocumentTypes\IdentityDocumentTypeQuery;
use App\Services\IdentityDocumentTypes\IdentityDocumentTypeService;
use App\Support\SafeDocumentPattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** P01.08 identity document types. */
class IdentityDocumentTypeController extends Controller
{
    public function __construct(
        private readonly IdentityDocumentTypeService $types,
        private readonly IdentityDocumentTypeQuery $query,
    ) {}

    public function index(Request $request, TypeTranslations $translations): Response
    {
        $filters = $this->query->filters($request);
        $hasCountry = $this->countryExists($filters['country']);

        $types = $hasCountry
                ? $this->query->build($filters)->paginate($filters['per_page'])->withQueryString()
                    ->through(fn (IdentityDocumentType $type): array => [
                        'id' => $type->id,
                        'country_code' => $type->country_code,
                        'code' => $type->code,
                        'name' => $type->name,
                        'optional_format_regex' => $type->optional_format_regex,
                        'is_active' => $type->is_active,
                    ])
                : null;

        return Inertia::render('platform/master-data/identity-document-types/index', [
            'documentTypes' => $types,
            'translationData' => $types === null ? null : $translations->forPage(
                new IdentityDocumentType,
                array_values($types->getCollection()->pluck('id')->all()),
                app()->getLocale(),
            ),
            'filters' => $filters,
            'countries' => Country::query()->orderBy('english_name')->get(['iso2', 'english_name', 'is_active'])
                ->map(fn (Country $country): array => ['iso2' => $country->iso2, 'english_name' => $country->english_name, 'is_active' => $country->is_active]),
            'patternMaxLength' => SafeDocumentPattern::MAX_LENGTH,
            'exportsAvailable' => IdentityDocumentTypeExporter::available(),
        ]);
    }

    public function store(IdentityDocumentTypeRequest $request): RedirectResponse
    {
        $type = $this->types->create($request->validated());

        return $this->done(__('Document type :code created.', ['code' => $type->code]));
    }

    public function update(IdentityDocumentTypeRequest $request, IdentityDocumentType $documentType): RedirectResponse
    {
        $this->types->update($documentType->id, $request->validated());

        return $this->done(__('Document type :code updated.', ['code' => $documentType->code]));
    }

    public function updateStatus(Request $request, IdentityDocumentType $documentType): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);

        $active ? $this->types->activate($documentType->id) : $this->types->deactivate($documentType->id);

        return $this->done($active
            ? __('Document type :code activated.', ['code' => $documentType->code])
            : __('Document type :code deactivated.', ['code' => $documentType->code]));
    }

    public function updateTranslations(Request $request, IdentityDocumentType $documentType, TypeTranslations $translations): RedirectResponse
    {
        $translations->update($documentType, $request->input('translations'));

        return $this->done(__('Translations for :name saved.', ['name' => $documentType->name]));
    }

    public function exportXlsx(Request $request, IdentityDocumentTypeExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->exportQuery($request));
    }

    public function exportPdf(Request $request, IdentityDocumentTypeExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->exportQuery($request));
    }

    /** @return Builder<IdentityDocumentType> */
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
