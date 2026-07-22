# Production Readiness Audit

> **Historical snapshot.** This audit records the repository and live-server
> state observed on 2026-07-21 at commit `beed55288a4d972523a01c1283b78aa28f384aeb`.
> Its counts and unresolved findings must not be treated as the current release
> status. Current evidence is maintained in `docs/PRODUCTION_CERTIFICATION.md`,
> `docs/GEOCORE_FINAL_VALIDATION.md` and
> `docs/PRODUCTION_BLOCKER_REMEDIATION.md`.

Audit date: 2026-07-21

Repository baseline: `main` at `beed55288a4d972523a01c1283b78aa28f384aeb`

Assessment: read-only repository, local-runtime, local-SQLite and unauthenticated public-production review. No production control panel, production shell, production `.env`, authenticated admin page, mailbox or backup store was accessed.

## Current Remediation Status (2026-07-22)

The historical audit is valid project documentation and is retained as the
source record that led to the hardening sprints. The following findings have
since been resolved in the repository and covered by automated tests:

- PR-001: GeoCore mapping now covers 255 of 257 cities and 1,552 of 1,557
  facilities; Cottbus district contains 72 facilities;
- PR-008: parser and admin URL handling enforce safe absolute HTTP(S) URLs;
- PR-009: `/up` is a minimal sessionless plain-text health response with no
  external assets;
- PR-010: production logging uses daily rotation with 30-day retention;
- PR-011: malformed and out-of-range public pagination returns 404;
- PR-005 repository portion: the shared Open Graph PNG exists at
  `public/assets/og-image.png` and is covered by tests.

The current suite baseline before Sprint 3.5.1 is 202 tests and 3,070
assertions. The final remediation result is recorded in
`docs/PRODUCTION_BLOCKER_REMEDIATION.md`.

Still external/manual: production deployment, nginx one-hop redirects and
default-vhost behavior, production environment/permissions/cache evidence,
backup and restore evidence, monitoring, and hosting/mailbox/legal facts. The
exact operator inputs are tracked in `docs/LEGAL_HOSTING_FACTS_CHECKLIST.md`.

All sections below remain deliberately unchanged as point-in-time evidence.

## 1. Executive Summary

The application code has a strong baseline: the complete test suite passes, Composer reports no known advisories, Laravel's production caches can be built, public write operations do not exist, admin writes are authenticated and CSRF-protected, invalid geographic slugs return 404, filter pages are `noindex,follow`, and the production package builder separates the private Laravel core from the public webroot.

The current production state is not ready for an unconditional launch approval. Four P0 gates remain:

1. the geographic data is incomplete: 77 of 257 cities are not linked to GeoCore, leaving 399 of 1,557 facilities outside district pages; the live Cottbus district page returns HTTP 200 with zero facilities and is included in the sitemap;
2. the real production environment, private paths, permissions, cache state and database migration state were not available for verification;
3. no evidence was available that the documented production backup, restore test and rollback inputs currently exist;
4. hosting/mail/privacy facts identified by the privacy audit are still operator unknowns, so legal production approval cannot be confirmed from the repository.

There is also direct evidence that production is not serving the current `main` state: the live homepage still contains the superseded nationwide positioning, and its declared Open Graph image returns HTTP 404. Nginx redirects and virtual-host behavior require hosting-panel corrections, and imported parser URLs need server-side scheme validation before they can be accepted into public facility links.

**Launch recommendation: NO-GO.** The exact conditions for GO are listed in sections 23 and 24.

## 2. Scope and Method

The review covered:

- repository configuration, routes, middleware, controllers, models, migrations, Blade views, tests, deployment scripts and operational documentation;
- dependency metadata and the installed local runtime;
- safe Laravel introspection and isolated cache-build checks;
- read-only local SQLite integrity, foreign-key and migration checks;
- unauthenticated production requests to public pages, `/up`, `/admin/login`, redirects, assets, invalid routes, pagination, `robots.txt` and `sitemap.xml`;
- public response headers and behavior with altered `Host` and forwarded headers.

The audit did not:

- read or reproduce secret values;
- inspect the production `.env`, database, filesystem, logs, control panel or backups;
- authenticate to admin;
- submit forms;
- modify code or data;
- run migrations, imports, deployment or GeoCore Completion;
- create a commit or push.

Evidence labels used below:

- **Repository-confirmed**: directly supported by tracked code/docs.
- **Local-confirmed**: verified against the local runtime/database and not assumed to equal production.
- **Production-confirmed**: observed from a public unauthenticated production response on the audit date.
- **Unknown**: requires operator, hosting-panel or production-shell evidence.

## 3. Environment Configuration

### Repository-confirmed production intent

Both production templates specify:

| Setting | Intended value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://pflegeindex.com` |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | `stack` / `single` / `warning` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | absolute external path placeholder |
| `DB_FOREIGN_KEYS` | explicitly true in the deployment template |
| `SESSION_DRIVER` | `database` |
| Session security | encrypted, Secure, HttpOnly, SameSite=Lax, 120 minutes |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `MAIL_MAILER` | `log` |

The application defaults are safe for `APP_DEBUG` (`false`) but defaults are not a substitute for verifying production. No direct `env()` use was found outside configuration files. No application-level trusted proxy or trusted host configuration is declared.

