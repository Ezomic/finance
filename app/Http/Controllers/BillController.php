<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BillController extends Controller
{
    public function index()
    {
        $household = $this->household();
        $bills = $household->bills()->with(['category', 'account'])->orderBy('due_day')->get()
            ->sortBy(fn (Bill $bill) => $bill->nextDueDate());

        $categories = $household->categoriesTree();
        $accounts = $household->accounts()->orderBy('name')->get();

        return view('bills.index', compact('bills', 'categories', 'accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['household_id'] = $this->household()->id;

        Bill::create($data);

        return back()->with('status', 'Bill added.');
    }

    public function calendar(Request $request)
    {
        $household = $this->household();
        $month = $request->filled('month') ? Carbon::parse($request->string('month').'-01') : Carbon::now()->startOfMonth();

        $bills = $household->bills()->where('is_active', true)->with(['category', 'account'])->get();

        $occurrencesByDay = collect();
        foreach ($bills as $bill) {
            foreach ($bill->occurrencesInMonth($month) as $date) {
                $key = $date->format('Y-m-d');
                $occurrencesByDay->put($key, $occurrencesByDay->get($key, collect())->push([
                    'bill' => $bill,
                    'paid' => $date->isSameDay($bill->nextDueDate()) ? $bill->isPaidThisCycle() : $date->isPast(),
                ]));
            }
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $leadingBlanks = $start->dayOfWeekIso - 1; // Monday-first grid

        $days = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $days->push(['date' => $date->copy(), 'occurrences' => $occurrencesByDay->get($date->format('Y-m-d'), collect())]);
        }

        return view('bills.calendar', compact('days', 'month', 'leadingBlanks'));
    }

    public function markPaid(Bill $bill)
    {
        $this->abortUnlessOwned($bill);
        $bill->update(['last_paid_on' => Carbon::today()]);

        return back()->with('status', "{$bill->name} marked as paid.");
    }

    public function update(Request $request, Bill $bill)
    {
        $this->abortUnlessOwned($bill);

        $data = $request->validate($this->rules() + [
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $bill->update($data);

        return back()->with('status', 'Bill updated.');
    }

    public function destroy(Bill $bill)
    {
        $this->abortUnlessOwned($bill);
        $bill->delete();

        return back()->with('status', 'Bill removed.');
    }

    private function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'frequency' => ['required', 'in:weekly,monthly,yearly'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('household_id', $householdId)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
        ];
    }
}
