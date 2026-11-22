<?php

namespace App\Support;

use Illuminate\Support\Str;
use Normalizer;

/**
 * "Safe Unicode" for names: trimmed, NFC-normalised, valid UTF-8, and free of control
 * characters and bidirectional override/isolate characters (which can make text display
 * differently from what is stored). Ordinary RTL text and LRM/RLM marks are allowed.
 */
class SafeText
{
    private const BIDI_CONTROLS = '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    public static function normalize(string $value): string
    {
        $value = Str::trim($value);

        if (class_exists(Normalizer::class) && mb_check_encoding($value, 'UTF-8')) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        return $value;
    }

    /** @return string|null a translated error message, or null when the text is safe */
    public static function problem(string $value): ?string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return __('The text is not valid UTF-8.');
        }

        if (preg_match('/\p{Cc}/u', $value) === 1) {
            return __('The text must not contain control characters.');
        }

        if (preg_match(self::BIDI_CONTROLS, $value) === 1) {
            return __('The text must not contain hidden direction-override characters.');
        }

        return null;
    }

    /** Validation rule closure. */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && ($problem = self::problem($value)) !== null) {
                $fail($problem);
            }
        };
    }
}
