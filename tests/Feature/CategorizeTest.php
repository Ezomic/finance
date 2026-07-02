<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorizeTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);
        $account = Account::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'name' => 'Checking',
            'type' => 'checking',
        ]);

        return [$household, $user, $account];
    }

    public function test_groups_uncategorized_transactions_by_similar_description_and_excludes_categorized_and_transfers(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 21, 'description' => 'Vitens NV – invoice 1', 'date' => '2026-06-01',
        ]);
        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 5, 'description' => 'Vitens NV – invoice 2', 'date' => '2026-06-15',
        ]);
        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 30, 'description' => 'Already categorized', 'date' => '2026-06-01',
            'category_id' => Category::create(['household_id' => $household->id, 'name' => 'Misc', 'type' => 'expense', 'color' => '#000000'])->id,
        ]);
        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'transfer', 'amount' => 100, 'description' => 'Own transfer', 'date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->get('/categorize');

        $response->assertOk();
        $response->assertViewHas('groups', function ($groups) {
            return $groups->count() === 1 && $groups->first()['transactions']->count() === 2;
        });
    }

    public function test_suggests_existing_category_from_households_own_history(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $utilities = Category::create(['household_id' => $household->id, 'name' => 'Nutsvoorzieningen', 'type' => 'expense', 'color' => '#000000']);

        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 20, 'description' => 'Vitens NV – old invoice', 'date' => '2026-05-01',
            'category_id' => $utilities->id,
        ]);
        $new = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 21, 'description' => 'Vitens NV – new invoice', 'date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->get('/categorize');

        $group = $response->viewData('groups')->first();
        $this->assertSame($utilities->id, $group['suggested_category_id']);
        $this->assertTrue($group['transactions']->pluck('id')->contains($new->id));
    }

    public function test_suggests_new_category_named_after_keyword_rule_when_none_exists(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 45, 'description' => 'Albert Heijn 1234', 'date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->get('/categorize');

        $group = $response->viewData('groups')->first();
        $this->assertNull($group['suggested_category_id']);
        $this->assertSame('Groceries', $group['suggested_new_name']);
    }

    public function test_apply_only_updates_selected_transactions_with_existing_category(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $category = Category::create(['household_id' => $household->id, 'name' => 'Utilities', 'type' => 'expense', 'color' => '#000000']);

        $keep = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 21, 'description' => 'Vitens NV – a', 'date' => '2026-06-01',
        ]);
        $skip = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 22, 'description' => 'Vitens NV – b', 'date' => '2026-06-02',
        ]);

        $response = $this->actingAs($user)->post('/categorize', [
            'transaction_ids' => [$keep->id],
            'category_choice' => (string) $category->id,
        ]);

        $response->assertRedirect(route('categorize.index'));
        $this->assertSame($category->id, $keep->fresh()->category_id);
        $this->assertNull($skip->fresh()->category_id);
    }

    public function test_apply_can_create_a_brand_new_category(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $transaction = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 21, 'description' => 'Some New Payee', 'date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->post('/categorize', [
            'transaction_ids' => [$transaction->id],
            'category_choice' => 'new',
            'new_category_name' => 'Pet Care',
            'new_category_type' => 'expense',
        ]);

        $response->assertRedirect(route('categorize.index'));
        $this->assertDatabaseHas('categories', ['household_id' => $household->id, 'name' => 'Pet Care', 'type' => 'expense']);
        $this->assertSame('Pet Care', $transaction->fresh()->category->name);
    }

    public function test_cannot_apply_a_category_belonging_to_another_household(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherCategory = Category::create(['household_id' => $otherHousehold->id, 'name' => 'Not yours', 'type' => 'expense', 'color' => '#000000']);

        $transaction = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 21, 'description' => 'Some Payee', 'date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->post('/categorize', [
            'transaction_ids' => [$transaction->id],
            'category_choice' => (string) $otherCategory->id,
        ]);

        $response->assertForbidden();
        $this->assertNull($transaction->fresh()->category_id);
    }
}
