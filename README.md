# Finance

A self-hosted household finance tracker: accounts, budgets, bills, bank statement import (CSV/XLS/MT940/CAMT.053), auto-categorization, subscription detection, cash flow forecasting, and spending insights — built with Laravel, Blade, Tailwind CSS, and Alpine.js.

## Documentation

See [`docs/`](docs/README.md) for architecture, a full feature tour, bank import format details, the security/multi-tenancy conventions this codebase relies on, and local development notes.

## Quick start

Runs under [Laravel Herd](https://herd.laravel.com) — Herd serves the app directly from this directory, no `php artisan serve` needed.

```bash
composer install
npm install && npm run build
php artisan migrate
php artisan test
```

See [`docs/development.md`](docs/development.md) for more.

## License

MIT.
