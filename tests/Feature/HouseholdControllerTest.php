<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_household_makes_the_user_its_owner_and_current_household(): void
    {
        $user = User::factory()->create(['current_household_id' => null]);

        $response = $this->actingAs($user)->post('/households', ['name' => 'My Household']);

        $response->assertRedirect(route('dashboard'));
        $household = Household::firstWhere('name', 'My Household');
        $this->assertNotNull($household);
        $this->assertSame($household->id, $user->fresh()->current_household_id);
        $this->assertSame('owner', $household->users()->find($user->id)->pivot->role);
    }

    public function test_creating_a_household_seeds_default_categories(): void
    {
        $user = User::factory()->create(['current_household_id' => null]);

        $this->actingAs($user)->post('/households', ['name' => 'My Household']);

        $household = Household::firstWhere('name', 'My Household');
        $this->assertGreaterThan(0, $household->categories()->count());
    }

    public function test_can_join_a_household_with_a_valid_invite_code(): void
    {
        $household = Household::create(['name' => 'Existing', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => null]);

        $response = $this->actingAs($user)->post('/households/join', ['invite_code' => strtolower($household->invite_code)]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($household->id, $user->fresh()->current_household_id);
        $this->assertTrue($household->users()->where('users.id', $user->id)->exists());
    }

    public function test_joining_with_an_invalid_invite_code_fails(): void
    {
        $user = User::factory()->create(['current_household_id' => null]);

        $response = $this->actingAs($user)->post('/households/join', ['invite_code' => 'BOGUSCODE']);

        $response->assertSessionHasErrors('invite_code');
        $this->assertNull($user->fresh()->current_household_id);
    }

    public function test_can_switch_between_households_the_user_belongs_to(): void
    {
        $householdA = Household::create(['name' => 'A', 'currency' => 'EUR']);
        $householdB = Household::create(['name' => 'B', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $householdA->id]);
        $householdA->users()->attach($user, ['role' => 'owner']);
        $householdB->users()->attach($user, ['role' => 'member']);

        $response = $this->actingAs($user)->post("/households/{$householdB->id}/switch");

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($householdB->id, $user->fresh()->current_household_id);
    }

    public function test_cannot_switch_to_a_household_the_user_does_not_belong_to(): void
    {
        $household = Household::create(['name' => 'Mine', 'currency' => 'EUR']);
        $otherHousehold = Household::create(['name' => 'Not mine', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);

        $response = $this->actingAs($user)->post("/households/{$otherHousehold->id}/switch");

        $response->assertForbidden();
        $this->assertSame($household->id, $user->fresh()->current_household_id);
    }
}
