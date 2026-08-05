<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_token_pages(): void
    {
        $this->get('/settings/api-tokens')->assertRedirect('/login');
        $this->post('/settings/api-tokens', ['name' => 'CI'])->assertRedirect('/login');
    }

    public function test_it_lists_the_users_tokens_without_exposing_the_secret(): void
    {
        $user = User::factory()->create();
        $plainText = $user->createToken('existing')->plainTextToken;

        $this->actingAs($user)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertSee('existing')
            ->assertDontSee($plainText);
    }

    public function test_it_only_lists_the_acting_users_own_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('mine');
        User::factory()->create()->createToken('theirs');

        $this->actingAs($user)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertSee('mine')
            ->assertDontSee('theirs');
    }

    public function test_it_creates_a_token_and_reveals_the_plaintext_exactly_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/api-tokens', ['name' => 'CI pipeline'])
            ->assertRedirect('/settings/api-tokens');

        $this->assertSame(1, $user->tokens()->count());

        $plainText = session('createdToken')['plainText'];
        $this->assertStringContainsString('|', $plainText);

        // The redirect target reveals it once.
        $this->actingAs($user)->get('/settings/api-tokens')->assertOk()->assertSee($plainText);

        // A later load no longer carries the secret.
        $this->actingAs($user)->get('/settings/api-tokens')->assertOk()->assertDontSee($plainText);
    }

    public function test_it_never_persists_the_plaintext_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/api-tokens', ['name' => 'CI']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => session('createdToken')['plainText'],
        ]);
    }

    public function test_it_requires_a_token_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/api-tokens', ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_it_rejects_a_duplicate_token_name_for_the_same_user(): void
    {
        $user = User::factory()->create();
        $user->createToken('CI');

        $this->actingAs($user)
            ->post('/settings/api-tokens', ['name' => 'CI'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_it_allows_the_same_token_name_for_different_users(): void
    {
        User::factory()->create()->createToken('CI');
        $second = User::factory()->create();

        $this->actingAs($second)
            ->post('/settings/api-tokens', ['name' => 'CI'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $second->tokens()->count());
    }

    public function test_it_revokes_the_users_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('CI')->accessToken;

        $this->actingAs($user)
            ->delete('/settings/api-tokens/'.$token->getKey())
            ->assertRedirect('/settings/api-tokens');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_it_cannot_revoke_another_users_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherToken = $other->createToken('theirs')->accessToken;

        $this->actingAs($user)
            ->delete('/settings/api-tokens/'.$otherToken->getKey())
            ->assertRedirect('/settings/api-tokens');

        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_a_created_token_authenticates_an_api_request(): void
    {
        $user = User::factory()->create();
        $plainText = $user->createToken('CI')->plainTextToken;

        $this->getJson('/api/user')->assertUnauthorized();

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$plainText])
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }
}
