import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CatalogName } from '@/components/catalog-translations-dialog';
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

type Translation = { display_name: string; help_text: string | null };

export function LocalizedTypeName({
    canonical,
    translation,
}: {
    canonical: string;
    translation?: Translation;
}) {
    return (
        <>
            <CatalogName
                canonical={canonical}
                translated={translation?.display_name}
            />
            {translation?.help_text && (
                <span className="block text-sm break-words whitespace-pre-wrap text-muted-foreground">
                    <bdi>{translation.help_text}</bdi>
                </span>
            )}
        </>
    );
}

export type TypeTranslationData = {
    locales: {
        code: string;
        english_name: string;
        native_name: string | null;
        text_direction: string;
    }[];
    translations: Record<number, Record<string, Translation>>;
    localized: Record<number, Translation>;
};

export function TypeTranslationsDialog({
    type,
    data,
    url,
    maxLength,
    helpLabel,
    helpMaxLength,
    onClose,
}: {
    type: { id: number; code: string; name: string };
    data: TypeTranslationData;
    url: string;
    maxLength: number;
    /** Label of the second field; defaults to "Help text" (tax codes use "Explanation"). */
    helpLabel?: string;
    helpMaxLength?: number;
    onClose: () => void;
}) {
    const { t } = useTrans();
    const stored = data.translations[type.id];
    const form = useForm<{ translations: Record<string, Translation> }>({
        translations: Object.fromEntries(
            data.locales.map(({ code }) => [
                code,
                {
                    display_name: stored?.[code]?.display_name ?? '',
                    help_text: stored?.[code]?.help_text ?? '',
                },
            ]),
        ),
    });
    const errors = form.errors as Record<string, string | undefined>;

    function setField(locale: string, field: keyof Translation, value: string) {
        form.setData('translations', {
            ...form.data.translations,
            [locale]: { ...form.data.translations[locale], [field]: value },
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform(({ translations }) => ({
            translations: Object.fromEntries(
                Object.entries(translations).filter(
                    ([code, entry]) =>
                        entry.display_name !==
                            (stored?.[code]?.display_name ?? '') ||
                        entry.help_text !== (stored?.[code]?.help_text ?? ''),
                ),
            ),
        }));
        form.put(url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <Dialog
            open
            onOpenChange={(open) => !open && !form.processing && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Translations')}</DialogTitle>
                    <DialogDescription>
                        <bdi dir="ltr">{type.code}</bdi> ·{' '}
                        <bdi>{type.name}</bdi>
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <InputError message={errors.translations} />
                    {data.locales.length === 0 ? (
                        <p>{t('No enabled languages.')}</p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('Clear both fields to remove the translation.')}
                        </p>
                    )}
                    <div className="max-h-[45vh] space-y-3 overflow-y-auto px-1">
                        {data.locales.map((locale) => {
                            const code = locale.code;
                            const dir =
                                locale.text_direction.toLowerCase() === 'rtl'
                                    ? 'rtl'
                                    : 'ltr';
                            const errorKey = `translations.${code}`;

                            return (
                                <fieldset
                                    key={code}
                                    className="space-y-2 rounded-md border px-3 pb-3"
                                >
                                    <legend className="px-1 text-sm">
                                        <bdi>
                                            {locale.native_name ||
                                                locale.english_name}
                                        </bdi>{' '}
                                        · <bdi dir="ltr">{code}</bdi>
                                    </legend>
                                    <InputError message={errors[errorKey]} />
                                    <label className="block space-y-1 text-sm">
                                        <span>{t('Display name')}</span>
                                        <Input
                                            dir={dir}
                                            lang={code}
                                            aria-invalid={Boolean(
                                                errors[
                                                    `${errorKey}.display_name`
                                                ],
                                            )}
                                            maxLength={maxLength}
                                            disabled={form.processing}
                                            value={
                                                form.data.translations[code]
                                                    ?.display_name ?? ''
                                            }
                                            onChange={(event) =>
                                                setField(
                                                    code,
                                                    'display_name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `${errorKey}.display_name`
                                                ]
                                            }
                                        />
                                    </label>
                                    <label className="block space-y-1 text-sm">
                                        <span>{helpLabel ?? t('Help text')}</span>
                                        <textarea
                                            className="w-full rounded-md border border-input bg-background px-3 py-2"
                                            dir={dir}
                                            lang={code}
                                            rows={2}
                                            maxLength={helpMaxLength}
                                            aria-invalid={Boolean(
                                                errors[`${errorKey}.help_text`],
                                            )}
                                            disabled={form.processing}
                                            value={
                                                form.data.translations[code]
                                                    ?.help_text ?? ''
                                            }
                                            onChange={(event) =>
                                                setField(
                                                    code,
                                                    'help_text',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[`${errorKey}.help_text`]
                                            }
                                        />
                                    </label>
                                </fieldset>
                            );
                        })}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={!form.isDirty || form.processing}
                        >
                            {t('Save translations')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
