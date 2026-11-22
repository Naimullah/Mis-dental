import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CalendarX,
    FileSpreadsheet,
    FileText,
    History,
    Languages,
    Pencil,
    Plus,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    TAX_CODES_URL,
    TaxCodeFormDialog,
} from '@/components/tax-codes/tax-code-form-dialog';
import {
    LocalizedTypeName,
    TypeTranslationsDialog,
} from '@/components/type-translations-dialog';
import type { TypeTranslationData } from '@/components/type-translations-dialog';
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
import { useTrans } from '@/lib/i18n';
import type { CountryOption } from '@/types/administrative-area';
import type { Paginated } from '@/types/currency';
import type { TaxCodeFilters, TaxCodeRow } from '@/types/tax-code';

type Props = {
    taxCodes: Paginated<TaxCodeRow> | null;
    translationData: TypeTranslationData | null;
    filters: TaxCodeFilters;
    today: string;
    countries: CountryOption[];
    exportsAvailable: { xlsx: boolean; pdf: boolean };
};

function queryFor(filters: TaxCodeFilters): Record<string, string> {
    const query: Record<string, string> = {
        country: filters.country,
        sort: filters.sort,
        dir: filters.dir,
        per_page: String(filters.per_page),
    };

    if (filters.q !== '') {
        query.q = filters.q;
    }

    if (filters.on !== '') {
        query.on = filters.on;
    }

    return query;
}

