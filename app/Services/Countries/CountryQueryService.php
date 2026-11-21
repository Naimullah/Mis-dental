<?php

namespace App\Services\Countries;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CountryQueryService
{
    private const SORT_COLUMNS = ['iso2', 'iso3', 'english_name', 'is_active'];

    /**
     * @param  array<string, mixed>  $input
     * @return LengthAwarePaginator<int, Country>
     */
    public function paginate(array $input = []): LengthAwarePaginator
    {
        [$query, $options] = $this->build($input);

        return $query->paginate(
            perPage: (int) ($options['per_page'] ?? 20),
            page: (int) ($options['page'] ?? 1),
        )->appends($options);
    }

    /**
     * The same filtered, sorted rows as paginate(), without pagination (used by exports).
     *
     * @param  array<string, mixed>  $input
     * @return Builder<Country>
     */
    public function query(array $input = []): Builder
    {
        return $this->build($input)[0];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: Builder<Country>, 1: array<string, mixed>}
     */
    private function build(array $input): array
    {
        if (is_string($input['search'] ?? null)) {
            $input['search'] = Str::trim($input['search']);
        }

        $options = Validator::make($input, [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'bail', 'required', 'string', Rule::in(['all', 'active', 'inactive'])],
            'sort' => ['sometimes', 'bail', 'required', 'string', Rule::in(self::SORT_COLUMNS)],
            'direction' => ['sometimes', 'bail', 'required', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'required', 'integer', 'between:1,2147483647'],
            'per_page' => ['sometimes', 'required', 'integer', 'between:1,100'],
        ])->validate();

        $query = Country::query();
        $search = $options['search'] ?? '';

        if ($search !== '') {
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

            $query->whereRaw(
                "(LOWER(iso2) LIKE LOWER(?) ESCAPE '!' OR LOWER(iso3) LIKE LOWER(?) ESCAPE '!' OR LOWER(english_name) LIKE LOWER(?) ESCAPE '!')",
                [$pattern, $pattern, $pattern],
            );
        }

        $status = $options['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        $sort = $options['sort'] ?? 'iso2';
        $query->orderBy($sort, $options['direction'] ?? 'asc');

        if ($sort !== 'iso2') {
            $query->orderBy('iso2');
        }

        return [$query, $options];
    }
}
