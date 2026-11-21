import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import type { Country } from '@/types/country';

export const COUNTRIES_URL = '/platform/master-data/countries';

type CountryForm = {
    iso2: string;
    iso3: string;
    english_name: string;
    is_active: boolean;
};

export function CountryFormDialog({
    country = null,
    open,
    onOpenChange,
}: {
    country?: Country | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const isEdit = country !== null;

    const form = useForm<CountryForm>({
        iso2: country?.iso2 ?? '',
        iso3: country?.iso3 ?? '',
        english_name: country?.english_name ?? '',
        is_active: country?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        // A blank ISO3 is sent as null so it clears the code instead of failing validation.
        if (isEdit) {
            form.transform(({ iso2: _iso2, iso3, ...rest }) => ({
                ...rest,
                iso3: iso3.trim() === '' ? null : iso3,
            }));
            form.put(`${COUNTRIES_URL}/${country.iso2}`, options);
        } else {
            form.transform(({ iso3, ...rest }) => ({
                ...rest,
                iso3: iso3.trim() === '' ? null : iso3,
            }));
            form.post(COUNTRIES_URL, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit ? t('Edit country') : t('Add country')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="country-iso2">{t('ISO2')}</Label>
                        <Input
                            id="country-iso2"
                            dir="ltr"
                            className="font-mono uppercase"
                            value={form.data.iso2}
                            onChange={(event) =>
                                form.setData(
                                    'iso2',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            maxLength={2}
                            disabled={isEdit}
                            required={!isEdit}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? t(
                                      'The ISO2 code cannot be changed after creation.',
                                  )
                                : t('Two uppercase letters, e.g. AF')}
                        </p>
                        <InputError message={form.errors.iso2} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="country-iso3">{t('ISO3')}</Label>
                        <Input
                            id="country-iso3"
                            dir="ltr"
                            className="font-mono uppercase"
                            value={form.data.iso3}
                            onChange={(event) =>
                                form.setData(
                                    'iso3',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            maxLength={3}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('Optional. Three uppercase letters, e.g. AFG')}
                        </p>
                        <InputError message={form.errors.iso3} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="country-name">
                            {t('English name')}
                        </Label>
                        <Input
                            id="country-name"
                            dir="ltr"
                            value={form.data.english_name}
                            onChange={(event) =>
                                form.setData('english_name', event.target.value)
                            }
                            maxLength={120}
                            required
                        />
                        <InputError message={form.errors.english_name} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="country-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="country-active">{t('Active')}</Label>
                    </div>
                    <InputError message={form.errors.is_active} />

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
