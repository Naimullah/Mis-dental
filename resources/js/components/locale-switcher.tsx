import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { applyDocumentLocale, useTrans } from '@/lib/i18n';
import type { Direction } from '@/lib/i18n';

export function LocaleSwitcher() {
    const { locales } = usePage().props;
    const { t, locale } = useTrans();

    const change = (value: string) => {
        router.post(
            '/locale',
            { locale: value },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const props = page.props as {
                        locale: string;
                        direction: Direction;
                    };
                    applyDocumentLocale(props.locale, props.direction);
                },
            },
        );
    };

    return (
        <div className="flex items-center gap-2 px-2 group-data-[collapsible=icon]:hidden">
            <Languages
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden
            />
            <Select value={locale} onValueChange={change}>
                <SelectTrigger
                    size="sm"
                    className="w-full"
                    aria-label={t('Language')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {locales.map((option) => (
                        <SelectItem key={option.code} value={option.code}>
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
