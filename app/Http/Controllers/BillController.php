<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'frequency' => ['required', 'in:weekly,monthly,yearly'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'account_id' => ['nullable', 'exists:accounts,id'],
        ]);
        $data['household_id'] = $this->household()->id;

        Bill::create($data);

        return back()->with('status', 'Bill added.');
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'frequency' => ['required', 'in:weekly,monthly,yearly'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'account_id' => ['nullable', 'exists:accounts,id'],
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
}
