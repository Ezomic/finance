<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceCheckpointTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);
        $account = Account::create([
            'household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Checking', 'type' => 'checking', 'opening_balance' => 0,
        ]);

        return [$household, $user, $account];
    }

    public function test_balance_is_opening_balance_plus_all_transactions_with_no_checkpoint(): void
    {
        [, $user, $account] = $this->setUpHousehold();

        Transaction::create(['household_id' => $account->household_id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'income', 'amount' => 100, 'date' => '2026-01-01']);
        Transaction::create(['household_id' => $account->household_id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'expense', 'amount' => 40, 'date' => '2026-02-01']);

        $this->assertSame(60.0, $account->fresh()->balance);
    }

    public function test_setting_a_checkpoint_then_importing_older_transactions_does_not_move_the_balance(): void
    {
        [, $user, $account] = $this->setUpHousehold();

        // Reconcile: as of today, the real balance is 500.
        $this->actingAs($user)->post("/accounts/{$account->id}/snapshot", [
            'date' => now()->format('Y-m-d'),
            'balance' => 500,
        ]);

        $this->assertSame(500.0, $account->fresh()->balance);

        // Import an older statement — dated before the checkpoint.
        Transaction::create([
            'household_id' => $account->household_id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 9999, 'date' => now()->subMonths(3)->format('Y-m-d'),
        ]);

        $this->assertSame(500.0, $account->fresh()->balance, 'An older transaction must not move the checkpointed balance.');
    }

    public function test_transactions_dated_after_the_checkpoint_do_move_the_balance(): void
    {
        [, $user, $account] = $this->setUpHousehold();

        $this->actingAs($user)->post("/accounts/{$account->id}/snapshot", [
            'date' => now()->subDays(5)->format('Y-m-d'),
            'balance' => 500,
        ]);

        Transaction::create([
            'household_id' => $account->household_id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 50, 'date' => now()->subDays(1)->format('Y-m-d'),
        ]);
        Transaction::create([
            'household_id' => $account->household_id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'income', 'amount' => 20, 'date' => now()->format('Y-m-d'),
        ]);

        $this->assertSame(470.0, $account->fresh()->balance);
    }

    public function test_balance_as_of_a_date_before_the_checkpoint_ignores_the_checkpoint(): void
    {
        [, $user, $account] = $this->setUpHousehold();

        Transaction::create(['household_id' => $account->household_id, 'account_id' => $account->id, 'user_id' => $user->id, 'type' => 'income', 'amount' => 100, 'date' => '2026-01-15']);

        $this->actingAs($user)->post("/accounts/{$account->id}/snapshot", [
            'date' => '2026-06-01',
            'balance' => 5000, // a big manual correction as of June
        ]);

        // Asking for the balance in, say, February should reflect pre-checkpoint history, not the June figure.
        $this->assertSame(100.0, $account->fresh()->balanceAsOf(\Illuminate\Support\Carbon::parse('2026-02-28')));
    }

    public function test_transaction_dated_the_same_day_as_the_checkpoint_does_not_double_count(): void
    {
        [, $user, $account] = $this->setUpHousehold();

        $today = now()->format('Y-m-d');

        Transaction::create([
            'household_id' => $account->household_id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 30, 'date' => $today,
        ]);

        $this->actingAs($user)->post("/accounts/{$account->id}/snapshot", [
            'date' => $today,
            'balance' => 500, // already reflects today's -30 transaction
        ]);

        $this->assertSame(500.0, $account->fresh()->balance);
    }
}
