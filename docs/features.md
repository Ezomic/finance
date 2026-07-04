# Features

## Accounts

Checking, savings, credit, cash, and investment accounts, each with an opening balance. Current balance is computed from opening balance + transaction history, or from the latest manual balance snapshot for investment accounts (or any account you've reconciled — see `Account::balanceAsOf()`). Archiving an account hides it from balance totals without deleting its history.

## Transactions

Income, expense, or transfer entries against an account. A transaction can be **split** across multiple categories instead of one (e.g. a supermarket receipt split into groceries + household) — split amounts must sum to the transaction total, and once split the transaction has no single `category_id` of its own; its splits carry the categories instead. Budgets, reports, and the categorize workflow all account for this via `CategorySpending`.

## Categorize workflow

`/categorize` groups uncategorized transactions by normalized description (so "BEA, Google Pay Kruidvat 3662" and "BEA, Google Pay Kruidvat 9981" group together) and suggests a category for each group, in order of preference:

1. What this household has called similar transactions before (`CategorizeController::historyVotes()`)
2. A keyword-based guess (`CategoryGuesser`, e.g. "Albert Heijn" → Groceries)
3. Falls back to suggesting a new category named after the merchant

Applying a suggestion updates every transaction in the group at once.

## Budgets

A target amount per category per month. Progress bars on the dashboard and budgets page compare actual spend (via `CategorySpending`, split-aware) against the target.

## Bills

Recurring payments (weekly/monthly/yearly) with a due day, optional linked account/category, and a "mark paid" action. `/bills/calendar` shows a month-grid view of every occurrence, computed by `Bill::occurrencesBetween()` walking the bill's cadence across a date range — the same method backs the cash flow forecast.

## Subscriptions

`/subscriptions` runs `RecurringDetector` over expense history to find charges from the same payee + account + amount, at least twice, roughly a month apart (tolerating a skipped month). Three sections:

- **Price changes** — a recurring charge whose most recent occurrence costs more than the one before it (same payee/account, cadence still holds, amount now differs)
- **Subscriptions** — the detected recurring charges themselves, with projected annual cost and next-expected date; flagged stale if not seen in 45+ days
- **Recurring income** — the same detector run against income transactions (salary, etc.)

## Cash flow forecast

`/forecast` projects account balances forward 30/60/90 days from:

- Every bill occurrence in the window (`Bill::occurrencesBetween()`)
- Detected recurring income/expenses (`RecurringDetector`), excluding any that already match an active bill by account + amount (to avoid double-counting)
- A smoothed daily rate for everything else, derived from trailing-90-day spending minus what's already attributed to bills/recurring expenses

This is a projection, not a guarantee — it's only as good as the recurring patterns it can detect.

## Spending insights

Surfaced on the dashboard, `SpendingInsights` flags two things, capped at 5 total:

- A category running >30% and ≥20 (currency units) above its trailing 3-month average, provided it has real spending history in each of those months
- An individual transaction more than 2x its category's trailing-6-month average transaction size, provided the category has at least 5 prior transactions to compare against

## Activity log

`/activity` records create/update/delete on transactions, categories, budgets, bills, and accounts (via the `LogsActivity` trait), plus one summary entry each for bulk operations that bypass those model events by design:

- Import (`ImportController::store` uses `Transaction::insert()`, not `create()`) — one "Imported N transactions from ..." entry, not one per row
- Bulk categorize (`CategorizeController::apply` uses a query-builder `update()`) — one "Categorized N transactions as ..." entry

Entries aren't recorded without an authenticated actor, so seeders/console commands stay silent.

## Bank statement import

See [import-formats.md](import-formats.md).

## Reports

Spending by category (this month, split-aware), income vs. expense (last 6 months), and net worth over time (last 12 months, honoring balance checkpoints) — all Chart.js graphs on `/reports`.

## Households

Multi-user by design: create a household (seeds a handful of default categories), invite others via an 8-character invite code, switch between households you belong to. See [security.md](security.md) for the invite-code rate limiting.
