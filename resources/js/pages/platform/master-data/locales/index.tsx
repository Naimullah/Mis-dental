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
import { LocaleFormDialog } from '@/components/locales/locale-form-dialog';
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
import type { Paginated } from '@/types/currency';
import type {
    CatalogEntry,
    LocaleFilters,
    LocaleRow,
    LocaleSort,
    TextDirection,
} from '@/types/locale';

export const LOCALES_URL = '/platform/master-data/locales';
const PER_PAGE_OPTIONS = [10, 25, 50, 100];

type Props = {
    localeRows: Paginated<LocaleRow>;
    filters: LocaleFilters;
    catalog: CatalogEntry[];
    calendars: string[];
    numberingSystems: string[];
    exportsAvailable: { xlsx: boolean; pdf: boolean };
};

function queryFor(filters: LocaleFilters): Record<string, string> {
    const query: Record<string, string> = {
        direction: filters.direction,
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

export default function LocalesIndex({
    localeRows: locales,
    filters,
    catalog,
    calendars,
    numberingSystems,
    exportsAvailable,
}: Props) {
    const { t } = useTrans();
    const [search, setSearch] = useState(filters.q);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<LocaleRow | null>(null);
    const [statusTarget, setStatusTarget] = useState<LocaleRow | null>(null);
    const [statusProcessing, setStatusProcessing] = useState(false);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('Master Data'), href: LOCALES_URL },
                { title: t('Languages'), href: LOCALES_URL },
            ],
        });
    }, [t]);

    const visit = (changes: Partial<LocaleFilters>) => {
        router.get(LOCALES_URL, queryFor({ ...filters, ...changes }), {
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
            `${LOCALES_URL}/${statusTarget.code}/status`,
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

    const directionLabel = (direction: TextDirection) =>
        direction === 'RTL' ? t('Right to left') : t('Left to right');

    const statusBadge = (locale: LocaleRow) => (
        <Badge variant={locale.is_active ? 'default' : 'secondary'}>
            {locale.is_active ? t('Active') : t('Inactive')}
        </Badge>
    );

    const sortHeader = (column: LocaleSort, label: string) => {
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
                    onClick={() =>
                        visit({
                            sort: column,
                            dir:
                                active && filters.dir === 'asc'
                                    ? 'desc'
                                    : 'asc',
                        })
                    }
                    className="inline-flex items-center gap-1 hover:text-foreground"
                    title={t('Sort by :column', { column: label })}
                >
                    {label}
                    <Icon className="size-3.5" aria-hidden />
                </button>
            </th>
        );
    };

    const rowActions = (locale: LocaleRow) => (
        <div className="flex flex-wrap gap-2">
            <Button
                variant="outline"
                size="sm"
                onClick={() => setEditing(locale)}
            >
                <Pencil aria-hidden />
                {t('Edit')}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setStatusTarget(locale)}
            >
                {locale.is_active ? t('Deactivate') : t('Activate')}
            </Button>
        </div>
    );

    const exportQuery = new URLSearchParams(queryFor(filters)).toString();

    return (
        <>
            <Head title={t('Languages')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('Languages')} />
                    <div className="flex flex-wrap gap-2">
                        {exportsAvailable.xlsx && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`${LOCALES_URL}/export/xlsx?${exportQuery}`}
                                >
                                    <FileSpreadsheet aria-hidden />
                                    {t('Export Excel')}
                                </a>
                            </Button>
                        )}
                        {exportsAvailable.pdf && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`${LOCALES_URL}/export/pdf?${exportQuery}`}
                                >
                                    <FileText aria-hidden />
                                    {t('Export PDF')}
                                </a>
                            </Button>
                        )}
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden />
                            {t('Add language')}
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search by code or name')}
                        aria-label={t('Search by code or name')}
                        className="sm:max-w-sm"
                        maxLength={100}
                        dir="auto"
                    />
                    <Select
                        value={filters.status}
                        onValueChange={(value) =>
                            visit({ status: value as LocaleFilters['status'] })
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
                    <Select
                        value={filters.direction}
                        onValueChange={(value) =>
                            visit({
                                direction: value as LocaleFilters['direction'],
                            })
                        }
                    >
                        <SelectTrigger
                            className="sm:w-44"
                            aria-label={t('Text direction')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('All')}</SelectItem>
                            <SelectItem value="LTR">
                                {t('Left to right')}
                            </SelectItem>
                            <SelectItem value="RTL">
                                {t('Right to left')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {locales.data.length === 0 ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('No languages found.')}
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
                                        {sortHeader(
                                            'native_name',
                                            t('Native name'),
                                        )}
                                        {sortHeader(
                                            'text_direction',
                                            t('Text direction'),
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
                                    {locales.data.map((locale) => (
                                        <tr
                                            key={locale.code}
                                            className="border-t"
                                        >
                                            <td
                                                className="px-3 py-2 font-mono"
                                                dir="ltr"
                                            >
                                                {locale.code}
                                            </td>
                                            <td className="px-3 py-2" dir="ltr">
                                                {locale.english_name}
                                            </td>
                                            <td className="px-3 py-2">
                                                <bdi>
                                                    {locale.native_name ?? '—'}
                                                </bdi>
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge variant="secondary">
                                                    {directionLabel(
                                                        locale.text_direction,
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2">
                                                {statusBadge(locale)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {rowActions(locale)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="flex flex-col gap-3 md:hidden">
                            {locales.data.map((locale) => (
                                <li
                                    key={locale.code}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span
                                            className="font-mono font-medium"
                                            dir="ltr"
                                        >
                                            {locale.code}
                                        </span>
                                        {statusBadge(locale)}
                                    </div>
                                    <p className="mt-1 text-sm" dir="ltr">
                                        {locale.english_name}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        <bdi>{locale.native_name ?? '—'}</bdi> ·{' '}
                                        {directionLabel(locale.text_direction)}
                                    </p>
                                    <div className="mt-3">
                                        {rowActions(locale)}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground" aria-live="polite">
                        {locales.total > 0 &&
                            t('Showing :from–:to of :total', {
                                from: locales.from ?? 0,
                                to: locales.to ?? 0,
                                total: locales.total,
                            })}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <Label
                            htmlFor="locales-per-page"
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
                                id="locales-per-page"
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
                            href={locales.prev_page_url}
                            label={t('Previous')}
                        />
                        <span className="text-muted-foreground">
                            {t('Page :current of :last', {
                                current: locales.current_page,
                                last: locales.last_page,
                            })}
                        </span>
                        <PageLink
                            href={locales.next_page_url}
                            label={t('Next')}
                        />
                    </div>
                </div>
            </div>

            <LocaleFormDialog
                key={creating ? 'create-open' : 'create-closed'}
                catalog={catalog}
                calendars={calendars}
                numberingSystems={numberingSystems}
                existing={locales.data.map((locale) => locale.code)}
                open={creating}
                onOpenChange={setCreating}
            />
            <LocaleFormDialog
                key={editing?.code ?? 'edit-closed'}
                catalog={catalog}
                calendars={calendars}
                numberingSystems={numberingSystems}
                existing={[]}
                locale={editing}
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
