<?php

namespace App\Services\Locales;

use App\Models\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Shared list filters for the Languages page and its exports. */
class LocaleQuery
{
    public const SORTS = ['code', 'english_name', 'native_name', 'text_direction', 'is_active'];

    public const DIRECTIONS = ['all', 'LTR', 'RTL'];

    public const PER_PAGE = [10, 25, 50, 100];

    /** @return array{q: string, direction: string, sort: string, dir: string, per_page: int} */
    public function filters(Request $request): array
    {
        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';
        $direction = $request->query('direction');
        $sort = $request->query('sort');
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return [
            'q' => $q,
            'direction' => in_array($direction, self::DIRECTIONS, true) ? $direction : 'all',
            'sort' => $this->sortColumn($sort),
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{q: string, direction: string, sort: string, dir: string}  $filters
     * @return Builder<Locale>
     */
    public function build(array $filters): Builder
    {
        $query = Locale::query()->select(['code', 'english_name', 'text_direction']);

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes(mb_strtolower($filters['q']), '\\%_').'%';

            $query->where(function (Builder $where) use ($term): void {
                foreach (['code', 'english_name'] as $column) {
                    $where->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '\\'", [$term]);
                }
            });
        }

        if ($filters['direction'] !== 'all') {
            $query->where('text_direction', $filters['direction']);
        }

        $sort = $this->sortColumn($filters['sort']);
        $query->orderBy($sort, $filters['dir'] === 'desc' ? 'desc' : 'asc');

        if ($sort !== 'code') {
            $query->orderBy('code');
        }

        return $query;
    }

    private function sortColumn(mixed $column): string
    {
        return in_array($column, self::SORTS, true) ? $column : 'code';
    }
}
