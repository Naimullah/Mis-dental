import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    FileSpreadsheet,
    FileText,
    Pencil,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    COUNTRIES_URL,
    CountryFormDialog,
} from '@/components/countries/country-form-dialog';
import { CountryLanguagesDialog } from '@/components/countries/country-languages-dialog';
import type { CountryLocalesPanel } from '@/components/countries/country-languages-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    countryQuery,
    defaultCountryFilters,
    nextCountrySort,
} from '@/lib/countries-query';
import { useTrans } from '@/lib/i18n';
import type {
    Country,
    CountryFilters,
    CountryPage,
    CountrySort,
} from '@/types/country';

const columns = [
    ['iso2', 'ISO2'],
    ['iso3', 'ISO3'],
    ['english_name', 'English name'],
    ['is_active', 'Status'],
] as const satisfies ReadonlyArray<readonly [CountrySort, string]>;

type Props = {
    countries: CountryPage;
    filters?: Partial<CountryFilters>;
    canMutate?: boolean;
    exportsAvailable?: { xlsx: boolean; pdf: boolean };
    countryLocales?: CountryLocalesPanel | null;
};

export default function CountriesIndex({
    countries,
    filters,
    canMutate = false,
    exportsAvailable,
    countryLocales = null,
}: Props) {
    const { url } = usePage();
    const { t, direction, locale } = useTrans();
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const applied = {
        ...defaultCountryFilters,
        per_page: countries.per_page,
        ...filters,
    };
    const numbers = new Intl.NumberFormat(locale);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Country | null>(null);
    const [statusTarget, setStatusTarget] = useState<Country | null>(null);
    const [statusProcessing, setStatusProcessing] = useState(false);

    const exportParams = new URLSearchParams();
    for (const [key, value] of Object.entries(countryQuery(applied))) {
        if (key !== 'page' && key !== 'per_page') {
            exportParams.set(key, String(value));
        }
    }
    const exportQuery = exportParams.toString();

    function confirmStatus() {
        if (!canMutate || !statusTarget) {
            return;
        }

        router.patch(
            `${COUNTRIES_URL}/${statusTarget.iso2}/status`,
            { is_active: !statusTarget.is_active },
            {
                preserveScroll: true,
                onStart: () => setStatusProcessing(true),
                onFinish: () => {
                    setStatusProcessing(false);
                    setStatusTarget(null);
                },
            },
        );
    }

    function visit(changes: Partial<CountryFilters> = {}, page = 1) {
        const fail = () => {
            setFailed(true);

            return false;
        };

        router.get(
            url.split(/[?#]/, 1)[0],
            countryQuery(applied, changes, page),
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    setFailed(false);
                    setLoading(true);
                },
                onFinish: () => setLoading(false),
                onError: fail,
                onHttpException: fail,
                onNetworkError: fail,
            },
        );
    }

    function showLanguages(iso2: string | null) {
        const query: Record<string, string | number> = countryQuery(
            applied,
            {},
            countries.current_page,
        );

        if (iso2 !== null) {
            query.languages = iso2;
        }

        router.get(url.split(/[?#]/, 1)[0], query, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function search(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const term = new FormData(event.currentTarget).get('search');

        if (typeof term === 'string') {
            visit({ search: term });
        }
    }

    return (
        <section
            className="min-w-0 space-y-4 p-4"
            dir={direction}
            aria-label={t('Countries')}
        >
            <Head title={t('Countries')} />
            <div className="flex flex-wrap items-start justify-between gap-4">
                <Heading title={t('Countries')} />
                <div className="flex flex-wrap gap-2">
                    {exportsAvailable?.xlsx && (
                        <Button variant="outline" asChild>
                            <a
                                href={`${COUNTRIES_URL}/export/xlsx?${exportQuery}`}
                            >
                                <FileSpreadsheet aria-hidden="true" />
                                {t('Export Excel')}
                            </a>
                        </Button>
                    )}
                    {exportsAvailable?.pdf && (
                        <Button variant="outline" asChild>
                            <a
                                href={`${COUNTRIES_URL}/export/pdf?${exportQuery}`}
                            >
                                <FileText aria-hidden="true" />
                                {t('Export PDF')}
                            </a>
                        </Button>
                    )}
                    {canMutate && (
                        <Button type="button" onClick={() => setCreating(true)}>
                            <Plus aria-hidden="true" />
                            {t('Add country')}
                        </Button>
                    )}
                </div>
            </div>

            <div className="flex flex-wrap items-end gap-3">
                <form
                    className="min-w-0 flex-1 space-y-2 sm:max-w-md"
                    onSubmit={search}
                >
                    <Label htmlFor="country-search">
                        {t('Search countries')}
                    </Label>
                    <div className="flex gap-2">
                        <Input
                            key={applied.search}
                            id="country-search"
                            name="search"
                            type="search"
                            dir="auto"
                            defaultValue={applied.search}
                            placeholder={t('Search by ISO code or name')}
                            maxLength={120}
                            disabled={loading}
                        />
                        <Button type="submit" disabled={loading}>
                            {t('Search')}
                        </Button>
                    </div>
                </form>
                <div className="w-full space-y-2 sm:w-40">
                    <Label htmlFor="country-status">{t('Status')}</Label>
                    <Select
                        value={applied.status}
                        onValueChange={(value) => {
                            if (
                                value === 'all' ||
                                value === 'active' ||
                                value === 'inactive'
                            ) {
                                visit({ status: value });
                            }
                        }}
                        disabled={loading}
                        dir={direction}
                    >
                        <SelectTrigger id="country-status" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('All')}</SelectItem>
                            <SelectItem value="active">
                                {t('Active')}
                            </SelectItem>
                            <SelectItem value="inactive">
                                {t('Inactive')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {failed && (
                <p
                    role="alert"
                    className="rounded-lg border border-destructive p-3 text-sm text-destructive"
                >
                    {t('Countries could not be loaded. Please try again.')}
                </p>
            )}
            {loading && (
                <p
                    role="status"
                    className="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Spinner aria-hidden="true" />
                    {t('Loading countries…')}
                </p>
            )}

            <div
                className="max-w-full overflow-x-auto rounded-lg border"
                role="region"
                aria-label={t('Countries')}
                aria-busy={loading}
                tabIndex={0}
            >
                <table className="w-full text-sm">
                    <thead className="bg-muted/50">
                        <tr>
                            {columns.map(([column, label]) => {
                                const selected = applied.sort === column;
                                const SortIcon = selected
                                    ? applied.direction === 'asc'
                                        ? ArrowUp
                                        : ArrowDown
                                    : ArrowUpDown;

                                return (
                                    <th
                                        key={column}
                                        scope="col"
                                        aria-sort={
                                            selected
                                                ? applied.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : undefined
                                        }
                                        className="px-2 py-2 text-start sm:px-3"
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="px-1 sm:px-2"
                                            title={t('Sort by :column', {
                                                column: t(label),
                                            })}
                                            disabled={loading}
                                            onClick={() =>
                                                visit(
                                                    nextCountrySort(
                                                        applied,
                                                        column,
                                                    ),
                                                )
                                            }
                                        >
                                            {t(label)}
                                            <SortIcon aria-hidden="true" />
                                        </Button>
                                    </th>
                                );
                            })}
                            <th
                                scope="col"
                                className="px-2 py-2 text-start sm:px-3"
                            >
                                {t('Languages')}
                            </th>
                            {canMutate && (
                                <th
                                    scope="col"
                                    className="px-2 py-2 text-start sm:px-3"
                                >
                                    {t('Actions')}
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {countries.data.map((country) => (
                            <tr key={country.iso2} className="border-t">
                                <td className="px-3 py-3 font-mono">
                                    <bdi dir="ltr">{country.iso2}</bdi>
                                </td>
                                <td className="px-3 py-3 font-mono">
                                    <bdi dir="ltr">{country.iso3 ?? '—'}</bdi>
                                </td>
                                <td className="px-3 py-3">
                                    <bdi>{country.english_name}</bdi>
                                </td>
                                <td className="px-3 py-3">
                                    <Badge
                                        variant={
                                            country.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {t(
                                            country.is_active
                                                ? 'Active'
                                                : 'Inactive',
                                        )}
                                    </Badge>
                                </td>
                                <td className="px-3 py-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            showLanguages(country.iso2)
                                        }
                                    >
                                        {t('Languages')}
                                    </Button>
                                </td>
                                {canMutate && (
                                    <td className="px-3 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditing(country)
                                                }
                                            >
                                                <Pencil aria-hidden="true" />
                                                {t('Edit')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setStatusTarget(country)
                                                }
                                            >
                                                {t(
                                                    country.is_active
                                                        ? 'Deactivate'
                                                        : 'Activate',
                                                )}
                                            </Button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                        {!loading && !failed && countries.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={
                                        columns.length + 1 + Number(canMutate)
                                    }
                                    className="p-8 text-center text-muted-foreground"
                                >
                                    {t('No countries found.')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                {countries.from !== null && countries.to !== null && (
                    <p className="text-sm text-muted-foreground">
                        {t('Showing :from–:to of :total', {
                            from: numbers.format(countries.from),
                            to: numbers.format(countries.to),
                            total: numbers.format(countries.total),
                        })}
                    </p>
                )}
                {(countries.last_page > 1 || countries.current_page > 1) && (
                    <nav
                        className="flex gap-2"
                        aria-label={t('Countries pagination')}
                    >
                        <Button
                            type="button"
                            variant="outline"
                            disabled={loading || countries.current_page <= 1}
                            onClick={() =>
                                visit(
                                    {},
                                    Math.min(
                                        countries.current_page - 1,
                                        countries.last_page,
                                    ),
                                )
                            }
                        >
                            {t('Previous')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                loading ||
                                countries.current_page >= countries.last_page
                            }
                            onClick={() =>
                                visit({}, countries.current_page + 1)
                            }
                        >
                            {t('Next')}
                        </Button>
                    </nav>
                )}
            </div>

            <CountryLanguagesDialog
                panel={countryLocales}
                onClose={() => showLanguages(null)}
            />

            <CountryFormDialog
                key={creating ? 'create-open' : 'create-closed'}
                open={canMutate && creating}
                onOpenChange={setCreating}
            />
            <CountryFormDialog
                key={editing?.iso2 ?? 'edit-closed'}
                country={editing}
                open={canMutate && editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            />

            <Dialog
                open={canMutate && statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                statusTarget?.is_active
                                    ? 'Deactivate'
                                    : 'Activate',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Change the status of :code?', {
                                code: statusTarget?.iso2 ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStatusTarget(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="button"
                            onClick={confirmStatus}
                            disabled={statusProcessing}
                        >
                            {t('Confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
