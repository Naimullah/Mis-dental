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
import type { TaxCodeRow } from '@/types/tax-code';

export const TAX_CODES_URL = '/platform/master-data/tax-codes';

export type TaxCodeDialogMode = 'create' | 'edit' | 'version';

type TaxCodeForm = {
    country_code: string;
    code: string;
    description: string;
    rate_percent: string;
    effective_from: string;
    effective_to: string;
};

/** Drop trailing zeros for editing: "10.50000" → "10.5". String-only, no float parsing. */
function trimRate(rate: string): string {
    return rate.includes('.') ? rate.replace(/\.?0+$/, '') : rate;
}

export function TaxCodeFormDialog({
    mode,
    country,
    taxCode = null,
    today,
    open,
    onOpenChange,
}: {
    mode: TaxCodeDialogMode;
    country: string;
    taxCode?: TaxCodeRow | null;
    today: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const isEdit = mode === 'edit';
    const isVersion = mode === 'version';

    const form = useForm<TaxCodeForm>({
        country_code: country,
        code: taxCode?.code ?? '',
        description: taxCode?.description ?? '',
        rate_percent:
            isEdit && taxCode ? trimRate(taxCode.rate_percent) : '',
        effective_from: isEdit && taxCode ? taxCode.effective_from : today,
        effective_to: isEdit && taxCode ? (taxCode.effective_to ?? '') : '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit && taxCode) {
            // Only the description can change; the rate and dates are a fixed snapshot.
            form.transform((data) => ({ description: data.description }));
            form.put(`${TAX_CODES_URL}/${taxCode.id}`, options);
        } else if (isVersion && taxCode) {
            form.transform((data) => ({
                description: data.description,
                rate_percent: data.rate_percent,
                effective_from: data.effective_from,
                effective_to: data.effective_to,
            }));
            form.post(`${TAX_CODES_URL}/${taxCode.id}/versions`, options);
        } else {
            form.transform((data) => data);
            form.post(TAX_CODES_URL, options);
        }
    };

    const title = isEdit
        ? t('Edit tax code')
        : isVersion
          ? t('New version of :code', { code: taxCode?.code ?? '' })
          : t('Add tax code');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            {t('Country')}: <bdi dir="ltr">{country}</bdi>
                        </DialogDescription>
                    </DialogHeader>

                    {isVersion && (
                        <p className="rounded-md bg-muted p-3 text-sm">
                            {t(
                                'The open version will be closed the day before the new version starts, and its translations are copied to the new version. Past versions are kept unchanged.',
                            )}
                        </p>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="tax-code-code">{t('Code')}</Label>
                        <Input
                            id="tax-code-code"
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
                            placeholder="VAT_STD"
                            disabled={mode !== 'create'}
                            required={mode === 'create'}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            {mode === 'create'
                                ? t(
                                      'Upper-case letters, digits, - or _. The same code can have several versions over time.',
                                  )
                                : t(
                                      'Country, code, rate and start date cannot be changed after creation.',
                                  )}
                        </p>
                        <InputError
                            message={
                                form.errors.code ?? form.errors.country_code
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tax-code-description">
                            {t('Description')}
                        </Label>
                        <Input
                            id="tax-code-description"
                            dir="auto"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            maxLength={160}
                            placeholder={t('Standard business receipts tax')}
                            required
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tax-code-rate">{t('Rate (%)')}</Label>
                        <Input
                            id="tax-code-rate"
                            dir="ltr"
                            inputMode="decimal"
                            className="font-mono"
                            value={form.data.rate_percent}
                            onChange={(event) =>
                                form.setData(
                                    'rate_percent',
                                    event.target.value.replace(',', '.'),
                                )
                            }
                            maxLength={9}
                            placeholder="10"
                            disabled={isEdit}
                            required={!isEdit}
                            autoComplete="off"
                        />
                        {!isEdit && (
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'From 0 to 100, at most 5 decimal places. Check the rate against the official source for this country.',
                                )}
                            </p>
                        )}
                        <InputError message={form.errors.rate_percent} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tax-code-from">
                                {t('Effective from')}
                            </Label>
                            <Input
                                id="tax-code-from"
                                type="date"
                                dir="ltr"
                                value={form.data.effective_from}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_from',
                                        event.target.value,
                                    )
                                }
                                disabled={isEdit}
                                required={!isEdit}
                            />
                            <InputError message={form.errors.effective_from} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tax-code-to">
                                {t('Effective to')}
                            </Label>
                            <Input
                                id="tax-code-to"
                                type="date"
                                dir="ltr"
                                value={form.data.effective_to}
                                min={form.data.effective_from || undefined}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_to',
                                        event.target.value,
                                    )
                                }
                                disabled={isEdit}
                            />
                            {!isEdit && (
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'Leave empty if the rate has no end date yet. Both dates are included in the period.',
                                    )}
                                </p>
                            )}
                            <InputError message={form.errors.effective_to} />
                        </div>
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
