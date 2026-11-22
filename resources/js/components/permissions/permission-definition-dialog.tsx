import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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

export type Sensitivity = 'low' | 'medium' | 'high' | 'critical';
type Definition = {
    id: number;
    code: string;
    description: string;
    sensitivity: Sensitivity;
};

export function PermissionDefinitionDialog({
    url,
    permission,
    sensitivities,
    close,
}: {
    url: string;
    permission: Definition | null;
    sensitivities: { value: Sensitivity; label: string }[];
    close: () => void;
}) {
    const { t } = useTrans();
    const form = useForm({
        code: permission?.code ?? 'custom.',
        description: permission?.description ?? '',
        sensitivity: permission?.sensitivity ?? 'low',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { onSuccess: close, preserveScroll: true };
        if (permission) {
            form.transform(({ description, sensitivity }) => ({
                description,
                sensitivity,
            }));
            form.put(`${url}/${permission.id}`, options);
        } else {
            form.post(url, options);
        }
    }

    return (
        <Dialog
            open
            onOpenChange={(open) => !open && !form.processing && close()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(permission ? 'Edit permission' : 'Add permission')}
                    </DialogTitle>
                    <DialogDescription>
                        {permission ? (
                            <bdi dir="ltr">{permission.code}</bdi>
                        ) : (
                            t('Custom permission codes start with custom.')
                        )}
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={submit}>
                    {!permission && (
                        <label className="block space-y-1 text-sm">
                            <span>{t('Code')}</span>
                            <Input
                                required
                                dir="ltr"
                                maxLength={120}
                                value={form.data.code}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                            />
                            <InputError message={form.errors.code} />
                        </label>
                    )}
                    <label className="block space-y-1 text-sm">
                        <span>{t('Description')}</span>
                        <Input
                            required
                            dir="auto"
                            maxLength={255}
                            value={form.data.description}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </label>
                    <label className="block space-y-1 text-sm">
                        <span>{t('Sensitivity')}</span>
                        <select
                            className="h-9 w-full rounded-md border bg-background px-2"
                            value={form.data.sensitivity}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData(
                                    'sensitivity',
                                    event.target.value as Sensitivity,
                                )
                            }
                        >
                            {sensitivities.map(({ value, label }) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.sensitivity} />
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={close}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function DeletePermissionDialog({
    url,
    permission,
    close,
}: {
    url: string;
    permission: Definition;
    close: () => void;
}) {
    const { t } = useTrans();
    const form = useForm<{ permission?: string }>({});

    return (
        <Dialog
            open
            onOpenChange={(open) => !open && !form.processing && close()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Delete permission?')}</DialogTitle>
                    <DialogDescription>
                        <bdi dir="ltr">{permission.code}</bdi>
                    </DialogDescription>
                </DialogHeader>
                <InputError message={form.errors.permission} />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={close}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={form.processing}
                        onClick={() =>
                            form.delete(`${url}/${permission.id}`, {
                                onSuccess: close,
                                preserveScroll: true,
                            })
                        }
                    >
                        {t('Delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
