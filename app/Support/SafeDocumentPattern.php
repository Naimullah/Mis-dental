<?php

namespace App\Support;

/**
 * P01.08 identity-document format patterns, typed by administrators and later run against
 * the numbers entered for patients. Only a small, safe regex subset is accepted so a
 * pattern can never freeze the server (ReDoS), behaves the same in PHP and JavaScript,
 * and always has to match the whole number.
 *
 * Allowed: literal characters (including spaces and non-Latin letters), character classes
 * [..] with ranges and negation, \d and \w, escaped special characters, `.`, non-capturing
 * or plain groups, alternation `|`, and the quantifiers ? * + {n} {n,} {n,m} (n, m ≤ 50).
 *
 * Rejected: backreferences, lookarounds, named groups, inline flags, recursion, other escapes
 * (\p, \x, \b, …), lazy/possessive quantifiers, ^ and $ (anchors are added automatically),
 * control characters, and the nested-repeat shapes that cause catastrophic backtracking:
 *  - `*`, `+` or `{n,}` on a group that contains any repeat or alternation, e.g. (a+)+ or (a|b)*;
 *  - `{n}` or `{n,m}` on a group that contains an unbounded repeat, e.g. (a+){2}.
 * An optional group `( … )?` is always allowed, e.g. \d{4}(-\d{2})?.
 */
class SafeDocumentPattern
{
    public const MAX_LENGTH = 200;

    public const MAX_REPEAT = 50;

    private const META = '.^$|?*+()[]{}\\/-';

    /** @return string|null a translated error message, or null when the pattern is safe */
    public static function problem(string $pattern): ?string
    {
        if ($pattern === '') {
            return __('The format pattern is empty.');
        }

        if (mb_strlen($pattern) > self::MAX_LENGTH || ! mb_check_encoding($pattern, 'UTF-8')) {
            return __('The format pattern must be at most :max characters.', ['max' => self::MAX_LENGTH]);
        }

        if (preg_match('/\p{Cc}/u', $pattern) === 1) {
            return __('The format pattern must not contain control characters.');
        }

        $structural = self::checkStructure($pattern);
        if ($structural !== null) {
            return $structural;
        }

        if (! self::compilesAndStaysFast($pattern)) {
            return __('The format pattern is invalid or too slow to check safely.');
        }

        return null;
    }

    public static function isSafe(string $pattern): bool
    {
        return self::problem($pattern) === null;
    }

    /** Whole-value match. Call only with patterns that passed problem(). */
    public static function matches(string $pattern, string $value): bool
    {
        return preg_match(self::toPhp($pattern), $value) === 1;
    }

    /** The anchored PHP form; the JavaScript form is new RegExp('^(?:' + pattern + ')$'). */
    public static function toPhp(string $pattern): string
    {
        return "\x01\\A(?:".$pattern.")\\z\x01u";
    }

