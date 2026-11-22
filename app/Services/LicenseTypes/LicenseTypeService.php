<?php

namespace App\Services\LicenseTypes;

use App\Concerns\LicenseTypeValidationRules;
use App\Models\LicenseType;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** P01.07 lifecycle: create → rename → deactivate/reactivate. No deletion. */
class LicenseTypeService
{
    use LicenseTypeValidationRules;

    /** @param array<string, mixed> $input */
    public function create(array $input): LicenseType
    {
        return $this->write(function () use ($input): LicenseType {
            $input = $this->normalizeLicenseTypeInput($input);
            $attributes = Validator::make($input, $this->licenseTypeRules(true, $input['country_code'] ?? null), $this->licenseTypeMessages())->validate();

            $type = new LicenseType;
            $type->country_code = $attributes['country_code'];
            $type->code = $attributes['code'];
            $type->applies_to = $attributes['applies_to'];

            return $this->save($type->fill([
                'name' => $attributes['name'],
                'is_active' => $attributes['is_active'] ?? true,
            ]));
        });
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): LicenseType
    {
        return $this->write(function () use ($id, $input): LicenseType {
            $type = LicenseType::query()->lockForUpdate()->findOrFail($id);
            $input = $this->normalizeLicenseTypeInput($input);
            $attributes = Validator::make($input, $this->licenseTypeRules(false, $type->country_code), $this->licenseTypeMessages())->validate();

            return $this->save($type->fill($attributes));
        });
    }

    public function activate(int $id): LicenseType
    {
        return $this->setActive($id, true);
    }

    public function deactivate(int $id): LicenseType
    {
        return $this->setActive($id, false);
    }

    private function setActive(int $id, bool $active): LicenseType
    {
        return $this->write(function () use ($id, $active): LicenseType {
            $type = LicenseType::query()->lockForUpdate()->findOrFail($id);

            if ($type->is_active === $active) {
                return $type;
            }

            $type->is_active = $active;

            return $this->save($type);
        });
    }

    /** @param Closure(): LicenseType $operation */
    private function write(Closure $operation): LicenseType
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            $message = $exception->getPrevious()?->getMessage() ?? '';

            if (str_contains($message, 'license_types.code') || str_contains($message, 'license_types_country_code_unique')) {
                throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
            }

            throw $exception;
        }
    }

    private function save(LicenseType $type): LicenseType
    {
        if (! $type->save()) {
            throw new RuntimeException('The license type write was cancelled.');
        }

        return $type;
    }
}
