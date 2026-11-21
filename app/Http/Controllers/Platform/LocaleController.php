<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocaleRequest;
use App\Http\Requests\UpdateLocaleRequest;
use App\Models\Locale;
use App\Services\Locales\LocaleExporter;
use App\Services\Locales\LocaleQuery;
use App\Services\Locales\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** P01.03 Languages (locales) master data. Not the UI language switcher. */
class LocaleController extends Controller
{
    public function __construct(
        private readonly LocaleService $locales,
        private readonly LocaleQuery $query,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->query->filters($request);

        $locales = $this->query->build($filters)
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Locale $locale): array => [
                'code' => $locale->code,
                'english_name' => $locale->english_name,
                'text_direction' => $locale->text_direction,
            ]);

        $catalog = collect(config('locale_catalog', []))
            ->map(fn (array $entry, string $code): array => [
                'code' => $code,
                'name' => $entry['name'],
                'direction' => $entry['direction'],
            ])
            ->values();

        return Inertia::render('platform/master-data/locales/index', [
            'locales' => $locales,
            'filters' => $filters,
            'catalog' => $catalog,
            'exportsAvailable' => LocaleExporter::available(),
        ]);
    }

    public function store(StoreLocaleRequest $request): RedirectResponse
    {
        $locale = $this->locales->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Language :code created.', ['code' => $locale->code])]);

        return back();
    }

    public function update(UpdateLocaleRequest $request, Locale $locale): RedirectResponse
    {
        $this->locales->update($locale->code, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Language :code updated.', ['code' => $locale->code])]);

        return back();
    }

    public function exportXlsx(Request $request, LocaleExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->query->build($this->query->filters($request)));
    }

    public function exportPdf(Request $request, LocaleExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->query->build($this->query->filters($request)));
    }
}
