# MAAT TECHNOLOGIE BD Production Launch Guide

## Recommended target
- Laravel Forge on a VPS or dedicated PHP host
- Point document root to `laravel-app/public`
- Use MySQL or MariaDB in production
- Use Redis for cache, queue, and rate-limit storage when available

## 1. Server baseline
- PHP 8.3+
- Nginx or OpenLiteSpeed
- MySQL/MariaDB
- Supervisor for queue workers
- SSL certificate enabled before going live

## 2. Environment variables
Set these in production `.env` and never commit secrets:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.com`
- `DB_*`
- `MAIL_*`
- `FACEBOOK_PIXEL_ID`
- `WHATSAPP_ORDER_NUMBER`

## 3. Auth and security
- Keep CSRF middleware enabled
- Keep validation on every form request and controller entry
- Use HTTPS only
- Set secure session cookies in production
- Enable admin 2FA from the dashboard after deployment
- Review Laravel rate limiters in `AppServiceProvider`

## 4. Mail and password reset
- Use a real SMTP provider or transactional provider
- Verify that password reset mails send successfully from production
- Test both customer reset and admin 2FA mail delivery

## 5. Queue and cron
- Run queue workers under Supervisor
- Use queues for notifications and future heavy tasks
- Configure Laravel scheduler cron:
  - `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`

## 6. SSL and monitoring
- Force HTTPS at the web server and application level
- Add uptime monitoring for:
  - homepage
  - checkout
  - admin login
- Review logs for failed auth attempts and mail delivery failures

## 7. Performance
- Run:
  - `php artisan migrate --force`
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`
- Use a CDN for images and static assets where possible
- Optimize uploaded product images to WebP or AVIF during content preparation

## 8. Go-live checks
- Test catalog search and filters
- Test cart to checkout to order placement
- Test guest checkout and logged-in checkout
- Test admin product CRUD
- Test admin order status updates
- Test customer password reset
- Test admin 2FA challenge
