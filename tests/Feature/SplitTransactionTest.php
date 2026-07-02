<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitTransactionTest extends TestCase
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

    public function test_creates_a_transaction_with_splits_across_categories(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $groceries = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000000']);
        $household_supplies = Category::create(['household_id' => $household->id, 'name' => 'Household', 'type' => 'expense', 'color' => '#000000']);

        $response = $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 100,
            'description' => 'Supermarket run',
            'date' => '2026-06-01',
            'splits' => [
                ['category_id' => $groceries->id, 'amount' => 70],
                ['category_id' => $household_supplies->id, 'amount' => 30],
            ],
        ]);

        $response->assertRedirect(route('transactions.index'));

        $transaction = Transaction::firstWhere('description', 'Supermarket run');
        $this->assertTrue($transaction->is_split);
        $this->assertNull($transaction->category_id);
        $this->assertCount(2, $transaction->splits);
        $this->assertSame(100.0, (float) $transaction->splits->sum('amount'));
    }

    public function test_rejects_splits_that_do_not_sum_to_the_transaction_amount(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $groceries = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000000']);

        $response = $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 100,
            'description' => 'Supermarket run',
            'date' => '2026-06-01',
            'splits' => [
                ['category_id' => $groceries->id, 'amount' => 40],
            ],
        ]);

        $response->assertSessionHasErrors('splits');
        $this->assertDatabaseMissing('transactions', ['description' => 'Supermarket run']);
    }

    public function test_budget_spent_includes_split_amounts_for_the_matching_category(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $groceries = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000000']);
        $household_supplies = Category::create(['household_id' => $household->id, 'name' => 'Household', 'type' => 'expense', 'color' => '#000000']);

        $transaction = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 100, 'description' => 'Supermarket run', 'date' => '2026-06-01', 'is_split' => true,
        ]);
        $transaction->splits()->createMany([
            ['category_id' => $groceries->id, 'amount' => 70],
            ['category_id' => $household_supplies->id, 'amount' => 30],
        ]);

        $budget = Budget::create([
            'household_id' => $household->id, 'category_id' => $groceries->id, 'month' => '2026-06-01', 'amount' => 200,
        ]);

        $this->assertSame(70.0, $budget->spent());
    }

    public function test_split_transactions_are_excluded_from_the_categorize_workflow(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $groceries = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000000']);

        $split = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 100, 'description' => 'Supermarket run', 'date' => '2026-06-01', 'is_split' => true,
        ]);
        $split->splits()->create(['category_id' => $groceries->id, 'amount' => 100]);

        $response = $this->actingAs($user)->get('/categorize');

        $this->assertSame(0, $response->viewData('groups')->count());
    }

    public function test_updating_a_transaction_to_remove_splits_clears_them(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $groceries = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000000']);

        $transaction = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 100, 'description' => 'Supermarket run', 'date' => '2026-06-01', 'is_split' => true,
        ]);
        $transaction->splits()->create(['category_id' => $groceries->id, 'amount' => 100]);

        $response = $this->actingAs($user)->put("/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'category_id' => $groceries->id,
            'type' => 'expense',
            'amount' => 100,
            'description' => 'Supermarket run',
            'date' => '2026-06-01',
        ]);

        $response->assertRedirect(route('transactions.index'));
        $transaction->refresh();
        $this->assertFalse($transaction->is_split);
        $this->assertCount(0, $transaction->splits);
        $this->assertSame($groceries->id, $transaction->category_id);
    }
}
