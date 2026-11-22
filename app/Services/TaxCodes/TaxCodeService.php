<?php

namespace App\Services\TaxCodes;

use App\Concerns\TaxCodeValidationRules;
use App\Models\Country;
use App\Models\TaxCode;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * P01.10 tax code versions.
 *
 * create → (edit description) → close, or add a new version (which closes the open one
 * the day before the new start and copies its translations). Versions of one code may
 * never overlap; every write that touches periods locks the country row first, so
 * concurrent writers queue up. Nothing is ever deleted.
 */
class TaxCodeService
{
    use TaxCodeValidationRules;

    /** @param array<string, mixed> $input */
    public function create(array $input): TaxCode
    {
        return $this->write(function () use ($input): TaxCode {
            $data = $this->validate($input, $this->taxCodeCreateRules());
            $this->lockActiveCountry($data['country_code']);

            return $this->insert($data['country_code'], $data['code'], $data);
        });
    }

    /** @param array<string, mixed> $input */
    public function updateDescription(int $id, array $input): TaxCode
    {
        return $this->write(function () use ($id, $input): TaxCode {
            $taxCode = TaxCode::query()->lockForUpdate()->findOrFail($id);
            $data = $this->validate($input, $this->taxCodeUpdateRules());

            return $this->save($taxCode->fill(['description' => $data['description']]));
        });
    }

    /**
     * Set the end date of an open version. A closed version cannot be changed again.
     *
     * @param  array<string, mixed>  $input
     */
    public function close(int $id, array $input): TaxCode
    {
        return $this->write(function () use ($id, $input): TaxCode {
            $data = $this->validate($input, $this->taxCodeCloseRules());
            $taxCode = $this->lockVersion($id);

            if ($taxCode->effective_to !== null) {
                throw ValidationException::withMessages(['effective_to' => __('This version is already closed.')]);
            }

            if ($data['effective_to'] < $taxCode->effective_from) {
                throw ValidationException::withMessages(['effective_to' => __('The end date cannot be before the start date.')]);
            }

            $taxCode->effective_to = $data['effective_to'];

            return $this->save($taxCode);
        });
    }

    /**
     * Add a new version of the code that $id belongs to. If an open version exists, it is
     * closed the day before the new version starts, in the same transaction.
     *
     * @param  array<string, mixed>  $input
     */
    public function addVersion(int $id, array $input): TaxCode
    {
        return $this->write(function () use ($id, $input): TaxCode {
            $data = $this->validate($input, $this->taxCodeVersionRules(false));
            $base = $this->lockVersion($id, requireActiveCountry: true);

            $open = TaxCode::query()
                ->where('country_code', $base->country_code)
                ->where('code', $base->code)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                if ($data['effective_from'] <= $open->effective_from) {
                    throw ValidationException::withMessages([
                        'effective_from' => __('The new version must start after the current version starts (:date).', ['date' => $open->effective_from]),
                    ]);
                }

                $open->effective_to = CarbonImmutable::createFromFormat('!Y-m-d', $data['effective_from'])->subDay()->format('Y-m-d');
                $this->save($open);
            }

            $source = $open ?? $base;
            $data['description'] ??= $source->description;
            $version = $this->insert($base->country_code, $base->code, $data);
            $this->copyTranslations($source, $version);

            return $version;
        });
    }

    /**
     * A new version starts with the translated names and explanations of the version it
     * replaces (P02.05), including those of currently disabled languages, so nothing is lost.
     */
    private function copyTranslations(TaxCode $from, TaxCode $to): void
    {
        $rows = DB::table('tax_code_translations')
            ->where('tax_code_id', $from->id)
            ->orderBy('locale_code')
            ->get(['locale_code', 'display_name', 'explanation'])
            ->map(fn (object $row): array => [
                'tax_code_id' => $to->id,
                'locale_code' => $row->locale_code,
                'display_name' => $row->display_name,
                'explanation' => $row->explanation,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('tax_code_translations')->insert($rows);
        }
    }

    /** @param array<string, mixed> $data */
    private function insert(string $countryCode, string $code, array $data): TaxCode
    {
        $to = $data['effective_to'] ?? null;

        if (TaxCode::query()->overlapping($countryCode, $code, $data['effective_from'], $to)->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => __('This period overlaps another version of :code. Close that version or choose other dates.', ['code' => $code]),
            ]);
        }

        $taxCode = new TaxCode;
        $taxCode->country_code = $countryCode;
        $taxCode->code = $code;
        $taxCode->description = $data['description'];
        $taxCode->rate_percent = $this->canonicalRate($data['rate_percent']);
        $taxCode->effective_from = $data['effective_from'];
        $taxCode->effective_to = $to;

        return $this->save($taxCode);
    }

    /**
     * Lock order is always country row, then tax code rows, so writers cannot deadlock.
     */
    private function lockVersion(int $id, bool $requireActiveCountry = false): TaxCode
    {
        $countryCode = TaxCode::query()->whereKey($id)->value('country_code');
        abort_if($countryCode === null, 404);

        $requireActiveCountry
            ? $this->lockActiveCountry($countryCode)
            : Country::query()->whereKey($countryCode)->lockForUpdate()->first();

        return TaxCode::query()->lockForUpdate()->findOrFail($id);
    }

    private function lockActiveCountry(string $countryCode): void
    {
        $country = Country::query()->whereKey($countryCode)->lockForUpdate()->first();

        if ($country === null || ! $country->is_active) {
            throw ValidationException::withMessages(['country_code' => __('Choose an existing, active country.')]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $input, array $rules): array
    {
        return Validator::make($this->normalizeTaxCodeInput($input), $rules, $this->taxCodeMessages())->validate();
    }

    /** @param Closure(): TaxCode $operation */
    private function write(Closure $operation): TaxCode
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            $message = $exception->getPrevious()?->getMessage() ?? '';

            if (str_contains($message, 'tax_codes_country_code_from_unique') || str_contains($message, 'tax_codes.effective_from')) {
                throw ValidationException::withMessages([
                    'effective_from' => __('Another version of this code already starts on this date.'),
                ]);
            }

            throw $exception;
        }
    }

    private function save(TaxCode $taxCode): TaxCode
    {
        if (! $taxCode->save()) {
            throw new RuntimeException('The tax code write was cancelled.');
        }

        return $taxCode;
    }
}
