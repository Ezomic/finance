# Security notes

## Household-scoping is the load-bearing invariant

This app has no other tenant isolation mechanism — every domain table has a `household_id`, and correctness depends entirely on every query and every validation rule respecting it. Two places this has to hold:

1. **Reads/writes through the current household**: controllers fetch data via `$this->household()->accounts()` / `->transactions()` etc. (see [architecture.md](architecture.md)), not `Model::query()` directly.
2. **Foreign-key input from a form must be scoped, not just checked for existence.** A bare `'category_id' => 'exists:categories,id'` only proves the ID exists *somewhere* — it says nothing about which household owns it. This exact bug was found and fixed twice in this app's history: once in `TransactionController` (account/category/transfer-account/split-category fields), once in `BudgetController` and `BillController` (category/account fields). The fix is always the same shape:

   ```php
   Rule::exists('categories', 'id')->where('household_id', $this->household()->id)
   ```

   When adding a new form field that references another model by ID, use this pattern from the start. `CategoryController`'s `parent_id` validation is a good existing reference.

3. **Route-model-bound resources need `abortUnlessOwned()`.** Any controller action that receives a model via route binding (`Bill $bill`, `Account $account`, etc.) and isn't scoped to the household through the relationship itself must call `$this->abortUnlessOwned($model)` before reading or mutating it.

If you're auditing for this class of bug: grep for `'exists:` in validation rules and check whether the accompanying model is genuinely global (rare) or should be household-scoped (almost always).

## Rate limiting

`/login` and `/households/join` are both throttled to 5 attempts/minute (`app/Providers/AppServiceProvider.php`):

- **Login** is keyed by `email|ip`, not IP alone — so one user's lockout can't be triggered by someone guessing a *different* account from the same address, and vice versa. The counter clears on a successful login via `App\Support\LoginThrottle`, which has to reproduce Laravel's internal key hashing (`ThrottleRequests::$shouldHashKeys` hashes named-limiter keys as `md5($limiterName.$rawKey)`) — calling `RateLimiter::clear()` with the raw, unhashed key silently clears nothing.
- **Household join** (guessing invite codes) is keyed by user ID, since it's an authenticated endpoint.

No other endpoints are throttled. If you add another guessable-secret endpoint (another invite/token flow), give it the same treatment.

## Known gaps

- `app/Http/Middleware/SetCurrentHousehold.php` exists but is never registered anywhere (`bootstrap/app.php`'s `withMiddleware()` closure is empty, and nothing else references the class) — it's dead code for what looks like an intended onboarding-redirect UX. Not currently causing incorrect behavior since users always end up with a valid `current_household_id` through the normal create/join flow, but worth either wiring up or removing.
- No CSRF/XSS-specific notes beyond Laravel's defaults — Blade's `{{ }}` escaping is used everywhere (no `{!! !!}` in the codebase), and there's no raw SQL (`DB::raw`/`whereRaw`) anywhere to audit for injection.
