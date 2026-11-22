<?php

namespace App\Services\Roles;

use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Roles list for platform administrators: platform roles and clinic system templates. */
class RoleQuery
{
    public const SORTS = ['name', 'code', 'scope'];

    public const PER_PAGE = [10, 25, 50, 100];

    /** @return array{q: string, scope: string, kind: string, sort: string, dir: string, per_page: int} */
    public function filters(Request $request): array
    {
        $q = $request->query('q');
        $scope = $request->query('scope');
        $kind = $request->query('kind');
        $sort = $request->query('sort');
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return [
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'scope' => in_array($scope, Role::SCOPES, true) ? $scope : 'all',
            'kind' => in_array($kind, ['system', 'custom'], true) ? $kind : 'all',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'name',
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{q: string, scope: string, kind: string, sort: string, dir: string}  $filters
     * @return Builder<Role>
     */
    public function build(array $filters): Builder
    {
        // Clinic custom roles belong to their clinic and are not listed on the platform page.
        $query = Role::query()->whereNull('clinic_id');

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes(mb_strtolower($filters['q']), '\\%_').'%';
            $query->where(fn (Builder $where) => $where
                ->whereRaw("LOWER(code) LIKE ? ESCAPE '\\'", [$term])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$term]));
        }

        if ($filters['scope'] !== 'all') {
            $query->where('scope', $filters['scope']);
        }

        if ($filters['kind'] !== 'all') {
            $query->where('is_system', $filters['kind'] === 'system');
        }

        return $query->orderBy($filters['sort'], $filters['dir'])->orderBy('code')->orderBy('id');
    }
}