### Production-confirmed

- HTTPS responds successfully and the TLS certificate was accepted by the client.
- Response headers expose PHP 8.2.27 and nginx 1.20.2.
- Public 404 output is generic and contains no visible stack trace.
- Admin login cookies observed in production are `Secure` and `SameSite=Lax`; the session cookie is also `HttpOnly`.
- Forwarded-host/protocol headers supplied during the audit did not change the application response.

### Unknown

- actual `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` presence and loaded config-cache values;
- actual database, cache, queue, session and mail drivers;
- whether nginx/PHP-FPM passes HTTPS and client IP information through a proxy layer;
- real `.env`, SQLite, `storage` and `bootstrap/cache` locations and permissions;
- whether production configuration/routes/views/events caches correspond to the deployed release.

## 4. Dependencies

### PHP and Composer

- `composer.json` requires PHP `^8.2` and pins the Composer platform to PHP 8.2.27.
- Local runtime: PHP 8.2.27; Laravel 12.64.0; Composer 2.10.2.
- Direct production packages: `laravel/framework` 12.64.0 and `laravel/tinker` 3.0.2.
- Dev-only packages are separated under `require-dev` and the production package builder runs Composer with `--no-dev --optimize-autoloader`.
- `composer.lock` is tracked and the package builder records its SHA-256.
- `composer validate --strict`: passed.
- `composer audit --locked`: passed; no known security advisories were reported and no abandoned-package warning was emitted.
- `composer check-platform-reqs --no-dev`: passed locally.

The public server advertises PHP 8.2.27, but production extension availability and the exact installed Composer dependency set remain unknown until `composer check-platform-reqs --no-dev` and manifest verification are run for the deployed private core.

### Frontend dependencies

- `package.json` contains only development dependencies: Tailwind/Vite tooling and `concurrently`.
- The production package does not use Node/Vite at runtime; it copies committed files from `public/assets`.
- No `package-lock.json` or npm shrinkwrap exists. Consequently, `npm audit` returns `ENOLOCK`, and frontend source-tool installation is not reproducible or auditable from a lockfile.
- The installed local `node_modules` includes several extraneous packages, reinforcing that it is not a clean reproducible source of dependency evidence.

## 5. Laravel Optimization

The following cache-build commands were executed with all generated files redirected into an isolated temporary directory under the ignored build area and removed afterwards:

- `php artisan config:cache` — passed;
- `php artisan route:cache` — passed;
- `php artisan view:cache` — passed;
- `php artisan event:cache` — passed.

No cached artifact from this audit remains in the working tree. Web routes contain no closure actions that prevent route caching. The only closure command found is the default console `inspire` command. No `env()` calls outside configuration were found.

The deployment checklist correctly requires `optimize:clear` followed by `optimize` after verifying the production `.env`. Whether this procedure was completed for the currently deployed release is unknown. The content mismatch between production and `main` means current production cache/release identity must be treated as unverified.

## 6. Routes and Public Exposure

`php artisan route:list --except-vendor` reports 32 application routes, plus Laravel's vendor health route at `/up`.

### Public routes

- home, directory, Brandenburg, city, district, facility, lexicon and legal/about pages;
- `robots.txt` and `sitemap.xml`;
- two permanent facility redirects and one old-about redirect;
- `/up` health endpoint;
- `GET /admin/login` and throttled `POST /admin/login`.

No public register, password-reset, parser/import, debug, tool, Telescope, Horizon or application API routes were found. The parser import exists only as an authenticated admin POST route and as CLI commands.

### Admin protection

- All admin functions except login require both `auth` and `admin` middleware.
- The custom admin middleware requires `is_admin=true`.
- Login POST is throttled to five attempts per minute.
- Admin state changes use POST/PUT and have CSRF protection in the `admin-session` middleware group.
- Logout is POST-only.
- Admin and `/up` responses receive `X-Robots-Tag: noindex, nofollow, noarchive`.

The public web middleware intentionally removes sessions and CSRF. This is currently safe because normal public application routes are read-only; the legacy redirect routes accept any method but perform no mutation. Any future public write route would need an explicit CSRF decision.

## 7. Error Handling

- Invalid city and district slugs return 404 locally in tests and on live production.
- A deliberately unknown production path returned a generic Laravel 404 page with no trace, source path or exception detail.
- No custom branded `resources/views/errors` pages exist; the framework pages are safe but provide a weak recovery experience.
- Application exceptions use Laravel's normal reporting pipeline. Parser-import exceptions are reported and converted to a generic admin validation message.
- With intended `APP_DEBUG=false`, 500 responses should be generic, but production debug/config state was not directly verified and no production error was deliberately triggered.
- A missing/unreadable SQLite file, unwritable storage/cache, disk-full condition or malformed database will normally produce an application error and log entry; there is no specialized public fallback.
- External provider, mail and map links are client-side links, so their unavailability does not block page rendering. The `/up` page is an exception because it loads third-party presentation assets.

## 8. Sensitive Files and Filesystem

### Repository-confirmed protections

