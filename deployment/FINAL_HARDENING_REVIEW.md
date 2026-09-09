# Final application hardening review and handoff

Review date: 2026-09-10
Branch: `codex/production-hardening`
Pre-hardening baseline: `b2e479b7760dab22a05284556f00659d2191dcfb`
Reviewed application code: `c3aa8af8e97b6898ab6a213e7ff3172f40a2f0b8`

## Readiness assessment

**Continued development:** ready. The feature branch is internally consistent, its tracked worktree was clean at review start, it matched `origin/codex/production-hardening`, and exact-code CI passed every configured job. The bounded review found no confirmed unresolved application-code defect in the implemented hardening boundaries.

**Production release:** not ready. Passing CI establishes the tested application and artifact behavior; it does not establish production-engine concurrency, safe migration of representative data, host upload handling, credential remediation, persistent storage, restore capability, TLS/proxy correctness, or operational monitoring. Those launch blockers remain below. This assessment does not claim that the application has zero vulnerabilities.

Hosting purchase, live deployment, merging to `main`, UI redesign, and lamp-viewer/3D integration were outside this review and remain deferred.

## Completed scope

The delta from the pre-hardening baseline was reviewed by commit history, changed-file inventory, current routes, services, migrations, middleware, tests, runtime configuration, and deployment scripts. It now provides:

- customer/admin guard separation; active-administrator checks; session-version enforcement for browser and product API writes; secure first-lead bootstrap and targeted password rotation without seeded default accounts;
- an authorized administrator invitation lifecycle with explicit states, hashed expiring single-use secrets, fixed non-lead permissions, atomic acceptance, identity-conflict handling, throttled resend, and post-commit mail delivery;
- hashed, expiring, attempt-limited, browser-bound administrator 2FA challenges that authenticate only after atomic consumption and recheck status and credential version;
- explicit public scopes for active products and approved reviews across homepage, catalog, detail, related products, sitemap, public APIs, carts, wishlists, and login merging, while administration and historical records remain unscoped;
- one cart per customer and one row per cart/product, deterministic legacy consolidation, stable-owner locking, atomic guest cart/wishlist merge, and database-backed merge replay protection;
- one checkout service for web/API paths with customer ownership, product/cart locks, atomic stock decrement/order creation/cart cleanup, scoped idempotency keys, and immutable order-item snapshots;
- shared allowlisted product validation for administrator web/API writes and compensating media handling that preserves existing files until database commit;
- deployment preflight, database-engine-specific backup creation, persistent-path-safe synchronization, maintenance/quiescence orchestration, bounded health checks, explicit proxy trust, an Apache container runtime, and deployment-free feature verification.

No 3D or viewer files were read, modified, staged, or integrated.

## Exact verification evidence

After `git fetch origin --prune`, local and remote final code SHA were identical (ahead 0, behind 0). [Release Verification run 34334028727](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34334028727) is a completed successful push run for exactly `c3aa8af8e97b6898ab6a213e7ff3172f40a2f0b8`, attempt 1, started 2026-09-09 09:19:14 UTC and completed 09:20:52 UTC.

| Exact-SHA CI job | Observed result | What it established |
| --- | --- | --- |
| [Workflow syntax](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34334028727/job/102409089212) | Success | `actionlint` accepted all workflow definitions. |
| [Dependency advisory audits](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34334028727/job/102409089072) | Success | Composer and npm locked dependencies, including development dependencies, passed the repository's advisory checks at that time. This is time-bound advisory evidence, not proof of future safety. |
| [PHP 8.3](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34334028727/job/102409089067) | Success | Locked Composer install, metadata/platform checks, printed SQLite `:memory:` isolation, and the Laravel suite passed on PHP 8.3. |
| [PHP 8.4](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34334028727/job/102409089376) | Success | The same isolated verification passed on PHP 8.4. |
| [Node 24 build and deployment fixtures](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34334028727/job/102409089247) | Success | Locked npm install, production Vite build/artifact checks, and disposable deployment safety fixtures passed. |
| [Container build and persistent-state smoke test](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34334028727/job/102409088866) | Success | The image built without publishing; explicit initialization, Apache startup, HTTP exposure controls, uploaded-PHP denial, persistent database/upload markers, graceful stop, and restart passed in disposable volumes. |

