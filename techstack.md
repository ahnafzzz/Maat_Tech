# Technology Stack - Maat Tech

## Complete Technology Stack Documentation

---

## 1. Backend Stack

### 1.1 Runtime & Language
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **PHP** | 8.5.8 | Server-side language | ✅ Installed |
| **PHP CLI** | 8.5.8 | Command-line PHP | ✅ Installed |
| **Composer** | Latest | PHP package manager | ✅ Installed |

### 1.2 Framework & Core
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Laravel** | 13.19.0 | Web framework | ✅ Active |
| **Illuminate Framework** | 13.x | Core Laravel packages | ✅ Active |
| **Symfony Components** | 8.1+ | Underlying utilities | ✅ Integrated |

### 1.3 Database & ORM
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **SQLite** | 3.x | Development database | ✅ Active |
| **PDO SQLite** | 8.5.8 | PHP database extension | ✅ Loaded |
| **Eloquent ORM** | 13.x | Object-relational mapping | ✅ Active |
| **Doctrine DBAL** | 4.4 | Database abstraction | ✅ Active |
| **Migration System** | Built-in | Schema versioning | ✅ Active |
| **MySQL** | 8.0+ | Production database (planned) | 📋 Ready |
| **PostgreSQL** | 12+ | Alternative production DB (planned) | 📋 Ready |

### 1.4 Authentication & Security
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Laravel Sanctum** | 4.0 | API authentication/tokens | ✅ Installed |
| **Bcrypt** | Built-in | Password hashing | ✅ Active |
| **Session Guard** | Built-in | Customer authentication | ✅ Active |
| **Custom Admin Guard** | Custom | Admin authentication | ✅ Active |
| **CSRF Protection** | Built-in | Cross-site request forgery | ✅ Active |

### 1.5 Queue & Asynchronous Processing
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Queue System** | Database | Job queue driver | ✅ Configured |
| **Job Processing** | Built-in | Background job execution | ✅ Ready |
| **Scheduler** | Built-in | Cron job scheduling | ✅ Ready |

### 1.6 Caching & Performance
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Cache Driver** | Database | Cache storage | ✅ Configured |
| **Redis** | N/A | Alternative cache (optional) | 📋 Planned |
| **Query Optimization** | Built-in | Database optimization | ✅ Active |

### 1.7 Mail & Notifications
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Mail Driver** | Log (dev) | Email system | ✅ Active |
| **SMTP** | Generic | Production email (planned) | 📋 Ready |
| **SendGrid** | API | Email service (optional) | 📋 Planned |
| **Mailgun** | API | Email service (alternative) | 📋 Planned |

### 1.8 File Storage
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Local Filesystem** | Built-in | File storage driver | ✅ Active |
| **AWS S3** | API | Cloud storage (planned) | 📋 Planned |
| **File Upload** | Built-in | Form file handling | ✅ Active |

### 1.9 Development & Testing Tools
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **PHPUnit** | 12.5.12 | Testing framework | ✅ Installed |
| **Mockery** | 1.6 | Mocking library | ✅ Installed |
| **Faker** | 1.23 | Test data generation | ✅ Installed |
| **Laravel Tinker** | 3.0 | Interactive shell | ✅ Installed |
| **Laravel Pail** | 1.2.5 | Log viewer | ✅ Installed |
| **Laravel Pint** | 1.27 | Code style formatter | ✅ Installed |
| **Collision** | 8.6 | Error rendering | ✅ Installed |
| **PsySH** | Latest | Interactive PHP shell | ✅ Included |

### 1.10 Additional PHP Libraries
| Package | Version | Purpose |
|---------|---------|---------|
| **Carbon** | Latest | Date/time handling |
| **Symfony Console** | 8.1+ | CLI commands |
| **Symfony Routing** | 8.1+ | URL routing |
| **Symfony Var-Dumper** | 8.1+ | Debug output |
| **Ramsey UUID** | Latest | UUID generation |
| **Brick Math** | Latest | Math operations |
| **Egulias Email Validator** | Latest | Email validation |
| **Dotenv** | 5.6.4 | Environment variables |

