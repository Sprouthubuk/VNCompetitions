# Forge setup (staging)

One server runs two sites:

| Site | Domain | What it runs |
|---|---|---|
| Storefront | `vaultninecompetitions.on-forge.com` | Astro SSR (Node), reverse-proxied by Nginx |
| API + admin | `vaultnine-api.on-forge.com` | Laravel (PHP-FPM), Filament at `/admin`, Horizon at `/horizon` |

Do the API site after build-plan step 1 exists in the repo. The storefront can go up first: without `API_URL` it shows the sample data.

## 1. Server

In Forge, choose **Create server**:

- **Provider:** Hetzner or DigitalOcean.
- **Region:** London or Frankfurt.
- **Size:** 2 vCPU / 4 GB.
- **Type:** App server.
- **PHP:** 8.5.
- **Database:** PostgreSQL 16. Create the database `vaultnine` and save the generated password.

Forge installs Nginx, Redis, Node and Supervisor.

Then:
- **Node:** run `node -v` on the server and make sure it's 20 or newer.
- **DNS:** in Cloudflare, add A records for `staging` and `api.staging` pointing at the server IP. Set them to **DNS only** (grey cloud) until the SSL certificates are issued.

## 2. API site (`api.staging…`)

1. **New site:**
   - Domain `api.staging.vaultninecompetitions.co.uk`.
   - Project type: General PHP / Laravel.
   - Web directory: `/api/public`.
2. **Repository:** connect the GitHub repo, branch `main`. Enable **Quick deploy**.
3. **Environment:** use the settings below, then run `php artisan key:generate` once.

   ```
   APP_ENV=staging
   APP_DEBUG=false
   APP_URL=https://api.staging.vaultninecompetitions.co.uk
   FRONTEND_URL=https://staging.vaultninecompetitions.co.uk
   SANCTUM_STATEFUL_DOMAINS=staging.vaultninecompetitions.co.uk
   SESSION_DOMAIN=.staging.vaultninecompetitions.co.uk
   DB_CONNECTION=pgsql
   DB_DATABASE=vaultnine
   DB_USERNAME=forge
   DB_PASSWORD=…
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   ```

4. **Deploy script:** replace Forge's default with:

   ```bash
   cd $FORGE_SITE_PATH/api
   git pull origin $FORGE_SITE_BRANCH
   $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
   ( flock -w 10 9 || exit 1
       echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
   $FORGE_PHP artisan migrate --force
   $FORGE_PHP artisan optimize
   $FORGE_PHP artisan filament:optimize
   $FORGE_PHP artisan horizon:terminate
   ```

   (Forge's Laravel template uses `$FORGE_SITE_PATH`. If yours doesn't, use `/home/forge/api.staging.vaultninecompetitions.co.uk`.)

5. **Queue:** in Daemons, add `php artisan horizon`, directory `…/api`, user `forge`.
6. **Scheduler:** in Scheduler, add `php /home/forge/api.staging.vaultninecompetitions.co.uk/api/artisan schedule:run`, every minute.
7. **SSL:** Let's Encrypt.
8. **First deploy:** click Deploy, then in the site's **Commands** tab run `php artisan db:seed --force` and `php artisan make:filament-user`.

## 3. Storefront site (`vaultninecompetitions.on-forge.com`)

This is how the storefront is deployed today. It runs on the Forge-provided domain; when `staging.vaultninecompetitions.co.uk` is pointed at it, add that domain to the site and change `SITE_URL`.

1. **Site:**
   - Domain `vaultninecompetitions.on-forge.com` (site path `/home/forge/vaultninecompetitions.on-forge.com`).
   - Web directory: `/web/public`. Nginx proxies pages to Node (step 6), so this only matters as the site root.
   - **Zero-downtime deployments: off.** The daemon and deploy script use the fixed site path above; zero-downtime releases would move the code into `releases/…` directories.
2. **Repository:** the same repo, branch `main`, Quick deploy on.
3. **Environment:** set these in the site's Environment tab. Forge saves them as `.env` at the repo root; the deploy script copies that file to **`web/.env`**, which is the file the daemon reads:

   ```
   API_URL=https://api.staging.vaultninecompetitions.co.uk
   SITE_URL=https://vaultninecompetitions.on-forge.com
   HOST=127.0.0.1
   PORT=4321
   ```

   Leave `API_URL` out until the API site (section 2) is deployed: without it the storefront shows the sample data from `web/src/data/comps.ts`.

4. **Deploy script:**

   ```bash
   cd /home/forge/vaultninecompetitions.on-forge.com
   git pull origin $FORGE_SITE_BRANCH

   # Forge's Environment tab writes .env at the repo root; the daemon reads web/.env.
   if [ -f .env ]; then cp .env web/.env; fi

   cd web
   npm ci
   npm run build

   # Stop the running server; Supervisor restarts the daemon on the new build.
   pkill -f "web/dist/server/entry.mjs" || true
   ```

   `pkill` needs no sudo: the daemon runs as `forge`, the same user as the deploy.

5. **Daemon** (`daemon-1216955`):
   - Directory: `/home/forge/vaultninecompetitions.on-forge.com/web/`
   - User: `forge`
   - Command:

     ```bash
     node --env-file=/home/forge/vaultninecompetitions.on-forge.com/web/.env /home/forge/vaultninecompetitions.on-forge.com/web/dist/server/entry.mjs
     ```

   Absolute paths on purpose, so the command doesn't depend on the directory Supervisor starts it in. Changing an environment variable needs a deploy (to copy `.env` and restart), or a daemon restart once `web/.env` has been updated.
6. **Nginx:** edit the site's Nginx config so `location /` proxies to Node and Astro's hashed assets are served directly:

   ```nginx
   location /_astro/ {
       root /home/forge/vaultninecompetitions.on-forge.com/web/dist/client;
       expires 1y;
       add_header Cache-Control "public, immutable";
   }

   location / {
       proxy_pass http://127.0.0.1:4321;
       proxy_http_version 1.1;
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
   }
   ```

   Replace Forge's default `location /` block with this. Keep the SSL and other lines Forge generated.

7. **SSL:** Let's Encrypt, then deploy.

## 4. While it's staging

- **Block search engines:** add HTTP basic auth on both sites (Forge → site → Security), or a `X-Robots-Tag: noindex` header.
- **Cloudflare:** once SSL is issued, turn the orange cloud on for both records, with SSL mode **Full (strict)**.
- **Backups:** Forge → Backups, daily Postgres backup to S3 or R2.

## 5. Going live (later)

Repeat the two sites for `vaultninecompetitions.co.uk` and `api.vaultninecompetitions.co.uk`, preferably on a separate production server. Then follow the cutover checklist in `docs/BUILD_PLAN.md` phase 5.
