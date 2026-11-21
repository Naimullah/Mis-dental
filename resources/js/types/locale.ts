export type TextDirection = 'LTR' | 'RTL';

export type LocaleRow = {
    code: string;
    english_name: string;
    text_direction: TextDirection;
};

export type CatalogEntry = {
    code: string;
    name: string;
    direction: TextDirection;
};

export type LocaleSort = 'code' | 'english_name' | 'text_direction';

export type LocaleFilters = {
    q: string;
    direction: 'all' | TextDirection;
    sort: LocaleSort;
    dir: 'asc' | 'desc';
    per_page: number;
};
