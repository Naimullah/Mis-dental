import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
import { Separator } from '@/components/ui/separator';
import { useTrans } from '@/lib/i18n';
import type {
    AreaRow,
    ParentOption,
    TranslationLocale,
} from '@/types/administrative-area';

export const AREAS_URL = '/platform/master-data/administrative-areas';

type AreaForm = {
    country_code: string;
    parent_id: number | null;
    level_code: string;
    area_code: string;
    canonical_name: string;
    is_active: boolean;
};

/** Same-country parent search; the server re-checks country, cycles and depth. */
function ParentPicker({
    country,
    excludeId,
    value,
    label,
    onChange,
}: {
    country: string;
    excludeId: number | null;
    value: number | null;
    label: string;
    onChange: (id: number | null, label: string) => void;
}) {
    const { t } = useTrans();
    const [term, setTerm] = useState('');
    const [options, setOptions] = useState<ParentOption[]>([]);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            const params = new URLSearchParams({ country, q: term });
            if (excludeId !== null) {
                params.set('exclude', String(excludeId));
            }

            fetch(`${AREAS_URL}/parents?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : []))
                .then((data: ParentOption[]) => setOptions(data))
                .catch(() => undefined);
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [country, excludeId, term, open]);

    return (
        <div className="grid gap-2">
            <Label htmlFor="area-parent-search">{t('Parent area')}</Label>
            <p className="text-sm">
                <bdi>{value === null ? t('Top level (no parent)') : label}</bdi>
            </p>
            <div className="flex gap-2">
                <Input
                    id="area-parent-search"
                    dir="auto"
                    value={term}
                    placeholder={t('Search areas in this country')}
                    aria-describedby="area-parent-hint"
                    onFocus={() => setOpen(true)}
                    onChange={(event) => {
                        setOpen(true);
                        setTerm(event.target.value);
                    }}
                />
                {value !== null && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onChange(null, '')}
                    >
                        {t('Top level')}
                    </Button>
                )}
            </div>
            <p id="area-parent-hint" className="text-xs text-muted-foreground">
                {t(
                    'Leave empty for a top-level area such as a province. To place it under another area, search for that area and choose it.',
                )}
            </p>
            {open && term.trim() !== '' && options.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    {t('No matching areas in this country.')}
                </p>
            )}
            {open && options.length > 0 && (
                <ul
                    className="max-h-48 overflow-y-auto rounded-md border text-sm"
                    role="listbox"
                >
                    {options.map((option) => (
                        <li key={option.id}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={option.id === value}
                                className="flex w-full items-center justify-between gap-2 px-3 py-2 text-start hover:bg-muted"
                                onClick={() => {
                                    onChange(option.id, option.path);
                                    setOpen(false);
                                    setTerm('');
                                }}
                            >
                                <bdi>{option.path}</bdi>
                                <span
                                    className="text-xs text-muted-foreground"
                                    dir="ltr"
                                >
                                    {option.level_code}
                                    {!option.is_active && ` · ${t('Inactive')}`}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function AreaFormDialog({
    country,
    area = null,
    defaultParent,
    levels,
    translationLocales,
    open,
    onOpenChange,
}: {
    country: string;
    area?: AreaRow | null;
    defaultParent: { id: number | null; label: string };
    levels: string[];
    translationLocales: TranslationLocale[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const isEdit = area !== null;
    const [parentLabel, setParentLabel] = useState(
        isEdit ? (area.parent_path ?? '') : defaultParent.label,
    );

    const form = useForm<AreaForm>({
        country_code: country,
        parent_id: isEdit ? area.parent_id : defaultParent.id,
        level_code: area?.level_code ?? '',
        area_code: area?.area_code ?? '',
        canonical_name: area?.canonical_name ?? '',
        is_active: area?.is_active ?? true,
    });

    const translations = useForm<{ translations: Record<string, string> }>({
        translations: Object.fromEntries(
            translationLocales.map((locale) => [
                locale.code,
                area?.translations[locale.code] ?? '',
            ]),
        ),
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit) {
            form.transform((data) => ({
                parent_id: data.parent_id,
                level_code: data.level_code,
                area_code: data.area_code.trim() === '' ? null : data.area_code,
                canonical_name: data.canonical_name,
            }));
            form.put(`${AREAS_URL}/${area.id}`, options);
        } else {
            form.transform((data) => ({
                ...data,
                area_code: data.area_code.trim() === '' ? null : data.area_code,
            }));
            form.post(AREAS_URL, options);
        }
    };

    const saveTranslations = (event: FormEvent) => {
        event.preventDefault();

        if (isEdit) {
            translations.put(`${AREAS_URL}/${area.id}/translations`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit ? t('Edit area') : t('Add area')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Country')}: <bdi dir="ltr">{country}</bdi>
                        </DialogDescription>
                    </DialogHeader>

                    <ParentPicker
                        country={country}
                        excludeId={area?.id ?? null}
                        value={form.data.parent_id}
                        label={parentLabel}
                        onChange={(id, label) => {
                            form.setData('parent_id', id);
                            setParentLabel(label);
                        }}
                    />
                    <InputError message={form.errors.parent_id} />

                    <div className="grid gap-2">
                        <Label htmlFor="area-canonical-name">
                            {t('Canonical name')}
                        </Label>
                        <Input
                            id="area-canonical-name"
                            dir="auto"
                            value={form.data.canonical_name}
                            onChange={(event) =>
                                form.setData(
                                    'canonical_name',
                                    event.target.value,
                                )
                            }
                            maxLength={160}
                            placeholder="Kabul"
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('The official name of this one area.')}
                        </p>
                        <InputError message={form.errors.canonical_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="area-level">{t('Level')}</Label>
                        <Input
                            id="area-level"
                            dir="ltr"
                            list="area-level-options"
                            value={form.data.level_code}
                            onChange={(event) =>
                                form.setData(
                                    'level_code',
                                    event.target.value.toLowerCase(),
                                )
                            }
                            maxLength={40}
                            placeholder="province"
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'The type of area, as one lowercase word: province, district, city, village…',
                            )}
                        </p>
                        <datalist id="area-level-options">
                            {levels.map((level) => (
                                <option key={level} value={level} />
                            ))}
                        </datalist>
                        <InputError message={form.errors.level_code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="area-code">{t('Area code')}</Label>
                        <Input
                            id="area-code"
                            dir="ltr"
                            className="font-mono"
                            value={form.data.area_code}
                            onChange={(event) =>
                                form.setData('area_code', event.target.value)
                            }
                            maxLength={50}
                            placeholder="KBL"
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('Optional. Unique within the country.')}
                        </p>
                        <InputError message={form.errors.area_code} />
                    </div>

                    {!isEdit && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="area-active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="area-active">{t('Active')}</Label>
                        </div>
                    )}
                    <InputError
                        message={
                            form.errors.is_active ?? form.errors.country_code
                        }
                    />

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

                {isEdit && (
                    <form
                        onSubmit={saveTranslations}
                        className="flex flex-col gap-3"
                    >
                        <Separator />
                        <div>
                            <h3 className="text-sm font-medium">
                                {t('Translations')}
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                {t('Leave blank to show the canonical name.')}
                            </p>
                        </div>
                        {translationLocales.map((locale) => (
                            <div key={locale.code} className="grid gap-1">
                                <Label
                                    htmlFor={`area-translation-${locale.code}`}
                                >
                                    <bdi dir="ltr" className="font-mono">
                                        {locale.code}
                                    </bdi>{' '}
                                    <bdi>
                                        {locale.native_name ??
                                            locale.english_name}
                                    </bdi>
                                </Label>
                                <Input
                                    id={`area-translation-${locale.code}`}
                                    dir={
                                        locale.text_direction === 'RTL'
                                            ? 'rtl'
                                            : 'ltr'
                                    }
                                    lang={locale.code}
                                    value={
                                        translations.data.translations[
                                            locale.code
                                        ] ?? ''
                                    }
                                    placeholder={area.canonical_name}
                                    maxLength={180}
                                    onChange={(event) =>
                                        translations.setData('translations', {
                                            ...translations.data.translations,
                                            [locale.code]: event.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    message={
                                        (
                                            translations.errors as Record<
                                                string,
                                                string
                                            >
                                        )[`translations.${locale.code}`]
                                    }
                                />
                            </div>
                        ))}
                        <InputError
                            message={
                                (translations.errors as Record<string, string>)
                                    .translations
                            }
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={translations.processing}
                        >
                            {t('Save translations')}
                        </Button>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
