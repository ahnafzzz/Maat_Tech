# Storefront security and deployment checklist

Updated: 2026-10-08

Legend: `[x]` complete, `[ ]` pending, `[!]` blocked or requires owner/infrastructure input.

## Preservation and baseline

- [x] Confirmed branch `codex/storefront-3d` and preserved the existing modified/untracked work.
- [x] Confirmed no applicable `AGENTS.md` exists in the project hierarchy.
- [x] Recorded framework/runtime baseline and current tests before further edits.
- [x] Preserve `../desk_lamp_viewer_final_final.html` byte-for-byte.

## Application fixes

- [x] Reproduce and restore intended protected administrator access.
- [x] Remove administrator links from customer-facing views while retaining `/admin/login`.
- [x] Require strong administrator 2FA enrollment and fresh verification for sensitive changes.
- [x] Repair product-page 3D pointer interaction and authoritative auto-rotation state.
- [x] Preserve rotation choice across media changes and browser navigation.
- [x] Use a neutral, accessible viewer background for black and white finishes.
- [x] Repair and verify Add to Cart, variants, rapid-click handling, errors, and cart merge.
- [x] Repair and verify isolated Buy Now and server-authoritative totals.
- [x] Install the official logo on customer login and registration views.
- [x] Apply requested customer-visible label corrections.
- [x] Verify order statuses use each persisted order record.
- [x] Repair lamp image paths/fallbacks throughout customer and administrator views.
- [!] Install only verified permanent product media. One real black-lamp photo is installed; no permanent product video or verified white-lamp photo was supplied or found, so neither was fabricated.

## Security and operations

- [x] Remove runtime CDN/changeable scripts from administrator views.
- [x] Add account/IP progressive authentication throttles without permanent lockout.
- [x] Apply shared abuse limits to checkout, Buy Now, and API order creation.
- [x] Define transactional inventory reservation/restoration lifecycle.
- [x] Recheck ownership, CSRF, XSS, redirects, queries, mass assignment, and uploads.
- [x] Add production-safe headers, CSP, no-store rules, host/proxy/cookie safeguards.
- [x] Add privacy-conscious audit events and operational documentation.
- [x] Audit Composer and npm dependencies; apply compatible fixes only.
- [x] Document protected backup/restore, rollback, scheduler, queues, and maintenance.

## Verification

- [x] Restore an explicit WebGL context-loss and recovery test.
- [x] Verify slideshow focus with real Tab/Shift+Tab input.
- [x] Prove real bfcache restoration via `pageshow.persisted`.
- [x] Run PHP, JavaScript, build, formatting, migration, and diff checks.
- [x] Run fresh desktop/mobile browser verification and capture screenshots.

## Deployment

- [x] Inspect current DNS and live HTTPS behavior without changing records.
- [!] Confirm hosting and receive authorized server/DNS/database/mail access before deployment.
- [!] Back up any existing deployment before migrations. The backup/restore and rollback procedures are documented, but there is no provisioned host or authorized destination to back up.
- [!] Deploy an exact reviewed build only after access and backup requirements are satisfied. Deployment is blocked by the absent hosting/access, not by local application work.
- [!] Verify production DNS, redirects, HTTPS, media, sessions, headers, and key flows after deployment. Current domains still resolve to registrar parking infrastructure and do not provide working production TLS.
