<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** P03.03 follow-ups: username login, maintainer-verified email, details and language props. */
class PlatformAccountLoginTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/security/users';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
    }

    public function test_users_can_sign_in_with_email_or_username_in_any_case(): void
    {
        $user = User::factory()->create(['username' => 'dr.karimi', 'email' => 'karimi@example.test']);

        foreach (['karimi@example.test', 'KARIMI@Example.Test', 'dr.karimi', '  DR.Karimi  '] as $login) {
            $this->post(route('login.store'), ['email' => $login, 'password' => 'password'])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
            $this->assertGuest();
        }
    }

    public function test_wrong_password_unknown_login_and_inactive_accounts_are_rejected(): void
    {
        $user = User::factory()->create(['username' => 'dr.karimi', 'email' => 'karimi@example.test']);

        $this->post(route('login.store'), ['email' => 'dr.karimi', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => 'nobody', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => '', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->post(route('login.store'), ['email' => 'dr.karimi', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => 'karimi@example.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_maintainer_created_email_is_verified_and_a_changed_email_must_be_verified_again(): void
    {
        $maintainer = $this->maintainer();
        $input = [
            'username' => 'new.user', 'name' => 'New User', 'email' => 'new@example.test', 'scope' => 'platform',
            'password' => 'example-password', 'password_confirmation' => 'example-password',
        ];

        $this->actingAs($maintainer)->post(self::URL, $input)->assertSessionHasNoErrors();
        $account = User::query()->where('username', 'new.user')->firstOrFail();
        $this->assertNotNull($account->email_verified_at);

        // The new user can open the password settings (which require a verified email) straight away.
        $this->withoutVite();
        $this->actingAs($account)->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/security')->assertOk();

        unset($input['password'], $input['password_confirmation']);
        $this->actingAs($maintainer)->put(self::URL.'/'.$account->id, array_replace($input, ['name' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertNotNull($account->fresh()->email_verified_at);

        $this->put(self::URL.'/'.$account->id, array_replace($input, ['email' => 'changed@example.test']))->assertSessionHasNoErrors();
        $this->assertNull($account->fresh()->email_verified_at);
        $this->actingAs($account->fresh())->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/security')->assertRedirect(route('verification.notice'));
    }

    public function test_page_shares_details_and_keeps_the_language_switcher_list(): void
    {
        $this->withoutVite();
        DB::table('locales')->insert([
            ['code' => 'fa-AF', 'english_name' => 'Dari', 'native_name' => 'دری', 'text_direction' => 'RTL', 'is_active' => true],
            ['code' => 'ps-AF', 'english_name' => 'Pashto', 'native_name' => 'پښتو', 'text_direction' => 'RTL', 'is_active' => false],
        ]);
        $maintainer = $this->maintainer();

        $this->actingAs($maintainer)->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('platform/security/users/index')
            // Shared switcher list stays intact (Pashto disabled).
            ->where('locales', [['code' => 'en', 'name' => 'English'], ['code' => 'fa-AF', 'name' => 'دری']])
            // Language choices for the account form: active locales only.
            ->has('languageOptions', 1)
            ->where('languageOptions.0.code', 'fa-AF')
            ->has('accounts.data.0.email_verified_at')
            ->has('accounts.data.0.created_at')
            ->missing('accounts.data.0.password')
            ->missing('accounts.data.0.remember_token'));
    }

    private function maintainer(): User
    {
        $maintainer = User::factory()->create();
        config(['accounts.maintainer_ids' => [(string) $maintainer->id]]);

        return $maintainer;
    }
}
