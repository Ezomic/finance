<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'throttle-test@example.com', 'password' => Hash::make('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', ['email' => 'throttle-test@example.com', 'password' => 'wrong-password']);
            $response->assertStatus(302);
        }

        $response = $this->post('/login', ['email' => 'throttle-test@example.com', 'password' => 'wrong-password']);
        $response->assertStatus(429);
    }

    public function test_a_successful_login_clears_the_throttle_counter(): void
    {
        $user = User::factory()->create(['email' => 'clears-test@example.com', 'password' => Hash::make('correct-password')]);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'clears-test@example.com', 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => 'clears-test@example.com', 'password' => 'correct-password']);
        $response->assertRedirect(route('dashboard'));

        $this->post('/logout');

        // Two more failed attempts after a successful login should not trip
        // the 5-per-minute limit, since the counter was cleared.
        $this->post('/login', ['email' => 'clears-test@example.com', 'password' => 'wrong-password']);
        $response = $this->post('/login', ['email' => 'clears-test@example.com', 'password' => 'wrong-password']);
        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }

    public function test_household_join_is_throttled_after_five_attempts(): void
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)->post('/households/join', ['invite_code' => 'WRONGCODE']);
            $response->assertStatus(302);
        }

        $response = $this->actingAs($user)->post('/households/join', ['invite_code' => 'WRONGCODE']);
        $response->assertStatus(429);
    }
}