---

## 2. Frontend Stack

### 2.1 Build Tools & Asset Management
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Vite** | 8.1.4 | Build tool & dev server | ✅ Active |
| **Laravel Vite Plugin** | 3.1.3 | Laravel integration | ✅ Active |
| **NPM** | Latest | Package manager | ✅ Installed |
| **Node.js** | Latest | Runtime | ✅ Installed |

### 2.2 CSS Framework & Styling
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Tailwind CSS** | 4.3.2 | Utility-first CSS | ✅ Active |
| **@tailwindcss/vite** | 4.3.2 | Vite plugin for Tailwind | ✅ Active |
| **PostCSS** | Latest | CSS preprocessing | ✅ Integrated |
| **Autoprefixer** | Latest | Browser compatibility | ✅ Integrated |

### 2.3 Templating
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Blade Templates** | Built-in | Laravel templating | ✅ Active |
| **HTML5** | 5 | Markup standard | ✅ Active |
| **Static HTML** | 5 | Prototype templates | ✅ Included |

### 2.4 JavaScript & Interactivity
| Component | Version | Purpose | Status |
|-----------|---------|---------|--------|
| **Vanilla JavaScript** | ES6+ | Client-side scripting | ✅ Active |
| **AJAX** | Standard | Asynchronous requests | ✅ Supported |
| **Fetch API** | Modern | HTTP requests | ✅ Supported |
| **Concurrently** | 9.2.4 | Run multiple commands | ✅ Dev tool |

### 2.5 Frontend Build Scripts
```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  }
}
```

### 2.6 Responsive Design
- Tailwind CSS responsive breakpoints (mobile-first)
- CSS Grid & Flexbox layouts
- Media queries for device-specific styling
- Touch-friendly on mobile devices

---

## 3. Database Stack

### 3.1 Development Database
| Component | Version | Purpose |
|-----------|---------|---------|
| **SQLite** | 3.x | Lightweight, file-based database |
| **Database File** | Latest | `database/database.sqlite` |
| **Extensions** | Loaded | sqlite3, pdo_sqlite |

### 3.2 Production Database (Planned)
| Component | Version | Purpose | Notes |
|-----------|---------|---------|-------|
| **MySQL** | 8.0+ | Primary production DB | Fully supported |
| **PostgreSQL** | 12+ | Alternative option | Via Doctrine DBAL |
| **Replication** | HA | High availability | For scaling |

### 3.3 Database Tables
```
Core Tables:
├── users (customers)
├── admins (admin accounts)
├── admin_invitation_requests

Catalog Tables:
├── products
├── categories
├── reviews

Commerce Tables:
├── carts
├── cart_items
├── orders
├── order_items

Wishlist Tables:
├── wishlists
├── wishlist_items

Infrastructure:
├── sessions (session storage)
├── cache (caching)
├── cache_locks (distributed locks)
├── jobs (queue)
├── failed_jobs (failed queue jobs)
├── migrations (schema versioning)
```

### 3.4 Database Relationships
- Foreign key constraints enabled
- Cascade delete for relational integrity
- Soft deletes (optional for some models)
- Timestamps (created_at, updated_at)

---

## 4. DevOps & Deployment Stack

### 4.1 Development Environment
| Component | Purpose | Status |
|-----------|---------|--------|
| **Local PHP** | Development server | ✅ CachyOS |
| **PHP Built-in Server** | `php artisan serve` | ✅ Active |
| **Vite Dev Server** | Frontend assets | ✅ 3000/5173 ports |
| **SQLite** | Local database | ✅ Active |

### 4.2 Static Hosting (Current)
| Platform | Purpose | Status |
|----------|---------|--------|
| **Netlify** | Static site hosting | ✅ Configured |
| **Vercel** | Alternative CDN | 📋 Ready |
| **GitHub Pages** | Repository hosting | 📋 Ready |

