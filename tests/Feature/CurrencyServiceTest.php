<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Services\Currencies\CurrencyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\CurrencyDatabaseTestCase;

class CurrencyServiceTest extends CurrencyDatabaseTestCase
{
    private CurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CurrencyService;
    }

    public function test_create_trims_input_and_stores_blank_symbol_as_null(): void
    {
        $currency = $this->service->create([
            'code' => 'AFN', 'english_name' => '  Afghani  ', 'symbol' => " \u{00A0}", 'minor_units' => 2, 'is_active' => false,
        ])->refresh();

        $this->assertSame('AFN', $currency->getKey());
        $this->assertSame('Afghani', $currency->english_name);
        $this->assertNull($currency->symbol);
        $this->assertSame(2, $currency->minor_units);
        $this->assertFalse($currency->is_active);
    }

    public function test_create_rejects_invalid_input_and_duplicate_code(): void
    {
        $this->service->create([
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);

        foreach ([
            [
                ['code' => 'afn', 'english_name' => ' ', 'symbol' => str_repeat('€', 13), 'minor_units' => 5, 'is_active' => 'unknown'],
                ['code', 'english_name', 'symbol', 'minor_units', 'is_active'],
            ],
            [
                ['code' => 'AFN', 'english_name' => 'Duplicate', 'symbol' => null, 'minor_units' => 2, 'is_active' => true],
                ['code'],
            ],
        ] as [$input, $fields]) {
            try {
                $this->service->create($input);
                $this->fail('Invalid input was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame($fields, array_keys($exception->errors()));
            }
        }

        $this->assertDatabaseCount('currencies', 1);
        $this->assertDatabaseHas('currencies', ['code' => 'AFN', 'english_name' => 'Afghani']);
    }

    public function test_update_keeps_code_preserves_omitted_symbol_and_can_clear_it(): void
    {
        $this->service->create([
            'code' => 'USD', 'english_name' => 'US Dollar', 'symbol' => '$', 'minor_units' => 2, 'is_active' => true,
        ]);
        $details = ['english_name' => ' United States Dollar ', 'minor_units' => '3', 'is_active' => false];

        $currency = $this->service->update('USD', $details)->refresh();
        $this->assertSame('$', $currency->symbol);
        $this->assertSame('United States Dollar', $currency->english_name);
        $this->assertSame(3, $currency->minor_units);
        $this->assertFalse($currency->is_active);

        $currency = $this->service->update('USD', $details + ['symbol' => null])->refresh();
        $this->assertSame('USD', $currency->getKey());
        $this->assertNull($currency->symbol);
    }

    public function test_update_rejects_code_changes_and_invalid_minor_units(): void
    {
        $this->service->create([
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);

        try {
            $this->service->update('AFN', [
                'code' => 'USD', 'english_name' => 'Changed', 'minor_units' => 9, 'is_active' => false,
            ]);
            $this->fail('Invalid update was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['code', 'minor_units'], array_keys($exception->errors()));
        }

        $this->assertDatabaseHas('currencies', [
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);
        $this->assertDatabaseCount('currencies', 1);
    }

    public function test_status_changes_are_repeatable_and_preserve_the_currency(): void
    {
        $this->service->create([
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);

        $this->assertFalse($this->service->deactivate('AFN')->refresh()->is_active);
        $this->assertFalse($this->service->deactivate('AFN')->refresh()->is_active);
        $this->assertTrue($this->service->activate('AFN')->refresh()->is_active);
        $this->assertTrue($this->service->activate('AFN')->refresh()->is_active);
        $this->assertDatabaseHas('currencies', [
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);
        $this->assertDatabaseCount('currencies', 1);
    }

    public function test_status_change_fails_for_a_missing_currency(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->activate('AFN');
    }

    public function test_update_fails_for_a_missing_currency(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->update('AFN', ['english_name' => 'Afghani', 'minor_units' => 2, 'is_active' => true]);
    }

    public function test_unique_constraint_failure_is_reported_and_rolls_back(): void
    {
        // Insert after validation so the database, rather than the validator, detects the conflict.
        Currency::creating(function (): void {
            DB::table('currencies')->insert([
                'code' => 'AFN', 'english_name' => 'Conflict', 'symbol' => null, 'minor_units' => 2, 'is_active' => true,
            ]);
        });

        try {
            $this->service->create([
                'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
            ]);
            $this->fail('A database conflict was not reported.');
        } catch (ValidationException $exception) {
            $this->assertSame(['code'], array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('currencies', 0);
        $this->assertSame(0, DB::transactionLevel());
    }
}