- Real `.env`, SQLite files, logs, ZIP deployment archives, server diagnostics, `vendor`, `node_modules`, runtime caches and PHPUnit result cache are ignored.
- The tracked sensitive-name scan found only safe environment templates and empty `.gitignore` placeholders in runtime directories.
- No tracked private key, credential, real database, backup or log was found by filename scan.
- The package builder exports a clean commit, separates `private-core` from `public-webroot`, excludes tests/dev dependencies/secrets/databases/logs/backups/caches, scans for local paths and secret-like assignments, and writes SHA-256 manifests.
- The production front controller intentionally returns 503 until its private-core placeholder is replaced.
- Deployment documentation explicitly keeps `.env`, SQLite, backups, logs, storage and the core outside the webroot.

### Webroot observations

The tracked `public` directory contains only the front controller, `.htaccess`, local assets, logos and favicons. The production package further narrows this list. The zero-byte `public/favicon.ico` is unused by the layout and intentionally excluded from the package.

Production uses nginx, so tracked Apache `.htaccess` protections are not active there. The actual nginx rules protecting hidden files, SQLite, backups, archives and the private core are unknown. Public probing did not disclose those files, but exhaustive exposure verification requires the real webroot layout and server configuration.

## 9. SQLite Readiness

### Repository design

- Production SQLite is intended to live outside both the release directory and public webroot.
- Production templates enable foreign keys.
- Migrations use foreign keys and useful uniqueness/index constraints for slugs, source IDs, facility city/type, postal code, session activity, cache expiration, contact suggestions and GeoCore relations.
- Multi-record imports, contact acceptance and bulk publication use database transactions.
- SQLite config leaves `busy_timeout`, `journal_mode` and `synchronous` unset and uses deferred transactions.

### Local read-only evidence

- Local database size: 3,346,432 bytes.
- No WAL/SHM/journal sidecar was present at audit time.
- Read-only `PRAGMA integrity_check`: `ok`.
- Read-only `PRAGMA foreign_key_check`: no violations returned.
- Local journal mode: `delete`; synchronous level: 2.
- All 11 migration files are marked as applied locally.

Raw SQLite connections do not inherit Laravel's connection initialization; the raw read-only audit reported foreign-key enforcement off for that standalone connection. This does not contradict the Laravel/deployment setting, but production enforcement must be confirmed through the actual application connection.

### Production unknowns

- database absolute path, ownership, containing-directory permissions and web isolation;
- file size, row counts, integrity, foreign-key violations and migration status;
- journal mode, busy timeout, lock contention and concurrent admin/write behavior;
- backup consistency when sidecar files exist;
- disk capacity and database growth.

SQLite remains appropriate for the current low-write directory if the single-host deployment, locking behavior, backups and integrity checks are operationally verified. It should not be approved solely from the local database result.

## 10. Backup and Recovery

The repository contains solid human procedures:

- daily and pre-change SQLite backups;
- maintenance mode before a database copy;
- `.env` backup after approved configuration changes;
- backups outside webroot and preferably on a second owner-controlled system;
- recommended retention of 7 daily, 4 weekly and 6 monthly copies;
- protection/encryption because backups can contain admin and contact-review data;
- monthly restore into a separate non-production directory;
- pre-deployment commit recording, rollback steps and exact database restoration rather than blind migration rollback.

No repository scheduler or backup program enforces these procedures. No backup inventory, successful restore-test record, off-site copy evidence, encryption evidence, responsible operator assignment, monitoring alert or disaster-recovery timing objective was available. Provider-level backups and their scope/retention are unknown.

The production package builder does not include SQLite or `.env`, which is correct, but this also means package readiness is independent of database recovery readiness. A verified current backup and restore path are mandatory before deployment or data work.

## 11. Web Server Configuration

Production-confirmed server behavior:

- nginx 1.20.2 serves the site; `.htaccess` is therefore not authoritative.
- HTTPS canonical host returns 200.
- `https://www.pflegeindex.com` returns a 301 to `https://pflegeindex.com/`.
- `http://pflegeindex.com` returns a 301 to `https://pflegeindex.com:443/`.
- `http://www.pflegeindex.com` returns a 301 to `https://www.pflegeindex.com:443/`, followed by a second application redirect to the non-www host.
- trailing-slash and front-controller rules are defined in `.htaccess`, but their nginx equivalents were not inspected.
- an arbitrary `Host: example.invalid` with TLS SNI for PflegeIndex returned an ISPmanager/default-host page with HTTP 200 and set an `ispmgrlang5` cookie instead of rejecting the mismatch.

The redirect chain should be one hop to the exact canonical host without an explicit default port. The unmatched-host vhost should return 400/421/444 rather than a control-panel/default-site response. These are hosting/nginx changes, not Laravel changes.

No compression or explicit browser cache policy was observed for CSS/JavaScript. Provider/PHP server-version disclosure remains enabled. Exact nginx rules for dotfiles, archives, database extensions, backup names, directory indexes and PHP execution are unknown.

## 12. Security Headers

Normal dynamic production responses include:

- `X-Content-Type-Options: nosniff`;
- `X-Frame-Options: SAMEORIGIN`;
- `Referrer-Policy: strict-origin-when-cross-origin`;
- restrictive camera/microphone/geolocation `Permissions-Policy`;
- HSTS for one year on HTTPS;
- `X-Robots-Tag` on admin and `/up`.

