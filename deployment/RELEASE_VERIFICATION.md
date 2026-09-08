# Release Runtime Verification

## Supported verification matrix

- PHP 8.3 and 8.4 with Composer 2.10. PHP 8.3 is security-supported through December 2027; PHP 8.4 through December 2028.
- Node 24 LTS for every maintained frontend build path. Node 24 is supported through April 2028. Node 20 reached end of life in April 2026.
- SQLite `:memory:` for the isolated Laravel test suite. This is not a substitute for testing migrations and backups on the selected production database engine.

The Composer resolver platform is PHP 8.3.0 so the committed lock remains installable on the lowest supported PHP release. Before this setting was added, the lock contained Symfony 8.1 packages requiring PHP 8.4.1 even though `composer.json` declared PHP 8.3 support. The corrected lock uses the compatible Symfony 7.4 line and is verified separately on PHP 8.3 and 8.4.

The npm engine requires Node 24. Vite and the Laravel Vite plugin accept Node 24 through their `>=22.12.0` ranges. CI, the production artifact workflow, and the Docker frontend stage use the same Node major.

Official schedules: [PHP supported versions](https://www.php.net/supported-versions.php) and [Node.js release schedule](https://github.com/nodejs/Release/blob/main/schedule.json).

## Deployment-free workflow

`.github/workflows/release-verification.yml` runs for pushes to `codex/production-hardening`, pull requests targeting `main`, and manual verification dispatches. It grants only `contents: read` and contains no environment, SSH, production-secret, registry-push, migration-against-external-data, or deployment steps. It does not call or depend on the production deployment workflow.

The workflow validates all workflow files with actionlint, installs the committed Composer lock on PHP 8.3 and 8.4, runs the isolated Laravel suite, installs the committed npm lock on Node 24, builds and inspects Vite assets, exercises the deployment safety fixture, audits both locks including development dependencies, and builds the Docker image without publishing it.

`deployment/verify-security-audits.sh` classifies known advisories as a failed audit and invalid/unavailable advisory-service results as an audit-service failure. It never treats an unreachable service as a clean result.

## Container verification and limitations

`deployment/tests/container-smoke-test.sh` first proves that an uninitialized database makes startup fail without creating migrations. It then provisions a fresh SQLite database as an explicit setup operation, creates synthetic persistent database and upload markers, and starts the built image with temporary storage and data volumes on a non-default port. It verifies the Apache process, `/up`, a built Vite asset, public uploads, denial of environment/source/vendor/database paths and uploaded PHP, and the absence of seeded customers or administrators. It requests graceful shutdown with a bounded timeout, restarts the same container, and verifies both persistence markers and the HTTP surface again. The volumes and container are removed afterward.

This smoke test demonstrates image construction, Apache startup/shutdown, the configured HTTP surface, and configured-volume persistence only. It does not terminate TLS, reproduce a hosting ingress, validate provider networking, or prove backup/restore behavior for an unknown production database engine. Before hosting the container, configure the real TLS endpoint and exact proxy CIDRs, verify volume and signal behavior on that platform, and repeat database backup/restore testing with the production engine.
