import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import {
    DeletePermissionDialog,
    PermissionDefinitionDialog,
} from '@/components/permissions/permission-definition-dialog';
import type { Sensitivity } from '@/components/permissions/permission-definition-dialog';
import { PermissionTranslations } from '@/components/permissions/permission-translations';
import type {
    PermissionLocale,
    PermissionTranslation,
} from '@/components/permissions/permission-translations';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import type { Paginated } from '@/types/currency';

const url = '/platform/security/permissions';

type Permission = {
    id: number;
    code: string;
    description: string;
    sensitivity: Sensitivity;
    translations: PermissionTranslation[];
};
type Filters = {
    q: string;
    sensitivity: Sensitivity | 'all';
    sort: 'code' | 'sensitivity';
    direction: 'asc' | 'desc';
    locale: string;
    per_page: number;
};
type Props = {
    permissions: Paginated<Permission>;
    /** Translation languages; the shared `locales` prop belongs to the interface language switcher. */
    languageOptions: PermissionLocale[];
    localized: Record<number, { display_name: string; description: string }>;
    canMaintainTranslations: boolean;
    canManageDefinitions: boolean;
    editablePermissionIds: number[];
    filters: Filters;
    sensitivities: { value: Sensitivity; label: string }[];
};

const PER_PAGE = [10, 25, 50, 100];

