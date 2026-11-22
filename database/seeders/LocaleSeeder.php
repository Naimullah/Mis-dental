<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds the three interface languages if they are missing. Never updates or removes
 * existing rows. Calendar and numbering preferences are left empty on purpose: they are
 * presentation choices for the owner, not assumptions built into the seed.
 * Run with: php artisan db:seed --class=LocaleSeeder
 */
class LocaleSeeder extends Seeder
{
    public const CODES = ['en', 'fa-AF', 'ps-AF'];

    /** Each language's own name, in its own script. */
    private const NATIVE_NAMES = ['en' => 'English', 'fa-AF' => 'دری', 'ps-AF' => 'پښتو'];

    public function run(): void
    {
        $catalog = config('locale_catalog', []);

        DB::table('locales')->insertOrIgnore(array_map(fn (string $code): array => [
            'code' => $code,
            'english_name' => $catalog[$code]['name'],
            'text_direction' => $catalog[$code]['direction'],
            'is_active' => true,
            'native_name' => self::NATIVE_NAMES[$code],
            'default_calendar_code' => null,
            'default_numbering_system_code' => null,
        ], self::CODES));
    }
}
