<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Services\Currencies\CurrencyExporter;
use App\Services\Currencies\CurrencyQuery;
use App\Services\Currencies\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly CurrencyQuery $query,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->query->filters($request);

        $currencies = $this->query->build($filters)
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Currency $currency): array => [
                'code' => $currency->code,
                'english_name' => $currency->english_name,
                'symbol' => $currency->symbol,
                'minor_units' => $currency->minor_units,
                'is_active' => $currency->is_active,
            ]);

        return Inertia::render('platform/master-data/currencies/index', [
            'currencies' => $currencies,
            'filters' => $filters,
            'exportsAvailable' => CurrencyExporter::available(),
        ]);
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $currency = $this->currencies->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Currency :code created.', ['code' => $currency->code])]);

        return back();
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $this->currencies->update($currency->code, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Currency :code updated.', ['code' => $currency->code])]);

        return back();
    }

    public function updateStatus(Request $request, Currency $currency): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $active = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        $active ? $this->currencies->activate($currency->code) : $this->currencies->deactivate($currency->code);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active
                ? __('Currency :code activated.', ['code' => $currency->code])
                : __('Currency :code deactivated.', ['code' => $currency->code]),
        ]);

        return back();
    }

    public function exportXlsx(Request $request, CurrencyExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->query->build($this->query->filters($request)));
    }

    public function exportPdf(Request $request, CurrencyExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->query->build($this->query->filters($request)));
    }
}
