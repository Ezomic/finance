<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_manually_creating_a_transaction_records_an_activity_log_entry(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 40,
            'description' => 'Groceries',
            'date' => '2026-06-01',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'user_id' => $user->id,
            'subject_type' => Transaction::class,
            'action' => 'created',
        ]);
    }

    public function test_editing_a_transaction_records_an_updated_entry_with_changes(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $transaction = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 40, 'description' => 'Groceries', 'date' => '2026-06-01',
        ]);

        $this->actingAs($user)->put("/transactions/{$transaction->id}", [
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 55,
            'description' => 'Groceries',
            'date' => '2026-06-01',
        ]);

        $log = ActivityLog::where('subject_type', Transaction::class)->where('action', 'updated')->first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('amount', $log->changes);
    }

    public function test_deleting_a_transaction_records_a_deleted_entry(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $transaction = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 40, 'description' => 'Groceries', 'date' => '2026-06-01',
        ]);

        $this->actingAs($user)->delete("/transactions/{$transaction->id}");

        $this->assertDatabaseHas('activity_logs', [
            'household_id' => $household->id,
            'subject_type' => Transaction::class,
            'action' => 'deleted',
        ]);
    }

    public function test_import_produces_a_single_summary_entry_not_one_per_row(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $csv = "date,amount,description\n2026-06-01,-10,Coffee\n2026-06-02,-20,Lunch\n2026-06-03,-30,Dinner\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $this->actingAs($user)->post('/import', [
            'files' => [$file],
            'account_id' => $account->id,
            'format' => 'csv_generic',
        ]);

        $this->assertSame(1, ActivityLog::where('action', 'imported')->count());
        $this->assertSame(0, ActivityLog::where('subject_type', Transaction::class)->where('action', 'created')->count());
    }

    public function test_categorize_apply_produces_a_single_summary_entry_not_one_per_transaction(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();
        $category = Category::create(['household_id' => $household->id, 'name' => 'Groceries', 'type' => 'expense', 'color' => '#000']);

        $t1 = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 10, 'description' => 'A', 'date' => '2026-06-01',
        ]);
        $t2 = Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 20, 'description' => 'B', 'date' => '2026-06-02',
        ]);

        $this->actingAs($user)->post('/categorize', [
            'transaction_ids' => [$t1->id, $t2->id],
            'category_choice' => (string) $category->id,
        ]);

        $this->assertSame(1, ActivityLog::where('action', 'categorized')->count());
    }

    public function test_does_not_log_activity_without_an_authenticated_actor(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 40, 'description' => 'Groceries', 'date' => '2026-06-01',
        ]);

        $this->assertSame(0, ActivityLog::count());
    }
}
