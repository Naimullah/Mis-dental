<?php

namespace App\Services\TaxCodes;

use App\Models\TaxCode;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** One country's tax code versions; shared by the page and its exports. */
class TaxCodeQuery
{
    public const SORTS = ['code', 'description', 'rate_percent', 'effective_from', 'effective_to'];

    public const PER_PAGE = [10, 25, 50, 100];

    /** @return array{country: string, q: string, on: string, sort: string, dir: string, per_page: int} */
    public function filters(Request $request): array
    {
        $country = $request->query('country');
        $q = $request->query('q');
        $on = $request->query('on');
        $sort = $request->query('sort');
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return [
            'country' => is_string($country) && preg_match('/\A[A-Z]{2}\z/', $country) === 1 ? $country : '',
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'on' => is_string($on) && $this->isDate($on) ? $on : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'code',
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{country: string, q: string, on: string, sort: string, dir: string}  $filters
     * @return Builder<TaxCode>
     */
    public function build(array $filters): Builder
    {
        $query = TaxCode::query()->where('country_code', $filters['country']);

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes(mb_strtolower($filters['q']), '\\%_').'%';
            $query->where(fn (Builder $where) => $where
                ->whereRaw("LOWER(code) LIKE ? ESCAPE '\\'", [$term])
                ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '\\'", [$term]));
        }

        if ($filters['on'] !== '') {
            $query->effectiveOn($filters['on']);
        }

        $query->orderBy($filters['sort'], $filters['dir']);

        // Within one code, newest version first; then a stable tie-break.
        if ($filters['sort'] === 'code') {
            $query->orderByDesc('effective_from');
        }

        return $query->orderBy('id');
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
