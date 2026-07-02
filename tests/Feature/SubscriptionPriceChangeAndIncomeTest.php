<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPriceChangeAndIncomeTest extends TestCase
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

    public function test_flags_a_recurring_charge_that_increased_in_price(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'expense', 'description' => 'Netflix'];

        Transaction::create($base + ['amount' => 12.99, 'date' => now()->subMonths(2)->format('Y-m-d')]);
        Transaction::create($base + ['amount' => 12.99, 'date' => now()->subMonths(1)->format('Y-m-d')]);
        Transaction::create($base + ['amount' => 15.99, 'date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');

        $changes = $response->viewData('priceChanges');
        $this->assertCount(1, $changes);
        $this->assertSame('Netflix', $changes->first()['label']);
        $this->assertSame(12.99, $changes->first()['old_amount']);
        $this->assertSame(15.99, $changes->first()['new_amount']);
    }

    public function test_does_not_flag_a_recurring_charge_that_decreased_or_stayed_the_same(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'expense', 'description' => 'Gym'];

        Transaction::create($base + ['amount' => 30, 'date' => now()->subMonths(2)->format('Y-m-d')]);
        Transaction::create($base + ['amount' => 30, 'date' => now()->subMonths(1)->format('Y-m-d')]);
        Transaction::create($base + ['amount' => 25, 'date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');

        $this->assertCount(0, $response->viewData('priceChanges'));
    }

    public function test_detects_recurring_income_separately_from_subscriptions(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'income', 'amount' => 2500, 'description' => 'Salary'];

        Transaction::create($base + ['date' => now()->subMonths(2)->format('Y-m-d')]);
        Transaction::create($base + ['date' => now()->subMonths(1)->format('Y-m-d')]);
        Transaction::create($base + ['date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');

        $income = $response->viewData('recurringIncome');
        $this->assertCount(1, $income);
        $this->assertSame('Salary', $income->first()['label']);
        $this->assertSame(2500.0, $income->first()['amount']);
        $this->assertCount(0, $response->viewData('subscriptions'));
    }
}
