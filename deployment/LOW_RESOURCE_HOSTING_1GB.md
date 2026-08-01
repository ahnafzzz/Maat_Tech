# Low-Resource Hosting Plan (1 Core / 1 GB RAM)

This guide is tuned for the shared hosting profile:

- RAM: 1 GB
- CPU: 1 Core
- Storage: 2 GB NVMe
- Bandwidth: 100 GB / month
- I/O: 10 MB
- Entry Processes: 20
- LiteSpeed web server

## 1) Recommended mode

For this package, use one of these:

1. Static mode (lightest): upload root HTML files only.
2. Laravel mode (possible): use strict low-resource settings.

If business features (auth/cart/orders/admin workflows) are not required immediately, static mode is safest.

## 2) Laravel runtime profile for this host

Use `laravel-app/.env.low-resource.example` as your base and set real credentials.

Important values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`
- `LOG_LEVEL=warning`

Why these values:

- avoids queue workers / Redis daemons
- reduces DB writes from sessions + cache tables
- lowers memory and CPU pressure

## 3) Deployment command (light profile)

From project root:

```bash
npm run laravel:low-resource
```

This command:

1. Installs PHP dependencies without dev packages.
2. Creates `.env` from low-resource template if missing.
3. Generates key.
4. Runs migrations.
5. Builds Laravel caches (`config`, `route`, `view`).

## 4) cPanel/LiteSpeed settings checklist

1. Domain document root must point to `laravel-app/public`.
2. Enable latest PHP version allowed (target 8.3+).
3. Set PHP memory limit to at least 256M in cPanel if possible.
4. Keep opcache enabled (if host exposes this setting).
5. Disable debug tools in production.

## 5) Storage and bandwidth discipline

1. Keep log growth under control (single log, warning level).
2. Do not store backups on same 2 GB hosting account.
3. Avoid uploading large media directly to this server.
4. Serve optimized images (WebP where possible).
5. Keep `vendor/` and app files only; do not keep duplicate zip archives on server.

## 6) Features to avoid on this plan

1. Long-running queue workers.
2. Heavy scheduled jobs every minute.
3. On-server Node builds (`npm ci`, `vite build`) in production.
4. Real-time broadcasting/Redis-heavy flows.

## 7) Upgrade triggers

Upgrade hosting when any of these appears:

1. Frequent 508/503 or CPU throttling.
2. Slow checkout/login during normal traffic.
3. Storage > 1.4 GB used continuously.
4. Need for background jobs, Redis, or high image/media volume.
