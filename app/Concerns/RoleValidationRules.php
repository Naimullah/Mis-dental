<?php

namespace App\Concerns;

use App\Support\SafeText;
use Illuminate\Support\Str;

/** P03.04 role code and name rules, shared by the catalog sync and the custom-role service. */
trait RoleValidationRules
{
    /** Lower-case letters, digits and underscores, starting with a letter; 2–100 characters. */
    protected const ROLE_CODE_PATTERN = '/\A[a-z][a-z0-9_]{1,99}\z/';

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeRoleInput(array $input): array
    {
        if (is_string($input['code'] ?? null)) {
            $input['code'] = Str::lower(Str::trim($input['code']));
        }

        if (is_string($input['name'] ?? null)) {
            $input['name'] = SafeText::normalize($input['name']);
        }

        return $input;
    }

    /** @return list<mixed> */
    protected function roleCodeRules(): array
    {
        return ['bail', 'required', 'string', 'regex:'.self::ROLE_CODE_PATTERN];
    }

    /** @return list<mixed> */
    protected function roleNameRules(): array
    {
        return ['bail', 'required', 'string', 'max:150', SafeText::rule()];
    }

    /** @return array<string, string> */
    protected function roleMessages(): array
    {
        return [
            'code.regex' => __('Use lower-case letters, digits or underscores, starting with a letter (for example front_desk).'),
        ];
    }
}
