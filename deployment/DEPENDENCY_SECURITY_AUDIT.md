# Dependency Security Audit — 2026-09-08

## Scope and toolchain

The maintained dependency locks are `laravel-app/composer.lock` for the Laravel runtime and test tooling, and `laravel-app/package-lock.json` for the Vite/Tailwind build. The root `package.json` contains repository helper scripts but no dependencies or lockfile. Netlify and the static/cPanel package do not add a separate package inventory.

The audit host used PHP 8.5.10, Composer 2.10.3, Node 26.8.1, and npm 12.0.2. CI uses PHP 8.3 and Node 20; the runtime container uses PHP 8.4 and its frontend stage uses Node 20. Composer requires PHP 8.3 or newer. Compatibility must continue to be proven in CI and the container because the local versions are newer.

## Composer findings and remediation

The fresh pre-update `composer audit --locked --format=json` result contained ten advisories for transitive runtime dependency `league/commonmark` 2.8.3. Laravel Framework directly requires `league/commonmark ^2.8.1`; the application does not directly call CommonMark or Laravel Markdown APIs.

| Advisory | Severity | Affected range | Patched release |
| --- | --- | --- | --- |
| GHSA-8rr7-cvq3-gmfh | High | `<2.10.0` in the installed major | 2.10.0 |
| GHSA-jjv6-8j6v-6j52 | High | `<2.9.1` in the installed major | 2.9.1 |
| GHSA-f8fg-pg57-v4j8 | High | `>=2.7.0,<2.9.1` | 2.9.1 |
| GHSA-j8pm-gj4c-rq4x | High | `<2.9.1` in the installed major | 2.9.1 |
| GHSA-mj63-m3rc-8ppr | Medium | `>=2.0.0,<2.9.0` | 2.9.0 |
| GHSA-mh25-x5hq-wrqp | High | `>=2.0.0,<2.9.0` | 2.9.0 |
| GHSA-jfm3-95jq-q3rf | High | `<2.9.0` in the installed major | 2.9.0 |
| GHSA-g2gp-3wwq-f4ph | High | `<2.9.0` in the installed major | 2.9.0 |
| GHSA-2q4p-g7hv-5rgv / CVE-2026-71488 | High | `<2.9.0` in the installed major | 2.9.0 |
| GHSA-29pj-957v-52mc / CVE-2026-71478 | Medium | `<=2.8.3` in the installed major | 2.9.0 |

The lock now selects `league/commonmark` 2.10.1, which is compatible with Laravel's existing constraint and includes the 2.10.0 completion of the AttributesExtension denial-of-service fix. The upstream 2.10 release also changes repeated table-of-contents rendering and the normalized shape returned for `default_attributes`; repository searches found no application use of those APIs. Version 2.10.1 adds an InlinesOnlyExtension configuration compatibility fix. No direct Composer constraint changed.

Official release notes: [CommonMark 2.10.0](https://github.com/thephpleague/commonmark/releases/tag/2.10.0) and [CommonMark 2.10.1](https://github.com/thephpleague/commonmark/releases/tag/2.10.1).

## npm findings and remediation

The fresh pre-update `npm audit --json --audit-level=info` result included development/build dependencies and found two transitive packages. They execute in the trusted asset-build environment and are not shipped as server-side Node services. The application supplies trusted CSS/build inputs, which limits current exploitability, but CI and Docker still execute this toolchain.

| Package | Locked before | Advisory | Severity | Dependency path | Fix and constraint |
| --- | --- | --- | --- | --- | --- |
| `nanoid` | 3.3.16 | GHSA-2v37-7h3g-55p8 | High | Vite → PostCSS → nanoid | 3.3.18; PostCSS's compatible range permits it |
| `postcss` | 8.5.19 | GHSA-fxqj-rqcc-2cmp | Moderate | Vite → PostCSS | Greater than 8.5.22; Vite's `^8.5.16` constraint permits it |

The lock now selects `nanoid` 3.3.18 and `postcss` 8.5.28. PostCSS 8.5.23 introduced the security fix by refusing to load a source map when `opts.from` is absent; later 8.5.x releases remain within Vite's existing range. Nanoid 3.3.18 fixes the affected zero-size custom-generator loop. Direct npm constraints and Vite 8.1.4 are unchanged.

Official release notes: [PostCSS 8.5.23](https://github.com/postcss/postcss/releases/tag/8.5.23), [PostCSS 8.5.28](https://github.com/postcss/postcss/releases/tag/8.5.28), and [nanoid 3.3.18](https://github.com/ai/nanoid/releases/tag/3.3.18).

## Result and limitations

Post-update Composer and npm audits completed successfully and reported no known advisories in their respective lockfiles, including development/build dependencies. This result is limited to advisories known to and returned by the configured audit services at the audit time. It is not proof that the dependencies or application are free of vulnerabilities.

No advisory remains as a release blocker from these two audit results. Production readiness still depends on the hosting, backup/restore, and deployment blockers in `DEPLOYMENT_RUNBOOK.md`, plus successful CI verification on the configured PHP 8.3 and Node 20 environments.