The final local Step 15 run printed `testing`, SQLite `:memory:`, array cache/session/mail, and synchronous queue, then passed **155 tests / 1,243 assertions**. Its focused product/authorization/publication/order/checkout group passed **75 tests / 591 assertions**. Fake storage, fake mail, synthetic accounts, and disposable data were used. Step 16 added no reproduction test because code inspection and exact-commit CI did not leave a specific application interaction uncertain enough to justify repeating the suite.

All current automated application races are sequential SQLite evidence unless a test explicitly says otherwise. Neither those tests nor the container smoke test prove row-lock, isolation, DDL, deadlock, or session-locking behavior on an eventual production engine/backend.

The feature verification workflow grants `contents: read`, contains no deploy job, and runs only verification on `codex/production-hardening`. The separate production workflow deploys only from `main`; this review did not invoke it.

## Cross-boundary review result

No confirmed unresolved application defect was found in the bounded review. The following interactions were checked against current code and tests:

| Boundary | Current evidence and conclusion |
| --- | --- |
| Customer/admin authorization and revocation | `AdminAuth`, `ApiAdminAuth`, and `AdminSessionVersion` require an active admin and current version; password rotation changes password, remember token, and session version and invalidates pending 2FA. Customer-owned carts/orders continue to use the web customer guard even in mixed sessions. Existing requests cannot be recalled mid-execution, and stored sessions are not proactively deleted; rejection occurs on the next protected request. |
| Invitation acceptance into login/2FA | Acceptance binds the stored identity and non-lead permissions, creates no authenticated session, and redirects to ordinary admin login. The accepted password is hashed and the new admin receives a session version. If 2FA is later enabled, the same challenge path remains authoritative. Tests cover acceptance followed by login; no bypass was found. |
| Public product/review visibility | Public queries use `Product::published()` and `Review::approved()`; unpublished detail is 404, saved unavailable items render generically and remain removable, administrator queries remain unrestricted, and order snapshots/replays remain readable to their authorized owners. No public rating aggregate currently exists to bypass the review scope. |
| Cart merge and checkout locking | Customer cart writes, wishlist writes, merge, and checkout serialize first on the stable user row; product locks are ordered. Unique keys enforce one customer cart and one cart/product row. Merge completion is committed with the merge and makes the captured snapshot replay-safe; cleanup preserves post-capture additions. Production lock behavior remains unverified. |
| Checkout idempotency, stock, and history | Attempt lookup/creation, product/cart locks, stock decrement, order snapshots, cart deletion, and attempt completion share one transaction. Completed replay returns the original order without rechecking a later product state; conflicts return 409. Product/customer deletion nulls relationships without rewriting snapshots. Production simultaneous-use behavior remains unverified. |
| Product validation and filesystem cleanup | Web/API mutations share target-aware validation and an explicit allowlist. New uploads are compensated on database failure; old files remain until commit; obsolete files are deleted only when unreferenced. Cleanup failure is reported without pretending the committed database change failed. No durable cleanup retry exists. |
| Persistence, runtime configuration, and proxy trust | Deployment excludes `.env`, storage, public storage link, and SQLite database sidecars; preflight checks production mode, debug, key, HTTPS URL, secure cookies, explicit proxy syntax, writable persistent paths, assets, and database reachability. The container upload denial is tested. Nginx/cPanel and actual ingress/storage remain host-dependent blockers. |

## Confirmed unresolved application defects

None were confirmed within the reviewed scope. This means the review did not produce a reproducible current application failure requiring another feature checkpoint; it is not a guarantee that no defect or vulnerability exists.

## Confirmed conditional release gaps

These are concrete repository/data-remediation findings, but they are not evidence of a currently exposed production host because no platform is selected or deployed.