export default function TaxCodesIndex({
    taxCodes,
    translationData,
    filters,
    today,
    countries,
    exportsAvailable,
}: Props) {
    const { t } = useTrans();
    const [search, setSearch] = useState(filters.q);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<TaxCodeRow | null>(null);
    const [versioning, setVersioning] = useState<TaxCodeRow | null>(null);
    const [closing, setClosing] = useState<TaxCodeRow | null>(null);
    const [translating, setTranslating] = useState<TaxCodeRow | null>(null);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('Master Data'), href: TAX_CODES_URL },
                { title: t('Tax codes'), href: TAX_CODES_URL },
            ],
        });
    }, [t]);

    const visit = (changes: Partial<TaxCodeFilters>) => {
        router.get(TAX_CODES_URL, queryFor({ ...filters, ...changes }), {
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

    const sortHeader = (column: TaxCodeFilters['sort'], label: string) => {
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
                    className="inline-flex items-center gap-1 hover:text-foreground"
                    onClick={() =>
                        visit({
                            sort: column,
                            dir:
                                active && filters.dir === 'asc'
                                    ? 'desc'
                                    : 'asc',
                        })
                    }
                    title={t('Sort by :column', { column: label })}
                >
                    {label}
                    <Icon className="size-3.5" aria-hidden />
                </button>
            </th>
        );
    };

    const statusBadge = (row: TaxCodeRow) => (
        <Badge
            variant={
                row.status === 'current'
                    ? 'default'
                    : row.status === 'future'
                      ? 'outline'
                      : 'secondary'
            }
        >
            {row.status === 'current'
                ? t('In effect')
                : row.status === 'future'
                  ? t('Upcoming')
                  : t('Ended')}
        </Badge>
    );

    const period = (row: TaxCodeRow) => (
        <bdi dir="ltr">
            {row.effective_from} → {row.effective_to ?? t('Open-ended')}
        </bdi>
    );

    const actions = (row: TaxCodeRow) => (
        <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={() => setEditing(row)}>
                <Pencil aria-hidden />
                {t('Edit')}
            </Button>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setTranslating(row)}
            >
                <Languages aria-hidden />
                {t('Translations')}
            </Button>
            {row.effective_to === null && (
                <>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setVersioning(row)}
                    >
                        <History aria-hidden />
                        {t('New version')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setClosing(row)}
                    >
                        <CalendarX aria-hidden />
                        {t('Close version')}
                    </Button>
                </>
            )}
        </div>
    );

    const exportQuery = new URLSearchParams(queryFor(filters)).toString();

    return (
        <>
            <Head title={t('Tax codes')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Tax codes')}
                        description={t(
                            'Each row is one version of a rate. To change a rate, add a new version; earlier versions stay as they were.',
                        )}
                    />
                    {taxCodes && (
                        <div className="flex flex-wrap gap-2">
                            {exportsAvailable.xlsx && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`${TAX_CODES_URL}/export/xlsx?${exportQuery}`}
                                    >
                                        <FileSpreadsheet aria-hidden />
                                        {t('Export Excel')}
                                    </a>
                                </Button>
                            )}
                            {exportsAvailable.pdf && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`${TAX_CODES_URL}/export/pdf?${exportQuery}`}
                                    >
                                        <FileText aria-hidden />
                                        {t('Export PDF')}
                                    </a>
                                </Button>
                            )}
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden />
                                {t('Add tax code')}
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <Select
                        value={
                            filters.country === '' ? undefined : filters.country
                        }
                        onValueChange={(value) =>
                            visit({ country: value, q: '' })
                        }
                    >
                        <SelectTrigger
                            className="sm:w-64"
                            aria-label={t('Country')}
                        >
                            <SelectValue placeholder={t('Choose a country')} />
                        </SelectTrigger>
                        <SelectContent>
                            {countries.map((country) => (
                                <SelectItem
                                    key={country.iso2}
                                    value={country.iso2}
                                >
                                    <bdi dir="ltr">
                                        {country.iso2} — {country.english_name}
                                    </bdi>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {taxCodes && (
                        <>
                            <Input
                                type="search"
                                dir="auto"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t('Search by code or description')}
                                aria-label={t('Search by code or description')}
                                className="sm:max-w-xs"
                                maxLength={100}
                            />
                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor="tax-codes-on"
                                    className="whitespace-nowrap"
                                >
                                    {t('In effect on')}
                                </Label>
                                <Input
                                    id="tax-codes-on"
                                    type="date"
                                    dir="ltr"
                                    className="sm:w-44"
                                    value={filters.on}
                                    onChange={(event) =>
                                        visit({ on: event.target.value })
                                    }
                                />
                                {filters.on !== '' && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => visit({ on: '' })}
                                    >
                                        {t('Clear')}
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </div>

                {!taxCodes ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('Choose a country to see its tax codes.')}
                    </p>
                ) : taxCodes.data.length === 0 ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('No tax codes found.')}
                    </p>
                ) : (
                    <>
                        <div className="hidden overflow-x-auto rounded-lg border md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        {sortHeader('code', t('Code'))}
                                        {sortHeader(
                                            'description',
                                            t('Description'),
                                        )}
                                        {sortHeader(
                                            'rate_percent',
                                            t('Rate (%)'),
                                        )}
                                        {sortHeader(
                                            'effective_from',
                                            t('Effective from'),
                                        )}
                                        {sortHeader(
                                            'effective_to',
                                            t('Effective to'),
                                        )}
                                        <th className="px-3 py-2 text-start font-medium">
                                            {t('Status')}
                                        </th>
                                        <th className="px-3 py-2 text-start font-medium">
                                            {t('Actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {taxCodes.data.map((row) => (
                                        <tr key={row.id} className="border-t">
                                            <td
                                                className="px-3 py-2 font-mono"
                                                dir="ltr"
                                            >
                                                {row.code}
                                            </td>
                                            <td className="px-3 py-2">
                                                <LocalizedTypeName
                                                    canonical={row.description}
                                                    translation={
                                                        translationData
                                                            ?.localized[row.id]
                                                    }
                                                />
                                            </td>
                                            <td
                                                className="px-3 py-2 font-mono"
                                                dir="ltr"
                                            >
                                                {row.rate_percent}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                dir="ltr"
                                            >
                                                {row.effective_from}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.effective_to ? (
                                                    <bdi dir="ltr">
                                                        {row.effective_to}
                                                    </bdi>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {t('Open-ended')}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {statusBadge(row)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {actions(row)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="flex flex-col gap-3 md:hidden">
                            {taxCodes.data.map((row) => (
                                <li key={row.id} className="rounded-lg border p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <span
                                            className="font-mono font-medium"
                                            dir="ltr"
                                        >
                                            {row.code}
                                        </span>
                                        {statusBadge(row)}
                                    </div>
                                    <p className="mt-1 text-sm">
                                        <LocalizedTypeName
                                            canonical={row.description}
                                            translation={
                                                translationData?.localized[
                                                    row.id
                                                ]
                                            }
                                        />
                                    </p>
                                    <p className="mt-1 font-mono text-sm" dir="ltr">
                                        {row.rate_percent} %
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {period(row)}
                                    </p>
                                    <div className="mt-3">{actions(row)}</div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                {taxCodes && (
                    <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-muted-foreground" aria-live="polite">
                            {taxCodes.total > 0 &&
                                t('Showing :from–:to of :total', {
                                    from: taxCodes.from ?? 0,
                                    to: taxCodes.to ?? 0,
                                    total: taxCodes.total,
                                })}
                        </p>
                        <div className="flex items-center gap-2">
                            <PageLink
                                href={taxCodes.prev_page_url}
                                label={t('Previous')}
                            />
                            <span className="text-muted-foreground">
                                {t('Page :current of :last', {
                                    current: taxCodes.current_page,
                                    last: taxCodes.last_page,
                                })}
                            </span>
                            <PageLink
                                href={taxCodes.next_page_url}
                                label={t('Next')}
                            />
                        </div>
                    </div>
                )}
            </div>

            {taxCodes && (
                <>
                    <TaxCodeFormDialog
                        key={creating ? 'create-open' : 'create-closed'}
                        mode="create"
                        country={filters.country}
                        today={today}
                        open={creating}
                        onOpenChange={setCreating}
                    />
                    <TaxCodeFormDialog
                        key={editing ? `edit-${editing.id}` : 'edit-closed'}
                        mode="edit"
                        country={filters.country}
                        taxCode={editing}
                        today={today}
                        open={editing !== null}
                        onOpenChange={(open) => !open && setEditing(null)}
                    />
                    <TaxCodeFormDialog
                        key={
                            versioning
                                ? `version-${versioning.id}`
                                : 'version-closed'
                        }
                        mode="version"
                        country={filters.country}
                        taxCode={versioning}
                        today={today}
                        open={versioning !== null}
                        onOpenChange={(open) => !open && setVersioning(null)}
                    />
                    {translationData && translating && (
                        <TypeTranslationsDialog
                            key={translating.id}
                            type={{
                                id: translating.id,
                                code: translating.code,
                                name: `${translating.description} · ${translating.effective_from} → ${translating.effective_to ?? t('Open-ended')}`,
                            }}
                            data={translationData}
                            url={`${TAX_CODES_URL}/${translating.id}/translations`}
                            maxLength={140}
                            helpLabel={t('Explanation')}
                            helpMaxLength={4000}
                            onClose={() => setTranslating(null)}
                        />
                    )}
                    <CloseVersionDialog
                        key={closing ? `close-${closing.id}` : 'close-closed'}
                        taxCode={closing}
                        today={today}
                        onClose={() => setClosing(null)}
                    />
                </>
            )}
        </>
    );
}

function CloseVersionDialog({
    taxCode,
    today,
    onClose,
}: {
    taxCode: TaxCodeRow | null;
    today: string;
    onClose: () => void;
}) {
    const { t } = useTrans();
    const form = useForm({
        effective_to:
            taxCode && taxCode.effective_from > today
                ? taxCode.effective_from
                : today,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!taxCode) {
            return;
        }

        form.patch(`${TAX_CODES_URL}/${taxCode.id}/close`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open={taxCode !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('Close :code', { code: taxCode?.code ?? '' })}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'Set the last day this rate applies. A closed version cannot be reopened.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="tax-code-close-to">
                            {t('Effective to')}
                        </Label>
                        <Input
                            id="tax-code-close-to"
                            type="date"
                            dir="ltr"
                            value={form.data.effective_to}
                            min={taxCode?.effective_from}
                            onChange={(event) =>
                                form.setData('effective_to', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.effective_to} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
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
