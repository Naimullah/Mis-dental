import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
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

const url = '/platform/security/users';
const selectClass = 'h-9 rounded-md border bg-background px-2 text-sm';
type Account = {
    id: number;
    username: string;
    name: string;
    email: string;
    scope: 'platform';
    is_active: boolean;
    locale_code: string | null;
    last_login_at: string | null;
    email_verified_at: string | null;
    created_at: string | null;
};
type Locale = {
    code: string;
    native_name: string | null;
    english_name: string;
};
type Sort = 'name' | 'email' | 'is_active' | 'last_login_at';
type Filters = {
    q: string;
    sort: Sort;
    status: 'all' | 'active' | 'inactive';
    direction: 'asc' | 'desc';
};

export default function PlatformUsers({
    accounts,
    filters,
    languageOptions,
}: {
    accounts: Paginated<Account>;
    filters: Filters;
    /** Not named `locales`: that shared prop feeds the interface language switcher. */
    languageOptions: Locale[];
}) {
    const { t, locale } = useTrans();
    const [selected, setSelected] = useState<Account | null>(null);
    const [editor, setEditor] = useState<Account | 'new' | null>(null);
    const [statusTarget, setStatusTarget] = useState<Account | null>(null);
    const date = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(locale, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              }).format(new Date(value))
            : t('Never');
    const status = (account: Account) =>
        t(account.is_active ? 'Active' : 'Inactive');
    const languageName = (code: string | null) => {
        if (code === null) {
            return t('Default');
        }

        const option = languageOptions.find((item) => item.code === code);

        return option ? option.native_name || option.english_name : code;
    };
    const visit = (changes: Partial<Filters>) =>
        router.get(url, { ...filters, ...changes }, { preserveScroll: true });
    const columns: [Sort, string][] = [
        ['name', t('Name')],
        ['email', t('Email')],
        ['is_active', t('Status')],
        ['last_login_at', t('Last login')],
    ];

    return (
        <div className="space-y-4 p-4">
            <Head title={t('Users')} />
            <div className="flex flex-wrap justify-between gap-3">
                <Heading title={t('Platform users')} />
                <Button onClick={() => setEditor('new')}>
                    {t('Add user')}
                </Button>
            </div>
            <div className="flex flex-wrap gap-2">
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const query = new FormData(event.currentTarget).get(
                            'q',
                        );
                        visit({ q: typeof query === 'string' ? query : '' });
                    }}
                >
                    <Input
                        key={filters.q}
                        dir="auto"
                        type="search"
                        name="q"
                        defaultValue={filters.q}
                        maxLength={120}
                        aria-label={t('Search by username, name, or email')}
                        placeholder={t('Search by username, name, or email')}
                    />
                    <Button type="submit" variant="outline">
                        {t('Search')}
                    </Button>
                </form>
                <select
                    className={selectClass}
                    value={filters.status}
                    aria-label={t('Status')}
                    onChange={(event) =>
                        visit({
                            status: event.target.value as Filters['status'],
                        })
                    }
                >
                    <option value="all">{t('All')}</option>
                    <option value="active">{t('Active')}</option>
                    <option value="inactive">{t('Inactive')}</option>
                </select>
            </div>
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
                                        column !== filters.sort
                                            ? 'none'
                                            : filters.direction === 'asc'
                                              ? 'ascending'
                                              : 'descending'
                                    }
                                >
                                    <button
                                        type="button"
                                        className="hover:underline"
                                        onClick={() =>
                                            visit({
                                                sort: column,
                                                direction:
                                                    column === filters.sort &&
                                                    filters.direction === 'asc'
                                                        ? 'desc'
                                                        : 'asc',
                                            })
                                        }
                                    >
                                        {label}{' '}
                                        {column === filters.sort &&
                                            (filters.direction === 'asc'
                                                ? '↑'
                                                : '↓')}
                                    </button>
                                </th>
                            ))}
                            <th scope="col" className="px-3 py-2 text-start">
                                {t('Actions')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {accounts.data.map((account) => (
                            <tr key={account.id} className="border-t">
                                <td className="px-3 py-2">
                                    <button
                                        type="button"
                                        className="underline"
                                        onClick={() => setSelected(account)}
                                    >
                                        <bdi>{account.name}</bdi>
                                    </button>
                                    <span className="block text-muted-foreground">
                                        <bdi dir="ltr">{account.username}</bdi>
                                    </span>
                                </td>
                                <td className="px-3 py-2">
                                    <bdi dir="ltr">{account.email}</bdi>
                                </td>
                                <td className="px-3 py-2">{status(account)}</td>
                                <td className="px-3 py-2">
                                    {date(account.last_login_at)}
                                </td>
                                <td className="px-3 py-2">
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setSelected(account)}
                                        >
                                            {t('View')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setEditor(account)}
                                        >
                                            {t('Edit')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                setStatusTarget(account)
                                            }
                                        >
                                            {t(
                                                account.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                            )}
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {accounts.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="p-6 text-center">
                                    {t('No users found.')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {accounts.last_page > 1 && (
                <nav
                    className="flex flex-wrap items-center gap-2"
                    aria-label={t('Pagination')}
                >
                    {accounts.prev_page_url && (
                        <Button asChild variant="outline">
                            <Link href={accounts.prev_page_url}>
                                {t('Previous')}
                            </Link>
                        </Button>
                    )}
                    <span className="text-sm">
                        {t('Page :current of :last', {
                            current: accounts.current_page,
                            last: accounts.last_page,
                        })}
                    </span>
                    {accounts.next_page_url && (
                        <Button asChild variant="outline">
                            <Link href={accounts.next_page_url}>
                                {t('Next')}
                            </Link>
                        </Button>
                    )}
                </nav>
            )}
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            <bdi>{selected?.name}</bdi>
                        </DialogTitle>
                        <DialogDescription>
                            <bdi dir="ltr">{selected?.email}</bdi>
                        </DialogDescription>
                    </DialogHeader>
                    {selected && (
                        <dl className="grid grid-cols-2 gap-2 text-sm">
                            <dt>{t('Username')}</dt>
                            <dd>
                                <bdi dir="ltr">{selected.username}</bdi>
                            </dd>
                            <dt>{t('Status')}</dt>
                            <dd>{status(selected)}</dd>
                            <dt>{t('Last login')}</dt>
                            <dd>{date(selected.last_login_at)}</dd>
                            <dt>{t('Language')}</dt>
                            <dd>
                                <bdi>{languageName(selected.locale_code)}</bdi>
                            </dd>
                            <dt>{t('Account type')}</dt>
                            <dd>{t('Platform account')}</dd>
                            <dt>{t('Email verified')}</dt>
                            <dd>
                                {selected.email_verified_at
                                    ? date(selected.email_verified_at)
                                    : t('Not verified')}
                            </dd>
                            <dt>{t('Created')}</dt>
                            <dd>{date(selected.created_at)}</dd>
                        </dl>
                    )}
                </DialogContent>
            </Dialog>
            {editor && (
                <AccountForm
                    key={editor === 'new' ? 'new' : editor.id}
                    account={editor === 'new' ? null : editor}
                    locales={languageOptions}
                    close={() => setEditor(null)}
                />
            )}
            {statusTarget && (
                <StatusForm
                    key={statusTarget.id}
                    account={statusTarget}
                    close={() => setStatusTarget(null)}
                />
            )}
        </div>
    );
}

function AccountForm({
    account,
    locales,
    close,
}: {
    account: Account | null;
    locales: Locale[];
    close: () => void;
}) {
    const { t } = useTrans();
    const form = useForm({
        username: account?.username ?? '',
        name: account?.name ?? '',
        email: account?.email ?? '',
        scope: 'platform',
        locale_code: account?.locale_code ?? '',
        password: '',
        password_confirmation: '',
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (account) {
            form.transform(({ username, name, email, scope, locale_code }) => ({
                username,
                name,
                email,
                scope,
                ...(locale_code !== (account.locale_code ?? '')
                    ? { locale_code }
                    : {}),
            }));
            form.put(`${url}/${account.id}`, options);
        } else {
            form.post(url, options);
        }
    }
    const fields = [
        ['name', 'Name', 'text'],
        ['username', 'Username', 'text'],
        ['email', 'Email', 'email'],
        ...(!account
            ? ([
                  ['password', 'Password', 'password'],
                  ['password_confirmation', 'Confirm password', 'password'],
              ] as const)
            : []),
    ] as const;
    return (
        <Dialog
            open
            onOpenChange={(open) => !open && !form.processing && close()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(account ? 'Edit user' : 'Add user')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('Platform account')}
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-3" onSubmit={submit}>
                    {fields.map(([key, label, type]) => (
                        <label className="block space-y-1 text-sm" key={key}>
                            <span>{t(label)}</span>
                            <Input
                                required
                                type={type}
                                dir={key === 'name' ? 'auto' : 'ltr'}
                                value={form.data[key]}
                                onChange={(event) =>
                                    form.setData(key, event.target.value)
                                }
                                autoComplete={
                                    type === 'password'
                                        ? 'new-password'
                                        : undefined
                                }
                                maxLength={key === 'username' ? 64 : undefined}
                            />
                            <InputError message={form.errors[key]} />
                        </label>
                    ))}
                    <label className="block space-y-1 text-sm">
                        <span>{t('Language')}</span>
                        <select
                            className={`w-full ${selectClass}`}
                            value={form.data.locale_code}
                            onChange={(event) =>
                                form.setData('locale_code', event.target.value)
                            }
                        >
                            <option value="">{t('Default')}</option>
                            {locales.map((locale) => (
                                <option key={locale.code} value={locale.code}>
                                    {locale.native_name || locale.english_name}
                                </option>
                            ))}
                            {account?.locale_code &&
                                !locales.some(
                                    ({ code }) => code === account.locale_code,
                                ) && (
                                    <option
                                        disabled
                                        value={account.locale_code}
                                    >
                                        {account.locale_code}
                                    </option>
                                )}
                        </select>
                        <InputError message={form.errors.locale_code} />
                    </label>
                    <InputError message={form.errors.scope} />
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

function StatusForm({
    account,
    close,
}: {
    account: Account;
    close: () => void;
}) {
    const { t } = useTrans();
    const form = useForm({ is_active: !account.is_active });
    return (
        <Dialog
            open
            onOpenChange={(open) => !open && !form.processing && close()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(account.is_active ? 'Deactivate' : 'Activate')}
                    </DialogTitle>
                    <DialogDescription>
                        <bdi>{account.name}</bdi>
                    </DialogDescription>
                </DialogHeader>
                <InputError message={form.errors.is_active} />
                <DialogFooter>
                    <Button variant="outline" onClick={close}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        disabled={form.processing}
                        onClick={() =>
                            form.patch(`${url}/${account.id}/status`, {
                                preserveScroll: true,
                                onSuccess: close,
                            })
                        }
                    >
                        {t('Confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
