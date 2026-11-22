<?php

namespace App\Services\IdentityDocumentTypes;

use App\Models\IdentityDocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** One country's identity document types; shared by the page and its exports. */
class IdentityDocumentTypeQuery
{
    public const SORTS = ['code', 'name', 'is_active'];

    public const STATUSES = ['all', 'active', 'inactive'];

    public const PER_PAGE = [10, 25, 50, 100];

    /** @return array{country: string, status: string, q: string, sort: string, dir: string, per_page: int} */
    public function filters(Request $request): array
    {
        $country = $request->query('country');
        $status = $request->query('status');
        $q = $request->query('q');
        $sort = $request->query('sort');
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return [
            'country' => is_string($country) && preg_match('/\A[A-Z]{2}\z/', $country) === 1 ? $country : '',
            'status' => in_array($status, self::STATUSES, true) ? $status : 'all',
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'code',
            'dir' => $request->query('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{country: string, status: string, q: string, sort: string, dir: string}  $filters
     * @return Builder<IdentityDocumentType>
     */
    public function build(array $filters): Builder
    {
        $query = IdentityDocumentType::query()->where('country_code', $filters['country']);

        if ($filters['q'] !== '') {
            $term = '%'.addcslashes(mb_strtolower($filters['q']), '\\%_').'%';
            $query->where(fn (Builder $where) => $where
                ->whereRaw("LOWER(code) LIKE ? ESCAPE '\\'", [$term])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$term]));
        }

        if ($filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return $query->orderBy($filters['sort'], $filters['dir'])->orderBy('id');
    }
}
