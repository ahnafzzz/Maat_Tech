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

## Historical order items

Order API list, detail, and checkout responses expose `product_name` and `product_sku` on each item as the authoritative purchased-product identity; the live nested `product` object is no longer included. New orders snapshot these fields from the locked database product. The upgrade backfills legacy items from the currently available catalog, so those values are the best available identity and are not guaranteed to be the original purchase-time name. Missing catalog identities use `Unavailable product` and `SKU unavailable`.

`GET /api/orders/{order}` requires the customer session and returns only an order whose `user_id` matches that explicit customer. Foreign, guest-owned, and missing orders return `404`; checkout-attempt keys and ownership metadata are not part of order responses.

Customer and product foreign keys on historical orders use `null on delete`. The Step 4 migrations intentionally refuse automatic rollback: removing snapshots would discard history, and restoring the old cascade constraints may be invalid after referenced records have been deleted. Recovery requires a verified backup and a reviewed manual migration.

## Administrator bootstrap and credential remediation

Ordinary `db:seed` execution creates catalog records only. It does not create customer or administrator accounts. Deployment and container startup do not run seeders automatically; catalog seeding is an explicit operator action.

After migrations have completed, create the first lead administrator from a trusted application console:

```bash
php artisan admin:bootstrap --admin-id=ADM-1234-X --name="Lead Administrator" --email=operator@example.com
```

The command prompts twice for a hidden password, requires at least 12 characters with upper- and lowercase letters, a number, and a symbol, and refuses to run if a lead administrator or either supplied identity already exists. It uses a five-second bounded application lock and a retrying database transaction. The lock prevents concurrent bootstrap on hosts sharing the configured cache store. Deployments with multiple application nodes and non-shared cache stores must run this command with all but one node stopped because the current schema cannot enforce a single lead row across those nodes.

To rotate exactly one existing administrator's password, first obtain and independently verify the intended administrator ID, then run:

```bash
php artisan admin:rotate-password ADM-1234-X
```

The command displays the matched ID, name, and email before prompting twice for the hidden password. It changes only the password and credential-revocation fields: pending two-factor codes are cleared, the remember token is rotated, and the administrator session version is rotated. Existing admin sessions are rejected on their next protected browser or product API request regardless of the configured session backend; stored session records are not proactively deleted. Any request already executing when rotation commits cannot be recalled, so operators must also investigate logs and active work, rotate any other exposed secrets, and confirm the account's email, lead flag, status, and two-factor setting.

The administrator login currently does not offer a remember-me option. Rotation still invalidates any Laravel administrator recaller cookie issued by older or custom code by changing its remember token and password hash. Regression coverage uses an actual pre-rotation recaller cookie to verify rejection; it does not claim support for fresh remembered administrator login.

Removing the seed code does not modify accounts already present in a deployed database. Existing deployments must migrate, identify every account created from historical defaults, rotate each affected administrator with the command above, reset or remove affected customer accounts through an authorized process, and verify no unknown accounts or changes remain. Do not rerun seeders expecting them to delete or repair existing accounts.

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
