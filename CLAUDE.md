# Finance

Self-hosted household finance tracker. See [`docs/`](docs/README.md) for architecture, features, and security conventions in depth — this file is the quick-reference for working in this specific codebase.

## Stack (overrides global defaults for this project)

This app does **not** use Inertia/Vue, Wayfinder, or Livewire, despite those being your usual defaults elsewhere:

- Plain Blade views + Tailwind CSS v4 + Alpine.js for interactivity + Chart.js for graphs
- No API layer, no SPA — every page is a controller action returning a Blade view
- Tests are class-based PHPUnit (`extends TestCase`, `RefreshDatabase`), not Pest's `it()`/`expect()` syntax
- SQLite by default; runs under Laravel Herd (serves the directory directly, no `artisan serve` needed)

## The one rule that matters most here

**Every table has a `household_id`. There is no other tenant isolation.** Two bug classes have already been found and fixed twice each because of this:

1. Any validation rule referencing another model by ID must scope it to the household, not just check existence:
   ```php
   Rule::exists('categories', 'id')->where('household_id', $this->household()->id)
   ```
   A bare `'exists:categories,id'` lets a request submit *any* household's ID and have it silently accepted.
2. Any controller action receiving a model via route binding must call `$this->abortUnlessOwned($model)` before reading/mutating it, unless it's already scoped through `$this->household()->relation()`.

See [`docs/security.md`](docs/security.md) for the full writeup and where this has bitten before.

## Conventions

- Controllers stay thin: fetch via `$this->household()`, hand off to a `Support/` class for any real logic, return a view or redirect with a `status` flash message.
- Business logic that doesn't need Eloquent lives in `app/Support/` as small static-method classes (see `docs/architecture.md` for the current list) — keeps it unit-testable without hitting the DB.
- One feature/fix per branch and PR, even when several were designed together — see `docs/development.md` for the branching approach used across this repo's history.
- Every feature gets its own test class named for what it covers, not one giant controller test.
- Herd's PHP isn't on `PATH` by default:
  ```bash
  export PATH="/Users/$(whoami)/Library/Application Support/Herd/bin:$PATH"
  ```

## Before adding a new form field that references another model

Check [`docs/security.md`](docs/security.md) first and use the `Rule::exists(...)->where('household_id', ...)` pattern from the start — don't let a new field slip in with a bare `exists:` rule.
