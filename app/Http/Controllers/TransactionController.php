<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $household = $this->household();

        $query = $household->transactions()->with(['account', 'category', 'user', 'transferAccount'])
            ->orderByDesc('date')->orderByDesc('id');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('import_batch')) {
            $query->where('import_batch', $request->string('import_batch'));
        }

        $transactions = $query->paginate(25)->withQueryString();
        $accounts = $household->accounts()->orderBy('name')->get();
        $categories = $household->categoriesTree();
        $importBatch = $request->string('import_batch')->toString() ?: null;
        $uncategorizedInBatch = $importBatch
            ? $household->transactions()->where('import_batch', $importBatch)->whereNull('category_id')->count()
            : 0;

        return view('transactions.index', compact('transactions', 'accounts', 'categories', 'importBatch', 'uncategorizedInBatch'));
    }

    public function create()
    {
        $household = $this->household();
        $accounts = $household->accounts()->where('is_archived', false)->orderBy('name')->get();
        $categories = $household->categoriesTree();

        return view('transactions.create', compact('accounts', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'transfer_account_id' => ['nullable', 'exists:accounts,id', 'different:account_id'],
            'type' => ['required', 'in:income,expense,transfer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        if ($data['type'] !== 'transfer') {
            $data['transfer_account_id'] = null;
        } else {
            $data['category_id'] = null;
        }

        $data['household_id'] = $this->household()->id;
        $data['user_id'] = $request->user()->id;

        Transaction::create($data);

        return redirect()->route('transactions.index')->with('status', 'Transaction recorded.');
    }

    public function edit(Transaction $transaction)
    {
        $this->abortUnlessOwned($transaction);
        $household = $this->household();
        $accounts = $household->accounts()->orderBy('name')->get();
        $categories = $household->categoriesTree();

        return view('transactions.edit', compact('transaction', 'accounts', 'categories'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->abortUnlessOwned($transaction);

        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'transfer_account_id' => ['nullable', 'exists:accounts,id', 'different:account_id'],
            'type' => ['required', 'in:income,expense,transfer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        if ($data['type'] !== 'transfer') {
            $data['transfer_account_id'] = null;
        } else {
            $data['category_id'] = null;
        }

        $transaction->update($data);

        return redirect()->route('transactions.index')->with('status', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction)
    {
        $this->abortUnlessOwned($transaction);
        $transaction->delete();

        return back()->with('status', 'Transaction deleted.');
    }
}