Gaps and observations:

- HSTS is emitted twice on many dynamic responses (application and nginx).
- HSTS lacks `includeSubDomains`; preload is not configured. This is not automatically required, but the intended subdomain policy should be explicit.
- No Content Security Policy is present.
- No COOP, COEP or CORP headers are present; these are optional for the current site and should not be added without compatibility testing.
- nginx and PHP versions are disclosed.
- Static assets receive HSTS from nginx but not the application headers, which is expected for directly served files.

A conservative CSP becomes more valuable because database-derived outbound links and the `/up` view exist, but it must account for the intentional third-party `/up` resources or those resources should first be removed.

## 13. Sessions and Authentication

- Public application pages do not start a Laravel session and the homepage test asserts no `Set-Cookie` header.
- The protected admin group uses database sessions, CSRF and Laravel session authentication.
- Production login sets a Secure SameSite=Lax CSRF cookie and a Secure HttpOnly SameSite=Lax session cookie.
- Login validates email/password, uses a generic failure message, limits attempts, regenerates the session after success, and only accepts admin users.
- Logout invalidates the session and regenerates the CSRF token.
- Password change requires the current password and a confirmed 12+ character mixed-case alphanumeric password, then regenerates the current session.
- No registration, public reset flow or remember-me UI exists. A remember-token column remains in the standard schema but is not actively used.

Hardening gaps:

- no MFA is implemented for the production administrator;
- password changes do not explicitly invalidate other active database sessions;
- the admin session cookie path is `/`, so it is sent on public paths after an admin logs in;
- actual session encryption, lifetime, cleanup and production table state are unknown until config is inspected;
- there is no documented account disable/recovery runbook beyond direct operator access.

## 14. Forms and Validation

All database-writing HTML forms are in the protected admin area, except public login. Existing controls are generally strong:

- facility updates validate lengths, email, HTTP(S) website/source URLs, booleans and allowed status combinations;
- description review validates actions, text length, date and every source as HTTP(S);
- bulk publication limits the list to 30 distinct existing IDs;
- password and login validation are appropriate;
- contact suggestion decisions verify pending state and use transactions for acceptance;
- parser upload requires a file and limits it to 10 MiB.

The parser upload/import path is the main gap. It accepts a JSON results list but has no strict per-field schema, type/length limits, status allow-list, email rule or HTTP(S)-only validation for `website`, source URLs and `pagesChecked`. The full item is stored as `raw_payload`. Accepted `website`/source values can be copied to a facility and rendered as `href` values. Blade escapes markup, but an unsafe URL scheme is still a potentially executable or misleading link. The source-list admin view also renders parser-derived URLs without a scheme check.

The upload is authenticated and not a public endpoint, which reduces but does not remove the risk from malformed, compromised or incorrectly generated parser output.

## 15. Search and Query Safety

- Public search/filter values are passed through Eloquent parameter binding; no raw user-supplied SQL fragment or user-controlled sort expression was found.
- Blade escapes search values and facility data; a test confirms escaped facility descriptions.
- Sort order is fixed and stable by city, facility name and ID.
- `type` and `city` filters use exact values; invalid values safely return no matches.
- Invalid/non-positive pages are normalized to page 1.
- Pagination uses a fixed page size of 24.

Gaps:

- `q`, `type` and `city` have no request-level maximum length or allow-list validation.
- Search treats `%` and `_` as SQL LIKE wildcards and uses leading/trailing `%`, preventing normal B-tree index use and causing scans.
- Public search has no route-level rate limit.
- Page numbers have no upper bound. Production returned HTTP 200 for `page=999999999`; paginated templates produce self-referential SEO metadata for such empty pages. This creates unbounded empty URLs and potentially large database offsets.
- With only 1,557 facilities the present scan cost is modest, but the input and pagination bounds should be fixed before traffic/data growth.

## 16. SEO Production Verification

Production-confirmed positive results:

- canonical host uses HTTPS;
- homepage canonical, title, description, Open Graph, WebSite and Organization JSON-LD are present;
- directory filter URL has `noindex,follow` and canonicalizes to the unfiltered directory;
- Brandenburg page 2 has a self-canonical URL, page-specific title/description/OG and matching CollectionPage URL;
- `robots.txt` allows crawling and points to the HTTPS sitemap;
- live sitemap parsed as valid XML with 1,899 unique URLs, no admin/login/up/legal URLs, and canonical HTTPS host URLs;
- invalid city/district routes return 404;
- `/up` and admin login are protected from indexing.

Production-confirmed problems:

- declared `https://pflegeindex.com/assets/og-image.png` returns HTTP 404;
- homepage content does not match current `main` and still uses superseded nationwide positioning;
- the live Cottbus district page returns HTTP 200, self-canonical metadata and CollectionPage JSON-LD for zero facilities; it is included in the sitemap;
- arbitrary out-of-range pagination returns indexable HTTP 200 with a self-canonical URL;
- HTTP host variants do not all redirect in one step to the exact canonical URL.

