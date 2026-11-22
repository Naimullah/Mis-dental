export type TaxCodeStatus = 'current' | 'future' | 'ended';

export type TaxCodeRow = {
    id: number;
    country_code: string;
    code: string;
    description: string;
    /** Decimal string with 5 places, e.g. "10.00000". Never parsed as a float. */
    rate_percent: string;
    effective_from: string;
    effective_to: string | null;
    status: TaxCodeStatus;
};

export type TaxCodeFilters = {
    country: string;
    q: string;
    on: string;
    sort:
        | 'code'
        | 'description'
        | 'rate_percent'
        | 'effective_from'
        | 'effective_to';
    dir: 'asc' | 'desc';
    per_page: number;
};
