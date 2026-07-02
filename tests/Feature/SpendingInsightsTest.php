<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SpendingInsightsTest extends TestCase
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

    public function test_flags_a_category_trending_well_above_its_recent_average(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $dining = Category::create(['household_id' => $household->id, 'name' => 'Dining', 'type' => 'expense', 'color' => '#000']);
        $thisMonth = Carbon::now()->startOfMonth();

        foreach ([1, 2, 3] as $i) {
            Transaction::create([
                'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
                'type' => 'expense', 'amount' => 100, 'category_id' => $dining->id,
                'date' => $thisMonth->copy()->subMonths($i)->addDays(2)->format('Y-m-d'),
            ]);
        }
        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 200, 'category_id' => $dining->id,
            'date' => $thisMonth->copy()->addDays(2)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $insights = $response->viewData('insights');

        $this->assertTrue($insights->contains(fn (array $i) => $i['type'] === 'category_trend' && str_contains($i['message'], 'Dining')));
    }

    public function test_does_not_flag_a_brand_new_category_with_no_prior_history(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $newCategory = Category::create(['household_id' => $household->id, 'name' => 'New Hobby', 'type' => 'expense', 'color' => '#000']);

        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 500, 'category_id' => $newCategory->id, 'date' => Carbon::now()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $insights = $response->viewData('insights');

        $this->assertFalse($insights->contains(fn (array $i) => str_contains($i['message'], 'New Hobby')));
    }

    public function test_flags_a_transaction_much_larger_than_its_categorys_typical_size(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $groceries = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000']);
        $thisMonth = Carbon::now()->startOfMonth();

        foreach (range(1, 5) as $n) {
            Transaction::create([
                'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
                'type' => 'expense', 'amount' => 40, 'category_id' => $groceries->id,
                'date' => $thisMonth->copy()->subMonths(1)->addDays($n)->format('Y-m-d'),
            ]);
        }

        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 300, 'description' => 'Big Party Shop', 'category_id' => $groceries->id,
            'date' => $thisMonth->copy()->addDays(2)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $insights = $response->viewData('insights');

        $this->assertTrue($insights->contains(fn (array $i) => $i['type'] === 'outlier_transaction' && str_contains($i['message'], 'Big Party Shop')));
    }

    public function test_does_not_flag_ordinary_transactions_within_normal_range(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $groceries = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000']);
        $thisMonth = Carbon::now()->startOfMonth();

        foreach (range(1, 5) as $n) {
            Transaction::create([
                'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
                'type' => 'expense', 'amount' => 40 + $n, 'category_id' => $groceries->id,
                'date' => $thisMonth->copy()->subMonths(1)->addDays($n)->format('Y-m-d'),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');
        $insights = $response->viewData('insights');

        $this->assertCount(0, $insights);
    }
}