Repository sitemap logic includes every Brandenburg district regardless of whether it has facilities. Therefore, incomplete GeoCore relations directly create indexable empty district landing pages.

## 17. Frontend Assets

- Normal public/admin layouts use local CSS, JavaScript, logos, favicon and Open Graph image references.
- No mixed-content URL was found in normal application templates.
- `public/assets/app.js` passes Node syntax checking.
- Local required assets exist and are included by the production package builder.
- The main logo has explicit dimensions, reducing layout shift.

Production observations:

- CSS and JavaScript return 200 with ETag/Last-Modified, but no explicit long-lived `Cache-Control` policy;
- CSS was not compressed when requested with gzip/Brotli support;
- live CSS size differs from the current repository asset, supporting the release-mismatch finding;
- `app.js` has no version query/hash, while CSS uses a manual query version;
- the Open Graph image is missing in production;
- `/up` loads Bunny Fonts and a browser Tailwind script from jsDelivr, adding privacy, availability and supply-chain dependencies to a health page.

The project does not rely on Vite output in production. The ignored `public/build` directory is not part of the production package.

## 18. Monitoring and Operations

The operations guide defines useful daily and weekly manual checks for `/up`, pages, logs, backup completion, disk space, admin login, sitemap, robots, search, SSL-facing behavior and accidental public files. Incident and rollback steps are documented.

No repository evidence exists for:

- external uptime monitoring or alerts;
- error aggregation or alert thresholds;
- Laravel log rotation under the intended `single` channel;
- disk-space/database-growth alerts;
- automated backup completion/failure alerts;
- scheduled SQLite integrity/foreign-key checks;
- SSL-expiry alerts;
- failed-job monitoring (the intended queue is synchronous, but failed-job infrastructure exists);
- sitemap/robots availability monitoring;
- an on-call or named incident owner;
- a verified cadence of restore exercises.

`/up` confirms Laravel can boot, but its external visual dependencies are inappropriate for a minimal health signal. Provider nginx/PHP/access/error-log monitoring remains unknown.

## 19. Test and Tool Results

| Check | Result |
|---|---|
| `php artisan test` | PASS — 122 tests, 2,652 assertions |
| `composer validate --strict` | PASS |
| `composer audit --locked` | PASS — no known advisories reported |
| `composer check-platform-reqs --no-dev` | PASS locally |
| `npm audit --audit-level=low` | NOT RUNNABLE — `ENOLOCK`, no npm lockfile |
| `npm list --depth=0` | Completed; several extraneous local modules reported |
| `node --check public/assets/app.js` | PASS |
| `php artisan route:list --except-vendor` | PASS — 32 application routes listed |
| `php artisan about` | PASS; local environment only, not production evidence |
| isolated `config:cache` | PASS |
| isolated `route:cache` | PASS |
| isolated `view:cache` | PASS |
| isolated `event:cache` | PASS |
| local SQLite read-only integrity check | PASS (`ok`) |
| local SQLite read-only foreign-key check | PASS (no rows) |
| local `migrate:status` | PASS — 11/11 migrations applied |
| production public smoke/header checks | Completed; findings recorded above |

The full test suite includes positive coverage for admin authentication/authorization, validation, GeoCore import safety, directory filtering, pagination SEO, city/district/facility pages, canonical redirects, security headers, `/up` robots protection, sitemap/robots, legal pages and XSS escaping. It does not cover real nginx behavior, deployment/package installation on the host, production database integrity, backup restoration, monitoring, parser URL-scheme rejection, maximum search length or out-of-range page rejection.

## 20. Confirmed Production Facts

1. The domain was publicly reachable over valid HTTPS on 2026-07-21.
2. Public server headers identify nginx 1.20.2 and PHP 8.2.27.
3. Home, `/up`, robots, sitemap, admin login, filter search and pagination returned HTTP 200.
4. Unknown city, district and generic paths returned generic 404 responses without visible traces.
5. `/up` and admin responses carried noindex headers.
6. Normal dynamic pages carried nosniff, frame, referrer, permissions and HSTS headers.
7. HSTS was duplicated on many dynamic responses.
8. The canonical HTTPS sitemap contained 1,899 unique public URLs and no inspected prohibited route class.
9. The homepage declared an Open Graph image that returned 404.
10. Production homepage content and CSS did not match the current repository state.
11. The Cottbus district page returned HTTP 200 with zero facilities.
12. An extreme page number returned HTTP 200.
13. HTTP redirects include explicit `:443`; HTTP www uses a two-hop canonical chain.
14. A mismatched Host header reached an ISPmanager/default-host page rather than being rejected.
15. The live health page loads assets from `fonts.bunny.net` and `cdn.jsdelivr.net`.

## 21. Unknown Production Facts

Operator/hosting evidence is required for:

