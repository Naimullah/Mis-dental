<?php

namespace Tests\Feature;

use App\Models\AdministrativeArea;
use App\Services\AdministrativeAreas\AdministrativeAreaService;
use App\Services\AdministrativeAreas\AdministrativeAreaTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** P01.05 hierarchy rules and P01.06 translations and fallback. */
class AdministrativeAreaServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdministrativeAreaService $areas;

    private AdministrativeAreaTranslationService $translations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        DB::table('countries')->insert([
            ['iso2' => 'AF', 'iso3' => 'AFG', 'english_name' => 'Afghanistan', 'is_active' => true],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'english_name' => 'Pakistan', 'is_active' => true],
        ]);
        DB::table('locales')->insert([
            ['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR', 'is_active' => true],
            ['code' => 'fa', 'english_name' => 'Persian', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'fa-AF', 'english_name' => 'Dari (Afghanistan)', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto (Afghanistan)', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'de', 'english_name' => 'German', 'text_direction' => 'LTR', 'is_active' => false],
        ]);

        $this->areas = new AdministrativeAreaService;
        $this->translations = new AdministrativeAreaTranslationService;
    }

    public function test_creates_a_trimmed_normalised_hierarchy(): void
    {
        $kabul = $this->make('AF', null, '  Kabul  ', 'province', ' KBL ');
        $child = $this->make('AF', $kabul->id, "Pagh\u{0061}\u{0301}n", 'district'); // a + combining accent

        $this->assertSame('Kabul', $kabul->canonical_name);
        $this->assertSame('KBL', $kabul->area_code);
        $this->assertTrue($kabul->refresh()->is_active);
        $this->assertSame("Pagh\u{00E1}n", $child->canonical_name, 'Names are stored in NFC.');
        $this->assertSame($kabul->id, $child->parent_id);
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_rejected(array $fields, array $errors): void
    {
        try {
            $this->areas->create([...['country_code' => 'AF', 'level_code' => 'province', 'canonical_name' => 'Kabul'], ...$fields]);
            $this->fail('Invalid area was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame($errors, array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('administrative_areas', 0);
    }

    public static function invalidFields(): array
    {
        return [
            'unknown country' => [['country_code' => 'ZZ'], ['country_code']],
            'lower-case country' => [['country_code' => 'af'], ['country_code']],
            'level with spaces' => [['level_code' => 'Province Level'], ['level_code']],
            'upper-case level' => [['level_code' => 'Province'], ['level_code']],
            'blank name' => [['canonical_name' => '   '], ['canonical_name']],
            'name too long' => [['canonical_name' => str_repeat('ک', 161)], ['canonical_name']],
            'hidden direction override' => [['canonical_name' => "Kabul\u{202E}lubaK"], ['canonical_name']],
            'control character' => [['canonical_name' => "Kabul\u{0007}"], ['canonical_name']],
            'bad area code' => [['area_code' => 'K B L'], ['area_code']],
        ];
    }

    public function test_area_code_is_unique_within_a_country_only(): void
    {
        $this->make('AF', null, 'Kabul', 'province', 'KBL');
        $this->make('PK', null, 'Karachi', 'province', 'KBL');

        $this->expectValidationError('area_code', fn () => $this->make('AF', null, 'Other', 'province', 'KBL'));
    }

    public function test_parent_must_be_in_the_same_country(): void
    {
        $kabul = $this->make('AF', null, 'Kabul');

        $this->expectValidationError('parent_id', fn () => $this->make('PK', $kabul->id, 'Cross-border'));
        $this->expectValidationError('parent_id', fn () => $this->make('AF', 99999, 'Missing parent'));
    }

    public function test_moves_cannot_create_cycles_or_self_parents(): void
    {
        $province = $this->make('AF', null, 'Kabul');
        $district = $this->make('AF', $province->id, 'Paghman', 'district');
        $village = $this->make('AF', $district->id, 'Village', 'village');

        $this->expectValidationError('parent_id', fn () => $this->move($province, $province->id));
        $this->expectValidationError('parent_id', fn () => $this->move($province, $village->id));

        $this->move($village, $province->id); // a legal move
        $this->assertSame($province->id, $village->refresh()->parent_id);
    }

    public function test_hierarchy_is_at_most_six_levels_deep(): void
    {
        $parent = null;
        foreach (range(1, AdministrativeArea::MAX_DEPTH) as $level) {
            $parent = $this->make('AF', $parent?->id, "Level {$level}", "level{$level}");
        }

        $this->expectValidationError('parent_id', fn () => $this->make('AF', $parent->id, 'Too deep', 'level7'));

        // Moving a two-level subtree under level 5 would make seven levels.
        $branch = $this->make('AF', null, 'Branch');
        $this->make('AF', $branch->id, 'Leaf', 'district');
        $level5 = AdministrativeArea::query()->where('canonical_name', 'Level 5')->firstOrFail();
        $this->expectValidationError('parent_id', fn () => $this->move($branch, $level5->id));
    }

    public function test_activation_rules_follow_the_hierarchy(): void
    {
        $province = $this->make('AF', null, 'Kabul');
        $district = $this->make('AF', $province->id, 'Paghman', 'district');

        $this->expectValidationError('is_active', fn () => $this->areas->deactivate($province->id));

        $this->areas->deactivate($district->id);
        $this->areas->deactivate($province->id);
        $this->assertFalse($province->refresh()->is_active);

        $this->expectValidationError('is_active', fn () => $this->areas->activate($district->id));
        $this->expectValidationError('parent_id', fn () => $this->make('AF', $province->id, 'New active child', 'district'));

        $inactiveChild = $this->areas->create(['country_code' => 'AF', 'parent_id' => $province->id, 'level_code' => 'district', 'canonical_name' => 'Inactive child', 'is_active' => false]);
        $this->assertFalse($inactiveChild->is_active);

        $this->areas->activate($province->id);
        $this->areas->activate($district->id);
        $this->assertTrue($district->refresh()->is_active);
    }

    public function test_country_cannot_change(): void
    {
        $kabul = $this->make('AF', null, 'Kabul');

        $this->expectValidationError('country_code', fn () => $this->areas->update($kabul->id, [
            'country_code' => 'PK', 'level_code' => 'province', 'canonical_name' => 'Kabul',
        ]));
        $this->assertSame('AF', $kabul->refresh()->country_code);
    }

    public function test_translations_are_synced_normalised_and_safe(): void
    {
        $kabul = $this->make('AF', null, 'Kabul');

        $this->translations->sync($kabul, ['fa-AF' => '  کابل ', 'ps-AF' => 'کابل']);
        $this->assertSame(['fa-AF' => 'کابل', 'ps-AF' => 'کابل'], $this->stored($kabul));

        $this->translations->sync($kabul, ['fa-AF' => 'کابُل', 'ps-AF' => '']);
        $this->assertSame(['fa-AF' => 'کابُل'], $this->stored($kabul), 'Blank removes; one row per locale.');

        $this->expectValidationError('translations.de', fn () => $this->translations->sync($kabul, ['de' => 'Kabul']));
        $this->expectValidationError('translations.fa-AF', fn () => $this->translations->sync($kabul, ['fa-AF' => "کابل\u{202B}"]));
        $this->expectValidationError('translations.fa-AF', fn () => $this->translations->sync($kabul, ['fa-AF' => str_repeat('ک', 181)]));
        $this->expectValidationError('translations', fn () => $this->translations->sync($kabul, ['not a code' => 'x']));

        $this->assertSame(['fa-AF' => 'کابُل'], $this->stored($kabul));
    }

    public function test_display_name_falls_back_to_base_language_then_canonical_name(): void
    {
        $herat = $this->make('AF', null, 'Herat');
        $this->translations->sync($herat, ['fa' => 'هرات', 'en' => 'Herat (English)']);

        $this->assertSame('هرات', $herat->refresh()->displayName('fa-AF'), 'fa-AF falls back to fa.');
        $this->assertSame('Herat', $herat->displayName('ps-AF'), 'No Pashto name: canonical, never English.');

        $this->translations->sync($herat, ['fa-AF' => 'هرات (افغانستان)']);
        $this->assertSame('هرات (افغانستان)', $herat->refresh()->displayName('fa-AF'));
    }

    private function make(string $country, ?int $parent, string $name, string $level = 'province', ?string $code = null): AdministrativeArea
    {
        return $this->areas->create([
            'country_code' => $country, 'parent_id' => $parent, 'level_code' => $level,
            'area_code' => $code, 'canonical_name' => $name,
        ])->refresh();
    }

    private function move(AdministrativeArea $area, ?int $parent): AdministrativeArea
    {
        return $this->areas->update($area->id, [
            'parent_id' => $parent, 'level_code' => $area->level_code, 'canonical_name' => $area->canonical_name,
        ]);
    }

    /** @return array<string, string> */
    private function stored(AdministrativeArea $area): array
    {
        return DB::table('administrative_area_translations')->where('area_id', $area->id)->orderBy('locale_code')->pluck('display_name', 'locale_code')->all();
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
