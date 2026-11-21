<?php

namespace App\Services\Currencies;

use App\Concerns\CurrencyValidationRules;
use App\Models\Currency;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CurrencyService
{
    use CurrencyValidationRules;

    /** @param array<string, mixed> $input */
    public function create(array $input): Currency
    {
        return $this->writeDetails(function () use ($input): Currency {
            $input = $this->normalizeCurrencyInput($input);
            $attributes = Validator::make($input, $this->currencyRules(creating: true))->validate();

            $currency = new Currency;
            $currency->code = $attributes['code'];
            unset($attributes['code']);

            return $this->save($currency->fill($attributes));
        });
    }

    /** @param array<string, mixed> $input */
    public function update(string $code, array $input): Currency
    {
        return $this->writeDetails(function () use ($code, $input): Currency {
            $currency = Currency::query()->lockForUpdate()->findOrFail($code);
            $input = $this->normalizeCurrencyInput($input);
            $attributes = Validator::make($input, $this->currencyRules(creating: false))->validate();

            return $this->save($currency->fill($attributes));
        });
    }

    public function activate(string $code): Currency
    {
        return $this->setActive($code, true);
    }

    public function deactivate(string $code): Currency
    {
        return $this->setActive($code, false);
    }

    private function setActive(string $code, bool $active): Currency
    {
        return DB::transaction(function () use ($code, $active): Currency {
            $currency = Currency::query()->lockForUpdate()->findOrFail($code);
            $currency->is_active = $active;

            return $this->save($currency);
        });
    }

    /** @param Closure(): Currency $operation */
    private function writeDetails(Closure $operation): Currency
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            // Inspect the driver error, excluding Laravel's appended SQL and values.
            $message = $exception->getPrevious()?->getMessage() ?? '';
            $field = match (true) {
                str_contains($message, 'UNIQUE constraint failed: currencies.code'),
                str_contains($message, '"currencies_pkey"') => 'code',
                default => null,
            };

            if ($field === null) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                $field => __('validation.unique', ['attribute' => $field]),
            ]);
        }
    }

    private function save(Currency $currency): Currency
    {
        if (! $currency->save()) {
            throw new RuntimeException('The currency write was cancelled.');
        }

        return $currency;
    }
}