1. exact deployed commit/package manifest and checksums;
2. actual `APP_ENV`, `APP_DEBUG`, `APP_URL` and loaded config-cache state;
3. presence/stability of the existing production `APP_KEY` without revealing it;
4. private-core, `.env`, SQLite, storage, cache, backup and webroot paths;
5. ownership and permissions for `.env`, SQLite directory/file, storage and cache;
6. production SQLite size, integrity, foreign-key state, journal mode, migrations and GeoCore row/link counts;
7. whether `.env`, SQLite, backups, logs, Git metadata or private core are reachable through any alias/subdomain;
8. exact nginx vhost, hidden-file, PHP execution, trailing-slash, cache/compression and error-page configuration;
9. proxy/CDN/load-balancer presence and trusted proxy/client-IP handling;
10. actual log channel/level, Laravel and hosting log retention, access control and rotation;
11. current backup schedule, latest successful copy, encryption, secondary location, retention deletion and last restore-test result;
12. provider-level backup scope and rollback availability;
13. disk free-space thresholds and alerts;
14. uptime, SSL-expiry, error, disk, backup and database-integrity monitoring;
15. actual session/cache/queue/mail drivers and session cleanup state;
16. hosting provider, data-center location, processing agreement and subprocessors;
17. mailbox provider, forwarding, retention, backup and deletion process for `info@pflegeindex.com`;
18. operational owner for deployments, backups, incidents and recovery;
19. current production administrator inventory and whether MFA or provider-level access controls exist;
20. whether server/PHP version disclosure and the ISPmanager default vhost can be changed in the hosting panel.

## 22. Findings

The Evidence cell begins with classification flags: `code`, `hosting`, `confirmed`, `unknown`, and `repo-fixable`.

