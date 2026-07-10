<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
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
}
