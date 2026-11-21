<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Services\Locales\LocaleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private LocaleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LocaleService;
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());

        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getConfig('driver'));
        $this->assertSame(':memory:', $connection->getConfig('database'));
        $this->assertEmpty($connection->getConfig('read'));
        $this->assertEmpty($connection->getConfig('write'));
        $this->assertSame([$connection->getName()], $this->connectionsToTransact());

        $pdo = $connection->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(['main'], array_column($databases, 'name'));
        $this->assertSame([''], array_column($databases, 'file'));
    }

    public function test_create_trims_name_and_stores_catalog_direction(): void
    {
        $locale = $this->service->create(['code' => 'ps-AF', 'english_name' => '  Pashto  ', 'text_direction' => 'RTL'])->refresh();

        $this->assertSame('ps-AF', $locale->getKey());
        $this->assertSame('Pashto', $locale->english_name);
        $this->assertSame('RTL', $locale->text_direction);
    }

    /** Problems found in the legacy P01.03 build must stay rejected. */
    #[DataProvider('legacyRegressions')]
    public function test_legacy_problem_values_are_rejected(array $input, array $fields): void
    {
        try {
            $this->service->create($input);
            $this->fail('Invalid locale was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame($fields, array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('locales', 0);
    }

    public static function legacyRegressions(): array
    {
        return [
            'af (Afrikaans) labelled Pashto' => [['code' => 'af', 'english_name' => 'pashto', 'text_direction' => 'LTR'], ['code']],
            'afg is not a language tag' => [['code' => 'afg', 'english_name' => 'pashto', 'text_direction' => 'LTR'], ['code']],
            'ps-AF saved as LTR' => [['code' => 'ps-AF', 'english_name' => 'Pashto', 'text_direction' => 'LTR'], ['text_direction']],
            'fa-AF saved as LTR' => [['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'LTR'], ['text_direction']],
            'English saved as RTL' => [['code' => 'en', 'english_name' => 'English', 'text_direction' => 'RTL'], ['text_direction']],
            'wrong case' => [['code' => 'PS-af', 'english_name' => 'Pashto', 'text_direction' => 'RTL'], ['code']],
            'lower-case region' => [['code' => 'fa-af', 'english_name' => 'Dari', 'text_direction' => 'RTL'], ['code']],
            'lower-case direction' => [['code' => 'ps', 'english_name' => 'Pashto', 'text_direction' => 'rtl'], ['text_direction']],
            'missing everything' => [[], ['code', 'english_name', 'text_direction']],
            'blank name' => [['code' => 'en', 'english_name' => '  ', 'text_direction' => 'LTR'], ['english_name']],
        ];
    }

    public function test_unsupported_code_uses_a_clear_message(): void
    {
        try {
            $this->service->create(['code' => 'af', 'english_name' => 'Afrikaans', 'text_direction' => 'LTR']);
            $this->fail('Unsupported code was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Choose a language code from the supported list.'], $exception->errors()['code']);
        }
    }

    public function test_base_and_regional_tags_are_separate_languages(): void
    {
        $this->service->create(['code' => 'de', 'english_name' => 'German', 'text_direction' => 'LTR']);
        $this->service->create(['code' => 'de-DE', 'english_name' => 'German (Germany)', 'text_direction' => 'LTR']);

        $this->assertDatabaseCount('locales', 2);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $this->service->create(['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR']);

        try {
            $this->service->create(['code' => 'en', 'english_name' => 'Again', 'text_direction' => 'LTR']);
            $this->fail('Duplicate was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['code'], array_keys($exception->errors()));
        }
    }

    public function test_update_changes_name_but_not_code_or_direction(): void
    {
        $this->service->create(['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL']);

        $locale = $this->service->update('fa-AF', ['english_name' => ' Dari (Afghanistan) ', 'text_direction' => 'RTL'])->refresh();
        $this->assertSame('Dari (Afghanistan)', $locale->english_name);

        try {
            $this->service->update('fa-AF', ['code' => 'fa', 'english_name' => 'Dari', 'text_direction' => 'LTR']);
            $this->fail('Invalid update was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['code', 'text_direction'], array_keys($exception->errors()));
        }

        $this->assertDatabaseHas('locales', ['code' => 'fa-AF', 'english_name' => 'Dari (Afghanistan)', 'text_direction' => 'RTL']);
    }

    public function test_update_fails_for_missing_locale(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->update('ps-AF', ['english_name' => 'Pashto', 'text_direction' => 'RTL']);
    }

    public function test_retrieves_a_locale_by_its_code(): void
    {
        $this->service->create(['code' => 'de-DE', 'english_name' => 'German (Germany)', 'text_direction' => 'LTR']);

        $this->assertSame('German (Germany)', $this->service->retrieve('de-DE')->english_name);
    }

    public function test_retrieval_fails_for_missing_locale(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->retrieve('de-DE');
    }

    public function test_activation_and_deactivation_are_idempotent_and_preserve_the_record(): void
    {
        // Exercise the service against a test-only column while the real migration awaits approval.
        if (! Schema::hasColumn('locales', 'is_active')) {
            Schema::table('locales', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true);
            });
        }

        DB::table('locales')->insert([
            'code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true,
        ]);

        $this->assertFalse($this->service->deactivate('fa-AF')->is_active);
        $this->assertFalse($this->service->deactivate('fa-AF')->is_active);
        $this->assertDatabaseHas('locales', ['code' => 'fa-AF', 'is_active' => false]);
        $this->assertTrue($this->service->activate('fa-AF')->is_active);
        $this->assertTrue($this->service->activate('fa-AF')->is_active);
        $this->assertDatabaseHas('locales', [
            'code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true,
        ]);
        $this->assertDatabaseCount('locales', 1);
    }

    public function test_unique_constraint_failure_is_reported_and_rolls_back(): void
    {
        // RefreshDatabase wraps each test in its own transaction, so compare with the starting level.
        $level = DB::transactionLevel();

        Locale::creating(function (): void {
            DB::table('locales')->insert(['code' => 'en', 'english_name' => 'Conflict', 'text_direction' => 'LTR']);
        });

        try {
            $this->service->create(['code' => 'en', 'english_name' => 'English', 'text_direction' => 'LTR']);
            $this->fail('A database conflict was not reported.');
        } catch (ValidationException $exception) {
            $this->assertSame(['code'], array_keys($exception->errors()));
        }

        $this->assertDatabaseCount('locales', 0);
        $this->assertSame($level, DB::transactionLevel());
    }
}
