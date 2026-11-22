import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import type { DocumentTypeRow } from '@/types/identity-document-type';

export const DOCUMENT_TYPES_URL =
    '/platform/master-data/identity-document-types';

type DocumentTypeForm = {
    country_code: string;
    code: string;
    name: string;
    optional_format_regex: string;
    is_active: boolean;
};

/**
 * Quick browser-side preview only; the server decides whether a pattern is safe and
 * always matches the whole number, exactly like this anchored form.
 */
function previewMatch(
    pattern: string,
    sample: string,
): 'none' | 'invalid' | 'match' | 'no-match' {
    if (pattern.trim() === '' || sample === '') {
        return 'none';
    }

    try {
        return new RegExp(`^(?:${pattern.trim()})$`).test(sample.trim())
            ? 'match'
            : 'no-match';
    } catch {
        return 'invalid';
    }
}

export function DocumentTypeFormDialog({
    country,
    documentType = null,
    patternMaxLength,
    open,
    onOpenChange,
}: {
    country: string;
    documentType?: DocumentTypeRow | null;
    patternMaxLength: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const isEdit = documentType !== null;
    const [sample, setSample] = useState('');

    const form = useForm<DocumentTypeForm>({
        country_code: country,
        code: documentType?.code ?? '',
        name: documentType?.name ?? '',
        optional_format_regex: documentType?.optional_format_regex ?? '',
        is_active: documentType?.is_active ?? true,
    });

    const preview = useMemo(
        () => previewMatch(form.data.optional_format_regex, sample),
        [form.data.optional_format_regex, sample],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };
        const pattern = (value: string) => (value.trim() === '' ? null : value);

        if (isEdit) {
            // Country and code are fixed at creation; name and pattern can change.
            form.transform((data) => ({
                name: data.name,
                optional_format_regex: pattern(data.optional_format_regex),
            }));
            form.put(`${DOCUMENT_TYPES_URL}/${documentType.id}`, options);
        } else {
            form.transform((data) => ({
                ...data,
                optional_format_regex: pattern(data.optional_format_regex),
            }));
            form.post(DOCUMENT_TYPES_URL, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEdit
                                ? t('Edit document type')
                                : t('Add document type')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Country')}: <bdi dir="ltr">{country}</bdi>
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="document-type-code">{t('Code')}</Label>
                        <Input
                            id="document-type-code"
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
                            placeholder="NATIONAL_ID"
                            disabled={isEdit}
                            required={!isEdit}
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? t(
                                      'Country and code cannot be changed after creation.',
                                  )
                                : t(
                                      'Upper-case letters, digits, - or _, starting with a letter. Unique within the country.',
                                  )}
                        </p>
                        <InputError
                            message={
                                form.errors.code ?? form.errors.country_code
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="document-type-name">{t('Name')}</Label>
                        <Input
                            id="document-type-name"
                            dir="auto"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={120}
                            placeholder={t('National identity card (Tazkira)')}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="document-type-pattern">
                            {t('Format pattern (optional)')}
                        </Label>
                        <Input
                            id="document-type-pattern"
                            dir="ltr"
                            className="font-mono"
                            value={form.data.optional_format_regex}
                            onChange={(event) =>
                                form.setData(
                                    'optional_format_regex',
                                    event.target.value,
                                )
                            }
                            maxLength={patternMaxLength}
                            placeholder="\d{4}-\d{4}-\d{5}"
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Leave empty to accept any number. The pattern must match the whole number. Allowed: [A-Z], [0-9], \\d, \\w, groups ( ), | and repeats ? * + {n} {n,m}.',
                            )}
                        </p>
                        <InputError
                            message={form.errors.optional_format_regex}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="document-type-sample">
                            {t('Test a sample number')}
                        </Label>
                        <div className="flex items-center gap-2">
                            <Input
                                id="document-type-sample"
                                dir="ltr"
                                className="font-mono"
                                value={sample}
                                onChange={(event) =>
                                    setSample(event.target.value)
                                }
                                maxLength={100}
                                autoComplete="off"
                            />
                            {preview === 'match' && (
                                <Badge>{t('Matches')}</Badge>
                            )}
                            {preview === 'no-match' && (
                                <Badge variant="secondary">
                                    {t('Does not match')}
                                </Badge>
                            )}
                            {preview === 'invalid' && (
                                <Badge variant="destructive">
                                    {t('Invalid pattern')}
                                </Badge>
                            )}
                        </div>
                    </div>

                    {!isEdit && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="document-type-active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="document-type-active">
                                {t('Active')}
                            </Label>
                        </div>
                    )}

                    <p className="rounded-md bg-muted p-2 text-xs text-muted-foreground">
                        {t(
                            'No identity document is ever required to register a patient.',
                        )}
                    </p>

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
