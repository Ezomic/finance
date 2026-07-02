<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);

        return [$household, $user];
    }

    public function test_a_monthly_bill_appears_once_on_its_due_day_this_month(): void
    {
        [$household, $user] = $this->setUpHousehold();
        Bill::create([
            'household_id' => $household->id, 'name' => 'Rent', 'amount' => 1200,
            'due_day' => 5, 'frequency' => 'monthly', 'is_active' => true,
        ]);

        $month = Carbon::now()->startOfMonth()->format('Y-m');
        $response = $this->actingAs($user)->get("/bills/calendar?month={$month}");

        $response->assertOk();
        $days = $response->viewData('days');
        $matches = $days->filter(fn ($day) => $day['occurrences']->isNotEmpty());
        $this->assertCount(1, $matches);
        $this->assertSame(5, $matches->first()['date']->day);
    }

    public function test_a_weekly_bill_appears_multiple_times_in_a_month(): void
    {
        [$household, $user] = $this->setUpHousehold();
        Bill::create([
            'household_id' => $household->id, 'name' => 'Groceries budget', 'amount' => 50,
            'due_day' => 1, 'frequency' => 'weekly', 'is_active' => true,
        ]);

        $month = Carbon::now()->startOfMonth()->format('Y-m');
        $response = $this->actingAs($user)->get("/bills/calendar?month={$month}");

        $days = $response->viewData('days');
        $matches = $days->filter(fn ($day) => $day['occurrences']->isNotEmpty());
        $this->assertGreaterThanOrEqual(3, $matches->count());
    }

    public function test_calendar_shows_occurrences_for_a_past_month(): void
    {
        [$household, $user] = $this->setUpHousehold();
        Bill::create([
            'household_id' => $household->id, 'name' => 'Rent', 'amount' => 1200,
            'due_day' => 10, 'frequency' => 'monthly', 'is_active' => true,
        ]);

        $pastMonth = Carbon::now()->subMonths(3)->startOfMonth()->format('Y-m');
        $response = $this->actingAs($user)->get("/bills/calendar?month={$pastMonth}");

        $days = $response->viewData('days');
        $matches = $days->filter(fn ($day) => $day['occurrences']->isNotEmpty());
        $this->assertCount(1, $matches);
        $this->assertSame(10, $matches->first()['date']->day);
    }
}
