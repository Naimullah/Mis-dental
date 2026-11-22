<?php

namespace App\Services\Currencies;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared list filters for the Currencies page and its exports, so both always
 * return the same rows in the same order.
 */
class CurrencyQuery
{
    public const SORTS = ['code', 'english_name', 'minor_units', 'is_active'];

    public const STATUSES = ['all', 'active', 'inactive'];

    public const PER_PAGE = [10, 25, 50, 100];

    /**
     * Invalid or unknown values fall back to defaults instead of failing the request.
     *
     * @return array{q: string, status: string, sort: string, dir: 'asc'|'desc', per_page: int}
     */
    public function filters(Request $request): array
    {
        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $status = $request->query('status');
        $sort = $request->query('sort');
        $dir = $request->query('dir');
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return [
            'q' => $q,
            'status' => in_array($status, self::STATUSES, true) ? $status : 'all',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'code',
            'dir' => $dir === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{q: string, status: string, sort: string, dir: 'asc'|'desc'}  $filters
     * @return Builder<Currency>
     */
    public function build(array $filters): Builder
    {
        $query = Currency::query()->select(['code', 'english_name', 'symbol', 'minor_units', 'is_active']);

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes(mb_strtolower($filters['q']), '\\%_').'%';

            $query->where(function (Builder $where) use ($term): void {
                foreach (['code', 'english_name', 'symbol'] as $column) {
                    $where->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '\\'", [$term]);
                }
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        $query->orderBy($filters['sort'], $filters['dir']);

        if ($filters['sort'] !== 'code') {
            $query->orderBy('code'); // stable tie-breaker
        }

        return $query;
    }
}
