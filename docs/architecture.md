# Architecture

## Stack

- PHP 8.3+ / Laravel
- Server-rendered Blade views + Tailwind CSS v4 + Alpine.js for interactivity (split-transaction editor, type toggles) + Chart.js for graphs
- No API layer, no Vue/Inertia, no Livewire — every page is a plain controller action returning a Blade view
- SQLite by default (`DB_CONNECTION=sqlite`); works with any Laravel-supported driver
- PHPUnit-style feature tests (class-based, `RefreshDatabase`, not Pest's functional syntax) under `tests/Feature`

## Domain model

```
User ──belongsToMany──▶ Household (pivot: household_user, role: owner|member)
                            │
                            ├─▶ Account (checking/savings/credit/cash/investment)
                            │     └─▶ NetWorthSnapshot (manual balance checkpoints)
                            │
                            ├─▶ Category (self-referential parent_id, one level of subcategories)
                            │
                            ├─▶ Transaction (belongs to Account + Category + User)
                            │     ├─▶ transfer_account_id (nullable, for account-to-account transfers)
                            │     └─▶ TransactionSplit (when is_split, divides the amount across categories)
                            │
                            ├─▶ Budget (per category, per month)
                            ├─▶ Bill (recurring: weekly/monthly/yearly)
                            └─▶ ActivityLog (audit trail)
```

A `User` has a `current_household_id` — the household they're actively working in. Every domain table carries a `household_id`; there's no global cross-household query anywhere in the app by design.

## Multi-tenancy convention

Every controller extends `app/Http/Controllers/Controller.php`, which provides:

- `household()` — returns `auth()->user()->currentHousehold`. Controllers use this instead of querying households directly.
- `abortUnlessOwned($model)` — aborts with 403 if `$model->household_id` doesn't match the current household. Called before any update/delete on a household-scoped model resolved via route-model binding.

**Validation rules that reference another model by ID must scope the `exists` check to the household**, not just check the ID exists at all:

```php
'category_id' => ['nullable', Rule::exists('categories', 'id')->where('household_id', $householdId)],
```

A plain `'exists:categories,id'` lets a user submit *any* household's category/account ID and have it silently accepted — see [security.md](security.md) for why this matters and where it's been missed before.

## The `Support/` convention

Business logic that doesn't need to be an Eloquent model lives in `app/Support/` as small, mostly-static classes with no side effects — easy to unit test in isolation:

- `CategoryGuesser` — keyword-based category suggestions for imported transactions
- `TransactionNormalizer` — strips noise from raw bank descriptions for grouping/matching
- `RecurringDetector` — subscription/recurring-income detection, price-change alerts
- `CategorySpending` — category totals that account for both direct and split transactions
- `CashFlowForecaster` — projects account balances forward from bills + recurring transactions + a smoothed daily rate
- `SpendingInsights` — flags categories trending above average and outlier transactions
- `LoginThrottle` — computes the rate-limiter cache key for clearing login throttling on success
- `Concerns/LogsActivity` — trait that records create/update/delete on a model into the activity log

Controllers stay thin: fetch data scoped to the household, hand it to a Support class or the view, redirect with a status message.

## Directory layout

```
app/
  Http/Controllers/   one controller per feature area
  Models/             Eloquent models, household-scoped
  Support/            pure logic (see above)
resources/views/      Blade views, one directory per feature, layouts/app.blade.php is the shell
database/migrations/  one migration per schema change (no consolidated "create everything" migration)
tests/Feature/        one test class per controller/feature, named for what it covers
```
