<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxCodeRequest;
use App\Models\Country;
use App\Models\TaxCode;
use App\Services\CatalogTranslations\TypeTranslations;
use App\Services\TaxCodes\TaxCodeExporter;
use App\Services\TaxCodes\TaxCodeQuery;
use App\Services\TaxCodes\TaxCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** P01.10 tax codes (versioned, never deleted). */
class TaxCodeController extends Controller
{
    public function __construct(
        private readonly TaxCodeService $taxCodes,
        private readonly TaxCodeQuery $query,
    ) {}

    public function index(Request $request, TypeTranslations $translations): Response
    {
        $filters = $this->query->filters($request);
        $hasCountry = $this->countryExists($filters['country']);
        $today = now()->toDateString();

        $taxCodes = $hasCountry
            ? $this->query->build($filters)->paginate($filters['per_page'])->withQueryString()
                ->through(fn (TaxCode $taxCode): array => [
                    'id' => $taxCode->id,
                    'country_code' => $taxCode->country_code,
                    'code' => $taxCode->code,
                    'description' => $taxCode->description,
                    'rate_percent' => $taxCode->rate_percent,
                    'effective_from' => $taxCode->effective_from,
                    'effective_to' => $taxCode->effective_to,
                    'status' => $taxCode->periodStatus($today),
                ])
            : null;

        return Inertia::render('platform/master-data/tax-codes/index', [
            'taxCodes' => $taxCodes,
            'translationData' => $taxCodes === null ? null : $translations->forPage(
                new TaxCode,
                array_values($taxCodes->getCollection()->pluck('id')->all()),
                app()->getLocale(),
            ),
            'filters' => $filters,
            'today' => $today,
            'countries' => Country::query()->orderBy('english_name')->get(['iso2', 'english_name', 'is_active'])
                ->map(fn (Country $country): array => ['iso2' => $country->iso2, 'english_name' => $country->english_name, 'is_active' => $country->is_active]),
            'exportsAvailable' => TaxCodeExporter::available(),
        ]);
    }

    public function store(TaxCodeRequest $request): RedirectResponse
    {
        $taxCode = $this->taxCodes->create($request->validated());

        return $this->done(__('Tax code :code created.', ['code' => $taxCode->code]));
    }

    public function update(TaxCodeRequest $request, TaxCode $taxCode): RedirectResponse
    {
        $this->taxCodes->updateDescription($taxCode->id, $request->validated());

        return $this->done(__('Tax code :code updated.', ['code' => $taxCode->code]));
    }

    public function close(Request $request, TaxCode $taxCode): RedirectResponse
    {
        $this->taxCodes->close($taxCode->id, $request->only(['effective_to']));

        return $this->done(__('Tax code :code closed.', ['code' => $taxCode->code]));
    }

    public function storeVersion(Request $request, TaxCode $taxCode): RedirectResponse
    {
        $this->taxCodes->addVersion($taxCode->id, $request->only(['description', 'rate_percent', 'effective_from', 'effective_to']));

        return $this->done(__('New version of :code added.', ['code' => $taxCode->code]));
    }

    public function updateTranslations(Request $request, TaxCode $taxCode, TypeTranslations $translations): RedirectResponse
    {
        $translations->update($taxCode, $request->input('translations'));

        return $this->done(__('Translations for :name saved.', ['name' => $taxCode->code]));
    }

    public function exportXlsx(Request $request, TaxCodeExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->exportQuery($request));
    }

    public function exportPdf(Request $request, TaxCodeExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->exportQuery($request));
    }

    /** @return Builder<TaxCode> */
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
