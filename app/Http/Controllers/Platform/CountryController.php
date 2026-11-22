<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCountryRequest;
use App\Http\Requests\UpdateCountryRequest;
use App\Models\Country;
use App\Services\CatalogTranslations\CatalogTranslations;
use App\Services\Countries\CountryExporter;
use App\Services\Countries\CountryQueryService;
use App\Services\Countries\CountryService;
use App\Services\CountryLocales\CountryLocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CountryController extends Controller
{
    private const FILTERS = ['search', 'status', 'sort', 'direction', 'per_page'];

    public function __construct(
        private readonly CountryService $countries,
        private readonly CountryQueryService $query,
    ) {}

    public function index(Request $request, CatalogTranslations $translations): Response
    {
        $countries = $this->query->paginate($request->query())
            ->through(fn (Country $country): array => [
                'iso2' => $country->iso2,
                'iso3' => $country->iso3,
                'english_name' => $country->english_name,
                'is_active' => $country->is_active,
            ]);

        $filters = $request->only(self::FILTERS);
        if (is_string($filters['search'] ?? null)) {
            $filters['search'] = Str::trim($filters['search']);
        }
        if (isset($filters['per_page'])) {
            $filters['per_page'] = (int) $filters['per_page'];
        }

        return Inertia::render('platform/master-data/countries/index', [
            'countries' => $countries,
            'catalogTranslations' => $translations->forPage(new Country, $countries->getCollection()->pluck('english_name', 'iso2')->all()),
            'filters' => $filters,
            'canMutate' => false,
            'exportsAvailable' => ['xlsx' => false, 'pdf' => false],
            'canManageCountryLocales' => $request->user()?->can('manage-master-data') ?? false,
            // P01.04: languages panel for ?languages=ISO2.
            'countryLocales' => $request->filled('languages')
                ? app(CountryLocaleService::class)->panel($request->query('languages'))
                : null,
        ]);
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        $country = $this->countries->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Country :code created.', ['code' => $country->iso2])]);

        return back();
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $this->countries->update($country->iso2, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Country :code updated.', ['code' => $country->iso2])]);

        return back();
    }

    public function updateStatus(Request $request, Country $country): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $active = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        $active ? $this->countries->activate($country->iso2) : $this->countries->deactivate($country->iso2);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active
                ? __('Country :code activated.', ['code' => $country->iso2])
                : __('Country :code deactivated.', ['code' => $country->iso2]),
        ]);

        return back();
    }

    public function exportXlsx(Request $request, CountryExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->query->query($this->exportInput($request)));
    }

    public function exportPdf(Request $request, CountryExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->query->query($this->exportInput($request)));
    }

    /** @return array<string, mixed> */
    private function exportInput(Request $request): array
    {
        return $request->only(['search', 'status', 'sort', 'direction']);
    }
}
