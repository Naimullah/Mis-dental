import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    FileSpreadsheet,
    FileText,
    Pencil,
    Plus,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useTrans } from '@/lib/i18n';
import type {
    Currency,
    CurrencyFilters,
    CurrencySort,
    Paginated,
} from '@/types/currency';

const BASE = '/platform/master-data/currencies';
const PER_PAGE_OPTIONS = [10, 25, 50, 100];
const MINOR_UNIT_OPTIONS = ['0', '1', '2', '3', '4'];

type Props = {
    currencies: Paginated<Currency>;
    filters: CurrencyFilters;
    exportsAvailable: { xlsx: boolean; pdf: boolean };
};

function queryFor(filters: CurrencyFilters): Record<string, string> {
    const query: Record<string, string> = {
        status: filters.status,
        sort: filters.sort,
        dir: filters.dir,
        per_page: String(filters.per_page),
    };

    if (filters.q !== '') {
        query.q = filters.q;
    }

    return query;
}

function previewAmount(
    locale: string,
    minorUnits: number,
    code: string,
): string {
    try {
        const amount = new Intl.NumberFormat(locale, {
            minimumFractionDigits: minorUnits,
            maximumFractionDigits: minorUnits,
        }).format(1234.5678);

        return `${amount} ${code || '___'}`;
    } catch {
        return '';
    }
}