    private static function checkStructure(string $pattern): ?string
    {
        $chars = mb_str_split($pattern);
        $count = count($chars);
        // Per open group: does it contain a repeat / an unbounded repeat / an alternation?
        $empty = ['quantified' => false, 'unbounded' => false, 'alternation' => false];
        $groups = [$empty];
        // What the last complete atom was: null (nothing yet), 'atom', or a closed group's flags.
        $previous = null;

        for ($i = 0; $i < $count; $i++) {
            $char = $chars[$i];

            switch ($char) {
                case '\\':
                    $next = $chars[$i + 1] ?? null;
                    if ($next === null || ! self::isAllowedEscape($next)) {
                        return __('Only \\d, \\w and escaped special characters are allowed after a backslash.');
                    }
                    $i++;
                    $previous = 'atom';
                    break;

                case '[':
                    $end = self::readClass($chars, $i);
                    if ($end === null) {
                        return __('A character class [...] is not valid.');
                    }
                    $i = $end;
                    $previous = 'atom';
                    break;

                case '(':
                    if (($chars[$i + 1] ?? null) === '?') {
                        if (($chars[$i + 2] ?? null) !== ':') {
                            return __('Lookarounds, named groups and inline flags are not allowed.');
                        }
                        $i += 2;
                    }
                    $groups[] = $empty;
                    $previous = null;
                    break;

                case ')':
                    if (count($groups) === 1) {
                        return __('The format pattern has an unmatched ")".');
                    }
                    $closed = array_pop($groups);
                    $top = array_key_last($groups);
                    $groups[$top]['quantified'] = $groups[$top]['quantified'] || $closed['quantified'];
                    $groups[$top]['unbounded'] = $groups[$top]['unbounded'] || $closed['unbounded'];
                    $previous = $closed;
                    break;

                case '|':
                    $groups[array_key_last($groups)]['alternation'] = true;
                    $previous = null;
                    break;

                case '?':
                case '*':
                case '+':
                case '{':
                    $kind = match ($char) {
                        '?' => 'optional',
                        '*', '+' => 'unbounded',
                        default => 'bounded',
                    };

                    if ($char === '{') {
                        $repeat = self::readRepeat($chars, $i);
                        if ($repeat === null) {
                            return __('Repeat counts must look like {n}, {n,} or {n,m} with numbers up to :max.', ['max' => self::MAX_REPEAT]);
                        }
                        [$i, $max] = $repeat;
                        $kind = $max === null ? 'unbounded' : 'bounded';
                    }

                    if ($previous === null) {
                        return __('A repeat symbol must follow something to repeat.');
                    }

                    if (is_array($previous) && (
                        ($kind === 'unbounded' && ($previous['quantified'] || $previous['alternation']))
                        || ($kind === 'bounded' && $previous['unbounded'])
                    )) {
                        return __('A group that already repeats or has alternatives cannot be repeated again.');
                    }

                    if (in_array($chars[$i + 1] ?? null, ['?', '+', '*', '{'], true)) {
                        return __('Lazy, possessive or stacked repeats are not allowed.');
                    }

                    $top = array_key_last($groups);
                    $groups[$top]['quantified'] = true;
                    $groups[$top]['unbounded'] = $groups[$top]['unbounded'] || $kind === 'unbounded';
                    $previous = null;
                    break;

                case '^':
                case '$':
                    return __('Do not use ^ or $: the pattern always has to match the whole number.');

                case ']':
                case '}':
                    return __('The format pattern has an unmatched "]" or "}".');

                default:
                    $previous = 'atom';
            }
        }

        return count($groups) === 1 ? null : __('The format pattern has an unclosed "(".');
    }

    private static function isAllowedEscape(string $char): bool
    {
        return $char === 'd' || $char === 'w' || str_contains(self::META, $char);
    }

    /**
     * @param  list<string>  $chars
     * @return int|null index of the closing ]
     */
    private static function readClass(array $chars, int $start): ?int
    {
        $i = $start + 1;
        if (($chars[$i] ?? null) === '^') {
            $i++;
        }

        $members = 0;
        for ($count = count($chars); $i < $count; $i++) {
            $char = $chars[$i];

            if ($char === ']') {
                return $members > 0 ? $i : null;
            }
            if ($char === '[') {
                return null; // nested classes and POSIX [:alpha:]
            }
            if ($char === '\\') {
                $next = $chars[$i + 1] ?? null;
                if ($next === null || ! self::isAllowedEscape($next)) {
                    return null;
                }
                $i++;
            }
            $members++;
        }

        return null;
    }

    /**
     * @param  list<string>  $chars
     * @return array{0: int, 1: int|null}|null index of the closing } and the maximum (null = unbounded)
     */
    private static function readRepeat(array $chars, int $start): ?array
    {
        $text = '';
        for ($i = $start + 1, $count = count($chars); $i < $count && $chars[$i] !== '}'; $i++) {
            $text .= $chars[$i];
        }

        if (($chars[$i] ?? null) !== '}' || preg_match('/\A(\d{1,2})(,(\d{1,2})?)?\z/', $text, $m) !== 1) {
            return null;
        }

        $min = (int) $m[1];
        $max = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
        if ($min > self::MAX_REPEAT || ($max !== null && ($max > self::MAX_REPEAT || $max < $min))) {
            return null;
        }

        return [$i, isset($m[2]) ? $max : $min];
    }

    /** Must compile, and must finish quickly on long, awkward inputs with a low backtrack limit. */
    private static function compilesAndStaysFast(string $pattern): bool
    {
        $regex = self::toPhp($pattern);
        $previousLimit = ini_get('pcre.backtrack_limit');
        $previousJit = ini_get('pcre.jit');
        ini_set('pcre.backtrack_limit', '20000');
        ini_set('pcre.jit', '0');

        try {
            if (@preg_match($regex, '') === false) {
                return false;
            }

            foreach (['a', '1', 'A', '-', ' ', 'aA1-'] as $unit) {
                foreach ([str_repeat($unit, 300), str_repeat($unit, 300).'!'] as $input) {
                    if (@preg_match($regex, $input) === false) {
                        return false;
                    }
                }
            }

            return true;
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previousLimit);
            ini_set('pcre.jit', (string) $previousJit);
        }
    }
}
