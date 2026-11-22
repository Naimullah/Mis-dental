import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { ROLES_URL, RoleFormDialog } from '@/components/roles/role-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { RoleFilters, RoleRow } from '@/types/role';

type Props = {
    roles: Paginated<RoleRow>;
    filters: RoleFilters;
};

const PER_PAGE = [10, 25, 50, 100];

export default function RolesIndex({ roles, filters }: Props) {
    const { t } = useTrans();
    const [search, setSearch] = useState(filters.q);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<RoleRow | null>(null);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('Security'), href: ROLES_URL },
                { title: t('Roles'), href: ROLES_URL },
            ],
        });
    }, [t]);

    const visit = (changes: Partial<RoleFilters>) => {
        router.get(
            ROLES_URL,
            { ...filters, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (search.trim() === filters.q) {
            return;
        }

        const timer = window.setTimeout(() => visit({ q: search.trim() }), 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const scopeLabel = (scope: RoleRow['scope']) =>
        scope === 'platform' ? t('Platform') : t('Clinic');

    const sortHeader = (column: RoleFilters['sort'], label: string) => {
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

    const kindBadge = (role: RoleRow) => (
        <Badge variant={role.is_system ? 'secondary' : 'outline'}>
            {role.is_system ? t('System') : t('Custom')}
        </Badge>
    );

    const actions = (role: RoleRow) =>
        role.is_system ? (
            <span className="text-xs text-muted-foreground">
                {t('Read-only')}
            </span>
        ) : (
            <Button variant="outline" size="sm" onClick={() => setEditing(role)}>
                <Pencil aria-hidden />
                {t('Edit')}
            </Button>
        );

    return (
        <>
            <Head title={t('Roles')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Roles')}
                        description={t(
                            'System roles come from the version-controlled catalog and cannot be changed here. Custom platform roles can be added and renamed.',
                        )}
                    />
                    <Button onClick={() => setCreating(true)}>
                        <Plus aria-hidden />
                        {t('Add role')}
                    </Button>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        type="search"
                        dir="auto"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search by code or name')}
                        aria-label={t('Search by code or name')}
                        className="sm:max-w-xs"
                        maxLength={100}
                    />
                    <Select
                        value={filters.scope}
                        onValueChange={(value) =>
                            visit({ scope: value as RoleFilters['scope'] })
                        }
                    >
                        <SelectTrigger
                            className="sm:w-40"
                            aria-label={t('Scope')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('All scopes')}</SelectItem>
                            <SelectItem value="platform">
                                {t('Platform')}
                            </SelectItem>
                            <SelectItem value="clinic">{t('Clinic')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.kind}
                        onValueChange={(value) =>
                            visit({ kind: value as RoleFilters['kind'] })
                        }
                    >
                        <SelectTrigger
                            className="sm:w-40"
                            aria-label={t('Type')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('All types')}</SelectItem>
                            <SelectItem value="system">{t('System')}</SelectItem>
                            <SelectItem value="custom">{t('Custom')}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {roles.data.length === 0 ? (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        {t('No roles found.')}
                    </p>
                ) : (
                    <>
                        <div className="hidden overflow-x-auto rounded-lg border md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        {sortHeader('name', t('Name'))}
                                        {sortHeader('code', t('Code'))}
                                        {sortHeader('scope', t('Scope'))}
                                        <th className="px-3 py-2 text-start font-medium">
                                            {t('Type')}
                                        </th>
                                        <th className="px-3 py-2 text-start font-medium">
                                            {t('Actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roles.data.map((role) => (
                                        <tr key={role.id} className="border-t">
                                            <td className="px-3 py-2">
                                                <bdi>{role.name}</bdi>
                                            </td>
                                            <td
                                                className="px-3 py-2 font-mono"
                                                dir="ltr"
                                            >
                                                {role.code}
                                            </td>
                                            <td className="px-3 py-2">
                                                {scopeLabel(role.scope)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {kindBadge(role)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {actions(role)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="flex flex-col gap-3 md:hidden">
                            {roles.data.map((role) => (
                                <li key={role.id} className="rounded-lg border p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <bdi className="font-medium">
                                            {role.name}
                                        </bdi>
                                        {kindBadge(role)}
                                    </div>
                                    <p
                                        className="mt-1 font-mono text-sm"
                                        dir="ltr"
                                    >
                                        {role.code}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {scopeLabel(role.scope)}
                                    </p>
                                    <div className="mt-3">{actions(role)}</div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <div className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground" aria-live="polite">
                        {roles.total > 0 &&
                            t('Showing :from–:to of :total', {
                                from: roles.from ?? 0,
                                to: roles.to ?? 0,
                                total: roles.total,
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
                        <PageLink href={roles.prev_page_url} label={t('Previous')} />
                        <span className="text-muted-foreground">
                            {t('Page :current of :last', {
                                current: roles.current_page,
                                last: roles.last_page,
                            })}
                        </span>
                        <PageLink href={roles.next_page_url} label={t('Next')} />
                    </nav>
                </div>
            </div>

            <RoleFormDialog
                key={creating ? 'create-open' : 'create-closed'}
                open={creating}
                onOpenChange={setCreating}
            />
            <RoleFormDialog
                key={editing ? `edit-${editing.id}` : 'edit-closed'}
                role={editing}
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            />
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
