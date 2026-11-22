<?php

namespace App\Enums;

/** What a license type is issued to (P01.07). */
enum LicenseAppliesTo: string
{
    case Practitioner = 'practitioner';
    case Facility = 'facility';
    case LegalEntity = 'legal_entity';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Practitioner => __('Practitioner'),
            self::Facility => __('Facility'),
            self::LegalEntity => __('Legal entity'),
        };
    }
}
