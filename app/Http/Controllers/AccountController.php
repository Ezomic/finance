<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $accounts = $this->household()->accounts()->with('owner')->orderBy('name')->get();

        return view('accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        return view('accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:checking,savings,credit,cash,investment'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric'],
        ]);

        $data = [];

        foreach (is_array($validated) ? $validated : [] as $key => $value) {
            $data[(string) $key] = $value;
        }

        $data['household_id'] = $this->household()->id;
        $data['user_id'] = $this->currentUser()->id;

        Account::create($data);

        return redirect()->route('accounts.index')->with('status', 'Account added.');
    }

    public function show(Account $account): View
    {
        $this->abortUnlessOwned($account);
        $account->load(['transactions' => fn (Relation $q) => $q->orderByDesc('date')->limit(25), 'transactions.category', 'netWorthSnapshots']);

        return view('accounts.show', compact('account'));
    }

    public function edit(Account $account): View
    {
        $this->abortUnlessOwned($account);

        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->abortUnlessOwned($account);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:checking,savings,credit,cash,investment'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric'],
            'is_archived' => ['sometimes', 'boolean'],
        ]);

        $data = [];

        foreach (is_array($validated) ? $validated : [] as $key => $value) {
            $data[(string) $key] = $value;
        }
        $data['is_archived'] = $request->boolean('is_archived');

        $account->update($data);

        return redirect()->route('accounts.show', $account)->with('status', 'Account updated.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->abortUnlessOwned($account);
        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Account removed.');
    }

    public function snapshot(Request $request, Account $account): RedirectResponse
    {
        $this->abortUnlessOwned($account);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'balance' => ['required', 'numeric'],
        ]);

        $data = [];

        foreach (is_array($validated) ? $validated : [] as $key => $value) {
            $data[(string) $key] = $value;
        }
        $data['household_id'] = $this->household()->id;

        $account->netWorthSnapshots()->updateOrCreate(['date' => $data['date']], ['balance' => $data['balance'], 'household_id' => $data['household_id']]);

        return back()->with('status', 'Balance snapshot saved.');
    }
}
