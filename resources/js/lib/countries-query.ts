import type { CountryFilters, CountrySort } from '../types/country';

export const defaultCountryFilters: CountryFilters = {
    search: '',
    status: 'all',
    sort: 'iso2',
    direction: 'asc',
    per_page: 20,
};

export function countryQuery(
    filters: CountryFilters,
    changes: Partial<CountryFilters> = {},
    page = 1,
) {
    const { search, ...options } = { ...filters, ...changes };
    const term = search.trim();

    return {
        ...options,
        ...(term ? { search: term } : {}),
        page,
    };
}

export function nextCountrySort(
    filters: CountryFilters,
    column: CountrySort,
): Pick<CountryFilters, 'sort' | 'direction'> {
    return {
        sort: column,
        direction:
            filters.sort === column && filters.direction === 'asc'
                ? 'desc'
                : 'asc',
    };
}
