<?php

namespace App\Services\IdentityDocumentTypes;

use App\Concerns\IdentityDocumentTypeValidationRules;
use App\Models\IdentityDocumentType;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * P01.08 lifecycle: create → edit name/pattern → deactivate/reactivate. No deletion.
 * Pattern edits apply only to documents entered or edited afterwards.
 */
class IdentityDocumentTypeService
{
    use IdentityDocumentTypeValidationRules;

    /** @param array<string, mixed> $input */
    public function create(array $input): IdentityDocumentType
    {
        return $this->write(function () use ($input): IdentityDocumentType {
            $input = $this->normalizeDocumentTypeInput($input);
            $attributes = Validator::make($input, $this->documentTypeRules(true, $input['country_code'] ?? null), $this->documentTypeMessages())->validate();

            $type = new IdentityDocumentType;
            $type->country_code = $attributes['country_code'];
            $type->code = $attributes['code'];

            return $this->save($type->fill([
                'name' => $attributes['name'],
                'optional_format_regex' => $attributes['optional_format_regex'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]));
        });
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): IdentityDocumentType
    {
        return $this->write(function () use ($id, $input): IdentityDocumentType {
            $type = IdentityDocumentType::query()->lockForUpdate()->findOrFail($id);
            $input = $this->normalizeDocumentTypeInput($input);
            $attributes = Validator::make($input, $this->documentTypeRules(false, $type->country_code), $this->documentTypeMessages())->validate();

            return $this->save($type->fill($attributes));
        });
    }

    public function activate(int $id): IdentityDocumentType
    {
        return $this->setActive($id, true);
    }

    public function deactivate(int $id): IdentityDocumentType
    {
        return $this->setActive($id, false);
    }

    private function setActive(int $id, bool $active): IdentityDocumentType
    {
        return $this->write(function () use ($id, $active): IdentityDocumentType {
            $type = IdentityDocumentType::query()->lockForUpdate()->findOrFail($id);

            if ($type->is_active === $active) {
                return $type;
            }

            $type->is_active = $active;

            return $this->save($type);
        });
    }

    /** @param Closure(): IdentityDocumentType $operation */
    private function write(Closure $operation): IdentityDocumentType
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            $message = $exception->getPrevious()?->getMessage() ?? '';

            if (str_contains($message, 'identity_document_types.code') || str_contains($message, 'identity_document_types_country_code_unique')) {
                throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
            }

            throw $exception;
        }
    }

    private function save(IdentityDocumentType $type): IdentityDocumentType
    {
        if (! $type->save()) {
            throw new RuntimeException('The identity document type write was cancelled.');
        }

        return $type;
    }
}
