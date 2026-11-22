<?php

namespace App\Concerns;

use App\Support\SafeText;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * P01.10 tax code input rules, shared by the form requests and the service.
 * The rate is validated as a decimal string (0–100, at most 5 decimals), never as a float.
 */
trait TaxCodeValidationRules
{
    /** 0 to 100 inclusive, at most 5 decimal places, no sign or exponent. */
    protected const RATE_PATTERN = '/\A(?:100(?:\.0{1,5})?|\d{1,2}(?:\.\d{1,5})?)\z/';

    protected const CODE_PATTERN = '/\A[A-Z0-9][A-Z0-9_-]{0,39}\z/';

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeTaxCodeInput(array $input): array
    {
        if (is_string($input['description'] ?? null)) {
            $input['description'] = SafeText::normalize($input['description']);
        }

        if (is_string($input['code'] ?? null)) {
            $input['code'] = Str::upper(Str::trim($input['code']));
        }

        if (is_int($input['rate_percent'] ?? null)) {
            $input['rate_percent'] = (string) $input['rate_percent'];
        }

        if (is_string($input['rate_percent'] ?? null)) {
            $input['rate_percent'] = Str::trim($input['rate_percent']);
        }

        foreach (['effective_from', 'effective_to'] as $date) {
            if (is_string($input[$date] ?? null)) {
                $input[$date] = Str::trim($input[$date]);
            }
        }

        if (($input['effective_to'] ?? null) === '') {
            $input['effective_to'] = null;
        }

        return $input;
    }

    /** @return array<string, list<mixed>> */
    protected function taxCodeCreateRules(): array
    {
        return [
            'country_code' => ['bail', 'required', 'string', 'regex:/\A[A-Z]{2}\z/', $this->activeCountry()],
            'code' => ['bail', 'required', 'string', 'regex:'.self::CODE_PATTERN],
            ...$this->taxCodeVersionRules(true),
        ];
    }

    /**
     * Rules for a new version of an existing code (country and code come from that code).
     *
     * @return array<string, list<mixed>>
     */
    protected function taxCodeVersionRules(bool $descriptionRequired): array
    {
        return [
            'description' => [$descriptionRequired ? 'required' : 'sometimes', 'string', 'max:160', SafeText::rule()],
            'rate_percent' => ['bail', 'required', 'string', 'regex:'.self::RATE_PATTERN],
            'effective_from' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'effective_to' => ['bail', 'nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * Editing a version only changes its description.
     *
     * @return array<string, list<mixed>>
     */
    protected function taxCodeUpdateRules(): array
    {
        return [
            'country_code' => ['missing'],
            'code' => ['missing'],
            'rate_percent' => ['missing'],
            'effective_from' => ['missing'],
            'effective_to' => ['missing'],
            'description' => ['required', 'string', 'max:160', SafeText::rule()],
        ];
    }

    /** @return array<string, list<string>> */
    protected function taxCodeCloseRules(): array
    {
        return ['effective_to' => ['bail', 'required', 'string', 'date_format:Y-m-d']];
    }

    /** @return array<string, string> */
    protected function taxCodeMessages(): array
    {
        return [
            'code.regex' => __('Use upper-case letters, digits, hyphens or underscores, starting with a letter or digit (for example VAT_STD).'),
            'rate_percent.regex' => __('Enter a rate from 0 to 100 with at most 5 decimal places (for example 10 or 2.5).'),
            'country_code.exists' => __('Choose an existing, active country.'),
            'effective_to.after_or_equal' => __('The end date cannot be before the start date.'),
        ];
    }

    /** Store every rate with exactly 5 decimals so equal rates compare equal. */
    protected function canonicalRate(string $rate): string
    {
        return (string) BigDecimal::of($rate)->toScale(5, RoundingMode::Unnecessary);
    }

    private function activeCountry(): Exists
    {
        return Rule::exists('countries', 'iso2')->where('is_active', true);
    }
}
