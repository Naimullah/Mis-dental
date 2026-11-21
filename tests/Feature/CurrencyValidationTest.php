<?php

namespace Tests\Feature;

use App\Http\Requests\CurrencyRequest;
use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CurrencyDatabaseTestCase;

class CurrencyValidationTest extends CurrencyDatabaseTestCase
{
    #[DataProvider('currencyRequests')]
    public function test_currency_requests_deny_authorization(string $requestClass): void
    {
        $this->expectException(AuthorizationException::class);

        $requestClass::create('/', 'POST')->setContainer($this->app)->validateResolved();
    }

    public static function currencyRequests(): array
    {
        return [[StoreCurrencyRequest::class], [UpdateCurrencyRequest::class]];
    }

    public function test_store_accepts_trimmed_unicode_name_and_symbol(): void
    {
        $name = str_repeat('é', 100);
        $request = StoreCurrencyRequest::create('/', 'POST', [
            'code' => 'AFN',
            'english_name' => "  {$name}  ",
            'symbol' => " ؋\u{00A0}",
            'minor_units' => 2,
            'is_active' => false,
        ]);

        $this->assertSame([
            'code' => 'AFN',
            'english_name' => $name,
            'symbol' => '؋',
            'minor_units' => 2,
            'is_active' => false,
        ], $this->currencyValidator($request)->validated());
    }

    public function test_store_converts_blank_symbol_to_null_and_accepts_twelve_multibyte_characters(): void
    {
        $blank = $this->currencyValidator($this->storeRequest(['symbol' => " \t\u{00A0}"]));
        $this->assertNull($blank->validated()['symbol']);

        $long = $this->currencyValidator($this->storeRequest(['symbol' => str_repeat('€', 12)]));
        $this->assertSame(str_repeat('€', 12), $long->validated()['symbol']);
    }

    #[DataProvider('validMinorUnits')]
    public function test_store_accepts_minor_units_from_zero_to_four(int|string $units): void
    {
        $this->assertFalse($this->currencyValidator($this->storeRequest(['minor_units' => $units]))->fails());
    }

    public static function validMinorUnits(): array
    {
        return ['zero' => [0], 'two' => [2], 'three as string' => ['3'], 'four' => [4]];
    }

    #[DataProvider('invalidMinorUnits')]
    public function test_store_rejects_minor_units_outside_zero_to_four_or_not_integer(mixed $units): void
    {
        $validator = $this->currencyValidator($this->storeRequest(['minor_units' => $units]));

        $this->assertSame(['minor_units'], $validator->errors()->keys());
    }

    public static function invalidMinorUnits(): array
    {
        return [
            'negative' => [-1],
            'five' => [5],
            'smallint overflow' => [40000],
            'decimal' => [2.5],
            'decimal string' => ['2.5'],
            'word' => ['two'],
            'array' => [[]],
        ];
    }

    public function test_store_rejects_missing_and_invalid_fields(): void
    {
        $missing = $this->currencyValidator(StoreCurrencyRequest::create('/', 'POST'));
        $this->assertSame(['code', 'english_name', 'minor_units', 'is_active'], $missing->errors()->keys());

        foreach ([
            ['usd', "\u{00A0}\t", str_repeat('€', 13), 'invalid'],
            ['USDX', str_repeat('é', 101), 12345, null],
            ['ÅFN', [], [], []],
            ['US', 123, ['$'], 'yes'],
        ] as [$code, $name, $symbol, $status]) {
            $request = StoreCurrencyRequest::create('/', 'POST', [
                'code' => $code, 'english_name' => $name, 'symbol' => $symbol, 'minor_units' => 2, 'is_active' => $status,
            ]);

            $this->assertSame(['code', 'english_name', 'symbol', 'is_active'], $this->currencyValidator($request)->errors()->keys());
        }
    }

    public function test_store_rejects_duplicate_code(): void
    {
        DB::table('currencies')->insert([
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);

        $this->assertSame(['code'], $this->currencyValidator($this->storeRequest(['code' => 'AFN']))->errors()->keys());
    }

    public function test_update_validates_fields_and_rejects_any_code_input(): void
    {
        $request = UpdateCurrencyRequest::create('/currencies/AFN', 'PUT', [
            'english_name' => ' Afghani ', 'symbol' => '', 'minor_units' => '2', 'is_active' => true,
        ]);

        $this->assertSame([
            'english_name' => 'Afghani', 'symbol' => null, 'minor_units' => '2', 'is_active' => true,
        ], $this->currencyValidator($request)->validated());

        $request->merge(['code' => 'USD']);
        $this->assertSame(['code'], $this->currencyValidator($request)->errors()->keys());

        $request->merge(['code' => null]);
        $this->assertSame(['code'], $this->currencyValidator($request)->errors()->keys());
    }

    /** @param array<string, mixed> $overrides */
    private function storeRequest(array $overrides = []): StoreCurrencyRequest
    {
        return StoreCurrencyRequest::create('/', 'POST', array_replace([
            'code' => 'USD', 'english_name' => 'US Dollar', 'symbol' => '$', 'minor_units' => 2, 'is_active' => true,
        ], $overrides));
    }

    private function currencyValidator(CurrencyRequest $request): Validator
    {
        $request->setContainer($this->app);

        // Exercise Form Request preparation and validation independently of the authorization gate.
        return (function (): Validator {
            $this->prepareForValidation();

            return $this->createDefaultValidator(app(Factory::class));
        })->call($request);
    }
}
