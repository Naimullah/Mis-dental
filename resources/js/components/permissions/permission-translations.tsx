import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/lib/i18n';

export type PermissionLocale = {
    code: string;
    english_name: string;
    native_name: string | null;
    text_direction: string;
    is_active: boolean;
};

export type PermissionTranslation = {
    locale_code: string;
    display_name: string;
    description: string | null;
};

export function PermissionTranslations({
    locales,
    translations,
    endpoint,
    onSuccess,
}: {
    locales: PermissionLocale[];
    translations: PermissionTranslation[];
    endpoint?: string;
    onSuccess: () => void;
}) {
    const { t } = useTrans();
    const [code, setCode] = useState('');
    const available = locales.filter(
        (locale) =>
            locale.is_active ||
            translations.some((item) => item.locale_code === locale.code),
    );
    const selected = available.find((locale) => locale.code === code);
    const languageName = (localeCode: string) => {
        const locale = locales.find((item) => item.code === localeCode);

        return locale ? locale.native_name || locale.english_name : localeCode;
    };

    return (
        <section className="space-y-3">
            <h3 className="font-medium">{t('Translations')}</h3>
            {translations.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No translations found.')}
                </p>
            ) : (
                <ul className="space-y-2 text-sm">
                    {translations.map((item) => (
                        <li key={item.locale_code}>
                            <button
                                type="button"
                                className="w-full rounded-md border p-2 text-start enabled:hover:bg-muted/50 aria-pressed:border-primary"
                                aria-pressed={code === item.locale_code}
                                disabled={!endpoint}
                                title={endpoint ? t('Edit') : undefined}
                                onClick={() => setCode(item.locale_code)}
                            >
                                <span className="text-muted-foreground">
                                    <bdi>{languageName(item.locale_code)}</bdi>{' '}
                                    (<bdi dir="ltr">{item.locale_code}</bdi>)
                                </span>{' '}
                                · <bdi>{item.display_name}</bdi>
                                {item.description && (
                                    <span className="block wrap-anywhere whitespace-pre-wrap">
                                        <bdi>{item.description}</bdi>
                                    </span>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {endpoint && available.length > 0 && (
                <>
                    <div className="space-y-1 text-sm">
                        <span id="permission-translation-language">
                            {t('Language')}
                        </span>
                        <Select
                            value={code === '' ? undefined : code}
                            onValueChange={setCode}
                        >
                            <SelectTrigger
                                className="w-full"
                                aria-labelledby="permission-translation-language"
                            >
                                <SelectValue
                                    placeholder={t('Choose a language')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {available.map((locale) => (
                                    <SelectItem
                                        key={locale.code}
                                        value={locale.code}
                                    >
                                        <bdi>
                                            {locale.native_name ||
                                                locale.english_name}
                                        </bdi>{' '}
                                        (<bdi dir="ltr">{locale.code}</bdi>)
                                        {!locale.is_active &&
                                            ' · ' + t('Inactive')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {selected && (
                        <TranslationForm
                            key={code}
                            locale={selected}
                            translation={translations.find(
                                (item) => item.locale_code === code,
                            )}
                            endpoint={`${endpoint}/${encodeURIComponent(code)}`}
                            onSuccess={onSuccess}
                        />
                    )}
                </>
            )}
        </section>
    );
}

function TranslationForm({
    locale,
    translation,
    endpoint,
    onSuccess,
}: {
    locale: PermissionLocale;
    translation?: PermissionTranslation;
    endpoint: string;
    onSuccess: () => void;
}) {
    const { t } = useTrans();
    const [confirming, setConfirming] = useState(false);
    const form = useForm({
        display_name: translation?.display_name ?? '',
        description: translation?.description ?? '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const dir = locale.text_direction.toLowerCase() === 'rtl' ? 'rtl' : 'ltr';

    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (locale.is_active && !confirming) {
            form.put(endpoint, { onSuccess, preserveScroll: true });
        }
    }

    return (
        <form onSubmit={save} className="space-y-3">
            <InputError message={errors.locale_code} />
            {!locale.is_active && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'This language is disabled. Its translation can only be removed.',
                    )}
                </p>
            )}
            {locale.is_active && (
                <>
                    <label className="block space-y-1 text-sm">
                        <span>{t('Display name')}</span>
                        <Input
                            required
                            maxLength={160}
                            lang={locale.code}
                            dir={dir}
                            disabled={form.processing}
                            value={form.data.display_name}
                            onChange={(event) =>
                                form.setData('display_name', event.target.value)
                            }
                        />
                        <InputError message={errors.display_name} />
                    </label>
                    <label className="block space-y-1 text-sm">
                        <span>{t('Description')}</span>
                        <textarea
                            className="w-full rounded-md border bg-background px-3 py-2"
                            rows={3}
                            maxLength={4000}
                            lang={locale.code}
                            dir={dir}
                            disabled={form.processing}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        <InputError message={errors.description} />
                    </label>
                </>
            )}
            <div className="flex flex-wrap items-center gap-2">
                {confirming ? (
                    <>
                        <span className="text-sm">
                            {t('Remove this translation?')}
                        </span>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={form.processing}
                            onClick={() =>
                                form.delete(endpoint, {
                                    onSuccess,
                                    preserveScroll: true,
                                })
                            }
                        >
                            {t('Remove')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setConfirming(false)}
                        >
                            {t('Cancel')}
                        </Button>
                    </>
                ) : (
                    <>
                        {locale.is_active && (
                            <Button
                                type="submit"
                                disabled={form.processing || !form.isDirty}
                            >
                                {t('Save')}
                            </Button>
                        )}
                        {translation && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setConfirming(true)}
                            >
                                {t('Remove')}
                            </Button>
                        )}
                    </>
                )}
            </div>
        </form>
    );
}
