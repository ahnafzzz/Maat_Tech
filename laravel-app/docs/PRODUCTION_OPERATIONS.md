# MAAT Technologies BD production operations

## Current deployment status (2026-10-08)

No application hosting or authorized deployment destination is available in this workspace. The public domain is still using registrar parking/forwarding infrastructure: the apex resolves to `192.64.119.177`, `www` resolves through a parking target, apex HTTP redirects to an HTTP `www` URL, and working HTTPS could not be established. The existing registrar mail-forwarding MX and SPF records must be preserved.

Production deployment is therefore blocked until the owner supplies all of the following through an approved secret/access channel:

- The hosting provider and an authorized SSH/SFTP or platform deployment credential, verified host key, document-root/runtime details, and process-manager access.
- DNS-management access and the hosting provider's actual DNS destination. Never guess an IP address.
- A persistent MySQL/PostgreSQL database and credentials, or an explicitly approved persistent SQLite volume.
- Persistent uploaded-media storage and an off-server encrypted backup destination.
- A transactional email provider and verified sender for admin invitations, password recovery, 2FA codes, and security-change notifications.
- The exact reverse-proxy IP/CIDR allow-list, if TLS terminates before Laravel.

The direct administrator URL is `/admin/login` (production: `https://www.maattechbd.store/admin/login`). It is intentionally absent from customer navigation. Administrator accounts are created or recovered only from a trusted server console using the owner-controlled commands in `README.md`; no default/shared password or public admin registration route exists.

## Required production configuration

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://www.maattechbd.store`.
- Preserve the existing production `APP_KEY`. Generate it exactly once only for a genuinely new installation.
- `CANONICAL_HOST=www.maattechbd.store` and `REDIRECT_HOSTS=maattechbd.store`.
- `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`.
- A shared persistent cache, database-backed sessions, and a persistent asynchronous queue. Do not use array/null cache or a sync queue in production.
- A real mail transport. The log/array mailer is not a production delivery mechanism.
- `TRUSTED_PROXIES` containing only verified immediate proxy IPs/CIDRs; leave empty for direct HTTPS.
- A web root pointing only to `public/`. Deny script execution in uploaded-media paths.

Use `docs/nginx-maattechbd.store.conf` as the reviewed Nginx baseline after replacing its documented filesystem/socket placeholders. Obtain one certificate covering both hostnames before enabling the redirect, so HTTPS requests to the apex can complete TLS and then redirect while preserving `$request_uri`.

## Backup gate and deployment

Before changing an existing installation:

1. Put the application in maintenance mode and stop queue/scheduler writers.
2. Create a consistent database backup with the engine-native tool. Record a SHA-256 digest and verify the artifact can be read.
3. Archive `.env` through the approved secret system, the stable `APP_KEY`, uploaded media, and the current application release identifier. Never place them in Git or logs.
4. Copy backups to encrypted off-server storage and perform a restore into a disposable database/storage location. A backup without a successful restore test is not a release gate.
5. Retain daily backups for 14 days, weekly backups for 8 weeks, and monthly backups for 12 months unless the owner adopts a stricter documented policy. Monitor backup age, size, integrity, and off-server replication.

Build and verify the release away from the live tree:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan test --compact
composer audit
npm audit --audit-level=moderate
```

Deploy while writers remain stopped, preserve `.env`, `storage`, uploaded media, and the database, then run:

```bash
php artisan deployment:reconcile-migration-aliases --no-interaction
php artisan migrate --force --no-interaction
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan deployment:preflight
```

Run `php artisan queue:work` under a supervised process and `php artisan schedule:run` every minute. The scheduler executes `orders:expire-pending` every 15 minutes. Pending COD orders reserve inventory when placed, expire after `PENDING_ORDER_EXPIRY_HOURS`, and restore it exactly once. Confirmation changes an order to processing and cancels expiry; shipped/delivered/refunded goods are never automatically returned to stock.

After enabling traffic, verify both hostnames over HTTP and HTTPS, the apex-to-`www` redirect with a path/query, `/up`, built assets, stored images/video, the model manifest/buffers, sessions, mail delivery, queue execution, security headers, admin 2FA, and a controlled staging checkout. Do not create paid transactions, courier requests, or contact customers for smoke tests.

## Rollback and restore

If failure occurs before migrations, restore the prior code artifact and caches, rerun preflight, then resume writers. If a migration has started, do not blindly run `migrate:rollback`: several historical migrations are intentionally forward-only. Keep maintenance mode on and choose a reviewed forward fix, compatible prior code, or a verified database restore.

A database restore must also restore the matching uploaded-media snapshot and stable application key. Reconcile every write made after the backup before accepting data loss. After restore, run integrity checks, migrations/status inspection, production preflight, read-only admin/catalog checks, and a controlled staging checkout before resuming workers and traffic.

## Routine maintenance

- Weekly: review failed jobs, pending-order expiry output, admin/security audit events, mail failures, storage capacity, backup freshness, and TLS expiry.
- Monthly: run `composer audit`, `npm audit`, review compatible framework/runtime updates, apply them in staging, and rerun PHP/JS/browser tests.
- Quarterly: restore the latest off-server backup into an isolated environment, verify order/media/admin integrity, rotate deploy credentials where policy requires it, and review administrator access and 2FA enrollment.
- Never log passwords, OTPs, recovery tokens, session IDs, full addresses, or unnecessary customer data. Current audit events use hashed administrator/order/source identifiers.

Phone verification for stronger COD abuse control is not enabled because no owner-approved SMS provider exists. The current controls are server-side Bangladesh phone/address validation, shared owner/IP throttles, idempotent checkout attempts, pending-order expiry, and transactional stock transitions.
