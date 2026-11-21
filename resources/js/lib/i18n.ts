import { usePage } from '@inertiajs/react';

export type Direction = 'ltr' | 'rtl';

type Replacements = Record<string, string | number>;

export function translate(
    translations: Record<string, string> | undefined,
    key: string,
    replace: Replacements = {},
): string {
    const text = translations?.[key] ?? key;

    // Match whole placeholder names so `:to` never consumes part of `:total`.
    return text.replace(/:([A-Za-z_]+)/g, (match, name: string) =>
        name in replace ? String(replace[name]) : match,
    );
}

export function useTrans() {
    const { translations, locale, direction } = usePage().props;

    return {
        t: (key: string, replace?: Replacements) =>
            translate(translations, key, replace),
        locale,
        direction,
        isRtl: direction === 'rtl',
    };
}

export function applyDocumentLocale(
    locale: string,
    direction: Direction,
): void {
    document.documentElement.lang = locale;
    document.documentElement.dir = direction;
}
