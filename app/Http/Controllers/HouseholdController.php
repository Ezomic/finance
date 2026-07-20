<?php

namespace App\Http\Controllers;

use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function create(): View
    {
        return view('households.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $household = Household::create(['name' => $data['name']]);
        $household->users()->attach($request->user()->id, ['role' => 'owner']);
        $request->user()->forceFill(['current_household_id' => $household->id])->save();

        // Seed a handful of sensible default categories so the app isn't empty on day one.
        $defaults = [
            ['name' => 'Salary', 'type' => 'income', 'color' => '#4E7A48'],
            ['name' => 'Groceries', 'type' => 'expense', 'color' => '#B4602F'],
            ['name' => 'Housing', 'type' => 'expense', 'color' => '#96502A'],
            ['name' => 'Transport', 'type' => 'expense', 'color' => '#3E6339'],
            ['name' => 'Utilities', 'type' => 'expense', 'color' => '#A8432E'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#C97B4A'],
        ];
        foreach ($defaults as $category) {
            $household->categories()->create($category);
        }

        return redirect()->route('dashboard')->with('status', 'Household created. Welcome in!');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $household = Household::where('invite_code', strtoupper($data['invite_code']))->first();

        if (! $household) {
            return back()->withErrors(['invite_code' => 'No household matches that invite code.']);
        }

        $household->users()->syncWithoutDetaching([$request->user()->id => ['role' => 'member']]);
        $request->user()->forceFill(['current_household_id' => $household->id])->save();

        return redirect()->route('dashboard')->with('status', "You've joined {$household->name}.");
    }

    public function switch(Request $request, Household $household): RedirectResponse
    {
        if (! $request->user()->households()->where('households.id', $household->id)->exists()) {
            abort(403);
        }

        $request->user()->forceFill(['current_household_id' => $household->id])->save();

        return redirect()->route('dashboard');
    }

    public function settings(): View
    {
        $household = $this->household();
        $household->load('users');

        return view('households.settings', compact('household'));
    }
}
