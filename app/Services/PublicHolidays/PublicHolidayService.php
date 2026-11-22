<?php

namespace App\Services\PublicHolidays;

use App\Models\PublicHoliday;
use App\Support\SafeText;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PublicHolidayService
{
    /** @param array<string, mixed> $input */
    public function save(PublicHoliday $holiday, array $input): void
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = SafeText::normalize($input['name']);
        }

        $country = $input['country_code'] ?? null;
        $attributes = Validator::make($input, [
            'country_code' => ['bail', 'required', 'string', 'regex:/\A[A-Z]{2}\z/', Rule::exists('countries', 'iso2')],
            'administrative_area_id' => ['bail', 'present', 'nullable', 'integer', Rule::exists('administrative_areas', 'id')
                ->where(fn (Builder $query) => $query->where('country_code', is_string($country) ? $country : ''))],
            'holiday_date' => ['required', 'date_format:Y-m-d'],
            'name' => ['bail', 'required', 'string', 'max:180', SafeText::rule()],
            'status' => $this->statusRules(),
        ])->validate();

        $holiday->fill($attributes)->saveOrFail();
    }

    public function updateStatus(PublicHoliday $holiday, mixed $status): void
    {
        $attributes = Validator::make(['status' => $status], ['status' => $this->statusRules()])->validate();
        $holiday->fill($attributes)->saveOrFail();
    }

    /** @return array<int, mixed> */
    private function statusRules(): array
    {
        return ['required', 'string', Rule::in(PublicHoliday::STATUSES)];
    }
}
