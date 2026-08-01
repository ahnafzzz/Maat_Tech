# cPanel Prep Checklist

This project can be prepared for cPanel in two ways:

1. Static prototype upload (quickest)
2. Laravel app upload (full backend features)

## 1) Static prototype prep

Use this if you are publishing the HTML prototype only.

1. Build the upload archive locally:
   - `npm run cpanel:package`
2. Confirm zip file exists in `dist/cpanel/`.
3. Log into cPanel -> File Manager.
4. Open `public_html/` (or your addon domain document root).
5. Upload the zip, then extract it.
6. Verify root redirect exists (`public_html/index.html`) and screen files exist under `public_html/screens/`.
7. Open domain in browser and verify:
   - root page redirects to storefront page
   - all linked pages open
   - dark mode default appears
   - theme toggle works and persists

## 2) Laravel app prep for cPanel

Use this only if your cPanel hosting supports PHP 8.3+ and Composer.

1. In hosting control panel, confirm:
   - PHP 8.3+
   - Composer access
   - MySQL database access
   - SSH access (recommended)
2. Create database + database user in cPanel MySQL tools.
3. Upload the repository (or `laravel-app/`) to a directory outside `public_html` when possible.
4. Point domain document root to `laravel-app/public`.
5. Copy `.env.example` to `.env`, then set:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://your-domain.com`
   - production `DB_*` values
6. Install dependencies and optimize:
   - `composer install --no-dev --optimize-autoloader`
   - `php artisan key:generate`
   - `php artisan migrate --force`
   - `php artisan storage:link`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
7. Ensure write permissions for `storage/` and `bootstrap/cache/`.
8. Enable SSL and force HTTPS.

### Low-resource variant (1 GB RAM / 1 Core)

If your hosting plan is low-resource shared hosting, use this profile:

1. Copy `laravel-app/.env.low-resource.example` to `laravel-app/.env`.
2. Set real DB and mail credentials.
3. Run from project root:
   - `npm run laravel:low-resource`
4. Keep these settings:
   - `QUEUE_CONNECTION=sync`
   - `CACHE_STORE=file`
   - `SESSION_DRIVER=file`
   - `APP_DEBUG=false`
5. Do not run `npm ci` / `npm run build` on the server.
   Build assets beforehand (local/CI) and upload generated files only.
6. Follow [deployment/LOW_RESOURCE_HOSTING_1GB.md](deployment/LOW_RESOURCE_HOSTING_1GB.md).

## 3) Go-live hold (for later)

Since you are not uploading yet, keep these done in advance:

1. Domain purchased and DNS provider chosen.
2. Hosting plan selected (shared cPanel or VPS).
3. Final deploy path decided:
   - static only now, or
   - Laravel full stack
4. Production credentials kept ready but not committed to git.
