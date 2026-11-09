# Forge setup (staging)

One server runs two sites:

| Site | Domain | What it runs |
|---|---|---|
| Storefront | `staging.vaultninecompetitions.co.uk` | Astro SSR (Node), reverse-proxied by Nginx |
| API + admin | `api.staging.vaultninecompetitions.co.uk` | Laravel (PHP-FPM), Filament at `/admin`, Horizon at `/horizon` |

Do the API site after build-plan step 1 exists in the repo. The storefront can go up first: without `API_URL` it shows the sample data.

## 1. Server

In Forge, choose **Create server**:

- **Provider:** Hetzner or DigitalOcean.
- **Region:** London or Frankfurt.
- **Size:** 2 vCPU / 4 GB.
- **Type:** App server.
- **PHP:** 8.4.
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

## 3. Storefront site (`staging…`)

1. **New site:**
   - Domain `staging.vaultninecompetitions.co.uk`.
   - Project type: **Static HTML / Other** (Nginx proxies to Node; nothing is served from disk).
   - Web directory: `/web/dist/client`.
2. **Repository:** connect the same repo and branch. Enable Quick deploy.
3. **Environment:** set these in the site's Environment tab. Forge saves them as `.env` at the repo root, and the daemon reads that file:

   ```
   API_URL=https://api.staging.vaultninecompetitions.co.uk
   SITE_URL=https://staging.vaultninecompetitions.co.uk
   HOST=127.0.0.1
   PORT=4321
   ```

4. **Deploy script:**

   ```bash
   cd $FORGE_SITE_PATH/web
   git pull origin $FORGE_SITE_BRANCH
   npm ci
   npm run build
   sudo -S supervisorctl restart daemon-vaultnine-web:* || true
   ```

5. **Daemon:** in Daemons, add `node --env-file=../.env dist/server/entry.mjs`, directory `…/web`, user `forge`. Note the daemon's ID and update the restart line in the deploy script to match. (Forge names it `daemon-<id>`, so the name above is only a placeholder.)
6. **Nginx:** edit the site's Nginx config so `location /` proxies to Node and static assets are served directly:

   ```nginx
   location /_astro/ {
       root /home/forge/staging.vaultninecompetitions.co.uk/web/dist/client;
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
