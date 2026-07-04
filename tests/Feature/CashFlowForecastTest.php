<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowForecastTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(float $openingBalance = 0): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);
        $account = Account::create([
            'household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Checking',
            'type' => 'checking', 'opening_balance' => $openingBalance,
        ]);

        return [$household, $user, $account];
    }

    public function test_running_balance_reflects_starting_balance_plus_all_projected_events_and_discretionary_spend(): void
    {
        [$household, $user, $account] = $this->setUpHousehold(1000);
        Bill::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'name' => 'Rent', 'amount' => 200,
            'due_day' => 15, 'frequency' => 'monthly', 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/forecast?days=30');

        $response->assertOk();
        $points = $response->viewData('points');
        $events = $response->viewData('events');
        $discretionary = $response->viewData('discretionaryDaily');

        $expectedFinal = 1000 + $events->sum('amount') - $discretionary * $points->count();
        $this->assertEqualsWithDelta($expectedFinal, $points->last()['balance'], 0.5);
    }

    public function test_a_bill_due_within_the_horizon_appears_as_a_known_event(): void
    {
        [$household, $user, $account] = $this->setUpHousehold(1000);
        Bill::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'name' => 'Rent', 'amount' => 200,
            'due_day' => 15, 'frequency' => 'monthly', 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/forecast?days=60');

        $events = $response->viewData('events');
        $this->assertTrue($events->contains(fn (array $e) => $e['label'] === 'Rent' && $e['amount'] == -200.0));
    }

    public function test_recurring_expense_matching_an_existing_bill_is_not_double_counted(): void
    {
        [$household, $user, $account] = $this->setUpHousehold(1000);

        Bill::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'name' => 'Internet', 'amount' => 45,
            'due_day' => 15, 'frequency' => 'monthly', 'is_active' => true,
        ]);

        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'expense', 'amount' => 45, 'description' => 'Internet Provider'];
        Transaction::create($base + ['date' => now()->subMonths(1)->format('Y-m-d')]);
        Transaction::create($base + ['date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/forecast?days=60');

        $events = $response->viewData('events');
        $this->assertFalse($events->contains(fn (array $e) => $e['type'] === 'subscription'));
    }

    public function test_recurring_income_is_projected_as_a_positive_event(): void
    {
        [$household, $user, $account] = $this->setUpHousehold(1000);

        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'income', 'amount' => 2500, 'description' => 'Salary'];
        Transaction::create($base + ['date' => now()->subMonths(1)->format('Y-m-d')]);
        Transaction::create($base + ['date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/forecast?days=60');

        $events = $response->viewData('events');
        $this->assertTrue($events->contains(fn (array $e) => $e['type'] === 'income' && $e['label'] === 'Salary' && $e['amount'] == 2500.0));
    }
}
