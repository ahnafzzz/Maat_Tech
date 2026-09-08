# Application hardening review — Step 10

Review date: 2026-09-09  
Reviewed branch: `codex/production-hardening`  
Reviewed commit: `0f96842968bbb4845a106c9bb90fd0626ddaacab`

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
| Invitation authorization | Resolved | All invitation routes require `admin.auth`; approve/reject additionally require `is_lead` (`routes/web.php:77-92`; `AdminController.php:250-275`). Active status and administrator session version are checked by the middleware (`AdminAuth.php:15-33`). |
| Invitation lifecycle/onboarding | **Unresolved** | Approval and rejection do not require `status=pending`, lock the request, or share a transaction (`AdminController.php:250-284`). Approval immediately creates an active administrator with an undisclosed random password, while the response refers to a “secure invite delivery hook” that has no route/service implementation (`AdminController.php:255-269`; route inventory in `routes/web.php:69-110`). The table has no token or expiration (`2026_07_14_140000_create_commerce_operations_tables.php:52-63`). Requests also do not reserve/check email uniqueness against administrators, so approval can fail late on `admins.email`. |
| 2FA expiration and ordinary replay | Resolved sequentially | Codes are hashed, expire after ten minutes, and are cleared after successful verification (`AdminController.php:44-50,92-106`). The exact-SHA suite includes successful 2FA completion coverage. |
| 2FA attempts, concurrent replay, and pending session | **Unresolved** | Login and challenge share an IP-only five-per-minute limiter (`AppServiceProvider.php:22-24`; `routes/web.php:70-75`), but there is no challenge/account attempt counter or invalidation threshold. Verification is read-then-save without a conditional consume/lock, so two requests holding the valid code can both pass before either clears it (`AdminController.php:92-106`). Expired/invalid challenges leave `pending_admin_id` and stale code fields in place; the challenge GET tests only whether the session key exists (`AdminController.php:77-96`). A second login overwrites the administrator-wide code, invalidating another pending browser. Production-engine race behavior remains to be demonstrated. |
| Public product/review visibility | **Unresolved and directly evident** | The home page selects featured products without `status=active` (`HomeController.php:11-16`); product detail loads any slug and all reviews without `is_approved` (`HomeController.php:56-67`); public API index/show return every product status (`Api/ProductController.php:11-34`). The detail template iterates every loaded review and exposes at least its rating; it currently references `comment` even though the model stores `body`, so the review body is not rendered (`product.blade.php:53-63`; `Review.php:7-14`). Cart and wishlist route-model binding likewise accepts inactive products, and their display queries do not filter status (`StorefrontController.php:31-49,145-179`; `SessionCartService.php:47-77`). Checkout correctly rejects inactive products (`CheckoutService.php:118-126`), but that does not prevent pre-checkout disclosure or interaction. Sitemap, catalog list, related products, and checkout's final availability check already filter/enforce active status. |
| Product upload validation and storage | Resolved at the application boundary; hosting enforcement remains | Admin web uploads require Laravel image validation (max 5 MiB) or an enumerated video MIME (max 100 MiB), and Laravel generates hashed names below `storage/app/public/products/{id}` (`AdminController.php:287-307,322-353`; `filesystems.php:41-48,76-78`). SVG and PHP are not accepted by the image rule. The maintained container additionally disables CGI and denies PHP-like upload paths (`docker/apache-vhost.conf:12-23`), and the exact-SHA container smoke job observed the denial. This review found no application route that accepts arbitrary public-path files. |
| Service/authorization bypasses | No checkout or customer-object authorization bypass confirmed; one write-parity gap remains | Web/API checkout use `CheckoutService`; web/API cart additions use `SessionCartService`; API cart deletion and order reads scope records to the web customer; admin API mutations use `api.admin.auth` (`routes/web.php:95-110`). However, the authorized product API writes directly through `Product` with a much smaller create validator and no update validator (`Api/ProductController.php:16-48`), bypassing the web product validation/media lifecycle. This is an admin-side data-integrity gap, not an unauthenticated mutation vulnerability. |

## Ordered application fixes needed before launch

### 1. Enforce a single public publication boundary — High

- **Consequence:** Draft/archived product names, descriptions, prices, and media can be disclosed through public pages/API; unapproved review records/ratings are included on detail pages; inactive items can be added to cart/wishlist and advertised through WhatsApp before checkout rejects them. The current review field mismatch prevents the stored body from rendering, but that incidental defect is not a publication control.
- **Affected operations:** `GET /`, `GET /products/{slug}`, `GET /api/products`, `GET /api/products/{id}`, and cart/wishlist mutations and displays.
- **Evidence/reproduction:** Create disposable draft and archived products (one featured) plus an unapproved review, then request the paths above as a guest. The current unscoped queries cited in the disposition table select them. Do this only in the isolated test database.
- **Smallest fix:** Introduce a reusable `published`/`active` product scope; apply it to every public lookup and route-bound mutation. Eager-load only approved reviews. Return 404 for non-public detail/API records and remove or clearly mark products that become inactive while already saved.
- **Verification:** Feature tests for every public surface, including featured, detail by slug/ID, unapproved reviews, cart/wishlist, sitemap, and active controls. Verify admin listings still see all statuses and checkout still rejects a product deactivated after it entered a cart.

### 2. Make administrator invitations an atomic, expiring acceptance workflow — High

