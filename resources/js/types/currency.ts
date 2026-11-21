export type Currency = {
    code: string;
    english_name: string;
    symbol: string | null;
    minor_units: number;
    is_active: boolean;
};

export type CurrencySort =
    | 'code'
    | 'english_name'
    | 'minor_units'
    | 'is_active';

export type CurrencyFilters = {
    q: string;
    status: 'all' | 'active' | 'inactive';
    sort: CurrencySort;
    dir: 'asc' | 'desc';
    per_page: number;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
