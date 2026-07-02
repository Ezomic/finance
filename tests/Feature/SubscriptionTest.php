<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
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

    private function expense(array $overrides): Transaction
    {
        return Transaction::create(array_merge([
            'type' => 'expense',
        ], $overrides));
    }

    public function test_detects_a_charge_repeating_monthly_at_the_same_amount(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'amount' => 12.99, 'description' => 'Netflix'];

        $this->expense($base + ['date' => now()->subMonths(2)->format('Y-m-d')]);
        $this->expense($base + ['date' => now()->subMonths(1)->format('Y-m-d')]);
        $this->expense($base + ['date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');

        $response->assertOk();
        $subs = $response->viewData('subscriptions');
        $this->assertCount(1, $subs);
        $this->assertSame('Netflix', $subs->first()['label']);
        $this->assertSame(3, $subs->first()['count']);
        $this->assertSame(12.99, $subs->first()['amount']);
        $this->assertFalse($subs->first()['is_stale']);
    }

    public function test_does_not_flag_a_single_occurrence(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $this->expense([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'amount' => 12.99, 'description' => 'Netflix', 'date' => now()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->get('/subscriptions');
        $this->assertCount(0, $response->viewData('subscriptions'));
    }

    public function test_does_not_flag_charges_with_irregular_gaps(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'amount' => 40, 'description' => 'Random Store'];

        $this->expense($base + ['date' => now()->subDays(60)->format('Y-m-d')]);
        $this->expense($base + ['date' => now()->subDays(10)->format('Y-m-d')]); // 50-day gap, not monthly
        $this->expense($base + ['date' => now()->format('Y-m-d')]); // 10-day gap, not monthly

        $response = $this->actingAs($user)->get('/subscriptions');
        $this->assertCount(0, $response->viewData('subscriptions'));
    }

    public function test_does_not_flag_charges_with_varying_amounts(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'description' => 'Groceries'];

        $this->expense($base + ['amount' => 45, 'date' => now()->subMonths(2)->format('Y-m-d')]);
        $this->expense($base + ['amount' => 60, 'date' => now()->subMonths(1)->format('Y-m-d')]);
        $this->expense($base + ['amount' => 38, 'date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');
        $this->assertCount(0, $response->viewData('subscriptions'));
    }

    public function test_marks_a_subscription_stale_if_not_seen_recently(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'amount' => 9.99, 'description' => 'Old Gym'];

        $this->expense($base + ['date' => now()->subMonths(5)->format('Y-m-d')]);
        $this->expense($base + ['date' => now()->subMonths(4)->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');
        $subs = $response->viewData('subscriptions');
        $this->assertCount(1, $subs);
        $this->assertTrue($subs->first()['is_stale']);
    }

    public function test_ignores_income_transactions(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $base = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'amount' => 2500, 'description' => 'Salary', 'type' => 'income'];

        Transaction::create($base + ['date' => now()->subMonths(2)->format('Y-m-d')]);
        Transaction::create($base + ['date' => now()->subMonths(1)->format('Y-m-d')]);
        Transaction::create($base + ['date' => now()->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');
        $this->assertCount(0, $response->viewData('subscriptions'));
    }

    public function test_summary_totals_only_include_active_not_stale_subscriptions(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $active = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'amount' => 10, 'description' => 'Active Sub'];
        $this->expense($active + ['date' => now()->subMonths(1)->format('Y-m-d')]);
        $this->expense($active + ['date' => now()->format('Y-m-d')]);

        $stale = ['household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id, 'amount' => 999, 'description' => 'Canceled Sub'];
        $this->expense($stale + ['date' => now()->subMonths(6)->format('Y-m-d')]);
        $this->expense($stale + ['date' => now()->subMonths(5)->format('Y-m-d')]);

        $response = $this->actingAs($user)->get('/subscriptions');
        $response->assertViewHas('activeMonthlyCost', 10.0);
    }
}
