import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTrans } from '@/lib/i18n';

export type CountryLocalesPanel = {
    country: { iso2: string; english_name: string; is_active: boolean };
    links: {
        code: string;
        english_name: string;
        text_direction: 'LTR' | 'RTL';
        is_default: boolean;
    }[];
    available: { code: string; english_name: string }[];
};

/**
 * P01.04: languages used in a country. Read-only while Countries changes are
 * blocked until platform permissions exist.
 */
export function CountryLanguagesDialog({
    panel,
    onClose,
}: {
    panel: CountryLocalesPanel | null;
    onClose: () => void;
}) {
    const { t } = useTrans();

    return (
        <Dialog
            open={panel !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
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
                                className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                            >
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
                                <span className="flex gap-2">
                                    <Badge variant="secondary">
                                        {link.text_direction === 'RTL'
                                            ? t('Right to left')
                                            : t('Left to right')}
                                    </Badge>
                                    {link.is_default && (
                                        <Badge>{t('Default')}</Badge>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                <p className="text-xs text-muted-foreground">
                    {t(
                        'Changes are disabled until platform permissions are set up.',
                    )}
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('Close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