| ID | Priority | Area | Finding | Evidence | Risk | Recommended Action |
|---|---|---|---|---|---|---|
| PR-001 | P0 | Data/SEO | GeoCore relations are incomplete; district pages omit 399 facilities and Cottbus is an indexable empty district page | `code=no; hosting/data=yes; confirmed=yes; unknown=no; repo-fixable=separate data task.` Read-only local counts: 77 unmapped cities, 399 facilities; production Cottbus district: HTTP 200/zero; sitemap logic includes all districts | Materially incorrect geographic navigation, thin indexable page and loss of trust | Complete the separately approved GeoCore data-fix with backup/dry-run/verification; verify 257 cities/1,557 facilities and make empty district pages non-indexable or unavailable until corrected |
| PR-002 | P0 | Environment/DB | Real production environment, paths, permissions, caches and migration state are unverified | `code=no; hosting=yes; confirmed=no; unknown=yes; repo-fixable=no.` Only templates/docs and public responses were available | Debug exposure, wrong URL generation, unreadable DB, stale code/cache or failed writes could appear after release | Record a sanitized production checklist: env/debug/url/drivers, private paths, writable storage/cache/DB directory, migration status, integrity and deployed manifest |
| PR-003 | P0 | Recovery | No current production backup or successful restore-test evidence was available | `code=no; hosting/operations=yes; confirmed=no; unknown=yes; repo-fixable=partly docs only.` Procedures exist, execution evidence does not | An unsuccessful deployment or SQLite corruption may be unrecoverable | Before deployment, create and verify protected SQLite + `.env` backups, record commit/checksum, confirm secondary copy and complete or reference a recent restore test |
| PR-004 | P0 | Privacy/launch governance | Hosting, processor, log and mailbox facts required for final privacy approval remain unknown | `code=no; hosting/operator=yes; confirmed=no; unknown=yes; repo-fixable=no.` Prior privacy audit and deployment checklist explicitly require these facts | Public legal statements may be incomplete or inaccurate | Obtain provider/DPA/location/log/backup/mail facts and complete the separate legal approval before launch |
| PR-005 | P1 | Deployment/assets | Production does not match current `main`; the declared Open Graph image is missing | `code=no; hosting/deployment=yes; confirmed=yes; unknown=exact deployed commit; repo-fixable=deployment.` Live old homepage wording/CSS; `og-image.png` 404; local/package asset exists | Users see superseded positioning and social previews fail; release integrity cannot be trusted | Build the approved clean package, verify checksums, deploy both core and complete webroot atomically, clear caches and run release verification |
| PR-006 | P1 | Canonical redirects | Nginx HTTP redirects add `:443`; HTTP www reaches canonical in two hops | `code=no; hosting=yes; confirmed=yes; unknown=no; repo-fixable=no.` Live `Location` headers | Extra crawl hop, inconsistent canonical surface and divergence from the documented single-host rule | Configure nginx/control panel so all three variants 301 directly to the exact `https://pflegeindex.com` URL |
| PR-007 | P1 | Virtual host | A mismatched Host header returns an ISPmanager/default-host HTTP 200 and cookie | `code=no; hosting=yes; confirmed=yes; unknown=panel exposure scope; repo-fixable=no.` Public header probe | Domain-fronting/default-panel exposure, unnecessary cookie and confusing security boundary | Configure the default TLS vhost to reject unknown Host values with 400/421/444 and ensure the control panel uses only its intended hostname |
| PR-008 | P1 | Forms/security | Parser-imported URLs are not schema/length validated before storage, admin rendering or facility publication | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` Importer, accept action and Blade links | Unsafe-scheme/misleading links and oversized/unexpected data can be persisted and exposed | Add a strict result schema and HTTP(S)-only URL validation before persistence/acceptance; reject unsafe existing suggestions in a separate reviewed data check |
| PR-009 | P1 | Health/privacy | `/up` loads Bunny Fonts and a browser Tailwind script from jsDelivr | `code=vendor view/config behavior; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` Live HTML | Third-party request/privacy dependency and a remote script on a health endpoint; reduced monitoring reliability | Replace with a minimal local/plain health response while preserving status semantics and noindex header |
| PR-010 | P1 | Logging | Intended production `single` Laravel log has no automatic rotation/retention | `code/config=yes; hosting/operations=yes; confirmed=intent only; unknown=actual prod; repo-fixable=yes.` Production templates and logging config | Disk growth and indefinite retention of exceptional request/admin data | Use controlled daily rotation/retention or provider log rotation, restrict access and monitor failures/disk use |
| PR-011 | P1 | Pagination | Arbitrary extreme page numbers return indexable HTTP 200/self-canonical empty pages | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` Controllers only clamp minimum; live `page=999999999` 200 | Infinite crawl space and expensive large-offset queries | Validate a reasonable page bound and return 404 or redirect/canonicalize beyond the last valid page; add tests for all four listing types |
| PR-012 | P1 | Monitoring | Critical operational checks are documented but no automated alerts/evidence exist | `code=no; hosting/operations=yes; confirmed=repo absence; unknown=provider tools; repo-fixable=partly.` No scheduler/integration in repo | Outage, disk exhaustion, backup failure, SSL expiry or DB corruption may remain unnoticed | Confirm provider monitoring or configure external uptime/SSL plus backup/disk/error/integrity alerts and name the responsible operator |
| PR-013 | P2 | Frontend supply chain | npm dependency state has no lockfile and cannot be audited reproducibly | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` `npm audit` ENOLOCK; extraneous local modules | Future asset rebuilds can resolve different packages and advisories cannot be reliably tracked | Generate/review a lockfile in a separate frontend-dependency task and use clean immutable installs; production runtime remains unaffected today |
| PR-014 | P2 | Search/performance | Search/filter input length is unbounded and LIKE wildcards trigger scans | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` Directory controller/repository | Avoidable CPU/DB work, wildcard surprises and long query URLs/log entries | Add concise length/format validation, escape literal wildcards where appropriate and rate-limit expensive public search after measuring |
| PR-015 | P2 | SQLite concurrency | Busy timeout, journal mode and synchronous settings are not explicit | `code=yes; hosting=yes; confirmed=config; unknown=production values; repo-fixable=yes.` SQLite config; local uses delete journal | Under concurrent admin/session/cache writes, lock errors or backup inconsistency are harder to predict | Measure production write contention and document/set suitable SQLite parameters; include sidecar-aware backup handling if WAL is adopted |
| PR-016 | P2 | Security headers | No Content Security Policy exists | `code=yes; hosting=yes; confirmed=yes; unknown=no; repo-fixable=yes.` Live headers and middleware | Browser has less defense against future injection or unsafe dynamic links | After URL validation and `/up` cleanup, deploy a conservative tested CSP, initially in report-only mode if needed |
| PR-017 | P2 | Server headers | HSTS is duplicated and exact nginx/PHP versions are disclosed | `code+hosting=yes; confirmed=yes; unknown=no; repo-fixable=partly.` Live headers | Configuration ambiguity and unnecessary fingerprinting | Choose one HSTS owner, remove duplication, decide subdomain scope, and suppress version headers where the host permits |
| PR-018 | P2 | Asset performance | Static assets lack explicit long cache lifetime/compression; JS is unversioned | `code+hosting=yes; confirmed=yes; unknown=no; repo-fixable=partly.` Live asset headers and layout | Slower repeat visits and stale JS risk after FileZilla deployments | Add immutable/versioned asset URLs and nginx cache/compression policy after the deployment baseline is stable |
| PR-019 | P2 | Authentication | No MFA/other-session invalidation; admin cookie path is site-wide | `code=yes; hosting/operator=yes; confirmed=yes; unknown=provider controls; repo-fixable=yes.` Auth/session code | A stolen password/session has a wider impact window than necessary | Add MFA or provider-level second factor, invalidate other sessions on password change, and assess `/admin` cookie scoping with tests |
| PR-020 | P2 | Errors/UX | Framework error pages are generic and unbranded | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` No custom error views; live generic 404 | Users have no guided recovery/navigation after errors | Add minimal accessible 404/500 pages without technical details in a later UX task |
| PR-021 | P2 | Middleware guardrail | Public web middleware globally removes CSRF/session | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` Bootstrap middleware | A future public write route could be added without expected CSRF protection | Document/test that public routes remain read-only or apply stateless middleware explicitly per route when public writes are introduced |
| PR-022 | P2 | Sitemap scalability | Sitemap loads all facilities through city relations into memory | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` Sitemap controller | Memory/time growth as the directory expands | Keep for current size; introduce chunking/sitemap indexes only when measured size approaches limits |
| PR-023 | P2 | Asset hygiene | Tracked `favicon.ico` is zero bytes | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=yes.` Local file; package intentionally excludes it | Accidental direct request can return an unusable icon in non-package deployments | Remove or replace it in a separate asset cleanup task; active SVG favicon is unaffected |
| PR-024 | INFO | Dependencies | Composer metadata, lock and current production dependency audit are healthy | `code=yes; hosting=no; confirmed=yes; unknown=production vendor identity; repo-fixable=n/a.` Validation/audit/platform checks passed | Positive baseline | Continue audit/update checks on reviewed branches and verify production manifest |
| PR-025 | INFO | Routes/auth | Public/admin route boundaries, authorization, methods, throttle and CSRF are appropriate for current features | `code=yes; hosting=no; confirmed=yes; unknown=no; repo-fixable=n/a.` Route list, middleware and tests | Positive baseline | Preserve regression coverage |
| PR-026 | INFO | Optimization | All Laravel production cache types build successfully in isolation | `code=yes; hosting=no; confirmed=local; unknown=production cache; repo-fixable=n/a.` Four cache commands passed | Positive deployability signal | Rebuild only after production `.env` verification |
| PR-027 | INFO | Sensitive files | Git/package rules exclude secrets, databases, logs, backups, caches and dev content | `code=yes; hosting=no; confirmed=yes; unknown=actual webroot; repo-fixable=n/a.` Ignore rules and package scanner | Positive baseline | Verify actual uploaded webroot and manifests each release |
| PR-028 | INFO | SQLite | Local database is internally consistent and locally migrated | `code/data=no; hosting=no; confirmed=local only; unknown=production; repo-fixable=n/a.` Read-only pragmas and migration status | Positive local baseline | Repeat safely against production before release approval |
| PR-029 | INFO | SEO | Core canonical, robots, sitemap, filter-noindex and page-2 metadata work in production | `code+hosting=yes; confirmed=yes; unknown=no; repo-fixable=n/a.` Live public checks | Positive crawl baseline | Preserve tests and add operational monitoring |
| PR-030 | INFO | Error/security | Invalid public URLs return generic 404 without traces and normal headers are present | `code+hosting=yes; confirmed=yes; unknown=500 behavior; repo-fixable=n/a.` Live probes | Positive disclosure baseline | Confirm a controlled staging 500 before release, not by triggering production failure |
| PR-031 | INFO | Public privacy | Normal public pages are sessionless and use local assets; map/provider contacts require a click | `code=yes; hosting=yes; confirmed=code/tests; unknown=provider logs; repo-fixable=n/a.` Layouts/tests | Positive privacy baseline | Keep third-party additions subject to review |

