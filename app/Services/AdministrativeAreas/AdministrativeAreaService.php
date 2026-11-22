<?php

namespace App\Services\AdministrativeAreas;

use App\Concerns\AdministrativeAreaValidationRules;
use App\Models\AdministrativeArea;
use App\Models\Country;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * P01.05 administrative areas. Every write locks the country row, so hierarchy checks
 * (same country, no cycles, depth, activation rules) cannot race. No deletion.
 */
class AdministrativeAreaService
{
    use AdministrativeAreaValidationRules;

    /** @param array<string, mixed> $input */
    public function create(array $input): AdministrativeArea
    {
        return $this->write(function () use ($input): AdministrativeArea {
            $input = $this->normalizeAreaInput($input);
            $country = $input['country_code'] ?? null;
            $this->lockCountry($country);

            $attributes = Validator::make($input, $this->areaRules(true, $country), $this->areaMessages())->validate();
            $attributes = $this->castParent($attributes);
            $active = (bool) ($attributes['is_active'] ?? true);
            $parentId = $attributes['parent_id'] ?? null;

            $this->assertValidParent($attributes['country_code'], $parentId, null);
            $this->assertParentActive($parentId, $active, 'parent_id');

            $area = new AdministrativeArea;
            $area->country_code = $attributes['country_code'];
            unset($attributes['country_code']);

            return $this->save($area->fill($attributes));
        });
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): AdministrativeArea
    {
        return $this->write(function () use ($id, $input): AdministrativeArea {
            $country = AdministrativeArea::query()->whereKey($id)->value('country_code');
            $this->lockCountry($country);
            $area = AdministrativeArea::query()->lockForUpdate()->findOrFail($id);

            $input = $this->normalizeAreaInput($input);
            $attributes = Validator::make($input, $this->areaRules(false, $area->country_code, $area->id), $this->areaMessages())->validate();
            $attributes = $this->castParent($attributes);

            if (array_key_exists('parent_id', $attributes) && $attributes['parent_id'] !== $area->parent_id) {
                $this->assertValidParent($area->country_code, $attributes['parent_id'], $area->id);
                $this->assertParentActive($attributes['parent_id'], $area->is_active, 'parent_id');
            }

            return $this->save($area->fill($attributes));
        });
    }

    public function activate(int $id): AdministrativeArea
    {
        return $this->setActive($id, true);
    }

    public function deactivate(int $id): AdministrativeArea
    {
        return $this->setActive($id, false);
    }

    private function setActive(int $id, bool $active): AdministrativeArea
    {
        return $this->write(function () use ($id, $active): AdministrativeArea {
            $this->lockCountry(AdministrativeArea::query()->whereKey($id)->value('country_code'));
            $area = AdministrativeArea::query()->lockForUpdate()->findOrFail($id);

            if ($area->is_active === $active) {
                return $area;
            }

            if ($active) {
                $this->assertParentActive($area->parent_id, true, 'is_active');
            } elseif (AdministrativeArea::query()->where('parent_id', $area->id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => __('Deactivate the child areas first.'),
                ]);
            }

            $area->is_active = $active;

            return $this->save($area);
        });
    }

    private function assertValidParent(string $country, ?int $parentId, ?int $selfId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            $this->fail('parent_id', __('An area cannot be its own parent.'));
        }

        $parent = AdministrativeArea::query()->find($parentId);
        if ($parent === null || $parent->country_code !== $country) {
            $this->fail('parent_id', __('The parent area must be in the same country.'));
        }

        // Walk up from the new parent: meeting the area itself means a cycle.
        $depth = 1;
        $cursor = $parent;
        while ($cursor->parent_id !== null) {
            if ($cursor->parent_id === $selfId || $depth > AdministrativeArea::MAX_DEPTH) {
                $this->fail('parent_id', __('An area cannot be placed under its own descendant.'));
            }
            $cursor = AdministrativeArea::query()->findOrFail($cursor->parent_id);
            $depth++;
        }

        $height = $selfId === null ? 1 : $this->subtreeHeight($selfId);
        if ($depth + $height > AdministrativeArea::MAX_DEPTH) {
            $this->fail('parent_id', __('Areas can be nested at most :max levels deep.', ['max' => AdministrativeArea::MAX_DEPTH]));
        }
    }

    /** Levels in the subtree rooted at the area, including the area itself. */
    private function subtreeHeight(int $id): int
    {
        $height = 1;
        $ids = [$id];

        while ($height <= AdministrativeArea::MAX_DEPTH) {
            $ids = AdministrativeArea::query()->whereIn('parent_id', $ids)->pluck('id')->all();
            if ($ids === []) {
                break;
            }
            $height++;
        }

        return $height;
    }

    private function assertParentActive(?int $parentId, bool $active, string $field): void
    {
        if ($active && $parentId !== null && AdministrativeArea::query()->whereKey($parentId)->where('is_active', false)->exists()) {
            $this->fail($field, __('The parent area is inactive. Activate it first.'));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function castParent(array $attributes): array
    {
        if (array_key_exists('parent_id', $attributes) && $attributes['parent_id'] !== null) {
            $attributes['parent_id'] = (int) $attributes['parent_id'];
        }

        return $attributes;
    }

    private function lockCountry(mixed $code): void
    {
        if (is_string($code) && $code !== '') {
            Country::query()->whereKey($code)->lockForUpdate()->first();
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** @param Closure(): AdministrativeArea $operation */
    private function write(Closure $operation): AdministrativeArea
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException $exception) {
            $message = $exception->getPrevious()?->getMessage() ?? '';

            if (str_contains($message, 'administrative_areas.area_code') || str_contains($message, 'administrative_areas_country_area_code_unique')) {
                $this->fail('area_code', __('validation.unique', ['attribute' => 'area code']));
            }

            throw $exception;
        }
    }

    private function save(AdministrativeArea $area): AdministrativeArea
    {
        if (! $area->save()) {
            throw new RuntimeException('The administrative area write was cancelled.');
        }

        return $area;
    }
}
