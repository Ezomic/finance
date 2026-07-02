<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('correct-password')]);

        $response = $this->post('/login', ['email' => 'jane@example.com', 'password' => 'correct-password']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_a_user_cannot_log_in_with_the_wrong_password(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('correct-password')]);

        $response = $this->post('/login', ['email' => 'jane@example.com', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_new_user_can_register_and_is_sent_to_create_a_household(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertRedirect(route('households.create'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_registration_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_logged_in_user_can_log_out(): void
    {
        $household = Household::create(['name' => 'Test', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
