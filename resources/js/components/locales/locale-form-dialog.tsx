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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/lib/i18n';
import type { CatalogEntry, LocaleRow, TextDirection } from '@/types/locale';

const LOCALES_URL = '/platform/master-data/locales';
const NONE = '__none';

type LocaleForm = {
    code: string;
    english_name: string;
    text_direction: TextDirection | '';
    is_active: boolean;
    native_name: string;
    default_calendar_code: string;
    default_numbering_system_code: string;
};

export function LocaleFormDialog({
    catalog,
    calendars,
    numberingSystems,
    existing,
    locale = null,
    open,
    onOpenChange,
}: {
    catalog: CatalogEntry[];
    calendars: string[];
    numberingSystems: string[];
    existing: string[];
    locale?: LocaleRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const isEdit = locale !== null;

    const form = useForm<LocaleForm>({
        code: locale?.code ?? '',
        english_name: locale?.english_name ?? '',
        text_direction: locale?.text_direction ?? '',
        is_active: locale?.is_active ?? true,
        native_name: locale?.native_name ?? '',
        default_calendar_code: locale?.default_calendar_code ?? '',
        default_numbering_system_code: locale?.default_numbering_system_code ?? '',
    });

    const choose = (code: string) => {
        const entry = catalog.find((item) => item.code === code);

        form.setData({
            ...form.data,
            code,
            english_name: entry?.name ?? '',
            text_direction: entry?.direction ?? '',
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        // Blank optional fields are sent as null so they clear stored values.
        const optional = (data: LocaleForm) => ({
            native_name: data.native_name.trim() === '' ? null : data.native_name,
            default_calendar_code: data.default_calendar_code || null,
            default_numbering_system_code: data.default_numbering_system_code || null,
        });

        if (isEdit) {
            // The code never changes, and status is changed with Activate/Deactivate on the list.
            form.transform((data) => ({
                english_name: data.english_name,
                text_direction: data.text_direction,
                ...optional(data),
            }));
            form.put(`${LOCALES_URL}/${locale.code}`, options);
        } else {
            form.transform((data) => ({
                code: data.code,
                english_name: data.english_name,
                text_direction: data.text_direction,
                is_active: data.is_active,
                ...optional(data),
            }));
            form.post(LOCALES_URL, options);
        }
    };

    const codeSelect = (
        field: 'default_calendar_code' | 'default_numbering_system_code',
        label: string,
        options: string[],
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`locale-${field}`}>{label}</Label>
            <Select
                value={form.data[field] === '' ? NONE : form.data[field]}
                onValueChange={(value) => form.setData(field, value === NONE ? '' : value)}
            >
                <SelectTrigger id={`locale-${field}`} className="w-full" dir="ltr">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>{t('None')}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option} value={option}>
                            <bdi dir="ltr">{option}</bdi>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={form.errors[field]} />
        </div>
    );

    const direction = form.data.text_direction;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{isEdit ? t('Edit language') : t('Add language')}</DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="locale-code">{t('Code')}</Label>
                        {isEdit ? (
                            <Input id="locale-code" dir="ltr" className="font-mono" value={form.data.code} disabled />
                        ) : (
                            <Select value={form.data.code} onValueChange={choose}>
                                <SelectTrigger id="locale-code" className="w-full">
                                    <SelectValue placeholder={t('Choose a language')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {catalog.map((entry) => (
                                        <SelectItem
                                            key={entry.code}
                                            value={entry.code}
                                            disabled={existing.includes(entry.code)}
                                        >
                                            <bdi dir="ltr">
                                                {entry.code} — {entry.name}
                                            </bdi>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? t('The language code cannot be changed after creation.')
                                : t('Only languages from the supported list can be added.')}
                        </p>
                        <InputError message={form.errors.code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="locale-name">{t('English name')}</Label>
                        <Input
                            id="locale-name"
                            dir="ltr"
                            value={form.data.english_name}
                            onChange={(event) => form.setData('english_name', event.target.value)}
                            maxLength={100}
                            required
                        />
                        <InputError message={form.errors.english_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="locale-native-name">{t('Native name')}</Label>
                        <Input
                            id="locale-native-name"
                            dir="auto"
                            value={form.data.native_name}
                            onChange={(event) => form.setData('native_name', event.target.value)}
                            maxLength={120}
                        />
                        <InputError message={form.errors.native_name} />
                    </div>

                    <div className="grid gap-1">
                        <span className="text-sm font-medium">{t('Text direction')}</span>
                        <p className="text-sm text-muted-foreground">
                            {direction === '' ? '—' : direction === 'RTL' ? t('Right to left') : t('Left to right')}
                        </p>
                        <InputError message={form.errors.text_direction} />
                    </div>

                    {codeSelect('default_calendar_code', t('Calendar'), calendars)}
                    {codeSelect('default_numbering_system_code', t('Numbering system'), numberingSystems)}

                    {!isEdit && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="locale-active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) => form.setData('is_active', checked === true)}
                            />
                            <Label htmlFor="locale-active">{t('Active')}</Label>
                        </div>
                    )}
                    <InputError message={form.errors.is_active} />

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing || form.data.code === ''}>
                            {isEdit ? t('Save') : t('Create')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
