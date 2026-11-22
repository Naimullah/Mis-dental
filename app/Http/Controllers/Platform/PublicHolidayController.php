<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeArea;
use App\Models\Country;
use App\Models\PublicHoliday;
use App\Services\PublicHolidays\PublicHolidayExporter;
use App\Services\PublicHolidays\PublicHolidayQuery;
use App\Services\PublicHolidays\PublicHolidayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicHolidayController extends Controller
{
    public function __construct(
        private readonly PublicHolidayService $holidays,
        private readonly PublicHolidayQuery $query,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->query->filters($request);
        $country = $filters['country'];
        $hasCountry = $country !== '' && Country::query()->whereKey($country)->exists();

        return Inertia::render('platform/master-data/public-holidays/index', [
            'filters' => $filters,
            'countries' => Country::query()->orderBy('english_name')->get(['iso2', 'english_name']),
            'areas' => $hasCountry ? AdministrativeArea::query()->where('country_code', $country)
                ->orderBy('canonical_name')->orderBy('id')->get(['id', 'canonical_name', 'level_code']) : [],
            'holidays' => $hasCountry ? $this->query->build($filters)
                ->paginate($filters['per_page'], ['id', 'administrative_area_id', 'holiday_date', 'name', 'status'])
                ->withQueryString() : null,
            'exportsAvailable' => PublicHolidayExporter::available(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->holidays->save(new PublicHoliday, $request->all());

        return $this->done(__('Public holiday added.'));
    }

    public function update(Request $request, PublicHoliday $holiday): RedirectResponse
    {
        $this->holidays->save($holiday, $request->all());

        return $this->done(__('Public holiday updated.'));
    }

    public function updateStatus(Request $request, PublicHoliday $holiday): RedirectResponse
    {
        $this->holidays->updateStatus($holiday, $request->input('status'));

        return $this->done(__('Public holiday updated.'));
    }

    public function exportXlsx(Request $request, PublicHolidayExporter $exporter): HttpResponse
    {
        return $exporter->xlsx($this->exportQuery($request));
    }

    public function exportPdf(Request $request, PublicHolidayExporter $exporter): HttpResponse
    {
        return $exporter->pdf($this->exportQuery($request));
    }

    /** @return Builder<PublicHoliday> */
    private function exportQuery(Request $request): Builder
    {
        $filters = $this->query->filters($request);
        abort_unless($filters['country'] !== '' && Country::query()->whereKey($filters['country'])->exists(), 422, __('Choose a country first.'));

        return $this->query->build($filters);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
