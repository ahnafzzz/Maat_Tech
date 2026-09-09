# Application hardening review — through Step 12

Review date: 2026-09-09  
Reviewed branch: `codex/production-hardening`  
Baseline review commit: `0f96842968bbb4845a106c9bb90fd0626ddaacab`

Step 12 starting checkpoint: `d83a1e4a59d82a7f284c7b84949e40069dcd5cac`

## Checkpoint and verification evidence

After `git fetch origin --prune`, local `HEAD` and `origin/codex/production-hardening` were identical (ahead 0, behind 0). The only pre-existing worktree item was the untracked `desk_lamp_viewer final final.html`; this review did not read, stage, modify, or integrate it.

Observed CI evidence, independently read from the GitHub Actions API rather than inferred from an earlier transcript: [Release Verification run 34194538237](https://github.com/ahnafzzz/Maat_Tech/actions/runs/34194538237) is a completed, successful push run for the exact commit above (attempt 1, 2026-09-08 06:24–06:26 UTC). All six reported jobs succeeded: Workflow syntax, Dependency advisory audits, PHP 8.3, PHP 8.4, Node 24 build and deployment fixtures, and Container build and persistent-state smoke test. The release workflow has read-only repository permission and no deploy job (`.github/workflows/release-verification.yml:3-15,17-143`). Production deployment is a separate workflow restricted to `main` (`.github/workflows/laravel-auto-deploy.yml:3-9,61-65`), so this feature-branch documentation push cannot deploy production.

Local verification was run only after forcing and printing `APP_ENV=testing`, SQLite `:memory:`, array cache/session/mail, and the synchronous queue. No persistent database, real account, mail transport, payment, or delivery service was used. `php artisan test --without-tty` passed: **94 tests, 580 assertions**. No reproduction test was added to the published suite. This SQLite run verifies existing sequential behavior; it is not evidence for production-engine locking or race behavior.

## Current disposition of the original findings

| Area | Disposition | Current evidence |
| --- | --- | --- |
| Checkout transaction and authorization | Resolved in current code | Web and API orders both call `CheckoutService::checkout` (`StorefrontController.php:96-102`; `Api/OrderController.php:69-75`). Attempt creation, cart/product locks, stock decrement, order creation, cart deletion, and attempt completion share one retried transaction (`CheckoutService.php:73-175`). Order reads are customer/session scoped (`StorefrontController.php:127-135`; `Api/OrderController.php:16-26`). Existing rollback, ownership, idempotency, and inactive-product tests pass. |
| Cart duplicate tolerance | Resolved as a compatibility behavior, not as an invariant | Reads aggregate duplicate rows and mutations lock and collapse them (`SessionCartService.php:47-77,105-128`); checkout locks and aggregates every customer cart (`CheckoutService.php:246-261`). Existing duplicate-cart tests pass. |
| Cart creation/item uniqueness and login merge | **Unresolved** | Neither `carts.user_id` nor `(cart_id, product_id)` has a unique constraint (`2026_07_14_054522_create_carts_table.php:14-19`; `2026_07_14_054524_create_cart_items_table.php:14-20`). Locking an empty cart query cannot serialize two first-cart creators, and API cart reads also call `firstOrCreate` without a database invariant (`Api/CartController.php:16-23`). Login merge commits each product separately and clears the session only after cart and wishlist loops (`CartMergeService.php:15-27`), so a stale/missing later product can leave earlier additions committed while retaining the entire guest cart for a duplicating retry. |
| Invitation authorization | Resolved | Request creation remains available to active authenticated administrators. Approval, rejection, resend, and revocation require `admin.auth` and recheck active lead status inside their transaction (`routes/web.php`; `AdminInvitationService.php`). Recipient acceptance is public only through a secret-bearing invitation and never grants lead status. |
| Invitation lifecycle/onboarding | **Resolved at the application boundary in Step 12; production-engine race verification remains** | Pending decisions and acceptance use transactions with row locks and explicit allowed states. Approval creates no account: it records fixed non-lead permissions and a hashed, expiring, single-use secret. Acceptance atomically creates the intended active operator and consumes the invitation; identity conflicts do not overwrite an account. Resend rotates the selector/secret after commit and revocation invalidates it. Focused sequential and migration tests pass; SQLite is not evidence of production-engine locking behavior. |
| 2FA expiration and ordinary replay | Resolved sequentially | Codes are hashed, expire after ten minutes, and are cleared after successful verification (`AdminController.php:44-50,92-106`). The exact-SHA suite includes successful 2FA completion coverage. |
| 2FA attempts, concurrent replay, and pending session | **Unresolved** | Login and challenge share an IP-only five-per-minute limiter (`AppServiceProvider.php:22-24`; `routes/web.php:70-75`), but there is no challenge/account attempt counter or invalidation threshold. Verification is read-then-save without a conditional consume/lock, so two requests holding the valid code can both pass before either clears it (`AdminController.php:92-106`). Expired/invalid challenges leave `pending_admin_id` and stale code fields in place; the challenge GET tests only whether the session key exists (`AdminController.php:77-96`). A second login overwrites the administrator-wide code, invalidating another pending browser. Production-engine race behavior remains to be demonstrated. |
| Public product/review visibility | **Resolved for application routes in Step 11** | Explicit `Product::published()` and `Review::approved()` scopes define the boundary without globally hiding administrative/bookkeeping records (`Product.php:29-36`; `Review.php:22-25`). Homepage featured products and public category counts, catalog, detail, related products, sitemap, and public product APIs use the product scope; detail eager-loads only approved reviews (`HomeController.php:11-72`; `routes/web.php:25-42`; `Api/ProductController.php:11-34`). Cart, API cart, wishlist, and login merge resolve current public data through the same scope; unavailable saved entries render generically and remain removable/clearable without GET deletion (`StorefrontController.php:20-230`; `SessionCartService.php:15-159`; `CartMergeService.php:15-36`). The review template now renders approved `title`, `rating`, and escaped `body` (`product.blade.php:53-63`). `PublicCatalogVisibilityTest` verifies these routes, rejected mutations and unchanged state, escaping, admin access, order snapshots, and replay after deactivation. |
| Product upload validation and storage | Resolved at the application boundary; hosting enforcement remains | Admin web uploads require Laravel image validation (max 5 MiB) or an enumerated video MIME (max 100 MiB), and Laravel generates hashed names below `storage/app/public/products/{id}` (`AdminController.php:287-307,322-353`; `filesystems.php:41-48,76-78`). SVG and PHP are not accepted by the image rule. The maintained container additionally disables CGI and denies PHP-like upload paths (`docker/apache-vhost.conf:12-23`), and the exact-SHA container smoke job observed the denial. This review found no application route that accepts arbitrary public-path files. |
| Service/authorization bypasses | No checkout or customer-object authorization bypass confirmed; one write-parity gap remains | Web/API checkout use `CheckoutService`; web/API cart additions use `SessionCartService`; API cart deletion and order reads scope records to the web customer; admin API mutations use `api.admin.auth` (`routes/web.php:95-110`). However, the authorized product API writes directly through `Product` with a much smaller create validator and no update validator (`Api/ProductController.php:16-48`), bypassing the web product validation/media lifecycle. This is an admin-side data-integrity gap, not an unauthenticated mutation vulnerability. |

## Resolved application checkpoint

### Public publication boundary — resolved in Step 11

- Active products are the only records returned by the homepage, catalog, detail, related, sitemap, and public product API queries. Draft/archived detail requests return 404.
- Only approved reviews are loaded for public detail. The page renders their title, rating, and body with normal Blade escaping. No public review count or average surface currently exists; if one is added, its query must use `approved()`.
- New cart/wishlist additions and positive cart updates require a currently published product. Rejections do not mutate guest session or customer rows. Existing unavailable entries reveal no product name, description, price, image, or link; GET preserves them, while removal and explicit cart clearing remain available. Checkout POST keeps its independent availability enforcement and now uses a generic inactive-product error.
- Login merge deliberately transfers only products published at login and clears stale unpublished/deleted guest IDs during that explicit mutation. Its previously documented lack of an all-or-nothing transaction and database uniqueness remains unresolved.
- Historical order snapshots and completed idempotent replay remain intentionally unscoped and were verified after product deactivation.
- **Remaining limitation:** product media lives on the public filesystem. A previously learned direct `/storage/...` URL can remain reachable after a product is unpublished; preventing that requires a separately designed private/signed-media lifecycle or removal policy. Application catalog/cart/wishlist routes no longer disclose those paths for unpublished products.

The Step 11 isolated SQLite run passed 102 tests (697 assertions), including eight focused visibility tests (117 assertions). The frontend production build also completed. These results do not change the production-engine concurrency limitations below.

## Resolved administrator invitation checkpoint

### Atomic, expiring acceptance workflow — resolved in Step 12

- States are explicit: `pending`, `approved`, `rejected`, `accepted`, `expired`, and `revoked`. Lead decisions, resend/revoke, and acceptance lock and recheck the invitation inside a retried transaction. Invalid or repeated transitions return controlled validation errors.
- Any active administrator may retain the existing request workflow. Only an active lead may approve, reject, resend, or revoke; lead authorization is rechecked under lock. Approval records exactly `{"is_lead": false}` and creates no administrator.
- Approval and resend generate a random non-secret selector plus a separate 256-bit secret. Only the secret's SHA-256 hash is stored, and expiry defaults to 60 minutes (`ADMIN_INVITATION_EXPIRATION_MINUTES`). The selector is the only value in the HTTP path; the emailed secret is placed in the URL fragment, removed immediately by the standalone acceptance page, and submitted in the POST body so ordinary request logs and referrers do not receive it. Acceptance errors render directly under no-store headers rather than flashing the secret into a potentially database-backed session.
- Acceptance GET is non-consuming and uses no third-party assets. It sends no-store, no-referrer, no-index, CSP, and frame-ancestor headers. Acceptance POST enforces the established 12-character mixed-case/number/symbol password policy, ignores submitted identity/role fields, rechecks expiry and identity conflicts, creates the intended active non-lead account, and consumes the invitation in the same transaction. It then redirects to normal administrator login, leaving existing login and 2FA authoritative.
- Notification dispatch occurs after the approval/resend transaction commits. Delivery failure leaves the request approved with a recoverable `failed` marker. Lead resend is limited to three attempts per hour per invitation, rotates both selector and secret, and invalidates the superseded link. Revocation clears the active selector/hash.
- The additive migration reserves normalized email for safely completable pending rows. It issues no token and creates no account for legacy pending data; duplicate or administrator-conflicting legacy pending rows become revoked for explicit re-request. A legacy approved row is marked accepted only when the exact administrator ID and normalized email already exist; otherwise it becomes expired. No account is activated by migration.
- The isolated focused run passed 13 tests (161 assertions), including authorization, state replay, expiry, resend, revocation, token/identity/role tampering, account-conflict and injected-failure rollback, post-commit delivery failure, normal login, and an actual old-schema migration rehearsal. The full isolated suite passed 115 tests (858 assertions). These sequential SQLite results do not prove production-engine lock behavior.

## Ordered application fixes still needed before launch

### 1. Atomically consume 2FA challenges and bound their lifecycle — Medium

- **Consequence:** A captured valid code can establish more than one session when verification requests race. Distributed/IP-changing guessing is not bounded per challenge, expired pending sessions persist, and another login for the same admin invalidates the first browser's code.
- **Affected operations:** `POST /admin/login`, `GET|POST /admin/two-factor`.
- **Evidence/reproduction:** Static read/save sequence is at `AdminController.php:92-106`; the schema stores only one code and expiry per administrator (`2026_08_02_000100_add_two_factor_fields_to_admins_table.php:11-15`). Reproduce concurrent consumption only on a disposable instance of the selected production engine.
- **Smallest fix:** Store a server-generated challenge identifier with hashed code, expiry, attempt count, and consumed timestamp (or equivalent cache record); bind the pending session to it; conditionally consume one unexpired/unconsumed row in a transaction; clear pending state on expiry, exhaustion, restart, and successful non-2FA login. Retain request throttling as an outer control.
- **Verification:** Frozen-time expiry tests, per-challenge attempt exhaustion, pending-session replacement/cleanup, inactive-admin transition, sequential replay, and a two-connection consume race that yields exactly one authenticated session.

### 2. Establish cart invariants and all-or-nothing login merge — Medium

- **Consequence:** Concurrent first use can create duplicate carts/items. Current aggregation masks many duplicates, but increases race/maintenance complexity. A failed multi-item login merge can commit early items, retain the guest cart, and add those items again on retry (bounded by stock but customer-visible).
- **Affected operations:** `GET|POST /api/cart`, web cart add/update/remove, customer registration/login merge, authenticated checkout cart loading.
- **Evidence/reproduction:** The two initial cart migrations have no relevant unique keys; `SessionCartService.php:107-128` locks only rows already found; `CartMergeService.php:15-27` has no outer transaction. For the merge failure, place a valid product before a deleted ID in a disposable guest session and invoke merge: the first item commits before `findOrFail` aborts, and session clearing is not reached.
- **Smallest fix:** Migrate existing duplicates deterministically, add one-cart-per-customer and `(cart_id, product_id)` unique constraints, serialize first creation (for example by locking the customer row), handle expected unique violations, and transact the database portion of a prevalidated cart/wishlist merge before clearing only the merged session snapshot.
- **Verification:** Migration tests with duplicate fixtures; rollback test for a stale guest product; two real database connections for first-cart and same-product races; then re-run duplicate compatibility, checkout, and ownership tests.

### 3. Unify authorized product-write validation — Medium

- **Consequence:** A valid administrator can create/update catalog data through the API without the web route's status, bounds, discount, string-length, or media consistency rules, causing invalid catalog state or engine-specific errors.
- **Affected operations:** `POST|PUT /api/products/{id?}`.
- **Evidence/reproduction:** Compare `Api/ProductController.php:16-48` with `AdminController.php:287-307,322-353`. Authorization is present and tested; validation parity is not.
- **Smallest fix:** Share Form Request rules and a product write/media service between web and API, or deliberately narrow/remove the API mutations if they are not a launch requirement.
- **Verification:** Boundary/invalid-payload tests across both interfaces, authorization regression tests, rollback on media/database failure, and unchanged order-history snapshots.

## Production-engine concurrency and migration checks

These checks cannot be closed by the current in-memory SQLite suite. After choosing the engine/version, use a disposable database with production isolation settings and at least two independent connections:

1. Run the cart cleanup/constraint migration against fixtures containing multiple carts per user and repeated product rows; verify quantities, foreign keys, indexes, rollback behavior, and an upgrade from the pre-fix schema.
2. Race first-cart creation, same-product add/update, login merge versus cart mutation/checkout, and confirm no lost update, duplicate invariant, deadlock leak, or double merge. Confirm the chosen retry strategy recognizes that engine's unique/deadlock SQL states.
3. Race invitation approve/approve, approve/reject, resend/accept, and accept/accept; exactly one incompatible transition or token consumer must succeed. Also race 2FA consume/consume. SQLite sequential replay is not concurrent evidence.
4. Re-run checkout stock/idempotency races on the selected engine. Current transaction structure and SQLite tests are strong evidence, but the repository has only sequential stock competition and no two-connection production-engine result.
5. Apply the complete migration chain to an empty database and a disposable copy of representative pre-hardening data, then run `deployment:preflight` and the full suite without external services.

## Hosting configuration and backup/restore prerequisites

These are deferred until a platform, database engine, and topology are selected; none is assumed here.

1. Ensure every chosen web server refuses script execution and script-source download anywhere under the public upload URL. The container Apache path is verified. The repository's Nginx sample has a general `location ~ \.php$` handler but no `/storage` denial (`deployment/nginx/maattech.com.conf:53-75`), and the generic cPanel path depends on host-controlled Apache/PHP handler behavior. Add and test an explicit upload-location deny before using either path; also enforce `nosniff` and allow only intended media response types.
2. Confirm the document root is only `laravel-app/public`, persistent storage links/volumes survive releases, upload permissions are narrow, upload/body limits match the application, and source, environment, database, backup, and temporary files are unreachable over HTTP.
3. Configure HTTPS termination, exact trusted proxy CIDRs (if any), secure cookies, host preservation, request limits, logs/alerts, graceful shutdown, and writer quiesce/resume for the selected topology.
4. Choose the database engine/version and rehearse the repository deployment path on a disposable host. Validate transactional-table requirements, migration duration/locks, maintenance behavior, health gates, and failure recovery.
5. Configure protected backup capacity, encryption, retention, monitoring, and off-host copies. Restore a database **and** uploaded media/configuration into an isolated environment, verify integrity and application reads, measure recovery time/data loss, and record the operator procedure. Archive creation or listing alone is not a restore rehearsal.

## Recommended next single checkpoint

Implement **atomic 2FA challenge consumption and bounded pending-session lifecycle** (remaining application backlog item 1). Keep that checkpoint separate from cart schema and product-write work.
