<?php

namespace App\Services\Locales;

use App\Concerns\LocaleValidationRules;
use App\Models\Locale;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class LocaleService
{
    use LocaleValidationRules;

    public function retrieve(string $code): Locale
    {
        return Locale::query()->findOrFail($code);
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): Locale
    {
        return $this->write(function () use ($input): Locale {
            $input = $this->normalizeLocaleInput($input);
            $attributes = Validator::make(
                $input,
                $this->localeRules(creating: true, code: $input['code'] ?? null),
                $this->localeMessages(),
            )->validate();

            $locale = new Locale;
            $locale->code = $attributes['code'];
            unset($attributes['code']);

            return $this->save($locale->fill($attributes));
        });
    }

    /** @param array<string, mixed> $input */
    public function update(string $code, array $input): Locale
    {
        return $this->write(function () use ($code, $input): Locale {
            $locale = Locale::query()->lockForUpdate()->findOrFail($code);
            $input = $this->normalizeLocaleInput($input);
            $attributes = Validator::make(
                $input,
                $this->localeRules(creating: false, code: $locale->getRawOriginal('code')),
                $this->localeMessages(),
            )->validate();

            return $this->save($locale->fill($attributes));
        });
    }

    public function activate(string $code): Locale
    {
        return $this->setActive($code, true);
    }

    public function deactivate(string $code): Locale
    {
        return $this->setActive($code, false);
    }

    private function setActive(string $code, bool $active): Locale
    {
        return $this->write(function () use ($code, $active): Locale {
            $locale = Locale::query()->lockForUpdate()->findOrFail($code);

            if (! array_key_exists('is_active', $locale->getAttributes())) {
                throw new LogicException('Locale status changes require the is_active schema migration.');
            }

            if ($locale->is_active === $active) {
                return $locale;
            }

            $locale->is_active = $active;

            return $this->save($locale);
        });
    }

    /** @param Closure(): Locale $operation */
    private function write(Closure $operation): Locale
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            $message = $exception->getPrevious()?->getMessage() ?? '';

            if (! str_contains($message, 'UNIQUE constraint failed: locales.code') && ! str_contains($message, '"locales_pkey"')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'code' => __('validation.unique', ['attribute' => 'code']),
            ]);
        }
    }

    private function save(Locale $locale): Locale
    {
        if (! $locale->save()) {
            throw new RuntimeException('The locale write was cancelled.');
        }

        return $locale;
    }
}
