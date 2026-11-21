<?php

namespace App\Services\Countries;

use App\Concerns\CountryValidationRules;
use App\Models\Country;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CountryService
{
    use CountryValidationRules;

    /** @param array<string, mixed> $input */
    public function create(array $input): Country
    {
        return $this->writeDetails(function () use ($input): Country {
            $input = $this->normalizeCountryInput($input);
            $attributes = Validator::make($input, $this->countryRules($input, creating: true))->validate();

            $country = new Country;
            $country->iso2 = $attributes['iso2'];
            unset($attributes['iso2']);

            return $this->save($country->fill($attributes));
        });
    }

    /** @param array<string, mixed> $input */
    public function update(string $iso2, array $input): Country
    {
        return $this->writeDetails(function () use ($iso2, $input): Country {
            $country = Country::query()->lockForUpdate()->findOrFail($iso2);
            $input = $this->normalizeCountryInput($input);
            $attributes = Validator::make($input, $this->countryRules($input, creating: false, country: $country))->validate();

            return $this->save($country->fill($attributes));
        });
    }

    public function activate(string $iso2): Country
    {
        return $this->setActive($iso2, true);
    }

    public function deactivate(string $iso2): Country
    {
        return $this->setActive($iso2, false);
    }

    private function setActive(string $iso2, bool $active): Country
    {
        return DB::transaction(function () use ($iso2, $active): Country {
            $country = Country::query()->lockForUpdate()->findOrFail($iso2);
            $country->is_active = $active;

            return $this->save($country);
        });
    }

    /** @param Closure(): Country $operation */
    private function writeDetails(Closure $operation): Country
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            // Inspect the driver error, excluding Laravel's appended SQL and values.
            $message = $exception->getPrevious()?->getMessage() ?? '';
            $field = match (true) {
                str_contains($message, 'UNIQUE constraint failed: countries.iso2'),
                str_contains($message, '"countries_pkey"') => 'iso2',
                str_contains($message, 'UNIQUE constraint failed: countries.iso3'),
                str_contains($message, '"countries_iso3_unique"') => 'iso3',
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

    private function save(Country $country): Country
    {
        if (! $country->save()) {
            throw new RuntimeException('The country write was cancelled.');
        }

        return $country;
    }
}
