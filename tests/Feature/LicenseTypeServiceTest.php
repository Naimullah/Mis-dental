<?php

namespace Tests\Feature;

use App\Enums\LicenseAppliesTo;
use App\Models\LicenseType;
use App\Services\LicenseTypes\LicenseTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** P01.07 lifecycle, immutable identifiers and selection for dependent workflows. */
class LicenseTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    private LicenseTypeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
        ]);
        $this->service = new LicenseTypeService;
    }

    public function test_creates_with_normalised_name_and_default_active_status(): void
    {
        $type = $this->make('AF', 'DENTIST_LIC', 'practitioner', "  Dental practitioner  ")->refresh();

        $this->assertSame('AF', $type->country_code);
        $this->assertSame('DENTIST_LIC', $type->code);
        $this->assertSame('Dental practitioner', $type->name);
        $this->assertSame('practitioner', $type->applies_to);
        $this->assertTrue($type->is_active);
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $fields, array $errors): void
    {
        try {
            $this->service->create([...['country_code' => 'AF', 'code' => 'DENTIST_LIC', 'name' => 'Dentist', 'applies_to' => 'practitioner'], ...$fields]);
            $this->fail('Invalid license type was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame($errors, array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('license_types', 0);
    }

    public static function invalidInput(): array
    {
        return [
            'unknown country' => [['country_code' => 'ZZ'], ['country_code']],
            'lower-case code' => [['code' => 'dentist_lic'], ['code']],
            'code starting with digit' => [['code' => '1DENTIST'], ['code']],
            'code with space' => [['code' => 'DENTIST LIC'], ['code']],
            'code too long' => [['code' => 'A'.str_repeat('B', 40)], ['code']],
            'unknown applies-to' => [['applies_to' => 'patient'], ['applies_to']],
            'blank name' => [['name' => '  '], ['name']],
            'name too long' => [['name' => str_repeat('ن', 161)], ['name']],
            'hidden direction override' => [['name' => "Dentist\u{202E}"], ['name']],
            'status not boolean' => [['is_active' => 'maybe'], ['is_active']],
        ];
    }

    public function test_forty_character_code_is_accepted(): void
    {
        $this->make('AF', 'A'.str_repeat('B', 39), 'facility', 'Long code');

        $this->assertDatabaseCount('license_types', 1);
    }

    public function test_code_is_unique_within_its_country_only(): void
    {
        $this->make('AF', 'DENTIST_LIC', 'practitioner', 'Dentist');
        $this->make('PK', 'DENTIST_LIC', 'practitioner', 'Dentist');

        $this->expectValidationError('code', fn () => $this->make('AF', 'DENTIST_LIC', 'facility', 'Other'));
    }

    public function test_only_the_name_can_change_after_creation(): void
    {
        $type = $this->make('AF', 'DENTIST_LIC', 'practitioner', 'Dentist');

        $this->service->update($type->id, ['name' => 'Dental practitioner']);
        $this->assertSame('Dental practitioner', $type->refresh()->name);

        foreach ([['country_code' => 'PK'], ['code' => 'OTHER'], ['applies_to' => 'facility'], ['is_active' => false]] as $change) {
            $field = array_key_first($change);
            $this->expectValidationError($field, fn () => $this->service->update($type->id, ['name' => 'Dentist', ...$change]));
        }

        $this->assertDatabaseHas('license_types', ['id' => $type->id, 'country_code' => 'AF', 'code' => 'DENTIST_LIC', 'applies_to' => 'practitioner', 'is_active' => true]);
    }

    public function test_model_refuses_identifier_changes_even_without_validation(): void
    {
        $type = $this->make('AF', 'DENTIST_LIC', 'practitioner', 'Dentist');

        foreach (['country_code' => 'PK', 'code' => 'OTHER', 'applies_to' => 'facility'] as $attribute => $value) {
            try {
                $type->{$attribute} = $value;
                $this->fail("{$attribute} was changed.");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_lifecycle_and_selection_for_dependent_workflows(): void
    {
        $dentist = $this->make('AF', 'DENTIST_LIC', 'practitioner', 'Dentist');
        $this->make('AF', 'CLINIC_LIC', 'facility', 'Clinic');
        $this->make('PK', 'PK_DENTIST', 'practitioner', 'Dentist (PK)');

        $selectable = fn (): array => LicenseType::query()->selectable('AF', LicenseAppliesTo::Practitioner)->pluck('code')->all();
        $this->assertSame(['DENTIST_LIC'], $selectable());

        $this->service->deactivate($dentist->id);
        $this->service->deactivate($dentist->id); // repeatable
        $this->assertSame([], $selectable());
        $this->assertDatabaseHas('license_types', ['id' => $dentist->id, 'name' => 'Dentist', 'is_active' => false]);

        $this->service->activate($dentist->id);
        $this->assertSame(['DENTIST_LIC'], $selectable());
    }

    private function make(string $country, string $code, string $appliesTo, string $name): LicenseType
    {
        return $this->service->create(['country_code' => $country, 'code' => $code, 'applies_to' => $appliesTo, 'name' => $name]);
    }

    private function expectValidationError(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
