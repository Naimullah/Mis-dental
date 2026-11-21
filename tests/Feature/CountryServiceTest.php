<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Services\Countries\CountryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CountryDatabaseTestCase;

class CountryServiceTest extends CountryDatabaseTestCase
{
    private CountryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CountryService;
    }

    public function test_create_trims_name_and_accepts_nullable_iso3(): void
    {
        $country = $this->service->create([
            'iso2' => 'AF', 'iso3' => null, 'english_name' => '  Afghanistan  ', 'is_active' => false,
        ])->refresh();

        $this->assertSame('AF', $country->getKey());
        $this->assertSame('Afghanistan', $country->english_name);
        $this->assertNull($country->iso3);
        $this->assertFalse($country->is_active);
    }

    public function test_create_rejects_invalid_input_and_duplicate_codes(): void
    {
        $this->service->create([
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);

        foreach ([
            [
                ['iso2' => 'af', 'iso3' => ' ', 'english_name' => ' ', 'is_active' => 'unknown'],
                ['iso2', 'iso3', 'english_name', 'is_active'],
            ],
            [
                ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Duplicate', 'is_active' => true],
                ['iso2', 'iso3'],
            ],
        ] as [$input, $fields]) {
            try {
                $this->service->create($input);
                $this->fail('Invalid input was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame($fields, array_keys($exception->errors()));
            }
        }

        $this->assertDatabaseCount('countries', 1);
    }

    public function test_update_keeps_iso2_and_preserves_omitted_iso3_but_can_clear_it(): void
    {
        $this->service->create([
            'iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Germany', 'is_active' => true,
        ]);
        $details = ['english_name' => ' Germany ', 'is_active' => false];

        $this->assertSame('DEU', $this->service->update('DE', $details + ['iso3' => 'DEU'])->iso3);
        $this->assertSame('DEU', $this->service->update('DE', $details)->iso3);
        $country = $this->service->update('DE', $details + ['iso3' => null])->refresh();
        $this->assertSame('DE', $country->getKey());
        $this->assertNull($country->iso3);
        $this->assertFalse($country->is_active);
    }

    public function test_update_rejects_iso2_changes_and_another_countrys_iso3(): void
    {
        $this->service->create([
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);
        $this->service->create([
            'iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Germany', 'is_active' => true,
        ]);

        try {
            $this->service->update('AF', [
                'iso2' => 'GB', 'iso3' => 'DEU', 'english_name' => 'Changed', 'is_active' => false,
            ]);
            $this->fail('Invalid update was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['iso2', 'iso3'], array_keys($exception->errors()));
        }

        $this->assertDatabaseHas('countries', [
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);
    }

    public function test_status_changes_are_repeatable_and_preserve_the_country(): void
    {
        $this->service->create([
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);

        $this->assertFalse($this->service->deactivate('AF')->refresh()->is_active);
        $this->assertFalse($this->service->deactivate('AF')->refresh()->is_active);
        $this->assertTrue($this->service->activate('AF')->refresh()->is_active);
        $this->assertTrue($this->service->activate('AF')->refresh()->is_active);
        $this->assertDatabaseHas('countries', [
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);
        $this->assertDatabaseCount('countries', 1);
    }

    public function test_status_change_fails_for_a_missing_country(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->activate('AF');
    }

    #[DataProvider('duplicateFields')]
    public function test_unique_constraint_failure_is_reported_and_rolls_back(string $field, array $conflict): void
    {
        // Insert after validation so the database, rather than the validator, detects the conflict.
        Country::creating(function () use ($conflict): void {
            DB::table('countries')->insert($conflict + [
                'iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Conflict', 'is_active' => true,
            ]);
        });

        try {
            $this->service->create([
                'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
            ]);
            $this->fail('A database conflict was not reported.');
        } catch (ValidationException $exception) {
            $this->assertSame([$field], array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('countries', 0);
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function duplicateFields(): array
    {
        return [
            'ISO2' => ['iso2', ['iso2' => 'AF']],
            'ISO3' => ['iso3', ['iso3' => 'AFG']],
        ];
    }
}
