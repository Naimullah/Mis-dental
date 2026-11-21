export type Country = {
    iso2: string;
    iso3: string | null;
    english_name: string;
    is_active: boolean;
};

export type CountrySort = keyof Country;

export type CountryFilters = {
    search: string;
    status: 'all' | 'active' | 'inactive';
    sort: CountrySort;
    direction: 'asc' | 'desc';
    per_page: number;
};

export type CountryPage = {
    data: Country[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
