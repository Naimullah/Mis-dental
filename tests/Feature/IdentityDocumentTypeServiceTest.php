<?php

namespace Tests\Feature;

use App\Models\IdentityDocumentType;
use App\Services\IdentityDocumentTypes\IdentityDocumentTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** P01.08 lifecycle, unchangeable identifiers, safe patterns and the optional-document rule. */
class IdentityDocumentTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdentityDocumentTypeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
        ]);
        $this->service = new IdentityDocumentTypeService;
    }

    public function test_creates_with_trimmed_values_and_optional_pattern(): void
    {
        $tazkira = $this->make('AF', 'NATIONAL_ID', '  National ID  ', '  \d{4}-\d{4}-\d{5}  ')->refresh();
        $passport = $this->make('AF', 'PASSPORT', 'Passport', '')->refresh();

        $this->assertSame('National ID', $tazkira->name);
        $this->assertSame('\d{4}-\d{4}-\d{5}', $tazkira->optional_format_regex);
        $this->assertTrue($tazkira->is_active);
        $this->assertNull($passport->optional_format_regex, 'A blank pattern means "no format".');
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $fields, array $errors): void
    {
        try {
            $this->service->create([...['country_code' => 'AF', 'code' => 'NATIONAL_ID', 'name' => 'National ID'], ...$fields]);
            $this->fail('Invalid document type was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame($errors, array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('identity_document_types', 0);
    }

    public static function invalidInput(): array
    {
        return [
            'unknown country' => [['country_code' => 'ZZ'], ['country_code']],
            'lower-case code' => [['code' => 'national_id'], ['code']],
            'code starting with digit' => [['code' => '1ID'], ['code']],
            'code too long' => [['code' => 'A'.str_repeat('B', 40)], ['code']],
            'blank name' => [['name' => ' '], ['name']],
            'name too long' => [['name' => str_repeat('ت', 121)], ['name']],
            'hidden direction override' => [['name' => "ID\u{202E}"], ['name']],
            'ReDoS pattern' => [['optional_format_regex' => '(a+)+'], ['optional_format_regex']],
            'backreference pattern' => [['optional_format_regex' => '(\d)\1'], ['optional_format_regex']],
            'anchored pattern' => [['optional_format_regex' => '^\d+$'], ['optional_format_regex']],
            'pattern too long' => [['optional_format_regex' => str_repeat('a', 201)], ['optional_format_regex']],
        ];
    }

    public function test_code_is_unique_within_its_country_only(): void
    {
        $this->make('AF', 'NATIONAL_ID', 'National ID');
        $this->make('PK', 'NATIONAL_ID', 'CNIC');

        $this->expectValidationError('code', fn () => $this->make('AF', 'NATIONAL_ID', 'Duplicate'));
    }

    public function test_name_and_pattern_are_editable_but_identifiers_are_not(): void
    {
        $type = $this->make('AF', 'NATIONAL_ID', 'National ID', '\d{13}');

        $this->service->update($type->id, ['name' => 'Tazkira', 'optional_format_regex' => '\d{4}-\d{4}-\d{5}']);
        $type->refresh();
        $this->assertSame('Tazkira', $type->name);
        $this->assertSame('\d{4}-\d{4}-\d{5}', $type->optional_format_regex);

        $this->service->update($type->id, ['name' => 'Tazkira', 'optional_format_regex' => null]);
        $this->assertNull($type->refresh()->optional_format_regex);

        foreach ([['country_code' => 'PK'], ['code' => 'OTHER'], ['is_active' => false]] as $change) {
            $this->expectValidationError(array_key_first($change), fn () => $this->service->update($type->id, ['name' => 'Tazkira', ...$change]));
        }
        $this->expectValidationError('optional_format_regex', fn () => $this->service->update($type->id, ['name' => 'Tazkira', 'optional_format_regex' => '(a*)*']));

        foreach (['country_code' => 'PK', 'code' => 'OTHER'] as $attribute => $value) {
            try {
                $type->{$attribute} = $value;
                $this->fail("{$attribute} was changed.");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** Acceptance focus: no identity document is ever mandatory to create a patient. */
    public function test_an_empty_document_number_is_always_acceptable(): void
    {
        $strict = $this->make('AF', 'NATIONAL_ID', 'National ID', '\d{4}-\d{4}-\d{5}')->refresh();
        $free = $this->make('AF', 'OTHER_ID', 'Other', null)->refresh();

        foreach ([null, '', '   '] as $none) {
            $this->assertTrue($strict->accepts($none), 'No document means nothing to check.');
            $this->assertTrue($free->accepts($none));
        }

        $this->assertTrue($strict->accepts(' 1400-0101-12345 '));
        $this->assertFalse($strict->accepts('1400010112345'));
        $this->assertTrue($free->accepts('anything at all'));
    }

    public function test_lifecycle_and_selection_for_patient_forms(): void
    {
        $tazkira = $this->make('AF', 'NATIONAL_ID', 'Tazkira');
        $this->make('AF', 'PASSPORT', 'Passport');
        $this->make('PK', 'CNIC', 'CNIC');

        $selectable = fn (): array => IdentityDocumentType::query()->selectable('AF')->orderBy('code')->pluck('code')->all();
        $this->assertSame(['NATIONAL_ID', 'PASSPORT'], $selectable());

        $this->service->deactivate($tazkira->id);
        $this->service->deactivate($tazkira->id);
        $this->assertSame(['PASSPORT'], $selectable());
        $this->assertDatabaseHas('identity_document_types', ['id' => $tazkira->id, 'name' => 'Tazkira', 'is_active' => false]);

        $this->service->activate($tazkira->id);
        $this->assertSame(['NATIONAL_ID', 'PASSPORT'], $selectable());
    }

    private function make(string $country, string $code, string $name, ?string $pattern = null): IdentityDocumentType
    {
        return $this->service->create(['country_code' => $country, 'code' => $code, 'name' => $name, 'optional_format_regex' => $pattern]);
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
