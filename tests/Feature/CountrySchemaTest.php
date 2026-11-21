<?php

namespace Tests\Feature;

use App\Http\Requests\CountryRequest;
use App\Http\Requests\StoreCountryRequest;
use App\Http\Requests\UpdateCountryRequest;
use App\Models\Country;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CountryDatabaseTestCase;

class CountrySchemaTest extends CountryDatabaseTestCase
{
    public function test_schema_has_exactly_four_columns_with_expected_nullability(): void
    {
        $columns = collect(Schema::getColumns('countries'))->keyBy('name');

        $this->assertEqualsCanonicalizing(['iso2', 'iso3', 'english_name', 'is_active'], $columns->keys()->all());
        $this->assertTrue($columns['iso3']['nullable']);
        foreach (['iso2', 'english_name', 'is_active'] as $name) {
            $this->assertFalse($columns[$name]['nullable']);
        }

        $primary = collect(Schema::getIndexes('countries'))->firstWhere('primary', true);
        $this->assertSame(['iso2'], $primary['columns'] ?? []);
    }

    public function test_multiple_countries_can_have_null_iso3(): void
    {
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => null, 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'DE', 'iso3' => null, 'english_name' => 'Germany', 'is_active' => false],
        ]);

        $this->assertSame(2, DB::table('countries')->whereNull('iso3')->count());
    }

    #[DataProvider('invalidRows')]
    public function test_database_rejects_invalid_rows(array $overrides): void
    {
        DB::table('countries')->insert([
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        DB::table('countries')->insert(array_replace([
            'iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Germany', 'is_active' => false,
        ], $overrides));
    }

    public static function invalidRows(): array
    {
        return [
            'duplicate ISO2' => [['iso2' => 'AF']],
            'duplicate ISO3' => [['iso3' => 'AFG']],
            'null ISO2' => [['iso2' => null]],
            'null name' => [['english_name' => null]],
            'null status' => [['is_active' => null]],
        ];
    }

    public function test_model_declares_key_cast_timestamps_and_fillable_attributes(): void
    {
        $country = new Country;

        $this->assertSame('iso2', $country->getKeyName());
        $this->assertSame('string', $country->getKeyType());
        $this->assertFalse($country->getIncrementing());
        $this->assertFalse($country->usesTimestamps());
        $this->assertSame('boolean', $country->getCasts()['is_active'] ?? null);
        $this->assertEqualsCanonicalizing(['iso3', 'english_name', 'is_active'], $country->getFillable());
    }

    public function test_model_persists_string_key_unicode_name_and_boolean_status(): void
    {
        $country = new Country(['iso3' => 'AFG', 'english_name' => 'افغانستان', 'is_active' => false]);
        $country->iso2 = 'AF';
        $country->save();

        $country = Country::query()->findOrFail('AF');
        $this->assertSame('AF', $country->getKey());
        $this->assertSame('افغانستان', $country->english_name);
        $this->assertFalse($country->is_active);

        $country->update(['is_active' => true]);
        $this->assertTrue($country->refresh()->is_active);
    }

    public function test_model_prevents_changing_persisted_iso2(): void
    {
        $country = new Country(['english_name' => 'Afghanistan', 'is_active' => true]);
        $this->assertFalse($country->isFillable('iso2'));
        $country->iso2 = 'AF';
        $country->save();

        $this->expectException(LogicException::class);
        $country->iso2 = 'DE';
    }

    #[DataProvider('countryRequests')]
    public function test_country_requests_deny_authorization(string $requestClass): void
    {
        $this->expectException(AuthorizationException::class);

        $requestClass::create('/', 'POST')->setContainer($this->app)->validateResolved();
    }

    public static function countryRequests(): array
    {
        return [[StoreCountryRequest::class], [UpdateCountryRequest::class]];
    }

    public function test_store_accepts_trimmed_unicode_name_false_and_nullable_iso3(): void
    {
        $name = str_repeat('é', 120);
        $request = StoreCountryRequest::create('/', 'POST', [
            'iso2' => 'AF',
            'iso3' => null,
            'english_name' => "  {$name}  ",
            'is_active' => false,
        ]);

        $this->assertSame([
            'iso2' => 'AF',
            'iso3' => null,
            'english_name' => $name,
            'is_active' => false,
        ], $this->countryValidator($request)->validated());
    }

    public function test_store_rejects_missing_and_invalid_fields(): void
    {
        $missing = $this->countryValidator(StoreCountryRequest::create('/', 'POST'));
        $this->assertSame(['iso2', 'english_name', 'is_active'], $missing->errors()->keys());

        foreach ([
            ['af', 'afg', "\u{00A0}\t", 'invalid'],
            ['AFG', 'AF', str_repeat('é', 121), null],
            ['ÅF', 'AFÉ', [], []],
        ] as [$iso2, $iso3, $name, $status]) {
            $request = StoreCountryRequest::create('/', 'POST', [
                'iso2' => $iso2, 'iso3' => $iso3, 'english_name' => $name, 'is_active' => $status,
            ]);

            $this->assertSame(['iso2', 'iso3', 'english_name', 'is_active'], $this->countryValidator($request)->errors()->keys());
        }
    }

    public function test_store_rejects_duplicate_codes(): void
    {
        DB::table('countries')->insert([
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);

        $request = StoreCountryRequest::create('/', 'POST', [
            'iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true,
        ]);

        $this->assertSame(['iso2', 'iso3'], $this->countryValidator($request)->errors()->keys());
    }

    public function test_update_excludes_only_the_bound_country_and_rejects_iso2_input(): void
    {
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'DE', 'iso3' => 'DEU', 'english_name' => 'Germany', 'is_active' => true],
        ]);

        $country = Country::query()->findOrFail('AF');
        $request = UpdateCountryRequest::create('/countries/AF', 'PUT', [
            'iso3' => 'AFG', 'english_name' => ' Afghanistan ', 'is_active' => false,
        ]);
        $route = (new Route('PUT', '/countries/{country}', []))->bind($request);
        $route->setParameter('country', $country);
        $request->setRouteResolver(fn () => $route);

        $this->assertSame('Afghanistan', $this->countryValidator($request)->validated()['english_name']);

        $request->merge(['iso3' => null]);
        $this->assertNull($this->countryValidator($request)->validated()['iso3']);

        $request->merge(['iso3' => 'DEU', 'iso2' => 'DE', 'country' => 'DE']);
        $this->assertSame(['iso2', 'iso3'], $this->countryValidator($request)->errors()->keys());

        $request->merge(['iso3' => null, 'iso2' => null]);
        $this->assertSame(['iso2'], $this->countryValidator($request)->errors()->keys());
    }

    private function countryValidator(CountryRequest $request): Validator
    {
        $request->setContainer($this->app);

        // Exercise Form Request preparation and validation independently of the authorization gate.
        return (function (): Validator {
            $this->prepareForValidation();

            return $this->createDefaultValidator(app(Factory::class));
        })->call($request);
    }
}
