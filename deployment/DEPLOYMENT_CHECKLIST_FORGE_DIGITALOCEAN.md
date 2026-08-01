# Laravel Deployment Checklist (Forge or DigitalOcean)

This checklist is for the Laravel app at laravel-app/.

## 1) Common prerequisites

1. Domain purchased and DNS managed (Cloudflare/registrar).
2. Ubuntu server ready (22.04/24.04 LTS).
3. SSH key-based login enabled.
4. Firewall configured (allow 22, 80, 443).
5. GitHub repository connected.

## 2) Infrastructure baseline (both Forge and DigitalOcean)

1. Create server with minimum:
   - 2 vCPU
   - 4 GB RAM
   - 80 GB SSD
2. Install core stack:
   - Nginx
   - PHP-FPM (8.3+)
   - Composer
   - Node.js LTS + npm
   - MySQL 8+ or PostgreSQL 14+
   - Redis
3. Configure swap if RAM is limited.
4. Install fail2ban.
5. Set server timezone and NTP sync.

## 3) Forge path checklist

1. Create server in Forge and connect provider.
2. Create new site with domain.
3. Set web directory to laravel-app/public.
4. Add environment variables in Forge:
   - APP_ENV=production
   - APP_DEBUG=false
   - APP_URL=https://your-domain.com
   - DB_* credentials
   - CACHE_STORE=redis
   - SESSION_DRIVER=redis
   - QUEUE_CONNECTION=redis
   - MAIL_* SMTP credentials
5. Enable database and Redis services.
6. Enable SSL from Forge (Let's Encrypt).
7. Add deployment script:
   - git pull
   - composer install --no-dev --prefer-dist --optimize-autoloader
   - npm ci
   - npm run build
   - php artisan migrate --force
   - php artisan config:cache
   - php artisan route:cache
   - php artisan view:cache
8. Configure queue worker in Forge daemon manager.
9. Configure scheduler (Forge scheduler or cron every minute).
10. Run first deployment and smoke test.

## 4) DigitalOcean manual path checklist

1. Create Droplet (Ubuntu LTS).
2. Point domain A record to droplet IP.
3. SSH in and create deploy user.
4. Install packages:
   - nginx
   - php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-redis
   - mysql-server or postgresql
   - redis-server
   - composer
   - nodejs npm
5. Clone repo to /var/www/maat-tech.
6. Create laravel-app/.env from .env.example and fill production values.
7. Install app dependencies:
   - cd /var/www/maat-tech/laravel-app
   - composer install --no-dev --prefer-dist --optimize-autoloader
   - npm ci && npm run build
8. Initialize app:
   - php artisan key:generate
   - php artisan migrate --force
   - php artisan storage:link
   - php artisan config:cache
   - php artisan route:cache
   - php artisan view:cache
9. Set ownership/permissions:
   - chown -R www-data:www-data storage bootstrap/cache
10. Add Nginx vhost from deployment/nginx/maattech.com.conf.
11. Enable SSL using certbot:
   - certbot --nginx -d your-domain.com -d www.your-domain.com
12. Configure queue worker via systemd:
   - ExecStart=php /var/www/maat-tech/laravel-app/artisan queue:work --sleep=3 --tries=3 --timeout=120
13. Configure scheduler cron:
   - * * * * * cd /var/www/maat-tech/laravel-app && php artisan schedule:run >> /dev/null 2>&1
14. Add backups for DB and storage.
15. Add monitoring and alerts.

## 5) GitHub Actions auto-deploy prerequisites

1. Add these GitHub repository secrets:
   - DEPLOY_HOST
   - DEPLOY_PORT
   - DEPLOY_USER
   - DEPLOY_SSH_PRIVATE_KEY
   - DEPLOY_PATH (example: /var/www/maat-tech)
2. Ensure deploy user has write access to DEPLOY_PATH.
3. Add deploy user SSH public key to server ~/.ssh/authorized_keys.
4. Confirm server has git, composer, npm, php, and required PHP extensions.

## 6) Go-live verification

1. Home page loads over HTTPS.
2. Admin login works.
3. Product/catalog pages load.
4. DB write test succeeds.
5. Queue worker is active.
6. Scheduler runs successfully.
7. Error log is clean after smoke test.
8. Backups are running and restorable.

## 7) 24/7 operations checklist

1. Enable uptime checks every 1 minute.
2. Track error rate with alerts.
3. Rotate logs and monitor disk usage.
4. Patch OS monthly and emergency patch on CVEs.
5. Test restore from backup monthly.
6. Keep a rollback-ready previous release.
