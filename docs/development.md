# Development

## Local setup

This project runs under [Laravel Herd](https://herd.laravel.com) — Herd serves the app directly from this directory at `https://finance.test`, no `php artisan serve` needed. Herd's PHP isn't on `PATH` by default; either add it or prefix commands:

```bash
export PATH="/Users/$(whoami)/Library/Application Support/Herd/bin:$PATH"
php artisan migrate
```

Frontend assets are built with Vite (`npm run dev` for live reload, `npm run build` for production assets — `public/build/manifest.json` must exist for pages to render correctly).

## Testing

```bash
php artisan test          # all tests
php artisan test --filter=SplitTransactionTest
```

Tests use `RefreshDatabase` against an in-memory SQLite DB and `CACHE_STORE=array` (see `phpunit.xml`) — both reset per test run, but the array cache does **not** reset between test *methods* within the same run, so tests that exercise rate limiting or other cache-backed state should use distinct keys (e.g. a unique email) per test rather than relying on isolation.

Every feature gets its own test class named for what it covers (`BillCalendarTest`, `TransactionOwnershipTest`, etc.) rather than one giant controller test — makes it obvious what's covered and what isn't at a glance.

## Branching and PR conventions

Each feature or fix gets its own branch and PR off `main` — even when several features were designed together, they're split into independently mergeable pieces rather than one large PR. When two features touch the same file (e.g. both add a trait/import to a model), that's fine — resolve it as a normal merge conflict when both land, don't avoid the overlap by bundling unrelated work into one PR.

Only stack a branch on another (rather than basing both off `main`) when there's a genuine code dependency — e.g. the cash flow forecast branch was based on the bill-calendar branch because it calls `Bill::occurrencesBetween()`, which didn't exist on `main` yet. Once the base branch merges, GitHub retargets the stacked PR's base to `main` automatically if the base branch is deleted after merging; if it isn't deleted, retarget manually.

Commit messages and PR descriptions explain *why*, not just what — a one-line summary of the change plus, where it's not obvious, the reasoning or trade-off behind it.
