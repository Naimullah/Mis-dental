import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
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
import Heading from '@/components/heading';
import {
    LICENSE_TYPES_URL,
    LicenseTypeFormDialog,
} from '@/components/license-types/license-type-form-dialog';
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
import type {
    AppliesToOption,
    LicenseTypeFilters,
    LicenseTypeRow,
} from '@/types/license-type';

type Props = {
    licenseTypes: Paginated<LicenseTypeRow> | null;
    translationData: TypeTranslationData | null;
    filters: LicenseTypeFilters;
    countries: CountryOption[];
    appliesToOptions: AppliesToOption[];
    exportsAvailable: { xlsx: boolean; pdf: boolean };
};

function queryFor(filters: LicenseTypeFilters): Record<string, string> {
    const query: Record<string, string> = {
        country: filters.country,
        applies_to: filters.applies_to,
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

export default function LicenseTypesIndex({
    licenseTypes,
    translationData,
    filters,
    countries,
    appliesToOptions,
    exportsAvailable,
}: Props) {
    const { t } = useTrans();
    const [search, setSearch] = useState(filters.q);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<LicenseTypeRow | null>(null);
    const [translating, setTranslating] = useState<LicenseTypeRow | null>(null);
    const [statusTarget, setStatusTarget] = useState<LicenseTypeRow | null>(
        null,
    );

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('Master Data'), href: LICENSE_TYPES_URL },
                { title: t('License types'), href: LICENSE_TYPES_URL },
            ],
        });
    }, [t]);

    const visit = (changes: Partial<LicenseTypeFilters>) => {
        router.get(LICENSE_TYPES_URL, queryFor({ ...filters, ...changes }), {
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

    const appliesToLabel = (value: string) =>
        appliesToOptions.find((option) => option.value === value)?.label ??
        value;

    const confirmStatus = () => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            `${LICENSE_TYPES_URL}/${statusTarget.id}/status`,
            { is_active: !statusTarget.is_active },
            { preserveScroll: true, onFinish: () => setStatusTarget(null) },
        );
    };

    const sortHeader = (column: LicenseTypeFilters['sort'], label: string) => {
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

    const statusBadge = (type: LicenseTypeRow) => (
        <Badge variant={type.is_active ? 'default' : 'secondary'}>
            {type.is_active ? t('Active') : t('Inactive')}
        </Badge>
    );

    const actions = (type: LicenseTypeRow) => (
        <div className="flex flex-wrap gap-2">
            <Button
                variant="outline"
                size="sm"
                onClick={() => setEditing(type)}
            >
                <Pencil aria-hidden />
                {t('Edit')}
            </Button>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setTranslating(type)}
            >
                {t('Translations')}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setStatusTarget(type)}
            >
                {type.is_active ? t('Deactivate') : t('Activate')}
            </Button>
        </div>
    );

    const exportQuery = new URLSearchParams(queryFor(filters)).toString();

    return (
        <>
            <Head title={t('License types')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('License types')} />
                    {licenseTypes && (
                        <div className="flex flex-wrap gap-2">
                            {exportsAvailable.xlsx && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`${LICENSE_TYPES_URL}/export/xlsx?${exportQuery}`}
                                    >
                                        <FileSpreadsheet aria-hidden />
                                        {t('Export Excel')}
                                    </a>
                                </Button>
                            )}
                            {exportsAvailable.pdf && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`${LICENSE_TYPES_URL}/export/pdf?${exportQuery}`}
                                    >
                                        <FileText aria-hidden />
                                        {t('Export PDF')}
                                    </a>
                                </Button>
                            )}
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden />
                                {t('Add license type')}
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
                    {licenseTypes && (
                        <>
                            <Input
                                type="search"
                                dir="auto"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t('Search by code or name')}
                                aria-label={t('Search by code or name')}
                                className="sm:max-w-xs"
                                maxLength={100}
                            />
                            <Select
                                value={filters.applies_to}
                                onValueChange={(value) =>
                                    visit({
                                        applies_to:
                                            value as LicenseTypeFilters['applies_to'],
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="sm:w-44"
                                    aria-label={t('Applies to')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('All')}
                                    </SelectItem>
                                    {appliesToOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.status}
                                onValueChange={(value) =>
                                    visit({
                                        status: value as LicenseTypeFilters['status'],
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="sm:w-36"
                                    aria-label={t('Status')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('All')}
                                    </SelectItem>
                                    <SelectItem value="active">
                                        {t('Active')}
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        {t('Inactive')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </>
                    )}
                </div>

                {!licenseTypes ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('Choose a country to see its license types.')}
                    </p>
                ) : licenseTypes.data.length === 0 ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('No license types found.')}
                    </p>
                ) : (
                    <>
                        <div className="hidden overflow-x-auto rounded-lg border md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        {sortHeader('code', t('Code'))}
                                        {sortHeader('name', t('Name'))}
                                        {sortHeader(
                                            'applies_to',
                                            t('Applies to'),
                                        )}
                                        {sortHeader('is_active', t('Status'))}
                                        <th className="px-3 py-2 text-start font-medium">
                                            {t('Actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {licenseTypes.data.map((type) => (
                                        <tr key={type.id} className="border-t">
                                            <td
                                                className="px-3 py-2 font-mono"
                                                dir="ltr"
                                            >
                                                {type.code}
                                            </td>
                                            <td className="px-3 py-2">
                                                <LocalizedTypeName
                                                    canonical={type.name}
                                                    translation={
                                                        translationData
                                                            ?.localized[type.id]
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                {appliesToLabel(
                                                    type.applies_to,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {statusBadge(type)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {actions(type)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="flex flex-col gap-3 md:hidden">
                            {licenseTypes.data.map((type) => (
                                <li
                                    key={type.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span
                                            className="font-mono font-medium"
                                            dir="ltr"
                                        >
                                            {type.code}
                                        </span>
                                        {statusBadge(type)}
                                    </div>
                                    <p className="mt-1 text-sm">
                                        <LocalizedTypeName
                                            canonical={type.name}
                                            translation={
                                                translationData?.localized[
                                                    type.id
                                                ]
                                            }
                                        />
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {appliesToLabel(type.applies_to)}
                                    </p>
                                    <div className="mt-3">{actions(type)}</div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                {licenseTypes && (
                    <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-muted-foreground" aria-live="polite">
                            {licenseTypes.total > 0 &&
                                t('Showing :from–:to of :total', {
                                    from: licenseTypes.from ?? 0,
                                    to: licenseTypes.to ?? 0,
                                    total: licenseTypes.total,
                                })}
                        </p>
                        <div className="flex items-center gap-2">
                            <PageLink
                                href={licenseTypes.prev_page_url}
                                label={t('Previous')}
                            />
                            <span className="text-muted-foreground">
                                {t('Page :current of :last', {
                                    current: licenseTypes.current_page,
                                    last: licenseTypes.last_page,
                                })}
                            </span>
                            <PageLink
                                href={licenseTypes.next_page_url}
                                label={t('Next')}
                            />
                        </div>
                    </div>
                )}
            </div>

            {licenseTypes && (
                <>
                    <LicenseTypeFormDialog
                        key={creating ? 'create-open' : 'create-closed'}
                        country={filters.country}
                        appliesToOptions={appliesToOptions}
                        open={creating}
                        onOpenChange={setCreating}
                    />
                    <LicenseTypeFormDialog
                        key={editing ? `edit-${editing.id}` : 'edit-closed'}
                        country={filters.country}
                        licenseType={editing}
                        appliesToOptions={appliesToOptions}
                        open={editing !== null}
                        onOpenChange={(open) => !open && setEditing(null)}
                    />
                </>
            )}

            {translationData && translating && (
                <TypeTranslationsDialog
                    key={translating.id}
                    type={translating}
                    data={translationData}
                    url={`${LICENSE_TYPES_URL}/${translating.id}/translations`}
                    maxLength={180}
                    onClose={() => setTranslating(null)}
                />
            )}
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
                        <Button onClick={confirmStatus}>{t('Confirm')}</Button>
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