### 4.3 Production Deployment (Planned)
| Component | Purpose | Status |
|-----------|---------|--------|
| **Nginx** | Web server | 📋 Planned |
| **Apache** | Alternative web server | 📋 Planned |
| **SSL/TLS** | HTTPS encryption | 📋 Planned |
| **Laravel Forge** | Server management | 📋 Planned |
| **Laravel Envoyer** | Zero-downtime deployment | 📋 Planned |
| **Docker** | Containerization (optional) | 📋 Planned |

### 4.4 CI/CD Pipeline (Planned)
| Tool | Purpose | Status |
|------|---------|--------|
| **GitHub Actions** | Automated testing/deployment | 📋 Planned |
| **PHPUnit** | Automated testing | ✅ Ready |
| **Code Coverage** | Test coverage reports | 📋 Planned |

---

## 5. Third-Party Integrations (Planned)

### 5.1 Payment Gateways
| Gateway | Status | Purpose |
|---------|--------|---------|
| **Bkash** | 📋 Planned | Mobile wallet (Bangladesh) |
| **SSLCommerz** | 📋 Planned | Payment processor |
| **Stripe** | 📋 Planned | International payments |
| **PayPal** | 📋 Planned | Digital wallet |

### 5.2 Email Services
| Service | Status | Purpose |
|---------|--------|---------|
| **SMTP** | ✅ Ready | Self-hosted email |
| **SendGrid** | 📋 Planned | Email delivery |
| **Mailgun** | 📋 Planned | Alternative service |

### 5.3 SMS & Notifications
| Service | Status | Purpose |
|---------|--------|---------|
| **Twilio** | 📋 Planned | SMS notifications |
| **Firebase** | 📋 Planned | Push notifications |

### 5.4 Shipping & Logistics
| Service | Status | Purpose |
|---------|--------|---------|
| **Pathao** | 📋 Planned | Shipping integration |
| **Redx** | 📋 Planned | Alternative courier |

### 5.5 Analytics & Monitoring
| Tool | Status | Purpose |
|------|--------|---------|
| **Google Analytics** | 📋 Planned | Traffic analytics |
| **Sentry** | 📋 Planned | Error tracking |
| **New Relic** | 📋 Planned | Performance monitoring |

---

## 6. Version Numbers & Compatibility

### 6.1 PHP Extensions Required
```
✅ Loaded Extensions:
├── pdo_sqlite       (Database)
├── sqlite3          (Database)
├── json             (Built-in)
├── dom              (XML processing)
├── filter           (Input validation)
├── hash             (Hashing)
├── libxml           (XML library)
├── openssl          (Encryption)
├── pcre             (Regular expressions)
├── phar             (Archives)
├── session          (Sessions)
├── tokenizer        (Code analysis)
├── xml              (XML parsing)
└── xmlwriter        (XML generation)
```

### 6.2 PHP Extensions Optional
```
📋 Recommended (for production):
├── redis            (Caching)
├── memcached        (Caching)
├── gd               (Image processing)
├── imagick          (Advanced image processing)
└── xdebug           (Debugging - dev only)
```

### 6.3 Supported Browsers
| Browser | Minimum Version | Support Level |
|---------|-----------------|----------------|
| Chrome/Edge | 90+ | Full support |
| Firefox | 88+ | Full support |
| Safari | 14+ | Full support |
| iOS Safari | 14+ | Full support |
| Chrome Mobile | 90+ | Full support |

---

## 7. Performance Stack

### 7.1 Optimization Tools
| Tool | Purpose | Status |
|------|---------|--------|
| **Laravel Debugbar** | Development profiling | ✅ Available |
| **Query Optimization** | Database performance | ✅ Active |
| **Eager Loading** | N+1 prevention | ✅ Implemented |
| **Caching Layer** | Response caching | ✅ Configured |