export default function CurrenciesIndex({
    currencies,
    filters,
    exportsAvailable,
}: Props) {
    const { t } = useTrans();
    const [search, setSearch] = useState(filters.q);
    const [editing, setEditing] = useState<Currency | null>(null);
    const [creating, setCreating] = useState(false);
    const [statusTarget, setStatusTarget] = useState<Currency | null>(null);
    const [statusProcessing, setStatusProcessing] = useState(false);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('Master Data'), href: BASE },
                { title: t('Currencies'), href: BASE },
            ],
        });
    }, [t]);

    const visit = (changes: Partial<CurrencyFilters>) => {
        router.get(BASE, queryFor({ ...filters, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (search.trim() === filters.q) {
            return;
        }

        const timer = window.setTimeout(() => visit({ q: search.trim() }), 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const toggleSort = (column: CurrencySort) => {
        visit({
            sort: column,
            dir:
                filters.sort === column && filters.dir === 'asc'
                    ? 'desc'
                    : 'asc',
        });
    };

    const confirmStatus = () => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            `${BASE}/${statusTarget.code}/status`,
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
    };

    const exportQuery = new URLSearchParams(queryFor(filters)).toString();

    const sortHeader = (column: CurrencySort, label: string) => {
        const active = filters.sort === column;
        const Icon = !active
            ? ArrowUpDown
            : filters.dir === 'asc'
              ? ArrowUp
              : ArrowDown;

        return (
            <th
                scope="col"
                className="px-3 py-2 text-start font-medium"
                aria-sort={
                    active
                        ? filters.dir === 'asc'
                            ? 'ascending'
                            : 'descending'
                        : 'none'
                }
            >
                <button
                    type="button"
                    onClick={() => toggleSort(column)}
                    className="inline-flex items-center gap-1 hover:text-foreground"
                    title={t('Sort by :column', { column: label })}
                >
                    {label}
                    <Icon className="size-3.5" aria-hidden />
                </button>
            </th>
        );
    };

    const statusBadge = (currency: Currency) => (
        <Badge variant={currency.is_active ? 'default' : 'secondary'}>
            {currency.is_active ? t('Active') : t('Inactive')}
        </Badge>
    );

    const rowActions = (currency: Currency) => (
        <div className="flex flex-wrap gap-2">
            <Button
                variant="outline"
                size="sm"
                onClick={() => setEditing(currency)}
            >
                <Pencil aria-hidden />
                {t('Edit')}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setStatusTarget(currency)}
            >
                {currency.is_active ? t('Deactivate') : t('Activate')}
            </Button>
        </div>
    );

    return (
        <>
            <Head title={t('Currencies')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('Currencies')} />
                    <div className="flex flex-wrap gap-2">
                        {exportsAvailable.xlsx && (
                            <Button variant="outline" asChild>
                                <a href={`${BASE}/export/xlsx?${exportQuery}`}>
                                    <FileSpreadsheet aria-hidden />
                                    {t('Export Excel')}
                                </a>
                            </Button>
                        )}
                        {exportsAvailable.pdf && (
                            <Button variant="outline" asChild>
                                <a href={`${BASE}/export/pdf?${exportQuery}`}>
                                    <FileText aria-hidden />
                                    {t('Export PDF')}
                                </a>
                            </Button>
                        )}
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden />
                            {t('Add currency')}
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search by code, name or symbol')}
                        aria-label={t('Search by code, name or symbol')}
                        className="sm:max-w-sm"
                        maxLength={100}
                    />
                    <Select
                        value={filters.status}
                        onValueChange={(value) =>
                            visit({
                                status: value as CurrencyFilters['status'],
                            })
                        }
                    >
                        <SelectTrigger
                            className="sm:w-40"
                            aria-label={t('Status')}
                        >
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

                {currencies.data.length === 0 ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('No currencies found.')}
                    </p>
                ) : (
                    <>
                        <div className="hidden overflow-x-auto rounded-lg border md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        {sortHeader('code', t('Code'))}
                                        {sortHeader(
                                            'english_name',
                                            t('English name'),
                                        )}
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-start font-medium"
                                        >
                                            {t('Symbol')}
                                        </th>
                                        {sortHeader(
                                            'minor_units',
                                            t('Minor units'),
                                        )}
                                        {sortHeader('is_active', t('Status'))}
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-start font-medium"
                                        >
                                            {t('Actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {currencies.data.map((currency) => (
                                        <tr
                                            key={currency.code}
                                            className="border-t"
                                        >
                                            <td
                                                className="px-3 py-2 font-mono"
                                                dir="ltr"
                                            >
                                                {currency.code}
                                            </td>
                                            <td className="px-3 py-2" dir="ltr">
                                                {currency.english_name}
                                            </td>
                                            <td className="px-3 py-2">
                                                <bdi>
                                                    {currency.symbol ?? '—'}
                                                </bdi>
                                            </td>
                                            <td className="px-3 py-2">
                                                {currency.minor_units}
                                            </td>
                                            <td className="px-3 py-2">
                                                {statusBadge(currency)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {rowActions(currency)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="flex flex-col gap-3 md:hidden">
                            {currencies.data.map((currency) => (
                                <li
                                    key={currency.code}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span
                                            className="font-mono font-medium"
                                            dir="ltr"
                                        >
                                            {currency.code}
                                        </span>
                                        {statusBadge(currency)}
                                    </div>
                                    <p className="mt-1 text-sm" dir="ltr">
                                        {currency.english_name}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {t('Symbol')}:{' '}
                                        <bdi>{currency.symbol ?? '—'}</bdi> ·{' '}
                                        {t('Minor units')}:{' '}
                                        {currency.minor_units}
                                    </p>
                                    <div className="mt-3">
                                        {rowActions(currency)}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground" aria-live="polite">
                        {currencies.total > 0 &&
                            t('Showing :from–:to of :total', {
                                from: currencies.from ?? 0,
                                to: currencies.to ?? 0,
                                total: currencies.total,
                            })}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <Label
                            htmlFor="per-page"
                            className="text-muted-foreground"
                        >
                            {t('Rows per page')}
                        </Label>
                        <Select
                            value={String(filters.per_page)}
                            onValueChange={(value) =>
                                visit({ per_page: Number(value) })
                            }
                        >
                            <SelectTrigger
                                id="per-page"
                                size="sm"
                                className="w-20"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PER_PAGE_OPTIONS.map((option) => (
                                    <SelectItem
                                        key={option}
                                        value={String(option)}
                                    >
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <PageLink
                            href={currencies.prev_page_url}
                            label={t('Previous')}
                        />
                        <span className="text-muted-foreground">
                            {t('Page :current of :last', {
                                current: currencies.current_page,
                                last: currencies.last_page,
                            })}
                        </span>
                        <PageLink
                            href={currencies.next_page_url}
                            label={t('Next')}
                        />
                    </div>
                </div>
            </div>

            <CurrencyFormDialog
                key={creating ? 'create-open' : 'create-closed'}
                open={creating}
                onOpenChange={setCreating}
            />
            <CurrencyFormDialog
                key={editing?.code ?? 'edit-closed'}
                currency={editing}
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            />

            <Dialog
                open={statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {statusTarget?.is_active
                                ? t('Deactivate')
                                : t('Activate')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Change the status of :code?', {
                                code: statusTarget?.code ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setStatusTarget(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            onClick={confirmStatus}
                            disabled={statusProcessing}
                        >
                            {t('Confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll preserveState>
                {label}
            </Link>
        </Button>
    );
}

type CurrencyForm = {
    code: string;
    english_name: string;
    symbol: string;
    minor_units: string;
    is_active: boolean;
};

function CurrencyFormDialog({
    currency = null,
    open,
    onOpenChange,
}: {
    currency?: Currency | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t, locale } = useTrans();
    const isEdit = currency !== null;

    const form = useForm<CurrencyForm>({
        code: currency?.code ?? '',
        english_name: currency?.english_name ?? '',
        symbol: currency?.symbol ?? '',
        minor_units: String(currency?.minor_units ?? 2),
        is_active: currency?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit) {
            form.transform(({ code: _code, ...rest }) => rest);
            form.put(`${BASE}/${currency.code}`, options);
        } else {
            form.transform((data) => data);
            form.post(BASE, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit ? t('Edit currency') : t('Add currency')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="currency-code">{t('Code')}</Label>
                        <Input
                            id="currency-code"
                            dir="ltr"
                            className="font-mono uppercase"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData(
                                    'code',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            maxLength={3}
                            disabled={isEdit}
                            required={!isEdit}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? t(
                                      'The code cannot be changed after creation.',
                                  )
                                : t('Three uppercase letters, e.g. AFN')}
                        </p>
                        <InputError message={form.errors.code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="currency-name">
                            {t('English name')}
                        </Label>
                        <Input
                            id="currency-name"
                            dir="ltr"
                            value={form.data.english_name}
                            onChange={(event) =>
                                form.setData('english_name', event.target.value)
                            }
                            maxLength={100}
                            required
                        />
                        <InputError message={form.errors.english_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="currency-symbol">{t('Symbol')}</Label>
                        <Input
                            id="currency-symbol"
                            value={form.data.symbol}
                            onChange={(event) =>
                                form.setData('symbol', event.target.value)
                            }
                            maxLength={12}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('Optional, up to 12 characters')}
                        </p>
                        <InputError message={form.errors.symbol} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="currency-minor-units">
                            {t('Minor units')}
                        </Label>
                        <Select
                            value={form.data.minor_units}
                            onValueChange={(value) =>
                                form.setData('minor_units', value)
                            }
                        >
                            <SelectTrigger
                                id="currency-minor-units"
                                className="w-24"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {MINOR_UNIT_OPTIONS.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            {t('Digits after the decimal point (0–4)')} ·{' '}
                            {t('Preview')}:{' '}
                            <bdi>
                                {previewAmount(
                                    locale,
                                    Number(form.data.minor_units),
                                    form.data.code,
                                )}
                            </bdi>
                        </p>
                        <InputError message={form.errors.minor_units} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="currency-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="currency-active">{t('Active')}</Label>
                    </div>
                    <InputError message={form.errors.is_active} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isEdit ? t('Save') : t('Create')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
