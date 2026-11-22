<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TaxCodes\TaxCodeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/** P02.05 translated names and explanations of tax code versions. */
class TaxCodeTranslationTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/platform/master-data/tax-codes';

    private const TABLE = 'tax_code_translations';

    private int $vat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        $this->withoutVite();
        Carbon::setTestNow('2026-06-15 10:00:00');

        DB::table('countries')->insert(['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true]);
        DB::table('locales')->insert([
            ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'fa', 'english_name' => 'Persian', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto', 'text_direction' => 'RTL', 'is_active' => false],
        ]);
        $this->vat = DB::table('tax_codes')->insertGetId([
            'country_code' => 'AF', 'code' => 'VAT_STD', 'description' => 'Standard tax',
            'rate_percent' => '10.00000', 'effective_from' => '2026-01-01', 'effective_to' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_schema_matches_the_specification(): void
    {
        $columns = collect(Schema::getColumns(self::TABLE))->keyBy('name');
        $this->assertEqualsCanonicalizing(['tax_code_id', 'locale_code', 'display_name', 'explanation'], $columns->keys()->all());
        $this->assertTrue($columns['explanation']['nullable']);
        $this->assertFalse($columns['display_name']['nullable']);

        $indexes = collect(Schema::getIndexes(self::TABLE));
        $this->assertSame(['tax_code_id', 'locale_code'], $indexes->firstWhere('primary', true)['columns'] ?? []);

        $foreign = collect(Schema::getForeignKeys(self::TABLE))->keyBy(fn (array $key): string => $key['columns'][0]);
        $this->assertSame(['tax_codes', ['id']], [$foreign['tax_code_id']['foreign_table'], $foreign['tax_code_id']['foreign_columns']]);
        $this->assertSame(['locales', ['code']], [$foreign['locale_code']['foreign_table'], $foreign['locale_code']['foreign_columns']]);

        $this->row($this->vat, 'fa-AF', 'Name');
        foreach ([fn () => $this->row($this->vat, 'fa-AF', 'Again'), fn () => $this->row($this->vat, 'xx', 'Unknown'), fn () => $this->row(999, 'fa', 'Orphan')] as $insert) {
            try {
                $insert();
                $this->fail('Invalid translation row was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(QueryException::class);
        DB::table('locales')->where('code', 'fa-AF')->delete();
    }

    public function test_access_is_closed_without_the_switch(): void
    {
        $url = self::BASE."/{$this->vat}/translations";
        $this->put($url, [])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        config(['master_data.open_access' => false]);
        $this->put($url, ['translations' => ['fa-AF' => ['display_name' => 'X']]])->assertForbidden();
        $this->assertDatabaseCount(self::TABLE, 0);
    }

    public function test_translations_are_validated_saved_and_removed(): void
    {
        $this->openAccess();
        $url = self::BASE."/{$this->vat}/translations";

        $this->putJson($url, ['translations' => ['fa-AF' => ['display_name' => str_repeat('a', 141)]]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa-AF.display_name');
        $this->putJson($url, ['translations' => ['fa-AF' => ['display_name' => 'Name', 'help_text' => str_repeat('b', 4001)]]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa-AF.help_text');
        $this->putJson($url, ['translations' => ['fa-AF' => ['display_name' => "Bad\u{202E}name"]]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa-AF.display_name');
        $this->putJson($url, ['translations' => ['fa-AF' => ['display_name' => '', 'help_text' => 'Orphan explanation']]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.fa-AF.display_name');
        $this->putJson($url, ['translations' => ['ps-AF' => ['display_name' => 'Disabled']]])
            ->assertUnprocessable()->assertJsonValidationErrors('translations.ps-AF');
        $this->assertDatabaseCount(self::TABLE, 0);

        $this->put($url, ['translations' => [
            'fa-AF' => ['display_name' => str_repeat('a', 140), 'help_text' => "Line one\r\nLine two"],
            'fa' => ['display_name' => 'Base name', 'help_text' => ''],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas(self::TABLE, ['tax_code_id' => $this->vat, 'locale_code' => 'fa-AF', 'display_name' => str_repeat('a', 140), 'explanation' => "Line one\nLine two"]);
        $this->assertDatabaseHas(self::TABLE, ['tax_code_id' => $this->vat, 'locale_code' => 'fa', 'display_name' => 'Base name', 'explanation' => null]);

        $this->put($url, ['translations' => ['fa' => ['display_name' => '', 'help_text' => '']]])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing(self::TABLE, ['tax_code_id' => $this->vat, 'locale_code' => 'fa']);
        $this->assertDatabaseCount(self::TABLE, 1);
    }

    public function test_page_resolves_exact_then_base_language_and_hides_disabled_languages(): void
    {
        $this->openAccess();
        $this->row($this->vat, 'fa', 'Base name', 'Base explanation');
        $this->row($this->vat, 'ps-AF', 'Hidden Pashto');
        $this->post('/locale', ['locale' => 'fa-AF']);

        // No exact fa-AF row yet: the base language is used, with its own explanation.
        $this->get(self::BASE.'?country=AF')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/master-data/tax-codes/index')
            ->where('translationData.localized.'.$this->vat, ['display_name' => 'Base name', 'help_text' => 'Base explanation'])
            ->where('translationData.translations.'.$this->vat, ['fa' => ['display_name' => 'Base name', 'help_text' => 'Base explanation']])
            ->has('translationData.locales', 2));

        // The exact row wins; its missing explanation is not borrowed from another language.
        $this->row($this->vat, 'fa-AF', 'Exact name');
        $this->get(self::BASE.'?country=AF')->assertInertia(fn (Assert $page) => $page
            ->where('translationData.localized.'.$this->vat, ['display_name' => 'Exact name', 'help_text' => null]));
    }

    public function test_untranslated_rows_fall_back_to_the_description(): void
    {
        $this->openAccess();

        $this->get(self::BASE.'?country=AF')->assertInertia(fn (Assert $page) => $page
            ->where('taxCodes.data.0.description', 'Standard tax')
            ->where('translationData.localized', []));
    }

    public function test_a_new_version_copies_the_translations_of_the_version_it_replaces(): void
    {
        $this->row($this->vat, 'fa', 'Base name', "Explanation\nline two");
        $this->row($this->vat, 'ps-AF', 'Kept although disabled');

        $next = app(TaxCodeService::class)->addVersion($this->vat, ['rate_percent' => '12', 'effective_from' => '2027-01-01']);

        $copied = DB::table(self::TABLE)->where('tax_code_id', $next->id)->orderBy('locale_code')->get(['locale_code', 'display_name', 'explanation'])
            ->map(fn (object $row): array => (array) $row)->all();
        $this->assertSame([
            ['locale_code' => 'fa', 'display_name' => 'Base name', 'explanation' => "Explanation\nline two"],
            ['locale_code' => 'ps-AF', 'display_name' => 'Kept although disabled', 'explanation' => null],
        ], $copied);
        $this->assertSame(2, DB::table(self::TABLE)->where('tax_code_id', $this->vat)->count());

        // Each version's translations are then independent.
        $this->openAccess();
        $this->put(self::BASE."/{$next->id}/translations", ['translations' => ['fa' => ['display_name' => 'New name']]])->assertSessionHasNoErrors();
        $this->assertSame('Base name', DB::table(self::TABLE)->where(['tax_code_id' => $this->vat, 'locale_code' => 'fa'])->value('display_name'));
    }

    public function test_ended_versions_can_still_be_translated(): void
    {
        $this->openAccess();
        DB::table('tax_codes')->where('id', $this->vat)->update(['effective_to' => '2026-03-31']);

        $this->put(self::BASE."/{$this->vat}/translations", ['translations' => ['fa-AF' => ['display_name' => 'Old name']]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas(self::TABLE, ['tax_code_id' => $this->vat, 'locale_code' => 'fa-AF']);
    }

    public function test_xlsx_export_adds_the_name_in_the_interface_language(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is not installed.');
        }
        $this->openAccess();
        $this->row($this->vat, 'fa-AF', 'نام دری');
        $this->post('/locale', ['locale' => 'fa-AF']);

        $response = $this->get(self::BASE.'/export/xlsx?country=AF')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('Standard tax', $sheet->getCell('C2')->getValue());
        $this->assertSame('نام دری', $sheet->getCell('D2')->getValue());
        $this->assertSame('10.00000', $sheet->getCell('E2')->getValue());
    }

    private function openAccess(): void
    {
        config(['master_data.open_access' => true]);
        $this->actingAs(User::factory()->create());
    }

    private function row(int $taxCodeId, string $locale, string $name, ?string $explanation = null): void
    {
        DB::table(self::TABLE)->insert([
            'tax_code_id' => $taxCodeId, 'locale_code' => $locale, 'display_name' => $name, 'explanation' => $explanation,
        ]);
    }
}