| Severity | Affected path | Practical consequence | Current evidence/reproduction | Smallest correction and verification |
| --- | --- | --- | --- | --- |
| High if the Nginx path is selected | `deployment/nginx/maattech.com.conf:53-75` | A PHP-like file already present or introduced outside the validated product route could reach the general PHP handler or be served as source beneath `/storage`. | Static inspection shows no higher-priority `/storage` denial before the general static/PHP locations. The Apache container has a denial and an exact-SHA smoke test; Nginx does not. | Add a precedence-safe `/storage` location that serves only intended media and denies PHP-like extensions/execution/source, then exercise allowed media and denied `.php`, `.php5`, `.phtml`, and `.phar` fixtures on disposable Nginx. |
| Medium availability/configuration mismatch if Nginx is selected | `deployment/nginx/maattech.com.conf:46-47`; `ProductWriteRequest.php:37-39` | Laravel accepts a video up to 100 MiB, but Nginx rejects a request above 20 MiB before Laravel, producing unexpected 413 responses. | Direct comparison of `client_max_body_size 20M` with Laravel's `max:102400`; no external request is needed to reproduce the contradictory limits. | Choose the intended maximum and align ingress, Nginx, PHP, and Laravel with multipart overhead; test just-below and just-above requests using synthetic files. |
| Medium deployment usability; fails safe | `laravel-app/.env.low-resource.example`; `DeploymentPreflight.php:41-43` | Copying the production-labelled low-resource template unchanged cannot pass deployment preflight because secure session cookies are unset. It does not silently deploy an insecure cookie through the maintained path; preflight stops. | The template omits `SESSION_SECURE_COOKIE`; Laravel therefore resolves it as false/null while preflight requires strict `true`. | Set `SESSION_SECURE_COOKIE=true` in the real production configuration and verify a Secure session cookie over HTTPS. Add an empty/documented `TRUSTED_PROXIES` value only after topology selection. |
| High if any historical credential was used outside disposable data | pre-hardening `db-seeder.txt` and `laravel-app/database/seeders/DatabaseSeeder.php` in Git history; existing deployed databases | Publicly known example/default passwords could permit account takeover when a matching seeded account or reused credential still exists. Removing current seeding does not rotate existing rows or erase history. | The baseline-to-current diff shows the committed default identities/passwords were removed in `b037ff2`; current normal seeding creates no customer/admin accounts. Whether a real database contains them is deliberately unverified. | Before release, inventory matching/derived accounts, investigate use, rotate or disable them, rotate reuse elsewhere, and verify current lead/status/email/2FA. Do not record replacement passwords. |

## Launch blockers

These must be closed before production release, but do not require purchasing hosting today.

### Production engine and migrations

1. Select the database engine and version, provision a disposable instance with production isolation settings, and apply the complete migration chain both to an empty database and to a sanitized representative pre-hardening copy.
2. Rehearse the cart consolidation/unique-index migration with representative volume while writers are quiesced. Verify quantities, foreign keys, index names, DDL locks/duration, failure recovery, and rerun behavior after any partially applied DDL.
3. Use at least two independent database connections to race first-cart creation, same-product mutation, login merge versus mutation/checkout, same-key checkout, invitation approve/reject/resend/accept transitions, and two consumes of one 2FA challenge. Confirm exactly-once outcomes and the selected engine's unique/deadlock SQL-state classification.
4. Verify every production table used by MySQL backup is transactional, or treat MySQL backup consistency as failed. Exercise the chosen session backend under overlapping requests; snapshot-aware application cleanup cannot correct a backend that resolves concurrent session writes by last-writer-wins.

### Hosting and runtime configuration

1. Serve only `laravel-app/public`; keep source, `.env`, database, backups, temporary files, and runtime internals unreachable. Verify persistent storage/uploads and, for SQLite, `/data` survive replacement and restart.
2. The maintained Apache container denies PHP-like uploads and was smoke-tested. Before using the repository Nginx sample, add and test an explicit `/storage` rule that prevents script execution and script-source download. Before using cPanel, prove the host-controlled handler does the same. This is a launch blocker for those paths.
3. Reconcile the Nginx sample's `client_max_body_size 20M` with the application contract permitting videos up to 100 MiB plus multipart overhead. Choose and test one deliberate limit across ingress, web server, PHP, and Laravel.
4. The low-resource production environment example omits `SESSION_SECURE_COOKIE=true`, although `DeploymentPreflight` requires it; copied unchanged, it fails preflight safely. Set and verify secure cookies in the real environment and add exact `TRUSTED_PROXIES` only after the ingress topology is known.
5. Configure HTTPS, verified public host preservation, exact immediate-proxy IPs/CIDRs, request/time limits, narrow filesystem permissions, graceful-stop timing, real quiesce/resume hooks, and a monitored mail transport. Run `deployment:preflight` and an HTTP exposure test on the selected platform.

### Credentials and access remediation

