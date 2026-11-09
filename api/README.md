# api/

Laravel 12 API, Filament admin (`/admin`) and Horizon (`/horizon`) for Vault Nine Competitions.
See `../CLAUDE.md` and `../docs/BUILD_PLAN.md`.

```bash
cp .env.example .env && php artisan key:generate   # then set DB_PASSWORD
composer install
php artisan migrate --seed
php artisan serve                                  # http://127.0.0.1:8000
```

Tests use Postgres database `vaultnine_test` (create it once: `createdb vaultnine_test`).

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Composer is pinned to the Forge platform (PHP 8.5, with `ext-pcntl`/`ext-posix` declared, which Horizon needs on Linux).
Horizon doesn't run on Windows; locally the queue uses the `database` driver.
