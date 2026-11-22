export type DocumentTypeRow = {
    id: number;
    country_code: string;
    code: string;
    name: string;
    optional_format_regex: string | null;
    is_active: boolean;
};

export type DocumentTypeFilters = {
    country: string;
    status: 'all' | 'active' | 'inactive';
    q: string;
    sort: 'code' | 'name' | 'is_active';
    dir: 'asc' | 'desc';
    per_page: number;
};
