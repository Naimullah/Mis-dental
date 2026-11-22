import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { useTrans } from '@/lib/i18n';
import type { Paginated } from '@/types/currency';

const url = '/platform/master-data/public-holidays';
const selectClass = 'h-9 w-full rounded-md border bg-background px-2 text-sm';

type Status = 'active' | 'inactive';
type Holiday = {
    id: number;
    administrative_area_id: number | null;
    administrative_area: { id: number; canonical_name: string } | null;
    holiday_date: string;
    name: string;
    status: Status;
};
type Area = { id: number; canonical_name: string; level_code: string };
type Filters = {
    country: string;
    q: string;
    status: 'all' | Status;
    sort: 'holiday_date' | 'name' | 'status';
    dir: 'asc' | 'desc';
    per_page: number;
};
type Props = {
    filters: Filters;
    countries: { iso2: string; english_name: string }[];
    areas: Area[];
    holidays: Paginated<Holiday> | null;
    exportsAvailable: { pdf: boolean; xlsx: boolean };
};

export default function PublicHolidays({
    filters,
    countries,
    areas,
    holidays,
    exportsAvailable,
}: Props) {
    const { t } = useTrans();
    const [editor, setEditor] = useState<Holiday | 'new' | null>(null);
    const [statusTarget, setStatusTarget] = useState<Holiday | null>(null);
    const statusForm = useForm<{ status: Status }>({ status: 'active' });
    const title = t('Public holidays');
    const masterData = t('Master Data');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: masterData, href: url },
                { title, href: url },
            ],
        });
    }, [masterData, title]);

    const visit = (changes: Partial<Filters>) =>
        router.get(url, { ...filters, ...changes }, { preserveScroll: true });

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const query = new FormData(event.currentTarget).get('q');
        visit({ q: typeof query === 'string' ? query : '' });
    };

    const exportQuery = new URLSearchParams({
        ...filters,
        per_page: String(filters.per_page),
    }).toString();
    const columns = [
        ['holiday_date', t('Holiday date')],
        ['name', t('Name')],
        ['status', t('Status')],
    ] as const;

    return (
        <div className="space-y-4 p-4">
            <Head title={title} />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading title={title} />
                {holidays && (
                    <div className="flex flex-wrap gap-2">
                        {exportsAvailable.xlsx && (
                            <Button variant="outline" asChild>
                                <a href={`${url}/export/xlsx?${exportQuery}`}>
                                    {t('Export Excel')}
                                </a>
                            </Button>
                        )}
                        {exportsAvailable.pdf && (
                            <Button variant="outline" asChild>
                                <a href={`${url}/export/pdf?${exportQuery}`}>
                                    {t('Export PDF')}
                                </a>
                            </Button>
                        )}
                        <Button onClick={() => setEditor('new')}>
                            {t('Add public holiday')}
                        </Button>
                    </div>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <select
                    className={`${selectClass} sm:w-64`}
                    aria-label={t('Country')}
                    value={holidays ? filters.country : ''}
                    onChange={(event) =>
                        visit({
                            country: event.target.value,
                            q: '',
                            status: 'all',
                        })
                    }
                >
                    <option value="">{t('Choose a country')}</option>
                    {countries.map((country) => (
                        <option
                            key={country.iso2}
                            value={country.iso2}
                            dir="ltr"
                        >
                            {country.iso2} — {country.english_name}
                        </option>
                    ))}
                </select>
                {holidays && (
                    <>
                        <form onSubmit={search} className="flex gap-2">
                            <Input
                                key={`${filters.country}:${filters.q}`}
                                name="q"
                                type="search"
                                dir="auto"
                                aria-label={t('Search by name')}
                                placeholder={t('Search by name')}
                                defaultValue={filters.q}
                                maxLength={100}
                            />
                            <Button type="submit" variant="outline">
                                {t('Search')}
                            </Button>
                        </form>
                        <select
                            className={`${selectClass} sm:w-36`}
                            aria-label={t('Status')}
                            value={filters.status}
                            onChange={(event) =>
                                visit({
                                    status: event.target
                                        .value as Filters['status'],
                                })
                            }
                        >
                            <option value="all">{t('All')}</option>
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </select>
                    </>
                )}
            </div>
            {holidays ? (
                <>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    {columns.map(([column, label]) => (
                                        <th
                                            key={column}
                                            scope="col"
                                            className="px-3 py-2 text-start"
                                            aria-sort={
                                                filters.sort === column
                                                    ? filters.dir === 'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : 'none'
                                            }
                                        >
                                            <button
                                                type="button"
                                                className="whitespace-nowrap hover:underline"
                                                onClick={() =>
                                                    visit({
                                                        sort: column,
                                                        dir:
                                                            filters.sort ===
                                                                column &&
                                                            filters.dir ===
                                                                'asc'
                                                                ? 'desc'
                                                                : 'asc',
                                                    })
                                                }
                                            >
                                                {label}{' '}
                                                {filters.sort === column &&
                                                    (filters.dir === 'asc'
                                                        ? '↑'
                                                        : '↓')}
                                            </button>
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-start"
                                    >
                                        {t('Administrative area')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-start"
                                    >
                                        {t('Actions')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {holidays.data.map((holiday) => (
                                    <tr key={holiday.id} className="border-t">
                                        <td className="px-3 py-2">
                                            <bdi dir="ltr">
                                                {holiday.holiday_date}
                                            </bdi>
                                        </td>
                                        <td className="px-3 py-2">
                                            <bdi>{holiday.name}</bdi>
                                        </td>
                                        <td className="px-3 py-2">
                                            {t(
                                                holiday.status === 'active'
                                                    ? 'Active'
                                                    : 'Inactive',
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <bdi>
                                                {holiday.administrative_area
                                                    ?.canonical_name ??
                                                    t('Nationwide')}
                                            </bdi>
                                        </td>
                                        <td className="flex gap-2 px-3 py-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditor(holiday)
                                                }
                                            >
                                                {t('Edit')}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    statusForm.clearErrors();
                                                    statusForm.setData(
                                                        'status',
                                                        holiday.status ===
                                                            'active'
                                                            ? 'inactive'
                                                            : 'active',
                                                    );
                                                    setStatusTarget(holiday);
                                                }}
                                            >
                                                {t(
                                                    holiday.status === 'active'
                                                        ? 'Deactivate'
                                                        : 'Activate',
                                                )}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {holidays.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="p-6 text-center"
                                        >
                                            {t('No public holidays found.')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <PageLink
                            href={holidays.prev_page_url}
                            label={t('Previous')}
                        />
                        <span className="text-sm">
                            {t('Page :current of :last', {
                                current: holidays.current_page,
                                last: holidays.last_page,
                            })}
                        </span>
                        <PageLink
                            href={holidays.next_page_url}
                            label={t('Next')}
                        />
                    </div>
                </>
            ) : (
                <p className="rounded-lg border p-8 text-center text-muted-foreground">
                    {t('Choose a country to see its public holidays.')}
                </p>
            )}
            {editor && (
                <HolidayForm
                    key={`${filters.country}:${editor === 'new' ? 'new' : editor.id}`}
                    country={filters.country}
                    areas={areas}
                    holiday={editor === 'new' ? null : editor}
                    close={() => setEditor(null)}
                />
            )}
            <Dialog
                open={statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                statusForm.data.status === 'active'
                                    ? 'Activate'
                                    : 'Deactivate',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            <bdi>{statusTarget?.name}</bdi>
                        </DialogDescription>
                    </DialogHeader>
                    <InputError message={statusForm.errors.status} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setStatusTarget(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            disabled={statusForm.processing || !statusTarget}
                            onClick={() => {
                                if (statusTarget) {
                                    statusForm.patch(
                                        `${url}/${statusTarget.id}/status`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                setStatusTarget(null),
                                        },
                                    );
                                }
                            }}
                        >
                            {t('Confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function HolidayForm({
    country,
    areas,
    holiday,
    close,
}: {
    country: string;
    areas: Area[];
    holiday: Holiday | null;
    close: () => void;
}) {
    const { t } = useTrans();
    const form = useForm({
        country_code: country,
        administrative_area_id:
            holiday?.administrative_area_id ?? (null as number | null),
        holiday_date: holiday?.holiday_date ?? '',
        name: holiday?.name ?? '',
        status: holiday?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };

        if (holiday) {
            form.put(`${url}/${holiday.id}`, options);
        } else {
            form.post(url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            holiday
                                ? 'Edit public holiday'
                                : 'Add public holiday',
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        <bdi dir="ltr">{country}</bdi>
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <InputError message={form.errors.country_code} />
                    <label className="block space-y-1 text-sm">
                        <span>{t('Name')}</span>
                        <Input
                            required
                            maxLength={180}
                            dir="auto"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </label>
                    <label className="block space-y-1 text-sm">
                        <span>{t('Holiday date')}</span>
                        <Input
                            required
                            type="date"
                            dir="ltr"
                            value={form.data.holiday_date}
                            onChange={(event) =>
                                form.setData('holiday_date', event.target.value)
                            }
                        />
                        <InputError message={form.errors.holiday_date} />
                    </label>
                    <label className="block space-y-1 text-sm">
                        <span>{t('Administrative area')}</span>
                        <select
                            className={selectClass}
                            value={form.data.administrative_area_id ?? ''}
                            onChange={(event) =>
                                form.setData(
                                    'administrative_area_id',
                                    event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                )
                            }
                        >
                            <option value="">{t('Nationwide')}</option>
                            {areas.map((area) => (
                                <option
                                    key={area.id}
                                    value={area.id}
                                    dir="auto"
                                >
                                    {area.canonical_name} ({area.level_code})
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={form.errors.administrative_area_id}
                        />
                    </label>
                    <label className="block space-y-1 text-sm">
                        <span>{t('Status')}</span>
                        <select
                            className={selectClass}
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData(
                                    'status',
                                    event.target.value as Status,
                                )
                            }
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Inactive')}</option>
                        </select>
                        <InputError message={form.errors.status} />
                    </label>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            {t('Cancel')}
                        </Button>
                        <Button disabled={form.processing}>{t('Save')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    return href ? (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    ) : (
        <Button variant="outline" size="sm" disabled>
            {label}
        </Button>
    );
}