- **Consequence:** A lead can approve rejected/already-reviewed requests; concurrent decisions can conflict or raise database errors; an approved row can diverge from administrator creation. The created account is active before the intended recipient securely establishes a password, but no acceptance mechanism exists, so onboarding is incomplete.
- **Affected operations:** `POST /admin/invitations`, `POST /admin/invitations/{id}/approve`, and `/reject`.
- **Evidence/reproduction:** In an isolated database, reject a pending request and then approve the same ID; current code performs both transitions because it never checks status. Approving a request whose email already exists reaches the unique constraint only during `Admin::create`. Route inventory contains no recipient acceptance endpoint.
- **Smallest fix:** Add an expiring, hashed, one-use acceptance token and explicit pending/approved/rejected/expired states; reserve both ID and normalized email; atomically condition decisions on pending state; create the administrator inactive (or only create it) when the invited recipient establishes a policy-compliant password. Keep lead-only review authorization.
- **Verification:** Sequential and two-connection tests for approve/approve, approve/reject, expired token, token replay, duplicate ID/email, inactive reviewer, non-lead denial, password setup, and rollback on delivery/creation failures. Fake notifications only.

### 3. Atomically consume 2FA challenges and bound their lifecycle — Medium

- **Consequence:** A captured valid code can establish more than one session when verification requests race. Distributed/IP-changing guessing is not bounded per challenge, expired pending sessions persist, and another login for the same admin invalidates the first browser's code.
- **Affected operations:** `POST /admin/login`, `GET|POST /admin/two-factor`.
- **Evidence/reproduction:** Static read/save sequence is at `AdminController.php:92-106`; the schema stores only one code and expiry per administrator (`2026_08_02_000100_add_two_factor_fields_to_admins_table.php:11-15`). Reproduce concurrent consumption only on a disposable instance of the selected production engine.
- **Smallest fix:** Store a server-generated challenge identifier with hashed code, expiry, attempt count, and consumed timestamp (or equivalent cache record); bind the pending session to it; conditionally consume one unexpired/unconsumed row in a transaction; clear pending state on expiry, exhaustion, restart, and successful non-2FA login. Retain request throttling as an outer control.
- **Verification:** Frozen-time expiry tests, per-challenge attempt exhaustion, pending-session replacement/cleanup, inactive-admin transition, sequential replay, and a two-connection consume race that yields exactly one authenticated session.

### 4. Establish cart invariants and all-or-nothing login merge — Medium

- **Consequence:** Concurrent first use can create duplicate carts/items. Current aggregation masks many duplicates, but increases race/maintenance complexity. A failed multi-item login merge can commit early items, retain the guest cart, and add those items again on retry (bounded by stock but customer-visible).
- **Affected operations:** `GET|POST /api/cart`, web cart add/update/remove, customer registration/login merge, authenticated checkout cart loading.
- **Evidence/reproduction:** The two initial cart migrations have no relevant unique keys; `SessionCartService.php:107-128` locks only rows already found; `CartMergeService.php:15-27` has no outer transaction. For the merge failure, place a valid product before a deleted ID in a disposable guest session and invoke merge: the first item commits before `findOrFail` aborts, and session clearing is not reached.
- **Smallest fix:** Migrate existing duplicates deterministically, add one-cart-per-customer and `(cart_id, product_id)` unique constraints, serialize first creation (for example by locking the customer row), handle expected unique violations, and transact the database portion of a prevalidated cart/wishlist merge before clearing only the merged session snapshot.
- **Verification:** Migration tests with duplicate fixtures; rollback test for a stale guest product; two real database connections for first-cart and same-product races; then re-run duplicate compatibility, checkout, and ownership tests.

### 5. Unify authorized product-write validation — Medium

- **Consequence:** A valid administrator can create/update catalog data through the API without the web route's status, bounds, discount, string-length, or media consistency rules, causing invalid catalog state or engine-specific errors.
- **Affected operations:** `POST|PUT /api/products/{id?}`.
- **Evidence/reproduction:** Compare `Api/ProductController.php:16-48` with `AdminController.php:287-307,322-353`. Authorization is present and tested; validation parity is not.
- **Smallest fix:** Share Form Request rules and a product write/media service between web and API, or deliberately narrow/remove the API mutations if they are not a launch requirement.
- **Verification:** Boundary/invalid-payload tests across both interfaces, authorization regression tests, rollback on media/database failure, and unchanged order-history snapshots.

## Production-engine concurrency and migration checks

These checks cannot be closed by the current in-memory SQLite suite. After choosing the engine/version, use a disposable database with production isolation settings and at least two independent connections:

1. Run the cart cleanup/constraint migration against fixtures containing multiple carts per user and repeated product rows; verify quantities, foreign keys, indexes, rollback behavior, and an upgrade from the pre-fix schema.
2. Race first-cart creation, same-product add/update, login merge versus cart mutation/checkout, and confirm no lost update, duplicate invariant, deadlock leak, or double merge. Confirm the chosen retry strategy recognizes that engine's unique/deadlock SQL states.
3. Race invitation approve/approve and approve/reject, and 2FA consume/consume; exactly one terminal transition/consumer must succeed with a controlled response for the loser.
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

Implement **publication-boundary enforcement and its focused feature tests** (application backlog item 1). It is the highest-confidence externally visible issue, has no hosting dependency, and can be completed without redesigning checkout or administrator identity. Stop after that checkpoint for review before beginning invitation, 2FA, or cart schema work.
