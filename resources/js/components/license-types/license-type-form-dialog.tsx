import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type {
    AppliesTo,
    AppliesToOption,
    LicenseTypeRow,
} from '@/types/license-type';

export const LICENSE_TYPES_URL = '/platform/master-data/license-types';

type LicenseTypeForm = {
    country_code: string;
    code: string;
    name: string;
    applies_to: AppliesTo | '';
    is_active: boolean;
};

export function LicenseTypeFormDialog({
    country,
    licenseType = null,
    appliesToOptions,
    open,
    onOpenChange,
}: {
    country: string;
    licenseType?: LicenseTypeRow | null;
    appliesToOptions: AppliesToOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const isEdit = licenseType !== null;

    const form = useForm<LicenseTypeForm>({
        country_code: country,
        code: licenseType?.code ?? '',
        name: licenseType?.name ?? '',
        applies_to: licenseType?.applies_to ?? '',
        is_active: licenseType?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit) {
            // Only the name can change; country, code and applies-to are fixed at creation.
            form.transform((data) => ({ name: data.name }));
            form.put(`${LICENSE_TYPES_URL}/${licenseType.id}`, options);
        } else {
            form.transform((data) => data);
            form.post(LICENSE_TYPES_URL, options);
        }
    };

    const appliesToLabel =
        appliesToOptions.find((option) => option.value === form.data.applies_to)
            ?.label ?? '—';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit
                                ? t('Edit license type')
                                : t('Add license type')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Country')}: <bdi dir="ltr">{country}</bdi>
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="license-type-code">{t('Code')}</Label>
                        <Input
                            id="license-type-code"
                            dir="ltr"
                            className="font-mono uppercase"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData(
                                    'code',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            maxLength={40}
                            placeholder="DENTIST_LIC"
                            disabled={isEdit}
                            required={!isEdit}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? t(
                                      'Country, code and applies-to cannot be changed after creation.',
                                  )
                                : t(
                                      'Upper-case letters, digits, - or _, starting with a letter. Unique within the country.',
                                  )}
                        </p>
                        <InputError
                            message={
                                form.errors.code ?? form.errors.country_code
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="license-type-name">{t('Name')}</Label>
                        <Input
                            id="license-type-name"
                            dir="auto"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={160}
                            placeholder={t('Dental practitioner license')}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="license-type-applies-to">
                            {t('Applies to')}
                        </Label>
                        {isEdit ? (
                            <p className="text-sm text-muted-foreground">
                                {appliesToLabel}
                            </p>
                        ) : (
                            <Select
                                value={
                                    form.data.applies_to === ''
                                        ? undefined
                                        : form.data.applies_to
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'applies_to',
                                        value as AppliesTo,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="license-type-applies-to"
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'Choose who receives this license',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
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
                        )}
                        <InputError message={form.errors.applies_to} />
                    </div>

                    {!isEdit && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="license-type-active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="license-type-active">
                                {t('Active')}
                            </Label>
                        </div>
                    )}

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
