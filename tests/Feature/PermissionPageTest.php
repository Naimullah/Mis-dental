<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** P03.01/P03.02 follow-ups: language switcher prop, page size, translated description length. */
class PermissionPageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/security/permissions';

    private int $id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
        $this->withoutVite();

        $this->id = DB::table('permissions')->insertGetId([
            'code' => 'platform.example.view', 'description' => 'Canonical description', 'sensitivity' => 'medium',
        ]);
        DB::table('locales')->insert([
            ['code' => 'fa-AF', 'english_name' => 'Dari', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'fa', 'english_name' => 'Persian', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto', 'text_direction' => 'RTL', 'is_active' => false],
        ]);
    }

    public function test_translation_languages_do_not_replace_the_interface_language_switcher(): void
    {
        $viewer = User::factory()->create();
        config(['permissions.viewer_ids' => [(string) $viewer->id]]);
        $this->actingAs($viewer);

        $this->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            // Shared switcher list: interface languages with display names (Pashto is disabled).
            ->where('locales', [['code' => 'en', 'name' => 'English'], ['code' => 'fa-AF', 'name' => 'دری']])
            // Page-specific translation languages: active only for a viewer.
            ->has('languageOptions', 2)
            ->where('languageOptions.0.code', 'fa')
            ->where('languageOptions.1.code', 'fa-AF'));

        $this->maintainer();
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->has('locales', 2)
            ->has('languageOptions', 3));
    }

    public function test_page_size_is_selectable_and_kept_in_links(): void
    {
        $viewer = User::factory()->create();
        config(['permissions.viewer_ids' => [(string) $viewer->id]]);
        $this->actingAs($viewer);
        foreach (range(1, 30) as $number) {
            DB::table('permissions')->insert([
                'code' => sprintf('platform.example.rule_%02d', $number), 'description' => 'Example', 'sensitivity' => 'low',
            ]);
        }

        $this->get(self::URL.'?per_page=10')->assertInertia(fn (Assert $page) => $page
            ->where('filters.per_page', 10)
            ->where('permissions.per_page', 10)
            ->has('permissions.data', 10)
            ->where('permissions.last_page', 4)
            ->where('permissions.next_page_url', fn (string $next): bool => str_contains($next, 'per_page=10')));

        foreach (['7', 'abc', '1000', '-10'] as $invalid) {
            $this->get(self::URL.'?per_page='.$invalid)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('filters.per_page', 25)
                ->has('permissions.data', 25));
        }
    }

    public function test_translated_description_is_limited_to_4000_characters(): void
    {
        $this->maintainer();
        $url = self::URL.'/'.$this->id.'/translations/fa';

        $this->putJson($url, ['display_name' => 'Name', 'description' => str_repeat('d', 4001)])
            ->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('permission_translations', 0);

        $this->put($url, ['display_name' => 'Name', 'description' => str_repeat('d', 4000)])->assertSessionHasNoErrors();
        $this->assertSame(4000, mb_strlen((string) DB::table('permission_translations')->value('description')));
    }

    private function maintainer(): void
    {
        $user = User::factory()->create();
        config(['permissions.translation_maintainer_ids' => [(string) $user->id]]);
        $this->actingAs($user);
    }
}
