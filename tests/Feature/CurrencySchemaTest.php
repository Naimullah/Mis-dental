<?php

namespace Tests\Feature;

use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CurrencyDatabaseTestCase;

class CurrencySchemaTest extends CurrencyDatabaseTestCase
{
    public function test_schema_has_exactly_five_columns_with_expected_nullability(): void
    {
        $columns = collect(Schema::getColumns('currencies'))->keyBy('name');

        $this->assertEqualsCanonicalizing(
            ['code', 'english_name', 'symbol', 'minor_units', 'is_active'],
            $columns->keys()->all(),
        );
        $this->assertTrue($columns['symbol']['nullable']);
        foreach (['code', 'english_name', 'minor_units', 'is_active'] as $name) {
            $this->assertFalse($columns[$name]['nullable']);
        }

        $primary = collect(Schema::getIndexes('currencies'))->firstWhere('primary', true);
        $this->assertSame(['code'], $primary['columns'] ?? []);
    }

    public function test_multiple_currencies_can_have_null_symbol(): void
    {
        DB::table('currencies')->insert([
            ['code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => null, 'minor_units' => 2, 'is_active' => true],
            ['code' => 'XAU', 'english_name' => 'Gold', 'symbol' => null, 'minor_units' => 0, 'is_active' => false],
        ]);

        $this->assertSame(2, DB::table('currencies')->whereNull('symbol')->count());
    }

    #[DataProvider('invalidRows')]
    public function test_database_rejects_invalid_rows(array $overrides): void
    {
        DB::table('currencies')->insert([
            'code' => 'AFN', 'english_name' => 'Afghani', 'symbol' => '؋', 'minor_units' => 2, 'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        DB::table('currencies')->insert(array_replace([
            'code' => 'USD', 'english_name' => 'US Dollar', 'symbol' => '$', 'minor_units' => 2, 'is_active' => false,
        ], $overrides));
    }

    public static function invalidRows(): array
    {
        return [
            'duplicate code' => [['code' => 'AFN']],
            'null code' => [['code' => null]],
            'null name' => [['english_name' => null]],
            'null minor units' => [['minor_units' => null]],
            'null status' => [['is_active' => null]],
        ];
    }

    public function test_model_declares_key_casts_timestamps_and_fillable_attributes(): void
    {
        $currency = new Currency;

        $this->assertSame('code', $currency->getKeyName());
        $this->assertSame('string', $currency->getKeyType());
        $this->assertFalse($currency->getIncrementing());
        $this->assertFalse($currency->usesTimestamps());
        $this->assertSame('integer', $currency->getCasts()['minor_units'] ?? null);
        $this->assertSame('boolean', $currency->getCasts()['is_active'] ?? null);
        $this->assertEqualsCanonicalizing(
            ['english_name', 'symbol', 'minor_units', 'is_active'],
            $currency->getFillable(),
        );
    }

    public function test_model_persists_string_key_multibyte_symbol_integer_units_and_boolean_status(): void
    {
        $currency = new Currency([
            'english_name' => 'UAE Dirham', 'symbol' => 'د.إ', 'minor_units' => '2', 'is_active' => false,
        ]);
        $currency->code = 'AED';
        $currency->save();

        $currency = Currency::query()->findOrFail('AED');
        $this->assertSame('AED', $currency->getKey());
        $this->assertSame('د.إ', $currency->symbol);
        $this->assertSame(2, $currency->minor_units);
        $this->assertFalse($currency->is_active);

        $currency->update(['is_active' => true, 'symbol' => null]);
        $currency->refresh();
        $this->assertTrue($currency->is_active);
        $this->assertNull($currency->symbol);
    }

    public function test_model_prevents_changing_persisted_code(): void
    {
        $currency = new Currency(['english_name' => 'Afghani', 'minor_units' => 2, 'is_active' => true]);
        $this->assertFalse($currency->isFillable('code'));
        $currency->code = 'AFN';
        $currency->save();

        $this->expectException(LogicException::class);
        $currency->code = 'USD';
    }
}
