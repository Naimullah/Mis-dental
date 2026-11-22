<?php

namespace App\Concerns;

use App\Support\SafeDocumentPattern;
use App\Support\SafeText;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

trait IdentityDocumentTypeValidationRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeDocumentTypeInput(array $input): array
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = SafeText::normalize($input['name']);
        }

        if (is_string($input['code'] ?? null)) {
            $input['code'] = Str::trim($input['code']);
        }

        // Patterns are kept exactly as typed apart from surrounding whitespace; blank means "no format".
        if (is_string($input['optional_format_regex'] ?? null)) {
            $pattern = Str::trim($input['optional_format_regex']);
            $input['optional_format_regex'] = $pattern === '' ? null : $pattern;
        }

        return $input;
    }

    /** @return array<string, list<string|Exists|Unique|Closure>> */
    protected function documentTypeRules(bool $creating, mixed $countryCode): array
    {
        $common = [
            'name' => ['required', 'string', 'max:120', SafeText::rule()],
            'optional_format_regex' => ['sometimes', 'nullable', 'string', 'max:'.SafeDocumentPattern::MAX_LENGTH, self::patternRule()],
        ];

        if (! $creating) {
            return ['country_code' => ['missing'], 'code' => ['missing'], 'is_active' => ['missing'], ...$common];
        }

        $code = ['bail', 'required', 'string', 'regex:/\A[A-Z][A-Z0-9_-]{0,39}\z/'];
        if (is_string($countryCode) && preg_match('/\A[A-Z]{2}\z/', $countryCode) === 1) {
            $code[] = Rule::unique('identity_document_types', 'code')->where('country_code', $countryCode);
        }

        return [
            'country_code' => ['bail', 'required', 'string', 'regex:/\A[A-Z]{2}\z/', Rule::exists('countries', 'iso2')],
            'code' => $code,
            ...$common,
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function documentTypeMessages(): array
    {
        return [
            'code.regex' => __('Use upper-case letters, digits, hyphens or underscores, starting with a letter (for example NATIONAL_ID).'),
        ];
    }

    private static function patternRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ($problem = SafeDocumentPattern::problem($value)) !== null) {
                $fail($problem);
            }
        };
    }
}
