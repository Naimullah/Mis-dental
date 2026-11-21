import assert from 'node:assert/strict';
import test from 'node:test';
import {
    countryQuery,
    defaultCountryFilters,
    nextCountrySort,
} from '../../resources/js/lib/countries-query.ts';

test('uses the server defaults and omits an empty search', () => {
    assert.deepEqual(countryQuery(defaultCountryFilters), {
        status: 'all',
        sort: 'iso2',
        direction: 'asc',
        per_page: 20,
        page: 1,
    });
    assert.equal(
        'search' in countryQuery(defaultCountryFilters, { search: '  ' }),
        false,
    );
});

test('trims search and preserves punctuation and Unicode for the server', () => {
    const query = countryQuery(defaultCountryFilters, {
        search: '  افغانستان %_!  ',
    });

    assert.equal(query.search, 'افغانستان %_!');
});

test('keeps the applied filters when moving through pages', () => {
    const filters = {
        search: 'afg',
        status: 'active',
        sort: 'iso3',
        direction: 'desc',
        per_page: 50,
    };

    assert.deepEqual(countryQuery(filters, {}, 3), { ...filters, page: 3 });
    assert.deepEqual(countryQuery(filters, { status: 'inactive' }), {
        ...filters,
        status: 'inactive',
        page: 1,
    });
});

test('toggles the current sort direction and starts a different column ascending', () => {
    assert.deepEqual(nextCountrySort(defaultCountryFilters, 'iso2'), {
        sort: 'iso2',
        direction: 'desc',
    });
    const descending = { ...defaultCountryFilters, direction: 'desc' };
    assert.deepEqual(nextCountrySort(descending, 'iso2'), {
        sort: 'iso2',
        direction: 'asc',
    });
    assert.deepEqual(nextCountrySort(descending, 'english_name'), {
        sort: 'english_name',
        direction: 'asc',
    });
});
