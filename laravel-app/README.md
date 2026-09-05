<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Checkout idempotency

`POST /api/orders` requires an `Idempotency-Key` header containing 16–128 ASCII letters, numbers, dots, underscores, colons, or hyphens; the first character must be alphanumeric. Generate a new cryptographically random key for each intended purchase and retain it when retrying the same request.

A retry by the same authenticated customer with the same normalized delivery, COD payment, and Pathao shipping details returns the original order with status `201` and the `Idempotent-Replayed: true` response header. Reusing the key with different checkout details returns `409`. Keys are scoped to the authenticated customer, so another customer cannot retrieve the order.

Web checkout embeds the same kind of attempt key in the checkout form. Guest ownership uses a separate random identity held in the server-side session; only its SHA-256 hash is persisted. A guest who loses that session cannot recover the order through the attempt key. The database unique constraint serializes ownership of an attempt key, but SQLite tests do not prove concurrent behavior on the production database; verify overlapping same-key requests against the production database engine before release.

To verify production-engine contention, provision a disposable database, migrate it, and create an authenticated customer with one cart item and known stock. Capture that session's cookie and CSRF header, then release two identical requests together, for example with `printf '1\n2\n' | xargs -P2 -I{} curl -sS -b checkout.cookies -H 'Accept: application/json' -H 'Content-Type: application/json' -H 'X-XSRF-TOKEN: <token>' -H 'Idempotency-Key: <same-random-key>' --data @checkout.json <test-app-url>/api/orders`. Confirm both responses contain the same order ID, one scoped attempt row exists, one order and its expected items exist, and stock decreased once. Repeat with changed delivery details and confirm the second response is `409`. Never run this procedure against production data.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Low-Resource Hosting Profile

This project includes a deployment mode for constrained shared hosting (1 Core / 1 GB RAM):

1. Copy `.env.low-resource.example` to `.env`.
2. Fill DB and mail credentials.
3. Run:

```bash
composer run deploy:low-resource
```

Runtime targets for this profile:

- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`
- `APP_DEBUG=false`
