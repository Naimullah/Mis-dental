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
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import type { RoleRow } from '@/types/role';

export const ROLES_URL = '/platform/security/roles';

/** Add or rename a custom platform role. System roles never reach this dialog. */
export function RoleFormDialog({
    role = null,
    open,
    onOpenChange,
}: {
    role?: RoleRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const isEdit = role !== null;
    const form = useForm({
        code: role?.code ?? '',
        name: role?.name ?? '',
        scope: 'platform',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit) {
            // Only the name can change; code and scope are fixed at creation.
            form.transform((data) => ({ name: data.name }));
            form.put(`${ROLES_URL}/${role.id}`, options);
        } else {
            form.transform((data) => data);
            form.post(ROLES_URL, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit ? t('Edit role') : t('Add role')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Custom platform role')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="role-code">{t('Code')}</Label>
                        <Input
                            id="role-code"
                            dir="ltr"
                            className="font-mono lowercase"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData(
                                    'code',
                                    event.target.value.toLowerCase(),
                                )
                            }
                            maxLength={100}
                            placeholder="support_desk"
                            disabled={isEdit}
                            required={!isEdit}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? t(
                                      'The code and scope cannot be changed after creation.',
                                  )
                                : t(
                                      'Lower-case letters, digits or _, starting with a letter. Cannot be changed later.',
                                  )}
                        </p>
                        <InputError
                            message={form.errors.code ?? form.errors.scope}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="role-name">{t('Name')}</Label>
                        <Input
                            id="role-name"
                            dir="auto"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={150}
                            placeholder={t('Support desk')}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

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
