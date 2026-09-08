# Laravel Deployment and Recovery Runbook

## Scope and deployment paths

The production hosting platform and database engine have not been confirmed. Do not treat this repository as production-ready until the inputs and blockers below are resolved.

Maintained Laravel paths:

1. `.github/workflows/laravel-auto-deploy.yml` prepares and tests an artifact, then deploys `main` to an explicitly configured Linux SSH target. Feature branches never deploy.
2. `deployment/deploy-laravel.sh` is the underlying in-place deployment path for a VPS, Forge deployment hook, or cPanel account that provides SSH plus the required tools. It uses a temporary incoming directory, not a release-directory/symlink architecture.
3. `laravel-app/Dockerfile` builds a PHP 8.4 Apache runtime image. Apache handles ordinary request-per-process PHP execution and static files from `/app/public`; no persistent application worker is enabled. Container startup validates configuration and starts Apache only; it never generates a key, migrates, or seeds. Database backup and migration must be a separately controlled operator job before the new container receives traffic.

`netlify.toml`, `deployment/prepare-cpanel-package.sh`, and the root static HTML package are legacy/static alternatives. They do not deploy Laravel, its database, or uploaded media.

## Required hosting inputs

The SSH workflow refuses to deploy unless these GitHub secrets are configured:

- `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`, and absolute `DEPLOY_PATH`.
- `DEPLOY_SSH_PRIVATE_KEY`.
- `DEPLOY_KNOWN_HOSTS`, populated out of band with the host key verified by a trusted administrator. CI does not use `ssh-keyscan` to establish trust.
- Absolute `DEPLOY_BACKUP_PATH` outside the source and live application trees, on persistent storage with sufficient free space and an off-host retention process.
- `DEPLOY_HEALTHCHECK_URL`, normally the HTTPS `/up` endpoint for the deployed application.
- Absolute executable `DEPLOY_QUIESCE_HOOK` and `DEPLOY_RESUME_HOOK` paths. These must stop and restart every process that can write application data, including queue workers. `/bin/true` is acceptable only after confirming the environment has no asynchronous workers and uses `QUEUE_CONNECTION=sync`.

The host must provide a supported PHP 8.3 or newer release with the application extensions, Composer, `rsync`, `flock`, `tar`, and `curl`. Maintained asset builds use Node 24 LTS. MySQL requires `mysqldump`; PostgreSQL requires `pg_dump` and `pg_restore`. The deploy user needs narrow write access to the application, backup location, and runtime directories. Do not grant recursive world-writable permissions.

