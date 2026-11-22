<?php

namespace Tests\Feature;

use App\Models\TaxCode;
use App\Services\TaxCodes\TaxCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

/** P01.10 versioning rules. */
class TaxCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaxCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'XX', 'iso3' => 'XXX', 'english_name' => 'Inactive land', 'is_active' => false],
        ]);
        $this->service = app(TaxCodeService::class);
    }

    public function test_create_normalizes_code_and_stores_the_rate_as_a_five_place_decimal(): void
    {
        $taxCode = $this->create(['code' => ' vat_std ', 'rate_percent' => '2.5']);

        $this->assertSame('VAT_STD', $taxCode->code);
        $this->assertSame('2.50000', $taxCode->fresh()->rate_percent);
        $this->assertNull($taxCode->effective_to);
    }

    public function test_rate_must_be_between_0_and_100_with_at_most_five_decimals(): void
    {
        foreach (['0', '100', '100.00000', '99.99999', '0.00001'] as $index => $rate) {
            $this->create(['code' => 'OK'.$index, 'rate_percent' => $rate]);
        }

        foreach (['-1', '100.00001', '101', '1.234567', '1e2', ' ', 'abc', '10,5', '+5'] as $rate) {
            $this->assertInvalid(fn () => $this->create(['code' => 'BAD', 'rate_percent' => $rate]), 'rate_percent');
        }

        $this->assertInvalid(fn () => $this->create(['code' => 'FLOAT', 'rate_percent' => 10.5]), 'rate_percent');
    }

    public function test_country_must_exist_and_be_active(): void
    {
        $this->assertInvalid(fn () => $this->create(['country_code' => 'ZZ']), 'country_code');
        $this->assertInvalid(fn () => $this->create(['country_code' => 'XX']), 'country_code');
    }

    public function test_dates_are_validated_and_the_end_is_not_before_the_start(): void
    {
        $this->assertInvalid(fn () => $this->create(['effective_from' => '2026-02-30']), 'effective_from');
        $this->assertInvalid(fn () => $this->create(['effective_from' => '01/02/2026']), 'effective_from');
        $this->assertInvalid(fn () => $this->create(['effective_from' => '2026-05-01', 'effective_to' => '2026-04-30']), 'effective_to');

        $oneDay = $this->create(['effective_from' => '2026-05-01', 'effective_to' => '2026-05-01']);
        $this->assertSame('2026-05-01', $oneDay->effective_to);
    }

    public function test_versions_of_one_code_cannot_overlap(): void
    {
        $this->create(['effective_from' => '2026-01-01', 'effective_to' => '2026-12-31']);

        $this->assertInvalid(fn () => $this->create(['effective_from' => '2026-12-31']), 'effective_from');
        $this->assertInvalid(fn () => $this->create(['effective_from' => '2025-01-01', 'effective_to' => '2026-01-01']), 'effective_from');
        $this->assertInvalid(fn () => $this->create(['effective_from' => '2025-06-01']), 'effective_from'); // open-ended covers it

        $this->create(['effective_from' => '2027-01-01']);
        $this->create(['effective_from' => '2025-01-01', 'effective_to' => '2025-12-31']);
        $this->create(['code' => 'OTHER', 'effective_from' => '2026-06-01']); // other codes are independent

        $this->assertSame(3, TaxCode::query()->where('code', 'VAT_STD')->count());
    }

    public function test_new_version_closes_the_open_version_the_day_before(): void
    {
        $first = $this->create(['rate_percent' => '10', 'effective_from' => '2026-01-01']);

        $second = $this->service->addVersion($first->id, ['rate_percent' => '12.5', 'effective_from' => '2027-03-01']);

        $this->assertSame('2027-02-28', $first->fresh()->effective_to);
        $this->assertSame('10.00000', $first->fresh()->rate_percent);
        $this->assertSame('12.50000', $second->fresh()->rate_percent);
        $this->assertSame('VAT_STD', $second->code);
        $this->assertSame('Standard tax', $second->description); // carried over when not given
        $this->assertNull($second->effective_to);

        $this->assertSame($first->id, TaxCode::versionOn('AF', 'VAT_STD', '2027-02-28')?->id);
        $this->assertSame($second->id, TaxCode::versionOn('AF', 'VAT_STD', '2027-03-01')?->id);
        $this->assertNull(TaxCode::versionOn('AF', 'VAT_STD', '2025-12-31'));
    }

    public function test_new_version_must_start_after_the_open_version_and_failure_changes_nothing(): void
    {
        $first = $this->create(['effective_from' => '2026-01-01']);

        $this->assertInvalid(fn () => $this->service->addVersion($first->id, ['rate_percent' => '5', 'effective_from' => '2026-01-01']), 'effective_from');
        $this->assertInvalid(fn () => $this->service->addVersion($first->id, ['rate_percent' => '5', 'effective_from' => '2025-01-01']), 'effective_from');

        $this->assertNull($first->fresh()->effective_to);
        $this->assertDatabaseCount('tax_codes', 1);
    }

    public function test_close_sets_the_end_once(): void
    {
        $taxCode = $this->create(['effective_from' => '2026-01-01']);

        $this->assertInvalid(fn () => $this->service->close($taxCode->id, ['effective_to' => '2025-12-31']), 'effective_to');
        $this->service->close($taxCode->id, ['effective_to' => '2026-06-30']);
        $this->assertSame('2026-06-30', $taxCode->fresh()->effective_to);

        $this->assertInvalid(fn () => $this->service->close($taxCode->id, ['effective_to' => '2026-12-31']), 'effective_to');
        $this->assertSame('2026-06-30', $taxCode->fresh()->effective_to);
    }

    public function test_only_the_description_can_be_edited(): void
    {
        $taxCode = $this->create();

        $this->service->updateDescription($taxCode->id, ['description' => '  Standard  VAT ']);
        $this->assertSame('Standard  VAT', $taxCode->fresh()->description);

        foreach (['rate_percent' => '5', 'effective_from' => '2020-01-01', 'code' => 'NEW', 'effective_to' => '2030-01-01'] as $field => $value) {
            $this->assertInvalid(fn () => $this->service->updateDescription($taxCode->id, ['description' => 'X', $field => $value]), $field);
        }

        $this->assertInvalid(fn () => $this->service->updateDescription($taxCode->id, ['description' => "Bad\u{202E}text"]), 'description');
    }

    public function test_model_blocks_changes_to_snapshot_fields_reopening_and_deletion(): void
    {
        $taxCode = $this->create();

        foreach (['rate_percent' => '11', 'effective_from' => '2020-01-01', 'code' => 'NEW', 'country_code' => 'XX'] as $field => $value) {
            $fresh = $taxCode->fresh();
            $fresh->{$field} = $value;

            try {
                $fresh->save();
                $this->fail("{$field} was changed.");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $closed = $taxCode->fresh();
        $closed->effective_to = '2026-12-31';
        $closed->save();

        $closed->effective_to = null;
        $this->expectException(LogicException::class);
        $closed->save();
    }

    public function test_model_refuses_delete(): void
    {
        $taxCode = $this->create();

        $this->expectException(LogicException::class);
        $taxCode->delete();
    }

    public function test_writes_run_in_a_transaction(): void
    {
        $level = DB::transactionLevel();
        $seen = null;
        TaxCode::creating(function () use (&$seen): void {
            $seen = DB::transactionLevel();
        });

        $this->create();

        $this->assertSame($level + 1, $seen);
    }

    /** @param array<string, mixed> $overrides */
    private function create(array $overrides = []): TaxCode
    {
        return $this->service->create([
            'country_code' => 'AF',
            'code' => 'VAT_STD',
            'description' => 'Standard tax',
            'rate_percent' => '10',
            'effective_from' => '2026-01-01',
            ...$overrides,
        ]);
    }

    private function assertInvalid(callable $write, string $field): void
    {
        try {
            $write();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
