<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);

        return [$household, $user];
    }

    public function test_index_only_lists_the_current_households_accounts(): void
    {
        [$household, $user] = $this->setUpHousehold();
        Account::create(['household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Checking', 'type' => 'checking']);

        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        Account::create(['household_id' => $otherHousehold->id, 'name' => 'Their Savings', 'type' => 'savings']);

        $response = $this->actingAs($user)->get('/accounts');

        $response->assertOk();
        $accounts = $response->viewData('accounts');
        $this->assertCount(1, $accounts);
        $this->assertSame('Checking', $accounts->first()->name);
    }

    public function test_can_create_an_account(): void
    {
        [, $user] = $this->setUpHousehold();

        $response = $this->actingAs($user)->post('/accounts', [
            'name' => 'Checking', 'type' => 'checking', 'currency' => 'EUR', 'opening_balance' => 1000,
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('accounts', ['name' => 'Checking', 'opening_balance' => 1000]);
    }

    public function test_can_update_an_owned_account(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $account = Account::create(['household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Checking', 'type' => 'checking']);

        $response = $this->actingAs($user)->put("/accounts/{$account->id}", [
            'name' => 'Renamed', 'type' => 'checking', 'currency' => 'USD', 'opening_balance' => 500,
        ]);

        $response->assertRedirect(route('accounts.show', $account));
        $this->assertSame('Renamed', $account->fresh()->name);
    }

    public function test_cannot_view_another_households_account(): void
    {
        [, $user] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherAccount = Account::create(['household_id' => $otherHousehold->id, 'name' => 'Their Checking', 'type' => 'checking']);

        $response = $this->actingAs($user)->get("/accounts/{$otherAccount->id}");

        $response->assertForbidden();
    }

    public function test_cannot_update_another_households_account(): void
    {
        [, $user] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherAccount = Account::create(['household_id' => $otherHousehold->id, 'name' => 'Their Checking', 'type' => 'checking']);

        $response = $this->actingAs($user)->put("/accounts/{$otherAccount->id}", [
            'name' => 'Hijacked', 'type' => 'checking', 'currency' => 'EUR', 'opening_balance' => 0,
        ]);

        $response->assertForbidden();
        $this->assertSame('Their Checking', $otherAccount->fresh()->name);
    }

    public function test_cannot_delete_another_households_account(): void
    {
        [, $user] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherAccount = Account::create(['household_id' => $otherHousehold->id, 'name' => 'Their Checking', 'type' => 'checking']);

        $response = $this->actingAs($user)->delete("/accounts/{$otherAccount->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('accounts', ['id' => $otherAccount->id]);
    }

    public function test_can_delete_an_owned_account(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $account = Account::create(['household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Checking', 'type' => 'checking']);

        $response = $this->actingAs($user)->delete("/accounts/{$account->id}");

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    public function test_can_save_a_balance_snapshot_on_an_owned_account(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $account = Account::create(['household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Investment', 'type' => 'investment']);

        $response = $this->actingAs($user)->post("/accounts/{$account->id}/snapshot", [
            'date' => '2026-06-01', 'balance' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('net_worth_snapshots', ['account_id' => $account->id, 'balance' => 5000]);
    }
}
