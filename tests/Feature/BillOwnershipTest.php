<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);
        $account = Account::create([
            'household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Checking', 'type' => 'checking',
        ]);

        return [$household, $user, $account];
    }

    public function test_cannot_create_a_bill_against_another_households_account(): void
    {
        [, $user] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherAccount = Account::create(['household_id' => $otherHousehold->id, 'name' => 'Their Checking', 'type' => 'checking']);

        $response = $this->actingAs($user)->post('/bills', [
            'name' => 'Rent', 'amount' => 900, 'due_day' => 5, 'frequency' => 'monthly',
            'account_id' => $otherAccount->id,
        ]);

        $response->assertSessionHasErrors('account_id');
        $this->assertDatabaseMissing('bills', ['account_id' => $otherAccount->id]);
    }

    public function test_cannot_create_a_bill_against_another_households_category(): void
    {
        [, $user] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherCategory = Category::create(['household_id' => $otherHousehold->id, 'name' => 'Not yours', 'type' => 'expense', 'color' => '#000']);

        $response = $this->actingAs($user)->post('/bills', [
            'name' => 'Rent', 'amount' => 900, 'due_day' => 5, 'frequency' => 'monthly',
            'category_id' => $otherCategory->id,
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('bills', ['category_id' => $otherCategory->id]);
    }

    public function test_cannot_update_a_bill_to_move_it_to_another_households_account(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $bill = Bill::create(['household_id' => $household->id, 'name' => 'Rent', 'amount' => 900, 'due_day' => 5, 'frequency' => 'monthly', 'is_active' => true]);
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherAccount = Account::create(['household_id' => $otherHousehold->id, 'name' => 'Their Checking', 'type' => 'checking']);

        $response = $this->actingAs($user)->put("/bills/{$bill->id}", [
            'name' => 'Rent', 'amount' => 900, 'due_day' => 5, 'frequency' => 'monthly',
            'account_id' => $otherAccount->id,
        ]);

        $response->assertSessionHasErrors('account_id');
        $this->assertNull($bill->fresh()->account_id);
    }

    public function test_can_still_create_a_bill_with_owned_account_and_category(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $category = Category::create(['household_id' => $household->id, 'name' => 'Housing', 'type' => 'expense', 'color' => '#000']);

        $response = $this->actingAs($user)->post('/bills', [
            'name' => 'Rent', 'amount' => 900, 'due_day' => 5, 'frequency' => 'monthly',
            'account_id' => $account->id, 'category_id' => $category->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['name' => 'Rent', 'account_id' => $account->id, 'category_id' => $category->id]);
    }
}
