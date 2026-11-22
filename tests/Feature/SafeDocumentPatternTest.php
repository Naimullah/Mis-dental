<?php

namespace Tests\Feature;

use App\Support\SafeDocumentPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** P01.08 format patterns: a small safe subset, always anchored, never catastrophic. */
class SafeDocumentPatternTest extends TestCase
{
    #[DataProvider('safePatterns')]
    public function test_safe_patterns_are_accepted(string $pattern, string $matching, string $notMatching): void
    {
        $this->assertNull(SafeDocumentPattern::problem($pattern), $pattern);
        $this->assertTrue(SafeDocumentPattern::matches($pattern, $matching), "{$pattern} should match {$matching}");
        $this->assertFalse(SafeDocumentPattern::matches($pattern, $notMatching), "{$pattern} should not match {$notMatching}");
    }

    public static function safePatterns(): array
    {
        return [
            'ten digits' => ['\d{10}', '1234567890', '123456789'],
            'grouped tazkira style' => ['\d{4}-\d{4}-\d{5}', '1400-0101-12345', '1400010112345'],
            'passport' => ['[A-Z]{2}\d{7}', 'PA1234567', 'pa1234567'],
            'optional suffix' => ['\d{4}(-\d{2})?', '1234-56', '1234-5'],
            'alternatives' => ['P\d{7}|[A-Z]{2}\d{6}', 'AB123456', 'P123456'],
            'bounded repeat of a fixed group' => ['(\d{3}-){2}\d{4}', '123-456-7890', '123-4567890'],
            'negated class' => ['[^ ]{5,20}', 'ABC-12', 'AB C12'],
            'non-Latin literal' => ['تذکره-\d{6}', 'تذکره-123456', 'تذکره-12345'],
            'word characters' => ['\w+', 'A_1', 'A-1'],
            'escaped specials' => ['\d+\/\d+\.\d', '12/34.5', '12-34.5'],
            'non-capturing group' => ['(?:AF|PK)\d{6}', 'AF123456', 'IR123456'],
            'spreadsheet-like text' => ['=1+1', '=11', '=1+1x'],
        ];
    }

    #[DataProvider('unsafePatterns')]
    public function test_unsafe_or_unsupported_patterns_are_rejected(string $pattern): void
    {
        $this->assertNotNull(SafeDocumentPattern::problem($pattern), "Pattern should be rejected: {$pattern}");
    }

    public static function unsafePatterns(): array
    {
        return [
            'nested plus (ReDoS)' => ['(a+)+'],
            'nested star (ReDoS)' => ['(a*)*'],
            'bounded repeat of unbounded group' => ['(\d+){2}'],
            'repeated alternation' => ['(a|b)*'],
            'overlapping alternation repeated' => ['(a|ab)+'],
            'deeply nested' => ['((a+)b)+'],
            'backreference' => ['(\d)\1'],
            'lookahead' => ['(?=\d)\d'],
            'lookbehind' => ['(?<=A)\d'],
            'named group' => ['(?<x>\d)'],
            'inline flag' => ['(?i)abc'],
            'start anchor' => ['^\d+'],
            'end anchor' => ['\d+$'],
            'lazy repeat' => ['\d+?'],
            'possessive repeat' => ['\d++'],
            'stacked repeat' => ['\d{2}{3}'],
            'repeat over limit' => ['\d{1,100}'],
            'reversed repeat' => ['\d{5,2}'],
            'malformed repeat' => ['a{'],
            'POSIX class' => ['[[:digit:]]'],
            'Unicode property' => ['\p{L}+'],
            'hex escape' => ['\x41'],
            'word boundary' => ['\bA'],
            'empty class' => ['[]'],
            'unclosed group' => ['(\d'],
            'unmatched close' => ['\d)'],
            'leading repeat' => ['+a'],
            'empty pattern' => [''],
            'too long' => [str_repeat('a', 201)],
            'control character' => ["a\x07"],
        ];
    }

    public function test_patterns_always_match_the_whole_value(): void
    {
        $this->assertFalse(SafeDocumentPattern::matches('\d{4}', '12345'));
        $this->assertFalse(SafeDocumentPattern::matches('\d{4}', 'x1234'));
        $this->assertFalse(SafeDocumentPattern::matches('A|B', 'AB'), 'Alternation is grouped before anchoring.');
        $this->assertTrue(SafeDocumentPattern::matches('A|B', 'B'));
    }

    public function test_checking_a_long_hostile_input_is_fast(): void
    {
        $start = microtime(true);
        $this->assertFalse(SafeDocumentPattern::matches('[A-Z0-9]{1,50}(-[A-Z0-9]{1,50})?', str_repeat('A', 5000).'!'));
        $this->assertLessThan(1.0, microtime(true) - $start);
    }
}