export default function PermissionsIndex({
    permissions,
    languageOptions,
    localized,
    canMaintainTranslations,
    canManageDefinitions,
    editablePermissionIds,
    filters,
    sensitivities,
}: Props) {
    const { t } = useTrans();
    const [search, setSearch] = useState(filters.q);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [editor, setEditor] = useState<Permission | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Permission | null>(null);
    const selected = permissions.data.find(({ id }) => id === selectedId);
    const title = t('Permissions');
    const security = t('Security');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: security, href: url },
                { title, href: url },
            ],
        });
    }, [security, title]);

    const visit = (changes: Partial<Filters>) =>
        router.get(
            url,
            { ...filters, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // Search as you type, like the Master Data lists.
    useEffect(() => {
        if (search.trim() === filters.q) {
            return;
        }

        const timer = window.setTimeout(() => visit({ q: search.trim() }), 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const sensitivityLabel = (value: Sensitivity) =>
        sensitivities.find((item) => item.value === value)?.label ?? value;

    return (
        <div className="space-y-4 p-4">
            <Head title={title} />
            <div className="flex flex-wrap justify-between gap-3">
                <Heading title={title} />
                {canManageDefinitions && (
                    <Button onClick={() => setEditor('new')}>
                        {t('Add permission')}
                    </Button>
                )}
            </div>
            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <Input
                    type="search"
                    dir="auto"
                    maxLength={120}
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    aria-label={t('Search by code, name, or description')}
                    placeholder={t('Search by code, name, or description')}
                    className="sm:max-w-xs"
                />
                <Select
                    value={filters.sensitivity}
                    onValueChange={(value) =>
                        visit({ sensitivity: value as Filters['sensitivity'] })
                    }
                >
                    <SelectTrigger
                        className="sm:w-40"
                        aria-label={t('Sensitivity')}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{t('All')}</SelectItem>
                        {sensitivities.map(({ value, label }) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select
                    value={filters.locale === '' ? 'all' : filters.locale}
                    onValueChange={(value) =>
                        visit({ locale: value === 'all' ? '' : value })
                    }
                >
                    <SelectTrigger
                        className="sm:w-56"
                        aria-label={t('Language')}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">
                            {t('All languages')}
                        </SelectItem>
                        {languageOptions
                            .filter((locale) => locale.is_active)
                            .map((locale) => (
                                <SelectItem
                                    key={locale.code}
                                    value={locale.code}
                                >
                                    <bdi>
                                        {locale.native_name ||
                                            locale.english_name}
                                    </bdi>{' '}
                                    (<bdi dir="ltr">{locale.code}</bdi>)
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-muted-foreground">
                        <tr>
                            {(
                                [
                                    ['code', t('Code')],
                                    ['sensitivity', t('Sensitivity')],
                                ] as const
                            ).map(([column, label]) => (
                                <th
                                    key={column}
                                    scope="col"
                                    className="px-3 py-2 text-start"
                                    aria-sort={
                                        filters.sort !== column
                                            ? 'none'
                                            : filters.direction === 'asc'
                                              ? 'ascending'
                                              : 'descending'
                                    }
                                >
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 whitespace-nowrap hover:text-foreground"
                                        title={t('Sort by :column', {
                                            column: label,
                                        })}
                                        onClick={() =>
                                            visit({
                                                sort: column,
                                                direction:
                                                    filters.sort === column &&
                                                    filters.direction === 'asc'
                                                        ? 'desc'
                                                        : 'asc',
                                            })
                                        }
                                    >
                                        {label}
                                        <SortIcon
                                            active={filters.sort === column}
                                            direction={filters.direction}
                                        />
                                    </button>
                                </th>
                            ))}
                            <th scope="col" className="px-3 py-2 text-start">
                                {t('Description')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {permissions.data.map((permission) => (
                            <tr key={permission.id} className="border-t">
                                <td className="px-3 py-2">
                                    <button
                                        type="button"
                                        className="font-mono underline underline-offset-2"
                                        onClick={() =>
                                            setSelectedId(permission.id)
                                        }
                                    >
                                        <bdi dir="ltr">{permission.code}</bdi>
                                    </button>
                                    {localized[permission.id]?.display_name !==
                                        permission.code && (
                                        <span className="block">
                                            <bdi>
                                                {
                                                    localized[permission.id]
                                                        ?.display_name
                                                }
                                            </bdi>
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    {sensitivityLabel(permission.sensitivity)}
                                </td>
                                <td className="px-3 py-2">
                                    <bdi>
                                        {localized[permission.id]
                                            ?.description ??
                                            permission.description}
                                    </bdi>
                                </td>
                            </tr>
                        ))}
                        {permissions.data.length === 0 && (
                            <tr>
                                <td colSpan={3} className="p-6 text-center">
                                    {t('No permissions found.')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p className="text-muted-foreground" aria-live="polite">
                    {permissions.total > 0 &&
                        t('Showing :from–:to of :total', {
                            from: permissions.from ?? 0,
                            to: permissions.to ?? 0,
                            total: permissions.total,
                        })}
                </p>
                <nav
                    aria-label={t('Pagination')}
                    className="flex flex-wrap items-center gap-2"
                >
                    <Select
                        value={String(filters.per_page)}
                        onValueChange={(value) =>
                            visit({ per_page: Number(value) })
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            className="w-20"
                            aria-label={t('Rows per page')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PER_PAGE.map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <PageLink
                        href={permissions.prev_page_url}
                        label={t('Previous')}
                    />
                    <span className="text-muted-foreground">
                        {t('Page :current of :last', {
                            current: permissions.current_page,
                            last: permissions.last_page,
                        })}
                    </span>
                    <PageLink
                        href={permissions.next_page_url}
                        label={t('Next')}
                    />
                </nav>
            </div>

            <Dialog
                open={selected !== undefined}
                onOpenChange={(open) => !open && setSelectedId(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            <bdi dir="ltr">{selected?.code}</bdi>
                        </DialogTitle>
                        <DialogDescription>
                            <bdi>
                                {selected &&
                                    (localized[selected.id]?.description ??
                                        selected.description)}
                            </bdi>
                        </DialogDescription>
                    </DialogHeader>
                    {selected && (
                        <dl className="space-y-1 text-sm">
                            <dt className="font-medium">{t('Sensitivity')}</dt>
                            <dd>{sensitivityLabel(selected.sensitivity)}</dd>
                        </dl>
                    )}
                    {selected &&
                        editablePermissionIds.includes(selected.id) && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setSelectedId(null);
                                        setEditor(selected);
                                    }}
                                >
                                    {t('Edit')}
                                </Button>
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        setSelectedId(null);
                                        setDeleting(selected);
                                    }}
                                >
                                    {t('Delete')}
                                </Button>
                            </div>
                        )}
                    {selected && (
                        <PermissionTranslations
                            key={selected.id}
                            locales={languageOptions}
                            translations={selected.translations}
                            endpoint={
                                canMaintainTranslations
                                    ? `${url}/${selected.id}/translations`
                                    : undefined
                            }
                            onSuccess={() => setSelectedId(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
            {editor && (
                <PermissionDefinitionDialog
                    key={editor === 'new' ? 'new' : editor.id}
                    url={url}
                    permission={editor === 'new' ? null : editor}
                    sensitivities={sensitivities}
                    close={() => setEditor(null)}
                />
            )}
            {deleting && (
                <DeletePermissionDialog
                    url={url}
                    permission={deleting}
                    close={() => setDeleting(null)}
                />
            )}
        </div>
    );
}

function SortIcon({
    active,
    direction,
}: {
    active: boolean;
    direction: 'asc' | 'desc';
}) {
    const Icon = !active
        ? ArrowUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return <Icon className="size-3.5" aria-hidden />;
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    return href ? (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll preserveState>
                {label}
            </Link>
        </Button>
    ) : (
        <Button variant="outline" size="sm" disabled>
            {label}
        </Button>
    );
}
