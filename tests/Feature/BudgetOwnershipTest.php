<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetOwnershipTest extends TestCase
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

    public function test_cannot_create_a_budget_against_another_households_category(): void
    {
        [, $user] = $this->setUpHousehold();
        $otherHousehold = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $otherCategory = Category::create(['household_id' => $otherHousehold->id, 'name' => 'Not yours', 'type' => 'expense', 'color' => '#000']);

        $response = $this->actingAs($user)->post('/budgets', [
            'category_id' => $otherCategory->id,
            'month' => '2026-06',
            'amount' => 200,
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('budgets', ['category_id' => $otherCategory->id]);
    }

    public function test_can_still_create_a_budget_for_an_owned_category(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $category = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000']);

        $response = $this->actingAs($user)->post('/budgets', [
            'category_id' => $category->id,
            'month' => '2026-06',
            'amount' => 200,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('budgets', ['category_id' => $category->id, 'amount' => 200]);
    }
}
