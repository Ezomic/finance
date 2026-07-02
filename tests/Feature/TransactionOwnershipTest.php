<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionOwnershipTest extends TestCase
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

    public function test_cannot_create_a_transaction_against_another_households_account(): void
    {
        [, $user] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherAccount = Account::create([
            'household_id' => $otherHousehold->id, 'name' => 'Their Checking', 'type' => 'checking',
        ]);

        $response = $this->actingAs($user)->post('/transactions', [
            'account_id' => $otherAccount->id,
            'type' => 'expense',
            'amount' => 50,
            'date' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('account_id');
        $this->assertDatabaseMissing('transactions', ['account_id' => $otherAccount->id]);
    }

    public function test_cannot_create_a_transaction_with_another_households_category(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherCategory = Category::create(['household_id' => $otherHousehold->id, 'name' => 'Not yours', 'type' => 'expense', 'color' => '#000']);

        $response = $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'category_id' => $otherCategory->id,
            'type' => 'expense',
            'amount' => 50,
            'date' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('category_id');
    }

    public function test_cannot_update_a_transaction_to_move_it_to_another_households_account(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $transaction = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 50, 'date' => '2026-06-01',
        ]);
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherAccount = Account::create([
            'household_id' => $otherHousehold->id, 'name' => 'Their Checking', 'type' => 'checking',
        ]);

        $response = $this->actingAs($user)->put("/transactions/{$transaction->id}", [
            'account_id' => $otherAccount->id,
            'type' => 'expense',
            'amount' => 50,
            'date' => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('account_id');
        $this->assertSame($account->id, $transaction->fresh()->account_id);
    }

    public function test_cannot_split_a_transaction_into_another_households_category(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherCategory = Category::create(['household_id' => $otherHousehold->id, 'name' => 'Not yours', 'type' => 'expense', 'color' => '#000']);

        $response = $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 100,
            'date' => '2026-06-01',
            'splits' => [
                ['category_id' => $otherCategory->id, 'amount' => 100],
            ],
        ]);

        $response->assertSessionHasErrors('splits.0.category_id');
    }

    public function test_can_still_create_a_transaction_with_a_valid_owned_account_and_category(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $category = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000']);

        $response = $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 50,
            'date' => '2026-06-01',
        ]);

        $response->assertRedirect(route('transactions.index'));
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'category_id' => $category->id]);
    }
}
