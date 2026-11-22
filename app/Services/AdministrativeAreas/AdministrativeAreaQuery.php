<?php

namespace App\Services\AdministrativeAreas;

use App\Models\AdministrativeArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** List, drill-down and search for one country's areas; shared by the page and exports. */
class AdministrativeAreaQuery
{
    public const SORTS = ['canonical_name', 'area_code', 'level_code', 'is_active'];

    public const STATUSES = ['all', 'active', 'inactive'];

    public const PER_PAGE = [10, 25, 50, 100];

    /** @return array{country: string, parent: int|null, q: string, level: string, status: string, sort: string, dir: string, per_page: int} */
    public function filters(Request $request): array
    {
        $country = $request->query('country');
        $parent = filter_var($request->query('parent'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $q = $request->query('q');
        $level = $request->query('level');
        $status = $request->query('status');
        $sort = $request->query('sort');
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return [
            'country' => is_string($country) && preg_match('/\A[A-Z]{2}\z/', $country) === 1 ? $country : '',
            'parent' => $parent === false ? null : $parent,
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'level' => is_string($level) && preg_match('/\A[a-z][a-z0-9_-]{0,39}\z/', $level) === 1 ? $level : 'all',
            'status' => in_array($status, self::STATUSES, true) ? $status : 'all',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'canonical_name',
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * Without a search, lists the children of `parent` (top-level areas when null).
     * With a search, looks across the whole country (canonical name, code, translations).
     *
     * @param  array{country: string, parent: int|null, q: string, level: string, status: string, sort: string, dir: string}  $filters
     * @return Builder<AdministrativeArea>
     */
    public function build(array $filters): Builder
    {
        $query = AdministrativeArea::query()
            ->where('country_code', $filters['country'])
            ->withCount('children')
            ->with('translations');

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes(mb_strtolower($filters['q']), '\\%_').'%';

            $query->where(function (Builder $where) use ($term): void {
                $where->whereRaw("LOWER(canonical_name) LIKE ? ESCAPE '\\'", [$term])
                    ->orWhereRaw("LOWER(area_code) LIKE ? ESCAPE '\\'", [$term])
                    ->orWhereHas('translations', fn (Builder $translation) => $translation
                        ->whereRaw("LOWER(display_name) LIKE ? ESCAPE '\\'", [$term]));
            });
        } else {
            $query->where('parent_id', $filters['parent']);
        }

        if ($filters['level'] !== 'all') {
            $query->where('level_code', $filters['level']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return $query->orderBy($filters['sort'], $filters['dir'])->orderBy('id');
    }

    /**
     * Breadcrumb from the top-level area down to the given area (same country only).
     *
     * @return list<array{id: int, name: string}>
     */
    public function ancestors(string $country, ?int $areaId): array
    {
        $path = [];

        while ($areaId !== null && count($path) <= AdministrativeArea::MAX_DEPTH) {
            $area = AdministrativeArea::query()->with('translations')->where('country_code', $country)->find($areaId);
            if ($area === null) {
                return [];
            }
            array_unshift($path, ['id' => $area->id, 'name' => $area->displayName()]);
            $areaId = $area->parent_id;
        }

        return $path;
    }

    /** "Kabul › Paghman" style path for one area. */
    public function pathName(AdministrativeArea $area): string
    {
        return implode(' › ', array_column($this->ancestors($area->country_code, $area->id), 'name'));
    }
}