1. Historical commits contained example/default administrator and customer credentials. Removing seed creation prevents new default accounts but does not change an existing database or erase Git history. Inventory the target database for matching or derived identities, investigate use, rotate or disable affected accounts through an authorized process, rotate any reused secrets elsewhere, and preserve an audit record without recording new passwords.
2. Create the first lead only through `admin:bootstrap` on a trusted console. Store the stable `APP_KEY`, database/mail credentials, deployment SSH key, and verified known-host data in the chosen secret manager with least privilege. Never generate a replacement `APP_KEY` during routine deployment.
3. Review repository/organization access, branch protection, production-environment approvals, secret scope, deploy-user permissions, and key rotation/revocation procedures before enabling the `main` deployment workflow.

### Backup, restore, and monitoring

1. Configure protected capacity, encryption, retention, off-host copies, and failure alerts for database backups and uploaded media/configuration. A successful dump or archive listing is not a restore test.
2. Restore database, uploads, and required configuration into an isolated environment; verify integrity and authenticated reads, measure recovery time/data loss, and record the operator procedure. Repeat with the chosen production engine and storage provider.
3. Monitor `/up`, external HTTPS, queue/mail failures, deployment and backup failures, disk/database capacity, elevated 4xx/5xx and throttling, administrator authentication/invitation events, checkout errors, and media-cleanup warnings. Define owners and escalation paths.

## Known operational limitations and acceptance decisions

- **Public media after unpublishing:** catalog routes stop disclosing inactive products, but a previously learned `/storage/...` URL can remain public. If unpublishing must revoke direct media access, private/signed delivery or an explicit removal policy is a launch blocker; otherwise record it as an accepted limitation.
- **Media cleanup retry:** post-commit cleanup is best-effort and reports failure, but there is no durable retry queue or reconciliation command. Manual monitoring/reconciliation is acceptable only if an owner and procedure are defined; automation is an optional improvement after that control exists.
- **Record retention:** terminal 2FA challenge rows and completed cart-merge rows have no pruning policy. Select retention windows longer than the applicable security-audit and maximum retry/session lifetimes, monitor growth, and test pruning before automating it. Checkout attempts should be included in the same capacity/privacy review because completed retries rely on their persistence.
- **Session revocation timing:** credential rotation invalidates old administrator sessions on their next protected request, not requests already executing, and does not proactively erase stored session rows. Incident response must account for this bounded window.
- **In-place deployment:** the SSH path uses maintenance mode and synchronized files, not atomic release-directory switching. Its tested failure behavior keeps unsafe states in maintenance, but operational recovery still requires an operator decision after migration begins.
- **Advisory evidence ages:** dependency audit success reflects advisory data available at the exact CI run. Continue scheduled/pre-release audits and evaluate new advisories.

## Optional operational improvements

These are not launch blockers once the explicit controls and acceptance decisions above are documented:

- add a durable media-cleanup queue/reconciliation command after a queue and storage topology are selected;
- automate tested retention jobs for terminal 2FA challenges and completed merge/checkout attempts;
- move from in-place synchronization to immutable release directories with an atomic current-release switch;
- proactively enumerate/delete stored administrator sessions during credential incidents where the chosen session backend supports reliable account indexing;
- move product media behind private/signed delivery if the business later requires unpublishing to revoke previously learned URLs.

## Future launch checklist

- [ ] Choose host, topology, database engine/version, session/cache/queue backends, and persistent media storage.
- [ ] Close Nginx/cPanel upload handling and align ingress/PHP/application upload limits.
- [ ] Configure production `.env`, stable secrets, secure cookies, HTTPS, exact proxy trust, and least-privilege access.
- [ ] Remediate historical/default credentials and review repository/deployment access controls.
- [ ] Rehearse the full migration chain and required independent-connection races on a disposable selected-engine environment.
- [ ] Configure backups, off-host retention, capacity alerts, and complete a measured database-plus-media restore rehearsal.
- [ ] Configure application, authentication, checkout, cleanup, mail, queue, infrastructure, and certificate monitoring with named responders.
- [ ] Decide and record acceptance or remediation for public unpublished-media URLs, cleanup retry, record retention, session-revocation timing, and in-place deployment recovery.
- [ ] Re-run exact-commit PHP 8.3/8.4, Node 24, dependency, deployment-fixture, and container verification after the eventual release candidate is formed.
- [ ] Conduct a final go/no-go review; only then merge through the protected process and separately authorize deployment.
