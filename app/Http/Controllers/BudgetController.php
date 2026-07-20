<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $household = $this->household();
        $month = $request->filled('month') ? Carbon::parse($request->string('month').'-01') : Carbon::now()->startOfMonth();

        $budgets = $household->budgets()
            ->with('category')
            ->whereYear('month', $month->year)
            ->whereMonth('month', $month->month)
            ->get();

        $categories = $household->categoriesTree('expense');

        return view('budgets.index', compact('budgets', 'categories', 'month'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')->where('household_id', $this->household()->id)],
            'month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        Budget::updateOrCreate(
            [
                'household_id' => $this->household()->id,
                'category_id' => $data['category_id'],
                'month' => $data['month'].'-01',
            ],
            ['amount' => $data['amount']]
        );

        return back()->with('status', 'Budget saved.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->abortUnlessOwned($budget);
        $budget->delete();

        return back()->with('status', 'Budget removed.');
    }
}
