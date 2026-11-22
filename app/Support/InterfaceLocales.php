<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Interface languages (P01.03 "enabled state" and "fallback rules").
 *
 * A language can be chosen in the interface when it is configured in config/locales.php
 * and is not disabled in the locales table. A language with no locales row stays
 * available, so a fresh install without seeded locales still works. The fallback
 * language (config app.fallback_locale) can never be disabled.
 *
 * Translation fallback chain: exact tag (fa-AF) → base language (fa) → fallback (en).
 */
class InterfaceLocales
{
    /** @var list<string>|null */
    private ?array $disabled = null;

    /** @return array<string, array{name: string, dir: string}> */
    public function enabled(): array
    {
        $fallback = $this->fallback();
        $disabled = $this->disabledCodes();

        return collect(config('locales.supported', []))
            ->filter(fn (array $locale, string $code): bool => $code === $fallback || ! in_array($code, $disabled, true))
            ->all();
    }

    public function isEnabled(mixed $code): bool
    {
        return is_string($code) && array_key_exists($code, $this->enabled());
    }

    public function direction(string $code): string
    {
        return config('locales.supported.'.$code.'.dir', 'ltr');
    }

    public function fallback(): string
    {
        return (string) config('app.fallback_locale', 'en');
    }

    /** @return list<string> exact tag, then base language, then fallback; no duplicates */
    public function chain(string $code): array
    {
        return array_values(array_unique([$code, Str::before($code, '-'), $this->fallback()]));
    }

    /** @return list<string> */
    private function disabledCodes(): array
    {
        if ($this->disabled !== null) {
            return $this->disabled;
        }

        try {
            $codes = DB::table('locales')
                ->whereIn('code', array_keys(config('locales.supported', [])))
                ->where('is_active', false)
                ->pluck('code')
                ->all();
        } catch (Throwable) {
            $codes = []; // locales table not migrated yet: configuration alone decides
        }

        return $this->disabled = array_values($codes);
    }
}
