<?php

namespace App\Services\PublicHolidays;

use App\Models\PublicHoliday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PublicHolidayQuery
{
    /** @return array{country: string, status: string, q: string, sort: string, dir: 'asc'|'desc', per_page: int} */
    public function filters(Request $request): array
    {
        $country = $request->query('country');
        $status = $request->query('status');
        $sort = $request->query('sort');
        $search = $request->query('q');
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return [
            'country' => is_string($country) && preg_match('/\A[A-Z]{2}\z/', $country) ? $country : '',
            'status' => in_array($status, PublicHoliday::STATUSES, true) ? $status : 'all',
            'q' => is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            'sort' => in_array($sort, ['holiday_date', 'name', 'status'], true) ? $sort : 'holiday_date',
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{country: string, status: string, q: string, sort: string, dir: 'asc'|'desc', per_page: int}  $filters
     * @return Builder<PublicHoliday>
     */
    public function build(array $filters): Builder
    {
        $query = PublicHoliday::query()->where('country_code', $filters['country'])
            ->with('administrativeArea:id,canonical_name');

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes(mb_strtolower($filters['q']), '!%_').'%';
            $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy($filters['sort'], $filters['dir'])->orderBy('id');
    }
}
