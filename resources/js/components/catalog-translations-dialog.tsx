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

export type CatalogTranslations = {
    locales: {
        code: string;
        english_name: string;
        native_name: string | null;
        text_direction: string;
    }[];
    names: Record<string, Record<string, string>>;
    displayNames: Record<string, string>;
};

export function CatalogName({
    canonical,
    translated,
}: {
    canonical: string;
    translated?: string;
}) {
    return (
        <>
            <bdi>{canonical}</bdi>
            {translated && translated !== canonical && (
                <span className="block text-muted-foreground">
                    <bdi>{translated}</bdi>
                </span>
            )}
        </>
    );
}

export function CatalogTranslationsDialog({
    data,
    code,
    name,
    url,
    maxLength,
    onClose,
}: {
    data: CatalogTranslations;
    code: string;
    name: string;
    url: string;
    maxLength: number;
    onClose: () => void;
}) {
    const { t } = useTrans();
    const form = useForm<{ translations: Record<string, string> }>({
        translations: Object.fromEntries(
            data.locales.map(({ code: locale }) => [
                locale,
                data.names[code]?.[locale] ?? '',
            ]),
        ),
    });
    const errors = form.errors as Record<string, string | undefined>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform(({ translations }) => ({
            translations: Object.fromEntries(
                Object.entries(translations).filter(
                    ([locale, value]) =>
                        value !== (data.names[code]?.[locale] ?? ''),
                ),
            ),
        }));
        form.put(url, { preserveScroll: true, onSuccess: onClose });
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
                        <bdi dir="ltr">{code}</bdi> · <bdi>{name}</bdi>
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <InputError message={errors.translations} />
                    {data.locales.length > 0 ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Clear the field to remove the translation.',
                                )}
                            </p>
                            <div className="max-h-[45vh] space-y-3 overflow-y-auto px-1">
                                {data.locales.map((locale) => (
                                    <label
                                        key={locale.code}
                                        className="block space-y-1 text-sm"
                                    >
                                        <span>
                                            <bdi>
                                                {locale.native_name ||
                                                    locale.english_name}
                                            </bdi>{' '}
                                            · <bdi dir="ltr">{locale.code}</bdi>
                                        </span>
                                        <Input
                                            aria-label={`${t('Display name')} (${locale.code})`}
                                            aria-invalid={Boolean(
                                                errors[
                                                    `translations.${locale.code}`
                                                ],
                                            )}
                                            dir={
                                                locale.text_direction.toLowerCase() ===
                                                'rtl'
                                                    ? 'rtl'
                                                    : 'ltr'
                                            }
                                            maxLength={maxLength}
                                            disabled={form.processing}
                                            value={
                                                form.data.translations[
                                                    locale.code
                                                ] ?? ''
                                            }
                                            onChange={(event) =>
                                                form.setData('translations', {
                                                    ...form.data.translations,
                                                    [locale.code]:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `translations.${locale.code}`
                                                ]
                                            }
                                        />
                                    </label>
                                ))}
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('No enabled languages.')}
                        </p>
                    )}
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