### 7.2 Asset Optimization
| Tool | Purpose | Status |
|------|---------|--------|
| **Vite** | Code splitting | ✅ Active |
| **CSS Minification** | Style optimization | ✅ Active |
| **JavaScript Minification** | Script optimization | ✅ Active |
| **Image Compression** | Asset optimization | 📋 Planned |

### 7.3 CDN & Delivery
| Component | Purpose | Status |
|-----------|---------|--------|
| **Browser Caching** | Client-side caching | ✅ Configured |
| **Server Caching** | Response caching | ✅ Active |
| **CDN Integration** | Global distribution | 📋 Planned |

---

## 8. Security Stack

### 8.1 Built-in Security Features
| Feature | Status | Details |
|---------|--------|---------|
| **HTTPS/TLS** | 📋 Production | SSL certificates |
| **CSRF Protection** | ✅ Active | Token validation |
| **XSS Prevention** | ✅ Active | Output escaping |
| **SQL Injection** | ✅ Protected | Parameterized queries (ORM) |
| **Password Hashing** | ✅ Active | Bcrypt algorithm |
| **Session Security** | ✅ Active | HTTPOnly cookies |
| **Rate Limiting** | ✅ Active | Throttle middleware |

### 8.2 Security Libraries
| Library | Purpose | Version |
|---------|---------|---------|
| **Symfony Security** | Authentication/authorization | 8.1+ |
| **Vlucas PHPDotenv** | Environment protection | 5.6.4 |
| **Egulias Email Validator** | Email validation | Latest |

### 8.3 Security Configurations
- `.env` file for sensitive data
- `.gitignore` excludes secrets
- Environment variable encryption (optional)
- Database encryption (optional for production)

---

## 9. Monitoring & Logging Stack

### 9.1 Logging
| Component | Level | Driver | Status |
|-----------|-------|--------|--------|
| **Laravel Log** | Debug | Stack (file + single) | ✅ Active |
| **Log Channel** | Debug | File: `storage/logs/laravel.log` | ✅ Active |
| **Error Logs** | DEBUG | Development mode | ✅ Active |

### 9.2 Monitoring Tools (Planned)
| Tool | Purpose | Status |
|------|---------|--------|
| **New Relic** | APM monitoring | 📋 Planned |
| **Datadog** | Infrastructure monitoring | 📋 Planned |
| **Grafana** | Metrics visualization | 📋 Planned |

---

## 10. Configuration Files & Locations

### 10.1 Configuration Files
```
laravel-app/
├── .env                     (Environment variables - dev)
├── .env.example             (Template for .env)
├── .env.production          (Production environment - optional)
├── config/
│  ├── app.php              (Application config)
│  ├── auth.php             (Authentication config)
│  ├── cache.php            (Caching config)
│  ├── database.php         (Database config)
│  ├── filesystems.php      (Storage config)
│  ├── mail.php             (Email config)
│  ├── queue.php            (Queue config)
│  ├── session.php          (Session config)
│  └── services.php         (Third-party services)
├── vite.config.js          (Vite build config)
├── phpunit.xml             (PHPUnit test config)
├── netlify.toml            (Netlify deployment config)
└── package.json            (NPM dependencies)
```

### 10.2 Environment Variables
```
APP_NAME=Laravel
APP_ENV=local|production
APP_DEBUG=true|false
APP_KEY=base64:...
DB_CONNECTION=sqlite|mysql|pgsql
DB_DATABASE=database.sqlite
CACHE_STORE=database|redis
QUEUE_CONNECTION=database|redis
MAIL_DRIVER=smtp|sendgrid|log
```

---

## 11. Dependency Management

### 11.1 Composer Dependencies
```
Total Packages: 50+ vendor packages

Key Dependencies:
├── Laravel Framework & Components
├── Symfony Components (8.1+)
├── Database Libraries
├── Authentication Libraries
├── Queue Libraries
├── Testing Libraries
├── Code Quality Tools
└── Utility Libraries
```

