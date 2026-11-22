export type RoleScope = 'platform' | 'clinic';

export type RoleRow = {
    id: number;
    code: string;
    name: string;
    scope: RoleScope;
    is_system: boolean;
};

export type RoleFilters = {
    q: string;
    scope: 'all' | RoleScope;
    kind: 'all' | 'system' | 'custom';
    sort: 'name' | 'code' | 'scope';
    dir: 'asc' | 'desc';
    per_page: number;
};
