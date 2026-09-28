<?php

namespace Tests\Feature\Auth;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Thijssensoftware\IdClient\Exceptions\AccessDeniedException;

class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeIdUser(): SocialiteUser
    {
        return (new SocialiteUser)->setRaw([
            'sub' => '42',
            'name' => 'Robbin Thijssen',
            'email' => 'robbin@example.com',
            'applications' => ['finance'],
        ])->map([
            'id' => '42',
            'name' => 'Robbin Thijssen',
            'email' => 'robbin@example.com',
        ]);
    }

    private function mockSocialite(callable $configure): void
    {
        $provider = Mockery::mock(Provider::class);
        $configure($provider);

        Socialite::shouldReceive('driver')->with('thijssensoftware')->andReturn($provider);
    }

    private function userWithHousehold(): User
    {
        $household = Household::create(['name' => 'Test', 'currency' => 'EUR']);
        $user = User::factory()->create(['email' => 'robbin@example.com', 'current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);

        return $user;
    }

    /**
     * Signs the user in through the ID callback and returns the remember-me
     * cookie the callback set.
     *
     * @return array{string, string}
     */
    private function signInThroughId(): array
    {
        $this->mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn($this->fakeIdUser()));

        $recaller = Auth::guard()->getRecallerName();
        $cookie = $this->get(route('sso.callback'))->assertRedirect('/dashboard')->getCookie($recaller);

        $this->assertNotNull($cookie);

        return [$recaller, (string) $cookie->getValue()];
    }

    /**
     * A browser coming back after its session expired, carrying nothing but
     * the remember-me cookie.
     *
     * @return TestResponse<Response>
     */
    private function returnWithRememberCookie(string $recaller, string $value): TestResponse
    {
        Auth::forgetGuards();
        $this->flushSession();

        return $this->withCookie($recaller, $value)->get(route('dashboard'));
    }

    public function test_the_redirect_route_starts_the_sso_flow(): void
    {
        $this->mockSocialite(fn ($provider) => $provider->shouldReceive('redirect')->andReturn(redirect('https://id.test/oauth/authorize')));

        $this->get(route('sso.redirect'))->assertRedirect('https://id.test/oauth/authorize');
    }

    public function test_it_links_an_existing_user_by_email_and_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'robbin@example.com', 'idp_id' => null]);

        $this->mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn($this->fakeIdUser()));

        $this->get(route('sso.callback'))->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('42', $user->fresh()->idp_id);
    }

    public function test_it_denies_an_unknown_user_because_provisioning_is_disabled(): void
    {
        $this->mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andReturn($this->fakeIdUser()));

        $this->get(route('sso.callback'))->assertForbidden();

        $this->assertGuest();
        $this->assertFalse(User::where('email', 'robbin@example.com')->exists());
    }

    public function test_it_denies_a_user_without_access(): void
    {
        $this->mockSocialite(fn ($provider) => $provider->shouldReceive('user')->andThrow(new AccessDeniedException('nope')));

        $this->get(route('sso.callback'))->assertForbidden();

        $this->assertGuest();
    }

    public function test_the_remember_me_cookie_keeps_the_browser_signed_in_after_an_id_sign_in(): void
    {
        $user = $this->userWithHousehold();

        [$recaller, $value] = $this->signInThroughId();

        $this->returnWithRememberCookie($recaller, $value)->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_the_remember_me_cookie_is_refused_once_id_signs_the_user_out(): void
    {
        config(['id-client.logout_secret' => 'test-logout-secret']);

        $this->userWithHousehold();

        [$recaller, $value] = $this->signInThroughId();

        $this->returnWithRememberCookie($recaller, $value)->assertOk();

        $body = json_encode(['sub' => '42', 'issued_at' => Carbon::now()->getTimestamp()], JSON_THROW_ON_ERROR);

        $this->call('POST', route('sso.logout'), server: [
            'HTTP_X_ID_SIGNATURE' => hash_hmac('sha256', $body, 'test-logout-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], content: $body)->assertOk();

        // Later, so the cookie itself is refused rather than a same-second
        // stamp ending the session it restores.
        $this->travel(1)->minute();

        $this->returnWithRememberCookie($recaller, $value)->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
