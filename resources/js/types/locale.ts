export type TextDirection = 'LTR' | 'RTL';

export type LocaleRow = {
    code: string;
    english_name: string;
    text_direction: TextDirection;
    is_active: boolean;
    native_name: string | null;
    default_calendar_code: string | null;
    default_numbering_system_code: string | null;
};

export type CatalogEntry = {
    code: string;
    name: string;
    direction: TextDirection;
};

export type LocaleSort =
    | 'code'
    | 'english_name'
    | 'native_name'
    | 'text_direction'
    | 'is_active';

export type LocaleFilters = {
    q: string;
    direction: 'all' | TextDirection;
    status: 'all' | 'active' | 'inactive';
    sort: LocaleSort;
    dir: 'asc' | 'desc';
    per_page: number;
};
