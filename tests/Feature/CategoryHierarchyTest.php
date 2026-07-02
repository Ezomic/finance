<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);

        return [$household, $user];
    }

    public function test_can_create_a_subcategory_which_inherits_the_parents_type(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $parent = Category::create(['household_id' => $household->id, 'name' => 'Housing', 'type' => 'expense', 'color' => '#000000']);

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Rent',
            'type' => 'income', // deliberately wrong — server should override from parent
            'color' => '#111111',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'household_id' => $household->id, 'name' => 'Rent', 'parent_id' => $parent->id, 'type' => 'expense',
        ]);
    }

    public function test_cannot_nest_a_subcategory_two_levels_deep(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $parent = Category::create(['household_id' => $household->id, 'name' => 'Housing', 'type' => 'expense', 'color' => '#000000']);
        $child = Category::create(['household_id' => $household->id, 'parent_id' => $parent->id, 'name' => 'Rent', 'type' => 'expense', 'color' => '#000000']);

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Grandchild',
            'type' => 'expense',
            'color' => '#222222',
            'parent_id' => $child->id, // child is not top-level -> invalid parent
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('categories', ['name' => 'Grandchild']);
    }

    public function test_cannot_reparent_a_category_that_already_has_children(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $parentA = Category::create(['household_id' => $household->id, 'name' => 'Housing', 'type' => 'expense', 'color' => '#000000']);
        Category::create(['household_id' => $household->id, 'parent_id' => $parentA->id, 'name' => 'Rent', 'type' => 'expense', 'color' => '#000000']);
        $parentB = Category::create(['household_id' => $household->id, 'name' => 'Utilities', 'type' => 'expense', 'color' => '#000000']);

        $response = $this->actingAs($user)->put("/categories/{$parentA->id}", [
            'name' => 'Housing',
            'type' => 'expense',
            'color' => '#000000',
            'parent_id' => $parentB->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseHas('categories', ['id' => $parentA->id, 'parent_id' => null]);
    }

    public function test_cannot_set_a_category_as_its_own_parent(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $category = Category::create(['household_id' => $household->id, 'name' => 'Housing', 'type' => 'expense', 'color' => '#000000']);

        $response = $this->actingAs($user)->put("/categories/{$category->id}", [
            'name' => 'Housing',
            'type' => 'expense',
            'color' => '#000000',
            'parent_id' => $category->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_use_another_households_category_as_parent(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $other = Household::create(['name' => 'Other', 'currency' => 'EUR']);
        $foreignParent = Category::create(['household_id' => $other->id, 'name' => 'Not yours', 'type' => 'expense', 'color' => '#000000']);

        $response = $this->actingAs($user)->post('/categories', [
            'name' => 'Rent',
            'type' => 'expense',
            'color' => '#000000',
            'parent_id' => $foreignParent->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_deleting_a_parent_promotes_children_to_top_level_instead_of_deleting_them(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $parent = Category::create(['household_id' => $household->id, 'name' => 'Housing', 'type' => 'expense', 'color' => '#000000']);
        $child = Category::create(['household_id' => $household->id, 'parent_id' => $parent->id, 'name' => 'Rent', 'type' => 'expense', 'color' => '#000000']);

        $this->actingAs($user)->delete("/categories/{$parent->id}");

        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => null]);
    }

    public function test_categories_tree_orders_children_immediately_after_their_parent(): void
    {
        [$household, $user] = $this->setUpHousehold();
        $b = Category::create(['household_id' => $household->id, 'name' => 'B Top', 'type' => 'expense', 'color' => '#000000']);
        $a = Category::create(['household_id' => $household->id, 'name' => 'A Top', 'type' => 'expense', 'color' => '#000000']);
        $bChild = Category::create(['household_id' => $household->id, 'parent_id' => $b->id, 'name' => 'B Child', 'type' => 'expense', 'color' => '#000000']);

        $tree = $household->categoriesTree();

        $this->assertSame(['A Top', 'B Top', 'B Child'], $tree->pluck('name')->all());
    }
}
