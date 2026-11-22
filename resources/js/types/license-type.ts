export type AppliesTo = 'practitioner' | 'facility' | 'legal_entity';

export type LicenseTypeRow = {
    id: number;
    country_code: string;
    code: string;
    name: string;
    applies_to: AppliesTo;
    is_active: boolean;
};

export type LicenseTypeFilters = {
    country: string;
    applies_to: 'all' | AppliesTo;
    status: 'all' | 'active' | 'inactive';
    q: string;
    sort: 'code' | 'name' | 'applies_to' | 'is_active';
    dir: 'asc' | 'desc';
    per_page: number;
};

export type AppliesToOption = { value: AppliesTo; label: string };
