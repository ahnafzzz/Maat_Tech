# Domain and Hosting Guide for Maat Tech

This project has two deploy paths:

1. Static prototype only (quickest): host HTML files on Netlify/Vercel/GitHub Pages.
2. Full Laravel app (recommended for real business use): host Laravel on a VPS or managed Laravel platform.

---

## 1) Decide what you are publishing

### A) Static prototype only
Use this if you only want the existing HTML views online.
- Files: index.html plus screens/mech-lamp-storefront.html, screens/admin-login.html, screens/lead-admin-panel.html
- Current config already supports this in netlify.toml.
- No PHP runtime/database required.

### B) Full application (Laravel)
Use this for login, cart, orders, admin logic, database, queue, email, and future ERP modules.
- App location: laravel-app/
- Needs PHP runtime, database, queue worker, cron, and persistent server.

---

## 2) Domain setup (works for both)

1. Buy domain from Namecheap/Cloudflare/GoDaddy (example: maattech.com).
2. In DNS zone:
   - Add A record for root (@) to your server IP (or provider IP).
   - Add CNAME for www to root domain (or provider target).
3. In hosting panel, add your custom domain.
4. Enable HTTPS (SSL certificate).
5. Force redirect HTTP to HTTPS.

Typical DNS records:
- A: @ -> <your_server_ipv4>
- CNAME: www -> @

If using Netlify/Vercel, use the DNS targets they provide.

---

## 3) Fastest way online (Static on Netlify)

Use this if you want immediate public access.

1. Push this repository to GitHub.
2. Netlify -> Add new site -> Import from Git.
3. Build settings:
   - Build command: echo 'Static site ready'
   - Publish directory: .
4. Deploy.
5. Add your domain in Netlify Domain settings.
6. Enable HTTPS (Netlify auto SSL).

Result: your prototype pages are online globally with CDN.

Important: This does not run Laravel backend features.

---

## 4) Full production setup (Laravel 24/7)

Use this for real operations.

### Required infrastructure
- Linux server (Ubuntu 22.04/24.04 LTS recommended), minimum:
  - 2 vCPU
  - 4 GB RAM
  - 80+ GB SSD
- Nginx (or Apache)
- PHP-FPM (PHP 8.3+; align with project requirements)
- MySQL 8+ or PostgreSQL 14+
- Redis (recommended for cache/queue/session)
- Supervisor or systemd service for queue workers
- Cron for Laravel scheduler
- SSL (Let's Encrypt)

### Deploy checklist
1. Provision server and create non-root deploy user.
2. Install stack: Nginx, PHP extensions, Composer, Node.js, DB, Redis.
3. Clone project into server (for example, /var/www/maat-tech).
4. Configure environment file in laravel-app/.env:
   - APP_ENV=production
   - APP_DEBUG=false
   - APP_URL=https://your-domain.com
   - DB_* values (production DB)
   - CACHE_STORE=redis (recommended)
   - SESSION_DRIVER=redis (recommended)
   - QUEUE_CONNECTION=redis (recommended)
   - MAIL_* with real SMTP provider
5. Install dependencies:
   - composer install --no-dev --optimize-autoloader
   - npm ci && npm run build
6. Laravel setup:
   - php artisan key:generate
   - php artisan migrate --force
   - php artisan db:seed --force (if needed)
   - php artisan storage:link
7. Optimization:
   - php artisan config:cache
   - php artisan route:cache
   - php artisan view:cache
8. Set permissions for storage and bootstrap/cache.
9. Configure Nginx document root to laravel-app/public.
10. Enable SSL and redirect HTTP -> HTTPS.
11. Start queue worker service:
   - php artisan queue:work --tries=3 --timeout=120
12. Add cron entry for scheduler:
   - * * * * * cd /var/www/maat-tech/laravel-app && php artisan schedule:run >> /dev/null 2>&1
13. Add CI/CD deploy workflow (GitHub Actions, Forge, Envoyer, or custom script).

---

## 5) What you need for 24/7/365 reliability

Minimum production requirements:
- Uptime monitoring (UptimeRobot/Better Stack/Pingdom)
- Error monitoring (Sentry/Bugsnag)
- Centralized logs and log rotation

---

## 6) Low-resource shared hosting plan (1 Core / 1 GB RAM)

If you deploy on a small shared plan, use the lightweight profile:

1. Copy `laravel-app/.env.low-resource.example` to `.env`.
2. Fill real DB + SMTP values.
3. Keep:
   - `SESSION_DRIVER=file`
   - `CACHE_STORE=file`
   - `QUEUE_CONNECTION=sync`
   - `APP_DEBUG=false`
4. Run from project root:
   - `npm run laravel:low-resource`
5. Set domain document root to `laravel-app/public`.

Important:
- Do not run Node build tasks on the server for this plan.
- Avoid background workers and Redis daemons on this package.
- Prefer static prototype mode if traffic grows.
- Daily automated database backups
- Offsite backup retention (7/30/90-day policy)
- Firewall and fail2ban
- Regular OS/security updates
- SSH key auth, disable password login
- WAF/CDN (Cloudflare recommended)
- Health check endpoint
- Queue worker auto-restart on failure
- SSL auto-renew checks
- Disaster recovery runbook

Performance and scale:
- Redis object cache
- DB indexing + slow query review
- CDN for static assets/images
- Horizontal scaling plan (load balancer + multiple app servers)

Operations:
- Staging environment separate from production
- Zero-downtime deployment strategy
- Rollback procedure (last known good release)
- Scheduled maintenance window policy

---

## 6) Suggested rollout plan for this project

1. Launch static prototype now on Netlify with custom domain.
2. Build/complete Laravel features in laravel-app.
3. Deploy Laravel production on VPS (or Laravel Forge).
4. Point domain to Laravel production server.
5. Keep static Netlify site as fallback/landing page if desired.

---

## 7) Cost expectation (rough)

- Domain: $10 to $25/year
- Static hosting: free to low cost
- Laravel production VPS: $20 to $80/month (small to medium traffic)
- Managed DB/monitoring/email can increase cost based on scale

---

## 8) Go-live preflight checklist

- Domain and DNS configured
- HTTPS active
- APP_ENV=production and APP_DEBUG=false
- Database migrated successfully
- Queue worker running
- Scheduler running
- Backups verified by restore test
- Monitoring alerts tested
- Contact/email flows tested
- Admin login and checkout smoke-tested

If all items pass, the project is ready to be public and online continuously.
