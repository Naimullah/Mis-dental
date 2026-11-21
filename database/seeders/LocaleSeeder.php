<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds the three interface languages if they are missing. Never updates or removes
 * existing rows. Run with: php artisan db:seed --class=LocaleSeeder
 */
class LocaleSeeder extends Seeder
{
    public const CODES = ['en', 'fa-AF', 'ps-AF'];

    public function run(): void
    {
        $catalog = config('locale_catalog', []);

        DB::table('locales')->insertOrIgnore(array_map(fn (string $code): array => [
            'code' => $code,
            'english_name' => $catalog[$code]['name'],
            'text_direction' => $catalog[$code]['direction'],
        ], self::CODES));
    }
}
