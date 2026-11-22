import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ChevronRight,
    FileSpreadsheet,
    FileText,
    FolderTree,
    Pencil,
    Plus,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    AREAS_URL,
    AreaFormDialog,
} from '@/components/administrative-areas/area-form-dialog';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/lib/i18n';
import type {
    AreaFilters,
    AreaRow,
    CountryOption,
    TranslationLocale,
} from '@/types/administrative-area';
import type { Paginated } from '@/types/currency';

type Props = {
    areas: Paginated<AreaRow> | null;
    filters: AreaFilters;
    countries: CountryOption[];
    ancestors: { id: number; name: string }[];
    levels: string[];
    translationLocales: TranslationLocale[];
    exportsAvailable: { xlsx: boolean; pdf: boolean };
};

function queryFor(filters: AreaFilters): Record<string, string> {
    const query: Record<string, string> = {
        country: filters.country,
        level: filters.level,
        status: filters.status,
        sort: filters.sort,
        dir: filters.dir,
        per_page: String(filters.per_page),
    };

    if (filters.parent !== null) {
        query.parent = String(filters.parent);
    }

    if (filters.q !== '') {
        query.q = filters.q;
    }

    return query;
}

export default function AdministrativeAreasIndex({
    areas,
    filters,
    countries,
    ancestors,
    levels,
    translationLocales,
    exportsAvailable,
}: Props) {
    const { t } = useTrans();
    const [search, setSearch] = useState(filters.q);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<AreaRow | null>(null);
    const [statusTarget, setStatusTarget] = useState<AreaRow | null>(null);
    const [statusError, setStatusError] = useState<string | null>(null);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('Master Data'), href: AREAS_URL },
                { title: t('Administrative areas'), href: AREAS_URL },
            ],
        });
    }, [t]);

    const visit = (changes: Partial<AreaFilters>) => {
        router.get(AREAS_URL, queryFor({ ...filters, ...changes }), {
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

    const confirmStatus = () => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            `${AREAS_URL}/${statusTarget.id}/status`,
            { is_active: !statusTarget.is_active },
            {
                preserveScroll: true,
                onSuccess: () => setStatusTarget(null),
                onError: (errors) => setStatusError(errors.is_active ?? null),
            },
        );
    };

    const currentParent =
        ancestors.length > 0 ? ancestors[ancestors.length - 1] : null;
    const exportQuery = new URLSearchParams(queryFor(filters)).toString();

    const nameCell = (area: AreaRow) => (
        <span className="flex flex-col">
            <bdi>{area.display_name}</bdi>
            {area.display_name !== area.canonical_name && (
                <bdi className="text-xs text-muted-foreground">
                    {area.canonical_name}
                </bdi>
            )}
            {filters.q !== '' && area.parent_path !== '' && (
                <bdi className="text-xs text-muted-foreground">
                    {area.parent_path}
                </bdi>
            )}
        </span>
    );

    const actions = (area: AreaRow) => (
        <div className="flex flex-wrap gap-2">
            <Button
                variant="outline"
                size="sm"
                onClick={() => visit({ parent: area.id, q: '' })}
                title={t('Open child areas')}
            >
                <FolderTree aria-hidden />
                {area.children_count}
            </Button>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setEditing(area)}
            >
                <Pencil aria-hidden />
                {t('Edit')}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                    setStatusError(null);
                    setStatusTarget(area);
                }}
            >
                {area.is_active ? t('Deactivate') : t('Activate')}
            </Button>
        </div>
    );

    return (
        <>
            <Head title={t('Administrative areas')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('Administrative areas')} />
                    {areas && (
                        <div className="flex flex-wrap gap-2">
                            {exportsAvailable.xlsx && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`${AREAS_URL}/export/xlsx?${exportQuery}`}
                                    >
                                        <FileSpreadsheet aria-hidden />
                                        {t('Export Excel')}
                                    </a>
                                </Button>
                            )}
                            {exportsAvailable.pdf && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={`${AREAS_URL}/export/pdf?${exportQuery}`}
                                    >
                                        <FileText aria-hidden />
                                        {t('Export PDF')}
                                    </a>
                                </Button>
                            )}
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden />
                                {t('Add area')}
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
                            visit({
                                country: value,
                                parent: null,
                                q: '',
                                level: 'all',
                            })
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
                    {areas && (
                        <>
                            <Input
                                type="search"
                                dir="auto"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t('Search by name or code')}
                                aria-label={t('Search by name or code')}
                                className="sm:max-w-xs"
                                maxLength={100}
                            />
                            <Select
                                value={filters.level}
                                onValueChange={(value) =>
                                    visit({ level: value })
                                }
                            >
                                <SelectTrigger
                                    className="sm:w-40"
                                    aria-label={t('Level')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('All levels')}
                                    </SelectItem>
                                    {levels.map((level) => (
                                        <SelectItem key={level} value={level}>
                                            <bdi dir="ltr">{level}</bdi>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.status}
                                onValueChange={(value) =>
                                    visit({
                                        status: value as AreaFilters['status'],
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

                {!areas ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('Choose a country to see its administrative areas.')}
                    </p>
                ) : (
                    <>
                        {filters.q === '' && (
                            <nav
                                aria-label={t('Area path')}
                                className="flex flex-wrap items-center gap-1 text-sm"
                            >
                                <button
                                    type="button"
                                    className="underline-offset-4 hover:underline"
                                    onClick={() => visit({ parent: null })}
                                >
                                    {t('Top level')}
                                </button>
                                {ancestors.map((ancestor) => (
                                    <span
                                        key={ancestor.id}
                                        className="flex items-center gap-1"
                                    >
                                        <ChevronRight
                                            className="size-4 rtl:rotate-180"
                                            aria-hidden
                                        />
                                        <button
                                            type="button"
                                            className="underline-offset-4 hover:underline"
                                            onClick={() =>
                                                visit({ parent: ancestor.id })
                                            }
                                        >
                                            <bdi>{ancestor.name}</bdi>
                                        </button>
                                    </span>
                                ))}
                            </nav>
                        )}

                        {areas.data.length === 0 ? (
                            <p className="rounded-lg border p-8 text-center text-muted-foreground">
                                {t('No areas found.')}
                            </p>
                        ) : (
                            <>
                                <div className="hidden overflow-x-auto rounded-lg border md:block">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-2 text-start font-medium">
                                                    {t('Name')}
                                                </th>
                                                <th className="px-3 py-2 text-start font-medium">
                                                    {t('Level')}
                                                </th>
                                                <th className="px-3 py-2 text-start font-medium">
                                                    {t('Area code')}
                                                </th>
                                                <th className="px-3 py-2 text-start font-medium">
                                                    {t('Status')}
                                                </th>
                                                <th className="px-3 py-2 text-start font-medium">
                                                    {t('Actions')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {areas.data.map((area) => (
                                                <tr
                                                    key={area.id}
                                                    className="border-t"
                                                >
                                                    <td className="px-3 py-2">
                                                        {nameCell(area)}
                                                    </td>
                                                    <td
                                                        className="px-3 py-2 font-mono"
                                                        dir="ltr"
                                                    >
                                                        {area.level_code}
                                                    </td>
                                                    <td
                                                        className="px-3 py-2 font-mono"
                                                        dir="ltr"
                                                    >
                                                        {area.area_code ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Badge
                                                            variant={
                                                                area.is_active
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {area.is_active
                                                                ? t('Active')
                                                                : t('Inactive')}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {actions(area)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <ul className="flex flex-col gap-3 md:hidden">
                                    {areas.data.map((area) => (
                                        <li
                                            key={area.id}
                                            className="rounded-lg border p-3"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                {nameCell(area)}
                                                <Badge
                                                    variant={
                                                        area.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {area.is_active
                                                        ? t('Active')
                                                        : t('Inactive')}
                                                </Badge>
                                            </div>
                                            <p
                                                className="mt-1 text-sm text-muted-foreground"
                                                dir="ltr"
                                            >
                                                {area.level_code} ·{' '}
                                                {area.area_code ?? '—'}
                                            </p>
                                            <div className="mt-3">
                                                {actions(area)}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </>
                        )}

                        <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p
                                className="text-muted-foreground"
                                aria-live="polite"
                            >
                                {areas.total > 0 &&
                                    t('Showing :from–:to of :total', {
                                        from: areas.from ?? 0,
                                        to: areas.to ?? 0,
                                        total: areas.total,
                                    })}
                            </p>
                            <div className="flex items-center gap-2">
                                <PageLink
                                    href={areas.prev_page_url}
                                    label={t('Previous')}
                                />
                                <span className="text-muted-foreground">
                                    {t('Page :current of :last', {
                                        current: areas.current_page,
                                        last: areas.last_page,
                                    })}
                                </span>
                                <PageLink
                                    href={areas.next_page_url}
                                    label={t('Next')}
                                />
                            </div>
                        </div>
                    </>
                )}
            </div>

            {areas && (
                <>
                    <AreaFormDialog
                        key={
                            creating
                                ? `create-${filters.parent ?? 'top'}`
                                : 'create-closed'
                        }
                        country={filters.country}
                        defaultParent={{
                            id: currentParent?.id ?? null,
                            label: ancestors.map((a) => a.name).join(' › '),
                        }}
                        levels={levels}
                        translationLocales={translationLocales}
                        open={creating}
                        onOpenChange={setCreating}
                    />
                    <AreaFormDialog
                        key={editing ? `edit-${editing.id}` : 'edit-closed'}
                        country={filters.country}
                        area={editing}
                        defaultParent={{ id: null, label: '' }}
                        levels={levels}
                        translationLocales={translationLocales}
                        open={editing !== null}
                        onOpenChange={(open) => !open && setEditing(null)}
                    />
                </>
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
                                code: statusTarget?.display_name ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    {statusError && (
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {statusError}
                        </p>
                    )}
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
