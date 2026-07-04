# Deployment

Self-hosted household finance tracker — single-server, Laravel Herd (local) or a plain VPS with PHP 8.3+.

## Requirements

| Dependency | Minimum version |
|------------|-----------------|
| PHP | 8.3 |
| Composer | 2.x |
| SQLite | 3.x (default) or MySQL / PostgreSQL 14+ |
| Node.js | 20 LTS (for asset builds only) |

## First-time setup

```bash
git clone git@github.com:Ezomic/finance.git
cd finance/web

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install JS dependencies and build assets
npm ci
npm run build

# Environment
cp .env.example .env
php artisan key:generate

# Edit .env — set APP_URL and DB_CONNECTION (sqlite by default)

# Database
php artisan migrate --force

# Storage link (not needed for Herd)
php artisan storage:link
```

## Environment variables

| Variable | Default | Notes |
|----------|---------|-------|
| `APP_ENV` | `local` | Set to `production` on a live server |
| `APP_DEBUG` | `true` | Must be `false` in production |
| `APP_URL` | `http://localhost` | Full URL including scheme |
| `DB_CONNECTION` | `sqlite` | Change to `mysql` or `pgsql` for shared hosts |
| `DB_DATABASE` | `database/database.sqlite` | SQLite: relative to `web/` |
| `SESSION_DRIVER` | `database` | `file` works too; avoid `cookie` |
| `QUEUE_CONNECTION` | `database` | The app dispatches no queued jobs, so no worker is required |
| `MAIL_MAILER` | `log` | Set to `smtp` if email features are added later |

## Local dev (Laravel Herd)

```bash
# Herd picks up the app via symlink — run this once
ln -s ~/Projects/finance/web ~/Herd/finance

# Then visit http://finance.test
# No artisan serve needed
```

## Updates

```bash
cd finance/web
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Database backup (SQLite)

SQLite is a single file. Back it up with any file-copy tool:

```bash
cp database/database.sqlite database/database.sqlite.bak
# Or via rsync to a remote destination:
rsync -az database/database.sqlite user@backup-host:/backups/finance/
```

## Cache / config

Clear cached config after editing `.env`:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Storage

Imported bank statement files are stored in `storage/app/private/imports/`.
They are not served publicly; the `storage/app/private/` directory is outside the web root.
