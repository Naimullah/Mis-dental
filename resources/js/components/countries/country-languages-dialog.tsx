import { router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/lib/i18n';

export type CountryLocalesPanel = {
    country: { iso2: string; english_name: string; is_active: boolean };
    links: {
        code: string;
        english_name: string;
        text_direction: 'LTR' | 'RTL';
        is_default: boolean;
        is_active: boolean;
    }[];
    available: { code: string; english_name: string }[];
};

/** P01.04: languages used in a country (not translations of the country name). */
export function CountryLanguagesDialog({
    panel,
    canManage = false,
    onClose,
}: {
    panel: CountryLocalesPanel | null;
    canManage?: boolean;
    onClose: () => void;
}) {
    const { t } = useTrans();
    const [adding, setAdding] = useState('');
    const [addAsDefault, setAddAsDefault] = useState(false);
    const [removing, setRemoving] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const base = panel
        ? `/platform/master-data/countries/${panel.country.iso2}/locales`
        : '';
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            setBusy(true);
            setErrors({});
        },
        onError: (next: Record<string, string>) => setErrors(next),
        onFinish: () => setBusy(false),
    };

    const add = () => {
        router.post(
            base,
            { locale_code: adding, is_default: addAsDefault },
            {
                ...options,
                onSuccess: () => {
                    setAdding('');
                    setAddAsDefault(false);
                },
            },
        );
    };

    const setDefault = (code: string, isDefault: boolean) => {
        router.patch(
            `${base}/${code}/default`,
            { is_default: isDefault },
            options,
        );
    };

    const remove = (code: string) => {
        router.delete(`${base}/${code}`, {
            ...options,
            onSuccess: () => setRemoving(null),
        });
    };

    return (
        <Dialog
            open={panel !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {t('Languages for :country', {
                            country: panel?.country.iso2 ?? '',
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        <bdi dir="ltr">{panel?.country.english_name}</bdi>
                    </DialogDescription>
                </DialogHeader>

                {panel && panel.links.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No languages linked yet.')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y rounded-lg border">
                        {panel?.links.map((link) => (
                            <li
                                key={link.code}
                                className="flex flex-col gap-2 px-3 py-2"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="flex flex-col">
                                        <bdi
                                            dir="ltr"
                                            className="font-mono text-sm"
                                        >
                                            {link.code}
                                        </bdi>
                                        <bdi
                                            dir="ltr"
                                            className="text-sm text-muted-foreground"
                                        >
                                            {link.english_name}
                                        </bdi>
                                    </span>
                                    <span className="flex flex-wrap gap-2">
                                        <Badge variant="secondary">
                                            {link.text_direction === 'RTL'
                                                ? t('Right to left')
                                                : t('Left to right')}
                                        </Badge>
                                        {link.is_default && (
                                            <Badge>{t('Default')}</Badge>
                                        )}
                                        {!link.is_active && (
                                            <Badge variant="outline">
                                                {t('Inactive')}
                                            </Badge>
                                        )}
                                    </span>
                                </div>

                                {canManage && (
                                    <div className="flex flex-wrap gap-2">
                                        {link.is_default ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={busy}
                                                onClick={() =>
                                                    setDefault(link.code, false)
                                                }
                                            >
                                                {t('Remove default')}
                                            </Button>
                                        ) : (
                                            link.is_active && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        setDefault(
                                                            link.code,
                                                            true,
                                                        )
                                                    }
                                                >
                                                    {t('Make default')}
                                                </Button>
                                            )
                                        )}
                                        {removing === link.code ? (
                                            <>
                                                <span className="self-center text-sm">
                                                    {t(
                                                        'Remove :code from :country?',
                                                        {
                                                            code: link.code,
                                                            country:
                                                                panel.country
                                                                    .iso2,
                                                        },
                                                    )}
                                                </span>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="destructive"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        remove(link.code)
                                                    }
                                                >
                                                    {t('Confirm')}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setRemoving(null)
                                                    }
                                                >
                                                    {t('Cancel')}
                                                </Button>
                                            </>
                                        ) : (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                disabled={busy}
                                                onClick={() =>
                                                    setRemoving(link.code)
                                                }
                                            >
                                                {t('Remove')}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <InputError message={errors.is_default} />

                {canManage && panel && (
                    <div className="flex flex-col gap-2 rounded-lg border p-3">
                        <Label htmlFor="country-language-add">
                            {t('Add language')}
                        </Label>
                        {panel.available.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No more enabled languages to add.')}
                            </p>
                        ) : (
                            <>
                                <Select
                                    value={adding}
                                    onValueChange={setAdding}
                                >
                                    <SelectTrigger
                                        id="country-language-add"
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={t('Choose a language')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {panel.available.map((locale) => (
                                            <SelectItem
                                                key={locale.code}
                                                value={locale.code}
                                            >
                                                <bdi dir="ltr">
                                                    {locale.code} —{' '}
                                                    {locale.english_name}
                                                </bdi>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="country-language-default"
                                        checked={addAsDefault}
                                        onCheckedChange={(checked) =>
                                            setAddAsDefault(checked === true)
                                        }
                                    />
                                    <Label htmlFor="country-language-default">
                                        {t('Set as default')}
                                    </Label>
                                </div>
                                <Button
                                    type="button"
                                    disabled={busy || adding === ''}
                                    onClick={add}
                                >
                                    {t('Add')}
                                </Button>
                            </>
                        )}
                        <InputError message={errors.locale_code} />
                    </div>
                )}

                {!canManage && (
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Changes are disabled until platform permissions are set up.',
                        )}
                    </p>
                )}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('Close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
