<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PlatformAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/platform/security/users';

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertFalse(app()->configurationIsCached());
        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getConfig('driver'));
        $this->assertSame(':memory:', $connection->getConfig('database'));
        $this->assertEmpty($connection->getConfig('read'));
        $this->assertEmpty($connection->getConfig('write'));
    }

    public function test_disabled_accounts_cannot_login_resume_a_session_or_reuse_a_remember_token(): void
    {
        $account = User::factory()->withTwoFactor()->create();
        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertFalse(Features::enabled(Features::passkeys()));

        $this->post(route('login.store'), ['email' => $account->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($account);
        $this->assertNotNull($account->fresh()->last_login_at);
        $sessionKey = Auth::guard('web')->getName();
        $token = $account->remember_token;

        DB::table('users')->where('id', $account->id)->update(['is_active' => false]);
        app('auth')->forgetGuards();
        $this->withSession([$sessionKey => $account->id])->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(Auth::guard('web')->getProvider()->retrieveByToken($account->id, $token));
        $this->post(route('login.store'), ['email' => $account->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_platform_maintainer_can_manage_only_platform_accounts(): void
    {
        $this->withoutVite();
        $this->get(self::URL)->assertRedirect(route('login'));
        $maintainer = User::factory()->create();
        $this->actingAs($maintainer)->get(self::URL)->assertForbidden();
        config(['accounts.maintainer_ids' => [(string) $maintainer->id]]);
        $clinic = User::factory()->create(['scope' => 'clinic']);
        $this->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where('accounts.total', 1)->missing('accounts.data.0.password'));
        $this->put(self::URL.'/'.$clinic->id)->assertNotFound();
        $this->patch(self::URL.'/'.$clinic->id.'/status', ['is_active' => false])->assertNotFound();
        config(['accounts.maintainer_ids' => [(string) $clinic->id, (string) $maintainer->id]]);
        $this->actingAs($clinic)->get(self::URL)->assertForbidden();
        $this->actingAs($maintainer);

        $input = [
            'username' => 'Example.User', 'name' => 'Example User', 'email' => 'EXAMPLE@example.test',
            'scope' => 'platform', 'password' => 'example-password', 'password_confirmation' => 'example-password',
        ];
        $this->postJson(self::URL, array_replace($input, ['scope' => 'clinic', 'locale_code' => 'xx']))
            ->assertUnprocessable()->assertJsonValidationErrors(['scope', 'locale_code']);
        $this->post(self::URL, $input)->assertRedirect();
        $account = User::query()->where('username', 'example.user')->firstOrFail();
        $this->assertSame('example@example.test', $account->email);
        $this->assertTrue(Hash::check('example-password', $account->password));
        $this->postJson(self::URL, $input)->assertUnprocessable()->assertJsonValidationErrors(['username', 'email']);

        unset($input['password'], $input['password_confirmation']);
        $this->put(self::URL.'/'.$account->id, array_replace($input, ['name' => 'Edited User']))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $account->id, 'name' => 'Edited User']);
        $this->patchJson(self::URL.'/'.$maintainer->id.'/status', ['is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');

        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'revoked', 'user_id' => $account->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->patch(self::URL.'/'.$account->id.'/status', ['is_active' => false])->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'revoked']);
        $this->get(self::URL.'?q=example.user&status=inactive')->assertInertia(fn (Assert $page) => $page
            ->where('accounts.total', 1)->where('accounts.data.0.is_active', false));
        $this->patch(self::URL.'/'.$account->id.'/status', ['is_active' => true])->assertRedirect();
        $this->assertTrue($account->fresh()->is_active);
    }
}
