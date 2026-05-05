# miyi-app — Agent instructions

## Stack

- **Backend**: Laravel **8.83** on **PHP 7.4.7** (strict; no PHP 8 features).
- **DB**: MariaDB **10.5** (see `docker-compose.local.yml`).
- **Auth**: JWT (`tymon/jwt-auth`), API guard `api`.
- **Frontend**: React admin is a **separate** repo; this app exposes API

## Hard constraints

- **Do not** use PHP 8+ syntax or APIs (no `match`, named args, union types, `readonly`, `#[Attributes]`, nullsafe `?->`, etc.).
- **Do not** upgrade to Laravel 9+ or PHP 8 in this codebase without an explicit product decision.
- Eloquent models live under **`App\Models\`** (`app/Models/`). Use `use App\Models\Foo;` and `Foo::class` — avoid string class names like `'App\Models\Foo'` unless unavoidable.

## Common commands

```bash
# Local stack (PHP 7.4.7 FPM + nginx + MariaDB)
docker compose -f docker-compose.local.yml up -d

# Dependencies (named volume `miyi_vendor` — run once after clone / composer.json change)
docker compose -f docker-compose.local.yml run --rm app composer install

# Artisan
docker compose -f docker-compose.local.yml exec app php artisan migrate
docker compose -f docker-compose.local.yml exec app php artisan optimize:clear
```

## Architecture notes

- HTTP API under `/api/v1/...` (see `routes/api.php`). Controllers use legacy string actions; `RouteServiceProvider` sets `$namespace = 'App\Http\Controllers'`.
- Business logic in `app/Services/`, policies in `app/Policies/`, observers in `app/Observers/` (registered in `AppServiceProvider`).
- **Removed**: Telescope, Spatie Backup cron, S3 flysystem adapter, PHPUnit test suite, dev-only packages (Ignition, Collision, Faker, etc.).

## What not to churn

- Do not edit committed bundles under `public/js/` unless rebuilding assets is part of the task.
- Do not commit secrets; use `.env` (not tracked) / `.env.example` for templates.