The verified release matrix is PHP 8.3 and 8.4 with Composer 2.10, plus Node 24 LTS. As of September 2026, PHP 8.3 receives security fixes through December 2027 and PHP 8.4 through December 2028. Node 24 remains supported through April 2028; Node 20 reached end of life in April 2026 and is not a maintained deployment runtime. See the official [PHP supported versions](https://www.php.net/supported-versions.php) and [Node.js release schedule](https://github.com/nodejs/Release/blob/main/schedule.json).

Production `.env` must already exist, be readable only by the application/deploy identity, retain a valid stable `APP_KEY`, use `APP_ENV=production`, disable debug mode, use an HTTPS `APP_URL`, set `SESSION_SECURE_COOKIE=true`, and contain valid database configuration. For SQLite, `DB_DATABASE` must be an absolute persistent path outside the application tree. External MySQL/PostgreSQL databases are never copied as application files.

## HTTP runtime and TLS topology

The container uses the maintained official [`php:8.4-apache` image](https://hub.docker.com/_/php). It listens on the unprivileged `PORT` environment variable, which defaults to `8000` and must be an integer from 1024 through 65535. Its only document root is `/app/public`; Laravel source, Composer metadata, vendor code, environment files, and `/data` are outside that root. Apache serves Vite assets and the `public/storage` link directly, denies directory indexes and PHP files under public uploads, sends access/error logs to container stdout/stderr, and remains PID 1 through the official `apache2-foreground` launcher. The inherited `SIGWINCH` stop signal requests [graceful Apache shutdown](https://httpd.apache.org/docs/2.4/stopping.html#gracefulstop).

TLS is not terminated inside this image. A hosting ingress or reverse proxy terminates HTTPS, preserves the public `Host` header, and forwards plain HTTP to the container. Following Laravel's [trusted-proxy configuration](https://laravel.com/docs/13.x/requests#configuring-trusted-proxies), set `TRUSTED_PROXIES` to the comma-separated IP addresses or CIDRs of only those immediate proxies, for example an internal load-balancer subnet determined from the actual host configuration. Wildcards and `REMOTE_ADDR` are rejected by production preflight. Laravel accepts `X-Forwarded-For`, `X-Forwarded-Port`, and `X-Forwarded-Proto` only from the configured addresses; `X-Forwarded-Host` is never trusted. With no value, no proxy is trusted.

For an SSH deployment where the separately managed web server terminates TLS and passes HTTPS directly to PHP, leave `TRUSTED_PROXIES` empty. If that web server instead proxies to another HTTP application process, configure only its verified address or CIDR. Do not copy provider ranges from examples or use a trust-all value.

## First installation

First installation is deliberately separate from routine deployment:

1. Provision the target, persistent backup location, runtime storage, database, verified SSH host key, and quiesce/resume hooks.
2. Place `.env` manually with restrictive permissions and reviewed production values.
3. Generate `APP_KEY` exactly once if it is absent. Record it in the approved secret-management/backup system. Never regenerate it after encrypted data, cookies, or credentials exist.
4. For Docker, mount persistent `storage` and, when using SQLite, `/data`; supply configuration through the platform secret mechanism. Set SQLite `DB_DATABASE` to a file under `/data`.
5. Install dependencies and build assets in a staging workspace. Initialize the empty database with `php artisan migrate --force`, create the public storage link, and explicitly run `php artisan db:seed --force` only if catalog initialization is intended.
6. Run `php artisan deployment:preflight`, verify `/up`, configure monitoring, and complete a disposable restore drill before enabling automated deployment.
7. Create the first administrator or remediate historical credentials using the commands documented in `laravel-app/README.md`.

## Administrator initialization and existing deployments

After first-install migrations, create the first lead administrator from a trusted application console with `php artisan admin:bootstrap` plus the required ID, name, and email options. The command obtains the password through a hidden confirmation prompt; never pass or record it in deployment automation.

Removing historical default-account seed code does not change accounts already stored in a deployed database. Inventory and investigate those accounts, then rotate each affected administrator selected by exact ID with `php artisan admin:rotate-password ADMIN_ID`. Reset or remove affected customer accounts through an authorized process and verify account identity, status, lead flag, email, and two-factor settings separately. Rotation invalidates administrator credentials and causes old sessions to be rejected on their next protected request, but does not recall a request already executing or proactively delete stored session rows. See `laravel-app/README.md` for the complete credential and session limitations.

## Routine SSH deployment

The workflow and `deploy-laravel.sh` perform this sequence:

1. Install production dependencies, build frontend assets, and run tests before contacting the host.
2. Verify SSH using only the configured trusted known-hosts data and upload to an isolated incoming directory.
3. Acquire a nonblocking environment lock so two deployers cannot modify the same target.
4. Validate runtime compatibility, configuration, writable paths, built assets, and database connectivity before replacing live files.
5. Enter maintenance mode and quiesce background writers.
6. Create a database-engine-specific backup in a new protected directory, then create and validate a separate application-code archive.
7. Synchronize code while excluding `.env`, all of `storage`, `public/storage`, and SQLite database/WAL/SHM/journal files. Repeat deployments preserve those paths. Obsolete application code is removed.
8. Verify key release files, run migrations, rebuild Laravel caches, restart queue state, and rerun production preflight.
9. Leave maintenance mode, perform a bounded `/up` check, and resume background writers. Any failed command returns nonzero.

There is no automatic production seeding. Catalog seeding remains an explicit operator action and must not be added to routine deploys or container startup.

## Backup guarantees and limits

- SQLite uses `VACUUM INTO`, which creates a consistent standalone snapshot incorporating committed WAL state; the result is opened separately and checked with `PRAGMA integrity_check`. Local tests exercise this real backup path.
- MySQL uses `mysqldump --single-transaction --quick` with routines, triggers, and binary-safe output. This is consistent only for transactional tables; any non-transactional production table is a release blocker.
- PostgreSQL uses custom-format `pg_dump`, then verifies archive readability with `pg_restore --list`.
- Every artifact must be nonempty and receives a SHA-256 sidecar. Credentials are passed to child tools through their environment and are not printed.

The repository cannot prove a MySQL/PostgreSQL restore without disposable server credentials. Tool success, artifact checks, and PostgreSQL archive listing are not substitutes for an operator restore drill. Backups are not automatically copied off-host or expired; retention, capacity monitoring, encryption, and off-host transfer remain hosting requirements.

## Failure handling and recovery

- Failure before maintenance mode leaves the current application untouched.
- Quiesce, database-backup, or code-snapshot failure occurs before synchronization. The script attempts to resume workers and leave maintenance mode, then exits nonzero.
- Synchronization, migration, cache, postflight, health-check, or resume failure exits nonzero with the failing phase. The application remains, or is returned to, maintenance mode and background writers remain quiesced.
- `/up` is bounded and confirms that the application boots over HTTP. The post-migration preflight separately checks configuration, assets, and database connectivity. Neither creates orders or other customer data.

Before migrations, an operator may restore application code from `application-code.tar.gz`, leaving `.env`, `storage`, uploads, and the database untouched, then rerun preflight, leave maintenance mode, and resume writers.

After migrations begin, do not automatically run `migrate:rollback`, restore the database, or start old code. The order-history migrations are forward-only, and new writes may make a database restore destructive. Keep maintenance mode enabled, preserve logs and the failed incoming release, inspect migration state, and choose one reviewed action:

1. Fix forward and complete the deployment.
2. Deploy reviewed compatible application code against the migrated schema.
3. Restore a verified database only under an incident plan that accounts for every write since the backup and accepts the resulting data loss.

After recovery, run production preflight, verify `/up`, resume writers, inspect logs without publishing secrets, and confirm checkout/admin read paths manually without placing a real order.

## Outstanding release blockers

- Confirm the actual host/platform, database engine/version, filesystem layout, deploy user permissions, and availability of required tools.
- Verify queue quiesce/resume hooks against the real process manager.
- Independently verify and configure the SSH host key.
- Configure backup capacity, encryption, retention, off-host copies, monitoring, and successful restore drills for the chosen engine.
- Verify persistent Docker/cPanel volumes and storage links on the actual host.
- Validate the deployment and migrations against a disposable instance of the production database engine.
- Continue automated Composer and npm advisory audits and review new findings before release.
- Configure the real TLS ingress, verified proxy CIDRs, public host preservation, and graceful-stop interval, then exercise them on the selected hosting platform. The container must not be exposed directly as an HTTPS endpoint because it serves HTTP only.