### 11.2 NPM Dependencies
```
Total Packages: 5 dev dependencies

├── @tailwindcss/vite@4.3.2
├── concurrently@9.2.4
├── laravel-vite-plugin@3.1.3
├── tailwindcss@4.3.2
└── vite@8.1.4
```

### 11.3 Dependency Update Strategy
- Composer: Monthly security updates
- NPM: Monthly security updates
- Major version updates: Quarterly review
- Lock files: Committed to version control

---

## 12. Development Workflow Commands

### 12.1 Backend Commands
```bash
# Install dependencies
composer install

# Database operations
php artisan migrate              # Run migrations
php artisan seed                 # Run seeders
php artisan migrate:rollback     # Undo migrations
php artisan tinker              # Interactive shell

# Development server
php artisan serve               # Start dev server

# Testing
php artisan test                # Run PHPUnit tests
php artisan test --filter=TestName

# Code quality
./vendor/bin/pint               # Format code style
./vendor/bin/phpunit            # Run tests

# Background jobs
php artisan queue:listen        # Process queue jobs

# Logging
php artisan pail                # Tail logs in real-time
```

### 12.2 Frontend Commands
```bash
# Install dependencies
npm install

# Development
npm run dev                     # Start Vite dev server

# Build
npm run build                   # Production build

# Combined dev environment
composer run dev                # Run all services concurrently
```

---

## 13. Technology Timeline & Roadmap

### Current Stack (Active)
- PHP 8.5.8 + Laravel 13.19.0
- SQLite (development)
- Vite + Tailwind CSS
- Static HTML prototypes

### Near-Term (Next Phase)
- MySQL/PostgreSQL production database
- Payment gateway integration
- Email service integration
- Admin dashboard (Filament)

### Medium-Term
- Redis caching layer
- Advanced analytics
- Search optimization (Algolia/Meilisearch)
- Mobile app (React Native/Flutter)

### Long-Term
- Multi-region deployment
- Microservices architecture
- Real-time notifications (WebSockets)
- AI-powered recommendations

---

## 14. Compatibility Matrix

### 14.1 PHP Version Support
| Version | Status | Notes |
|---------|--------|-------|
| 8.3 | ✅ Supported | Minimum required |
| 8.4 | ✅ Supported | Latest |
| 8.5 | ✅ Active | Current deployment |
| 9.0+ | ⏳ TBD | Future consideration |

### 14.2 Laravel Version Support
| Version | Status | Notes |
|---------|--------|-------|
| 12 | ❌ Not supported | Too old |
| 13 | ✅ Active | Current: 13.19.0 |
| 14 | ⏳ Planned | Future upgrade |

### 14.3 Database Support
| Database | Version | Status | Notes |
|----------|---------|--------|-------|
| SQLite | 3.x | ✅ Active | Development |
| MySQL | 8.0+ | ✅ Supported | Production ready |
| PostgreSQL | 12+ | ✅ Supported | Production ready |

---

## 15. System Requirements Summary

### 15.1 Minimum Requirements
```
Server:
├── PHP 8.3+
├── 512MB RAM minimum
├── SQLite or MySQL/PostgreSQL
└── 100MB storage

Development Machine:
├── PHP 8.3+ CLI
├── Composer 2.x
├── Node.js 16+
├── npm 8+
└── Git 2.x
```

### 15.2 Recommended Requirements
```
Production Server:
├── PHP 8.5+ with OPcache
├── 2GB+ RAM
├── MySQL 8.0+ or PostgreSQL 14+
├── Redis 6+ (caching)
├── 10GB+ SSD storage
├── Nginx 1.20+ or Apache 2.4+
└── SSL/TLS certificate

Development Machine:
├── PHP 8.5+
├── 4GB+ RAM
├── Node.js 18+
├── 5GB+ SSD storage
└── Docker (optional)
```

---

## Document Version
- **Version**: 1.0.0
- **Last Updated**: 2026-07-17
- **Author**: Technology Team
- **Status**: Active
- **Next Review**: 2026-09-01