Counts: **P0: 4; P1: 8; P2: 11; INFO: 8.**

## 23. Recommended Remediation Order

1. **Freeze launch/deployment approval and record the intended release commit.** Do not mutate production yet.
2. **Close production unknowns in the hosting panel/shell:** confirm sanitized env values, private paths, permissions, PHP extensions, disk space, migration status, cache state and current deployed manifest.
3. **Create and verify recovery inputs:** maintenance window, SQLite + `.env` backup, checksum, previous commit/package, protected secondary copy and a documented restore test.
4. **Complete the separately approved GeoCore data correction:** backup, dry-run, apply, integrity/count checks, district/city/facility verification, sitemap review. Do not use this audit as authorization to run it.
5. **Close privacy/operator P0 facts** and obtain separate legal approval without changing legal text as part of this audit.
6. **Fix nginx/control-panel behavior:** one-hop exact canonical redirects, reject unknown Host, confirm private webroot rules, remove duplicate HSTS/version leakage where possible.
7. **Build and deploy an immutable package from the approved commit:** verify Composer status, checksums, front-controller path, complete public assets and external SQLite scenario. Confirm the Open Graph image and current homepage copy after switch.
8. **Fix application P1 items in separate reviewed tasks:** strict parser schema/HTTP(S) URL validation, bounded pagination, minimal local `/up`, controlled log rotation.
9. **Enable/confirm monitoring:** uptime, SSL expiry, errors, disk, backups and periodic SQLite integrity/restore evidence.
10. **Run the full release verification checklist** over HTTPS, including admin login/logout without exposing credentials, then observe logs before GO.
11. Schedule P2 hardening only after P0/P1 closure: npm lock, search bounds, SQLite tuning, CSP, session hardening, asset caching/compression and branded errors.

## 24. Launch Recommendation

**NO-GO**

The status may change to **GO** only when all of the following are evidenced:

1. production environment values are verified as production-safe without revealing secrets;
2. the private-core/webroot/SQLite/backup separation and permissions are confirmed on the actual host;
3. production SQLite integrity, foreign keys, migrations and expected row counts pass;
4. a current protected backup, previous release reference and tested restore/rollback path exist;
5. GeoCore relations are corrected or affected district URLs are safely withheld from indexing/public launch, with Cottbus no longer an empty indexable page;
6. hosting/mail/provider facts have separate privacy/legal approval;
7. nginx sends every HTTP/www variant in one 301 to the exact canonical URL and rejects unknown Host values;
8. the approved release is deployed completely, matches its manifest, and `og-image.png` returns 200;
9. parser-derived public/source URLs cannot use unsafe schemes;
10. extreme pagination cannot create unbounded indexable empty pages;
11. production log rotation and essential availability/backup/disk/SSL monitoring are confirmed;
12. Composer/platform checks, all 122 tests and the complete post-deployment verification checklist pass for the exact deployed commit, with no new critical errors.
