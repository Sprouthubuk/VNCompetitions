# Vault Nine Competitions — rebuild

Darts prize-competition site (vaultninecompetitions.co.uk) moving off Lovable/Supabase onto:

- `web/` — Astro storefront, server-rendered with `@astrojs/node`. Already designed and working on sample data.
- `api/` — Laravel 12 API + Filament admin + Postgres 16 + Redis/Horizon. **Not created yet**: start with `docs/BUILD_PLAN.md` step 1.

Hosting: one Laravel Forge server, two sites (`staging.` → Astro, `api.staging.` → Laravel). See `docs/FORGE.md`.

## Read before working

1. `docs/BUILD_PLAN.md`: what to build, in order, with acceptance checks.
2. `docs/API_CONTRACT.md`: the JSON the storefront already consumes. Don't change these shapes without updating `web/src/data/comps.ts` and `web/src/lib/data.ts`.
3. `docs/DECISIONS.md`: product decisions already made (what's kept, what's cut).
4. `docs/lovable-audit/`: the audit of the current Lovable build (schema, edge functions, business logic, known issues, data migration). Use it for field names and data migration, not as a design to copy.

## Rules that matter

- **The browser never decides** a price, a ticket number, an instant-win outcome, a draw result or a spend limit. All of it is server-side, inside DB transactions.
- **Every ticket number is random**, for every comp and every entry, paid or postal. Pick it with `random_int()` from the comp's free numbers. There is no sequential mode.
- **Instant-win prize numbers are random** and generated server-side. They are never exposed by any public endpoint before they're won.
- **The database enforces integrity:**
  - unique `(competition_id, number)` on tickets;
  - unique payment reference per order;
  - check constraints on money and capacity.
  - Don't rely on application checks alone.
- **Money:** `decimal(10,2)` pounds. **Times:** stored UTC, shown Europe/London.
- **Ledgers are append-only:** wallet transactions, draws, admin audit log.
- **Copy:** say "prize competition", never "lottery" or "gamble". There's no skill question; free postal entry is the legal route and must be as prominent as paid entry.
- **Tests:** every engine (checkout, allocation, instant win, draw, wallet, limits) has Pest feature tests. Run `php artisan test` before committing.

## Commands

```bash
# storefront
cd web && npm install && npm run dev          # http://localhost:4321, sample data
API_URL=http://localhost:8000 npm run dev      # against the local API
npm run build && node dist/server/entry.mjs    # production build
npm run build:preview                          # static design preview (claude.ai artifact)

# api (after step 1 of the build plan)
cd api && composer install && php artisan migrate --seed && php artisan serve
php artisan test
```

## Brand

Black-first. Vault green `#00B050` (highlight `#15D46B`), gold `#FFC83D` for instant wins, red `#FF3B3B` for urgency. Fonts: Anton (display, slanted), Hanken Grotesk (body), JetBrains Mono (numbers). Tokens live in `web/src/styles/global.css`.
