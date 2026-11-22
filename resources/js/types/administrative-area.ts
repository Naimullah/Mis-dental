export type AreaRow = {
    id: number;
    country_code: string;
    parent_id: number | null;
    level_code: string;
    area_code: string | null;
    canonical_name: string;
    display_name: string;
    is_active: boolean;
    children_count: number;
    translations: Record<string, string>;
    parent_path: string;
};

export type AreaFilters = {
    country: string;
    parent: number | null;
    q: string;
    level: string;
    status: 'all' | 'active' | 'inactive';
    sort: 'canonical_name' | 'area_code' | 'level_code' | 'is_active';
    dir: 'asc' | 'desc';
    per_page: number;
};

export type CountryOption = {
    iso2: string;
    english_name: string;
    is_active: boolean;
};

export type TranslationLocale = {
    code: string;
    english_name: string;
    native_name: string | null;
    text_direction: 'LTR' | 'RTL';
};

export type ParentOption = {
    id: number;
    path: string;
    level_code: string;
    is_active: boolean;
};
